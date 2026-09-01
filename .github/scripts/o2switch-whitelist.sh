#!/usr/bin/env bash
# This script is used to whitelist the GitHub Actions runner's public IP on the o2switch server, 
# and drop the two oldest whitelisted IPs (o2switch only allows 3 at a time). 
# The whitelist script is idempotent, so if the runner's IP is already whitelisted it won't be 
# added again, and the two oldest IPs will still be dropped.
set -euo pipefail

: "${CPANEL_USER:?missing}" "${CPANEL_PASSWORD:?missing}" "${CPANEL_HOST:?missing}" "${RUNNER_IP:?missing}"

ENDPOINT='frontend/o2switch/o2switch-ssh-whitelist/index.live.php'
CPANEL="https://${CPANEL_HOST}:2083"

# Bounds every call below -- without these, a curl request that connects but
# never gets a response (seen in practice against this cPanel endpoint) hangs
# until the job's overall 15-minute timeout kills it, with no error message
# pointing at why. Failing fast here at least surfaces which call stalled.
#
# Credentials go through --user rather than embedded in the URL
# (https://user:pass@host/...) -- a password containing a URL-special
# character (@, :, /, %, whitespace...) breaks the embedded form with an
# opaque "URL malformed" (curl exit 3) that gives no hint it's the password.
# --user handles arbitrary bytes without escaping.
CURL_OPTS=(--connect-timeout 10 --max-time 30 --user "${CPANEL_USER}:${CPANEL_PASSWORD}")

echo "Fetching currently whitelisted IPs..."
LIST_HTTP=$(curl -sX GET "${CURL_OPTS[@]}" -o /tmp/o2switch-list.json -w '%{http_code}' "$CPANEL/$ENDPOINT?r=list")
RESPONSE=$(cat /tmp/o2switch-list.json)
if ! echo "$RESPONSE" | jq empty 2>/dev/null; then
    echo "::error::o2switch list endpoint returned non-JSON (HTTP $LIST_HTTP). First 500 chars of body below:" >&2
    echo "$RESPONSE" | head -c 500 >&2
    echo >&2
    exit 5
fi
LAST_IPS=$(echo "$RESPONSE" | jq -r '.data.list[]? | .address' | tail -n2)

for address in $LAST_IPS; do
    echo "Removing old CI IP: $address (in & out)"
    curl -sX GET "${CURL_OPTS[@]}" "$CPANEL/$ENDPOINT?r=remove&address=$address&direction=in&port=22" | jq -r '.message // .success'
    sleep 2
    curl -sX GET "${CURL_OPTS[@]}" "$CPANEL/$ENDPOINT?r=remove&address=$address&direction=out&port=22" | jq -r '.message // .success'
    sleep 2
done

echo "Whitelisting runner IP $RUNNER_IP..."
ADD_RESPONSE=$(curl -sX POST "${CURL_OPTS[@]}" -d "whitelist[address]=$RUNNER_IP" -d 'whitelist[port]=22' "$CPANEL/$ENDPOINT?r=add")
echo "$ADD_RESPONSE" | jq

# Fail loudly instead of silently sleeping and letting a later SSH step fail
# with an opaque "Permission denied" -- this is exactly what happens when the
# quota message ("Vous avez atteint la limite d'exceptions autorisées") comes
# back instead of a real success.
if [ "$(echo "$ADD_RESPONSE" | jq -r '.success')" != "true" ]; then
    echo "::error::Failed to whitelist $RUNNER_IP on o2switch -- check the whitelist quota (cPanel > Sécurité > Accès SSH) or the DEPLOY_CPANEL_PASSWORD secret." >&2
    exit 1
fi

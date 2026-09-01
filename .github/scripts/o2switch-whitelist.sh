#!/usr/bin/env bash
# Whitelists the GitHub Actions runner's public IP for SSH (port 22) on
# o2switch, and drops the oldest whitelisted IP first to stay under the
# quota. Uses the cPanel API Token method (Authorization: cpanel
# user:token header) -- o2switch's recommended auth since 2025-05-02,
# replacing the old --user/password Basic Auth this project used before
# (see git history: fix(ci) "pass o2switch credentials via curl --user").
#
# API docs: https://faq.o2switch.fr/cpanel/outils/exception-parefeu/
# Quota is 5 exceptions total (not 3 -- an earlier version of this script
# assumed 3 and got that wrong).
set -euo pipefail

: "${O2SWITCH_API_LOGIN:?missing}" "${O2SWITCH_API_TOKEN:?missing}" "${CPANEL_HOST:?missing}" "${RUNNER_IP:?missing}"

ENDPOINT='execute/SshWhitelist'
CPANEL="https://${CPANEL_HOST}:2083"

# Bounds every call below -- without these, a curl request that connects but
# never gets a response hangs until the job's overall timeout kills it, with
# no error message pointing at why.
CURL_OPTS=(--connect-timeout 10 --max-time 30 -H "Authorization: cpanel ${O2SWITCH_API_LOGIN}:${O2SWITCH_API_TOKEN}")

echo "Fetching currently whitelisted IPs..."
LIST_HTTP=$(curl -sX GET "${CURL_OPTS[@]}" -o /tmp/o2switch-list.json -w '%{http_code}' "$CPANEL/$ENDPOINT/list")
RESPONSE=$(cat /tmp/o2switch-list.json)
if ! echo "$RESPONSE" | jq empty 2>/dev/null; then
    echo "::error::o2switch list endpoint returned non-JSON (HTTP $LIST_HTTP). First 500 chars of body below:" >&2
    echo "$RESPONSE" | head -c 500 >&2
    echo >&2
    exit 5
fi

# Already whitelisted -- idempotent, nothing to do.
if echo "$RESPONSE" | jq -e --arg ip "$RUNNER_IP" '.data.list[]? | select(.address == $ip)' > /dev/null; then
    echo "$RUNNER_IP is already whitelisted."
    exit 0
fi

# Quota is 5 total entries (shared across in+out directions for the same
# IP, so one IP typically counts as 2). Drop the single oldest address (both
# directions) only if we're already at/over quota, to make room for the new
# one -- never drop anything on a run that doesn't need to.
COUNT=$(echo "$RESPONSE" | jq -r '.data.count // 0')
if [ "$COUNT" -ge 5 ]; then
    OLDEST=$(echo "$RESPONSE" | jq -r '.data.list | sort_by(.updateDate) | .[0].address')
    echo "Quota reached ($COUNT/5) -- removing oldest CI IP: $OLDEST (in & out)"
    curl -sX GET "${CURL_OPTS[@]}" "$CPANEL/$ENDPOINT/remove?address=$OLDEST&direction=in&port=22" | jq -r '.message // .status'
    sleep 2
    curl -sX GET "${CURL_OPTS[@]}" "$CPANEL/$ENDPOINT/remove?address=$OLDEST&direction=out&port=22" | jq -r '.message // .status'
    sleep 2
fi

echo "Whitelisting runner IP $RUNNER_IP..."
ADD_RESPONSE=$(curl -sX GET "${CURL_OPTS[@]}" "$CPANEL/$ENDPOINT/add?address=$RUNNER_IP&port=22")
echo "$ADD_RESPONSE" | jq

# Fail loudly instead of silently letting a later SSH step fail with an
# opaque "Permission denied" or a 120s timeout with no clear cause.
if [ "$(echo "$ADD_RESPONSE" | jq -r '.status')" != "1" ]; then
    echo "::error::Failed to whitelist $RUNNER_IP on o2switch -- check the whitelist quota (cPanel > Sécurité > Accès SSH) or the O2SWITCH_API_TOKEN secret." >&2
    exit 1
fi

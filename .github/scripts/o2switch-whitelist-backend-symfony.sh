#!/usr/bin/env bash
# Whitelists the current GitHub Actions runner's IP for SSH (port 22) on
# o2switch (cPanel), removing the two most recently whitelisted addresses
# first -- o2switch caps the whitelist size, and every CI run adds a new IP
# with nothing removing old ones on its own, so the quota fills up after a
# few runs and blocks SSH entirely.
#
# Named "-backend-symfony" (not just "o2switch-whitelist.sh") to avoid
# colliding with the same-purpose script for the unrelated WordPress project
# hosted on this same o2switch account -- the two are independent, don't
# share state, and shouldn't share a filename either.
#
# Known trade-off: "remove the last 2 entries" assumes they're stale CI
# runner IPs. The o2switch API this endpoint wraps has no per-entry label
# to distinguish a CI-added IP from one whitelisted by hand -- if a human IP
# got whitelisted right before a CI run, it could get swept up here.
# Accepted as a known limitation rather than adding state-tracking (a
# GitHub Actions cache entry per whitelisted IP) for a narrow edge case.
#
# Required env vars: CPANEL_USER, CPANEL_PASSWORD, CPANEL_HOST, RUNNER_IP

set -euo pipefail

: "${CPANEL_USER:?missing}" "${CPANEL_PASSWORD:?missing}" "${CPANEL_HOST:?missing}" "${RUNNER_IP:?missing}"

ENDPOINT='frontend/o2switch/o2switch-ssh-whitelist/index.live.php'
CPANEL="https://${CPANEL_USER}:${CPANEL_PASSWORD}@${CPANEL_HOST}:2083"

echo "Fetching currently whitelisted IPs..."
RESPONSE=$(curl -sX GET "$CPANEL/$ENDPOINT?r=list")
LAST_IPS=$(echo "$RESPONSE" | jq -r '.data.list[]? | .address' | tail -n2)

for address in $LAST_IPS; do
    echo "Removing old CI IP: $address (in & out)"
    curl -sX GET "$CPANEL/$ENDPOINT?r=remove&address=$address&direction=in&port=22" | jq -r '.message // .success'
    sleep 2
    curl -sX GET "$CPANEL/$ENDPOINT?r=remove&address=$address&direction=out&port=22" | jq -r '.message // .success'
    sleep 2
done

echo "Whitelisting runner IP $RUNNER_IP..."
ADD_RESPONSE=$(curl -sX POST -d "whitelist[address]=$RUNNER_IP" -d 'whitelist[port]=22' "$CPANEL/$ENDPOINT?r=add")
echo "$ADD_RESPONSE" | jq

# Fail loudly instead of silently sleeping and letting a later SSH step fail
# with an opaque "Permission denied" -- this is exactly what happens when the
# quota message ("Vous avez atteint la limite d'exceptions autorisées") comes
# back instead of a real success.
if [ "$(echo "$ADD_RESPONSE" | jq -r '.success')" != "true" ]; then
    echo "::error::Failed to whitelist $RUNNER_IP on o2switch -- check the whitelist quota (cPanel > Sécurité > Accès SSH) or the DEPLOY_CPANEL_PASSWORD secret." >&2
    exit 1
fi

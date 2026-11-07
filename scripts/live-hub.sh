#!/bin/sh
# The run script of the push hub (scripts/live-hub.mjs) for the daemon of NearlyFreeSpeech, whose command
# line takes no arguments. exec leaves the node process with scripts/live-hub.mjs in its arguments, which
# deploy.sh's pkill looks for. The hub keeps its own defaults (port 8091 and the rest); only the address
# is set here, since NFSN's proxy may not reach a daemon listening on 127.0.0.1 alone.
cd "$(dirname "$0")/.." || exit 1
NODE="$(command -v node || echo /usr/local/bin/node)"
LIVE_HUB_HOST="${LIVE_HUB_HOST:-0.0.0.0}" exec "$NODE" scripts/live-hub.mjs

#!/bin/bash
# Local cron trigger for Tsumego Hero.
# Runs the daily cron endpoint only if it hasn't run today yet.
# Designed for DDEV environments where the container is not running 24/7.

STAMP_FILE="/tmp/cron-last-run"
TODAY=$(date +%Y-%m-%d)

LAST_RUN=$(cat "$STAMP_FILE" 2>/dev/null || echo "2000-01-01")

if [ "$LAST_RUN" = "$TODAY" ]; then
    echo "[cron] Already ran today ($TODAY), skipping."
    exit 0
fi

# Read CRON_SECRET from the CakePHP config file
SECRET=$(sed -n "s/.*define\s*(\s*'CRON_SECRET'\s*,\s*'\([^']*\)'.*/\1/p" /var/www/html/config/core.local.php)

echo "[cron] Running daily cron for $TODAY ..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/cron/daily/$SECRET")
if [ "$HTTP_CODE" = "200" ]; then
    echo "$TODAY" > "$STAMP_FILE"
    echo "[cron] Done (HTTP $HTTP_CODE)."
else
    echo "[cron] FAILED (HTTP $HTTP_CODE)."
    exit 1
fi

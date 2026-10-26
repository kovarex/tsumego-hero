#!/bin/bash
# Local cron trigger for Tsumego Hero.
# The endpoint is idempotent (it skips work it already did today), so it is safe
# to call on every container start without a local "already ran" stamp.

# Read CRON_SECRET from the CakePHP config file
SECRET=$(sed -n "s/.*define\s*(\s*'CRON_SECRET'\s*,\s*'\([^']*\)'.*/\1/p" /var/www/html/config/core.local.php)

if [ -z "$SECRET" ]; then
    echo "[cron] CRON_SECRET not found in config/core.local.php, skipping."
    exit 1
fi

echo "[cron] Running daily cron ..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/cron/daily/$SECRET")
if [ "$HTTP_CODE" = "200" ]; then
    echo "[cron] Done (HTTP $HTTP_CODE)."
else
    echo "[cron] FAILED (HTTP $HTTP_CODE)."
    exit 1
fi

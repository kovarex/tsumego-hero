#!/bin/bash
# Local cron trigger for Tsumego Hero (the ddev post-start hook): the daily work of the site, as the
# server's scheduled task runs it. It is idempotent (it skips work it already did today), so it is safe
# to run on every container start.

cd /var/www/html || exit 1
echo "[cron] Running daily cron ..."
if php bin/cake cron_daily; then
    echo "[cron] Done."
else
    echo "[cron] FAILED."
    exit 1
fi

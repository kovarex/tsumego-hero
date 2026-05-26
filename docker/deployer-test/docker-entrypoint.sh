#!/bin/bash
set -e

SHARED_DIR="/var/www/deploy/shared"
SHARED_CONFIG="$SHARED_DIR/config"
SHARED_TMP="$SHARED_DIR/tmp"

# Initialize shared directory structure on first run
if [ ! -d "$SHARED_DIR" ]; then
    echo "=== First run: initializing Deployer shared directory ==="

    # Create TMP subdirectories required by CakePHP
    mkdir -p "$SHARED_TMP/cache/models"
    mkdir -p "$SHARED_TMP/cache/persistent"
    mkdir -p "$SHARED_TMP/cache/views"
    mkdir -p "$SHARED_TMP/logs"
    mkdir -p "$SHARED_TMP/sessions"
    chmod -R 777 "$SHARED_TMP"

    mkdir -p "$SHARED_CONFIG"
fi

# Create database.php from template if not present
if [ ! -f "$SHARED_CONFIG/database.php" ]; then
    echo "Creating database.php from template..."
    cp /var/www/html/config/database.example.php "$SHARED_CONFIG/database.php"

    DB_HOST="${DB_HOST:-db}"
    DB_USER="${DB_USER:-db}"
    DB_PASS="${DB_PASS:-db}"

    sed -i "s/template_db_host/${DB_HOST}/g"         "$SHARED_CONFIG/database.php"
    sed -i "s/template_db_user/${DB_USER}/g"          "$SHARED_CONFIG/database.php"
    sed -i "s/template_db_password/${DB_PASS}/g"      "$SHARED_CONFIG/database.php"

    echo "Database config created (host=${DB_HOST}, user=${DB_USER})"
fi

# Create email.php stub if not present (avoids shared-file error on deploy)
if [ ! -f "$SHARED_CONFIG/email.php" ]; then
    cp /var/www/html/config/email.example.php "$SHARED_CONFIG/email.php" 2>/dev/null || \
    echo '<?php' > "$SHARED_CONFIG/email.php"
fi

# Start OpenSSH server (runs alongside Apache)
service ssh start

exec "$@"


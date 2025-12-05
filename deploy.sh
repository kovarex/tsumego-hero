#!/bin/bash

git pull
set -e

ROOT_DIR="$(cd "$(dirname "$0")"; pwd)"

DB_CONFIG_FILE="$ROOT_DIR/config/database.php"
DB_CONFIG_TEMPLATE="$ROOT_DIR/config/database.example.php"

PHPBB_CONFIG_FILE="$ROOT_DIR/webroot/forums/config.php"
PHPBB_CONFIG_TEMPLATE="$ROOT_DIR/webroot/forums/config.example.php"

NEED_DB_INPUT=false

echo "=== Deploy started ==="

### Detect if we need DB credentials
if [[ ! -f "$DB_CONFIG_FILE" || ! -f "$PHPBB_CONFIG_FILE" ]]; then
    NEED_DB_INPUT=true
fi

### Ask for user input only if needed
if [[ "$NEED_DB_INPUT" = true ]]; then
    echo "Database configuration is missing. Please enter database credentials."

    read -p "Database host (default: db): " DB_HOST
    DB_HOST=${DB_HOST:-db}

    read -p "Database name (default: db): " DB_NAME
    DB_NAME=${DB_NAME:-db}

    read -p "Database user (default: db): " DB_USER
    DB_USER=${DB_USER:-db}

    read -p "Database password (default: db): " DB_PASS
    DB_PASS=${DB_PASS:-db}

    # Escape replacement values for sed (only once)
    ESC_HOST=$(printf '%s\n' "$DB_HOST" | sed 's/[&/\]/\\&/g')
    ESC_USER=$(printf '%s\n' "$DB_USER" | sed 's/[&/\]/\\&/g')
    ESC_PASS=$(printf '%s\n' "$DB_PASS" | sed 's/[&/\]/\\&/g')
    ESC_NAME=$(printf '%s\n' "$DB_NAME" | sed 's/[&/\]/\\&/g')

    echo ""
    echo "Using values:"
    echo "  Host:     $DB_HOST"
    echo "  Database: $DB_NAME"
    echo "  User:     $DB_USER"
    echo "  Password: $DB_PASS"

fi


### Generate CakePHP config/database.php if missing
if [[ ! -f "$DB_CONFIG_FILE" ]]; then
    echo "Creating config/database.php ..."

    cp "$DB_CONFIG_TEMPLATE" "$DB_CONFIG_FILE"

    sed -i '' \
         -e "s|'template_db_host'|'$ESC_HOST'|g" \
         -e "s|'template_db_user'|'$ESC_USER'|g" \
         -e "s|'template_db_password'|'$ESC_PASS'|g" \
         -e "s|'db'|'$ESC_NAME'|g" \
         "$DB_CONFIG_FILE"
else
    echo "config/database.php already exists, skipping."
fi


### Generate phpBB config.php if missing
if [[ ! -f "$PHPBB_CONFIG_FILE" ]]; then
    echo "Creating webroot/forums/config.php ..."

    cp "$PHPBB_CONFIG_TEMPLATE" "$PHPBB_CONFIG_FILE"

    sed -i '' \
        -e "s|template_db_host|$ESC_HOST|g" \
        -e "s|template_db_user|$ESC_USER|g" \
        -e "s|template_db_password|$ESC_PASS|g" \
        -e "s|dbname = 'db'|dbname = '$ESC_NAME'|g" \
        "$PHPBB_CONFIG_FILE"
else
    echo "webroot/forums/config.php already exists, skipping."
fi

echo "Running Composer..."
composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction

echo "=== Setting up permissions ==="
chmod 777 "$ROOT_DIR/tmp"
chmod 777 "$ROOT_DIR/tmp/logs"
mkdir -p "$ROOT_DIR/tmp/logs/cache"
chmod 777 "$ROOT_DIR/tmp/logs/cache"
mkdir -p "$ROOT_DIR/tmp/cache/models"
chmod 777 "$ROOT_DIR/tmp/cache/models"
mkdir -p "$ROOT_DIR/tmp/cache/persistent"
chmod 777 "$ROOT_DIR/tmp/cache/persistent"
mkdir -p "$ROOT_DIR/tmp/cache/views"
chmod 777 "$ROOT_DIR/tmp/cache/views"
chmod 777 "$ROOT_DIR/webroot/forums/cache"
mkdir -p "$ROOT_DIR/webroot/forums/cache/production"
chmod 777 "$ROOT_DIR/webroot/forums/cache/production"
chmod 777 "$ROOT_DIR/webroot/forums/store"
chmod 777 "$ROOT_DIR/webroot/forums/files"
chmod 777 "$ROOT_DIR/webroot/forums/images/avatars/upload"

echo "=== Running migrations ==="
vendor/bin/phinx migrate
#vendor/bin/phinx migrate -e test

echo "=== Deploy complete ==="

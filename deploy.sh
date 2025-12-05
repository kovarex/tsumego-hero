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

### Detect DDEV environment
IS_DDEV=false
if command -v ddev >/dev/null 2>&1; then
    IS_DDEV=true
    echo "DDEV environment detected."
fi

### Select MySQL client (local or DDEV)
if [[ "$IS_DDEV" = true ]]; then
    MYSQL="ddev mysql"
else
    MYSQL="mysql"
fi

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

    db_exists() {
        $MYSQL -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
            -e "SHOW DATABASES LIKE '$1';" 2>/dev/null | grep "$1" >/dev/null
    }

    echo "=== Ensuring main database exists ==="
    if db_exists "$DB_NAME"; then
        echo "Database $DB_NAME already exists."
    else
        echo "Creating database $DB_NAME..."
        $MYSQL -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
            -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    fi

    if [[ "$IS_DDEV" = true ]]; then
        echo "=== Ensuring test database exists (DDEV only) ==="
        if db_exists "test"; then
            echo "Test database already exists."
        else
            echo "Creating test database..."
            $MYSQL -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
                -e "CREATE DATABASE test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        fi
    else
        echo "Skipping test database creation (not DDEV)."
    fi
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


### Composer install (host in dev, local in prod)
echo "Running Composer..."
if [[ "$IS_DDEV" = true ]]; then
    ddev composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction
else
    composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction
fi


echo "=== Setting up permissions ==="
chmod 777 "$ROOT_DIR/tmp"
chmod 777 "$ROOT_DIR/tmp/logs"
mkdir -p "$ROOT_DIR/tmp/cache"
chmod 777 "$ROOT_DIR/tmp/cache"
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


### Run migrations (inside container in dev, locally in production)
echo "=== Running migrations ==="
if [[ "$IS_DDEV" = true ]]; then
    ddev exec vendor/bin/phinx migrate
    ddev exec vendor/bin/phinx migrate -e test
else
    vendor/bin/phinx migrate
fi

echo "=== Deploy complete ==="

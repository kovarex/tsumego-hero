#!/bin/bash

# Everything the deploy writes (the pulled code, the bundles, vendor) must be readable by PHP,
# which runs as another user, whatever umask the shell had: under 077 the pull wrote
# config/bootstrap.php owner-only and the site stopped. The secrets set their own stricter modes.
umask 022

ROOT_DIR="$(cd "$(dirname "$0")"; pwd)"

# The site shows the static maintenance page while this flag exists (webroot/.htaccess), from before
# the pull to the end of the deploy. Whatever way the deploy ends, failed or stopped too, the flag
# goes, so a failed deploy doesn't leave the site down unseen. exec runs no EXIT trap, so the flag
# stays on through the re-exec after the pull, whose new script sets it and its trap again.
MAINTENANCE_FLAG="$ROOT_DIR/tmp/maintenance.flag"
end_maintenance() {
    status=$?
    rm -f "$MAINTENANCE_FLAG"
    if [ "$status" -ne 0 ]; then
        echo "=== Deploy FAILED (exit $status): the maintenance page is off again, check the site ===" >&2
    else
        echo "=== Maintenance page off ==="
    fi
}
trap end_maintenance EXIT
trap 'exit 130' INT
trap 'exit 143' TERM HUP
mkdir -p "$ROOT_DIR/tmp"
date > "$MAINTENANCE_FLAG"
echo "=== Maintenance page on ==="

# The secrets that are not in git first, so the code the pull brings never starts without them
# (scripts/ensure-secrets.sh). After the pull the new script runs it again, which changes nothing.
bash "$ROOT_DIR/scripts/ensure-secrets.sh" "$ROOT_DIR" || exit 1

if [ -z "$DEPLOY_REEXEC" ]; then
    git pull || exit 1
    exec env DEPLOY_REEXEC=1 bash "$0" "$@"
fi

set -e

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

### Changelog manifest + revision (before anything that touches the site)
### The generator aborts when a fragment's ship date is not in git, and a bad
### fragment must not end up half-deployed.
echo "=== Generating changelog manifest + revision ==="
if [[ "$IS_DDEV" = true ]]; then
    ddev exec CI=true npx pnpm@10 run changelog:gen
else
    CI=true npx pnpm@10 run changelog:gen
fi

### Build the JS and CSS bundles with Vite
### Right after the pull, as the new pages need the new bundles. build:deploy builds into
### webroot/dist-next and only then swaps it into webroot/dist (scripts/publish-dist.mjs):
### building in place empties webroot/dist first, and a page rendered meanwhile loaded
### without the legacy scripts.
echo "=== Building React app ==="
if [[ "$IS_DDEV" = true ]]; then
    ddev exec CI=true npx pnpm@10 install --frozen-lockfile
    ddev exec CI=true npx pnpm@10 run build:deploy
else
    CI=true npx pnpm@10 install --frozen-lockfile
    CI=true npx pnpm@10 run build:deploy
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

# The application writes 4-byte characters (an emoji in a comment, for one), and a
# utf8mb3 connection cannot carry them: the write loses them to "?" or fails with
# "Incorrect string value". This also brings a config created by an older deploy in
# line with database.example.php.
#
# The pattern ends at the closing quote, so a config that already says utf8mb4 is
# left alone and running the deploy twice changes nothing. A backup suffix keeps
# in-place editing working with both GNU sed and BSD sed.
sed -i.bak "s/'encoding' => 'utf8'/'encoding' => 'utf8mb4'/" "$DB_CONFIG_FILE"
rm -f "$DB_CONFIG_FILE.bak"

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
echo "=== Running Composer... ==="
if [[ "$IS_DDEV" = true ]]; then
    ddev exec composer install --prefer-dist --optimize-autoloader --no-interaction
else
    composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction
fi


echo "=== Setting up permissions ==="
mkdir -p "$ROOT_DIR/tmp"
chmod 777 "$ROOT_DIR/tmp"
mkdir -p "$ROOT_DIR/logs"
chmod 777 "$ROOT_DIR/logs"
mkdir -p "$ROOT_DIR/tmp/cache"
chmod 777 "$ROOT_DIR/tmp/cache"
mkdir -p "$ROOT_DIR/tmp/cache/models"
chmod 777 "$ROOT_DIR/tmp/cache/models"
mkdir -p "$ROOT_DIR/tmp/cache/persistent"
chmod 777 "$ROOT_DIR/tmp/cache/persistent"
mkdir -p "$ROOT_DIR/tmp/cache/htmlpurifier"
chmod 777 "$ROOT_DIR/tmp/cache/htmlpurifier"
# The change stamps (src/Utility/Live.php), which PHP writes and Apache serves
mkdir -p "$ROOT_DIR/webroot/live"
chmod 777 "$ROOT_DIR/webroot/live"

chmod 777 "$ROOT_DIR/webroot/forums/cache"
mkdir -p "$ROOT_DIR/webroot/forums/cache/production"
chmod 777 "$ROOT_DIR/webroot/forums/cache/production"
chmod 777 "$ROOT_DIR/webroot/forums/store"
chmod 777 "$ROOT_DIR/webroot/forums/files"
chmod 777 "$ROOT_DIR/webroot/forums/images/avatars/upload"

# Set cover images are uploaded by the web user at runtime, so the directory they
# land in has to be writable for that user. The directory's .htaccess blocks
# execution of anything uploaded there.
mkdir -p "$ROOT_DIR/webroot/img/sets"
chmod 777 "$ROOT_DIR/webroot/img/sets"


### Run migrations (inside container in dev, locally in production)
echo "=== Running migrations ==="
if [[ "$IS_DDEV" = true ]]; then
    ddev exec vendor/bin/phinx migrate
    ddev exec vendor/bin/phinx migrate -e test
else
    vendor/bin/phinx migrate
fi

# Clear all caches, so the new code doesn't run on stale cached models
echo "=== Clearing caches ==="
rm -rf "$ROOT_DIR/tmp/cache/models"/*
rm -rf "$ROOT_DIR/tmp/cache/persistent"/*

# The push hub of the change stamps (scripts/live-hub.mjs), where the site runs it as a daemon of
# NearlyFreeSpeech: stopped, so that NFSN starts it again with the new code. Its pages reconnect, or ask
# for the stamps themselves meanwhile. Nothing to stop where no hub runs, which is no failure.
pkill -TERM -f 'scripts/live-hub.mjs' >/dev/null 2>&1 || true


echo "=== Deploy complete ==="

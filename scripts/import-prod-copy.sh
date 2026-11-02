#!/usr/bin/env bash
# Imports a copy of the production database into a local MariaDB 11.7 in Docker,
# anonymizes it and migrates it to the schema of the checkout.
# Usage: scripts/import-prod-copy.sh
#
# It reads the dump (a mysqldump, gzipped or not) from
#   TSUMEGO_DB_DUMP_URL       https://... of the dump
#   TSUMEGO_DB_DUMP_USER      basic auth user (optional)
#   TSUMEGO_DB_DUMP_PASSWORD  basic auth password (optional)
# and streams it straight into the database, so the dump never lands on the disk.
# Docs: docs/prod-db-copy.md
#
# Anonymization, right after the import:
# - every email becomes user<id>@example.invalid (forum: forum<id>@example.invalid),
# - every password hash becomes the hash of the password below, so anyone can log in as any user,
# - tokens of password resets, email changes and account deletions are cleared,
# - Google ids are cleared, so Google accounts log in with the email and the password too,
# - forum sessions, OAuth tokens, IP addresses and the secrets in phpbb_config are cleared.
# A column that looks private and that the script doesn't know stops it before anything is used,
# so a new column gets a rule here instead of slipping through.
# No user data is printed, only counts and column names.

set -euo pipefail

cd "$(dirname "$0")/.."

TEST_PASSWORD='tsumego-copy-login'
CONTAINER="${TSUMEGO_DB_CONTAINER:-tsumego-db}"
DATABASE="${TSUMEGO_DB_COPY_NAME:-tsumego_prod}"
PORT="${TSUMEGO_DB_PORT:-3306}"
IMAGE='mirror.gcr.io/library/mariadb:11.7'
ROOT_PASSWORD='root'

log() { echo "[import-prod-copy] $*" >&2; }
fail() { log "ERROR: $*"; exit 1; }

#The dump, before anything is started
: "${TSUMEGO_DB_DUMP_URL:?set TSUMEGO_DB_DUMP_URL (see docs/prod-db-copy.md)}"
#Basic auth is optional, for a URL that is a secret itself, but one of the two alone is a mistake
TSUMEGO_DB_DUMP_USER="${TSUMEGO_DB_DUMP_USER:-}"
TSUMEGO_DB_DUMP_PASSWORD="${TSUMEGO_DB_DUMP_PASSWORD:-}"
if [[ -n "$TSUMEGO_DB_DUMP_USER" && -z "$TSUMEGO_DB_DUMP_PASSWORD" || -z "$TSUMEGO_DB_DUMP_USER" && -n "$TSUMEGO_DB_DUMP_PASSWORD" ]]; then
	fail "set both TSUMEGO_DB_DUMP_USER and TSUMEGO_DB_DUMP_PASSWORD, or neither"
fi
[[ "$DATABASE" =~ ^[A-Za-z0-9_]+$ ]] || fail "TSUMEGO_DB_COPY_NAME may only contain letters, digits and _"
[[ "$DATABASE" != test ]] || fail "the tests empty the database 'test', the copy needs another name"
command -v php > /dev/null || fail "php is needed for the password hash and the migrations"
[[ -x vendor/bin/phinx ]] || fail "vendor/bin/phinx is missing, run composer install first (docs/prod-db-copy.md)"

#The credentials go to curl in a config read from a pipe, so they are not in the process list
curlConfig() {
	[[ -n "$TSUMEGO_DB_DUMP_USER" ]] || return 0
	local credentials="$TSUMEGO_DB_DUMP_USER:$TSUMEGO_DB_DUMP_PASSWORD"
	credentials="${credentials//\\/\\\\}"
	printf 'user = "%s"\n' "${credentials//\"/\\\"}"
}

#Docker, started when the session hasn't got it running
if ! docker info > /dev/null 2>&1; then
	log "starting dockerd ..."
	nohup dockerd > /tmp/dockerd.log 2>&1 &
	timeout 60 sh -c 'until docker info > /dev/null 2>&1; do sleep 1; done' || fail "dockerd didn't start, see /tmp/dockerd.log"
fi

#The server, tuned for a bulk import: the copy is disposable, so the durability settings trade safety for speed.
#Total memory minus 5 GB for PHP, the browser and the rest, at least 1 GB.
memoryGigabytes=$(awk '/MemTotal/ { printf "%d", $2 / 1024 / 1024 }' /proc/meminfo)
bufferPoolGigabytes=$(( memoryGigabytes > 6 ? memoryGigabytes - 5 : 1 ))
if docker container inspect "$CONTAINER" > /dev/null 2>&1; then
	log "using the existing container $CONTAINER"
	docker start "$CONTAINER" > /dev/null
else
	log "starting $IMAGE as $CONTAINER on port $PORT (buffer pool ${bufferPoolGigabytes} GB) ..."
	docker run -d --name "$CONTAINER" \
		-e MARIADB_ROOT_PASSWORD="$ROOT_PASSWORD" -e MARIADB_ROOT_HOST=% -e MARIADB_DATABASE=test \
		-p "127.0.0.1:$PORT:3306" \
		-v "$CONTAINER-data:/var/lib/mysql" \
		"$IMAGE" \
		--innodb-buffer-pool-size="${bufferPoolGigabytes}G" \
		--innodb-log-file-size=2G \
		--innodb-log-buffer-size=256M \
		--innodb-flush-log-at-trx-commit=2 \
		--innodb-doublewrite=0 \
		--innodb-io-capacity=4000 \
		--innodb-io-capacity-max=8000 \
		--skip-log-bin \
		--max-allowed-packet=1G \
		--character-set-server=utf8mb4 \
		--collation-server=utf8mb4_unicode_ci \
		> /dev/null || fail "the container didn't start (is port $PORT taken? set TSUMEGO_DB_PORT)"
fi

sql() { docker exec -i "$CONTAINER" mariadb --protocol=tcp -h127.0.0.1 -uroot -p"$ROOT_PASSWORD" "$@"; }
#Over TCP, which the temporary server of the first start doesn't listen on
timeout 120 sh -c "until docker exec $CONTAINER mariadb --protocol=tcp -h127.0.0.1 -uroot -p$ROOT_PASSWORD -e 'SELECT 1' > /dev/null 2>&1; do sleep 1; done" \
	|| { docker logs --tail 30 "$CONTAINER" >&2; fail "MariaDB didn't come up"; }

#A fresh database, so a dump without DROP TABLE or with tables of an older import can't mix in
log "recreating the database $DATABASE ..."
sql -e "DROP DATABASE IF EXISTS \`$DATABASE\`; CREATE DATABASE \`$DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
#Until the anonymization is done, a failure drops the database, so no copy with the original data stays around
anonymized=false
phinxConfig=''
cleanUp() {
	[[ -z "$phinxConfig" ]] || rm -f "$phinxConfig"
	if [[ "$anonymized" != true ]]; then
		log "dropping the database $DATABASE, it isn't anonymized"
		sql -e "DROP DATABASE IF EXISTS \`$DATABASE\`" || log "ERROR: dropping it failed, drop it by hand"
	fi
}
trap cleanUp EXIT

#The size of the download, for the progress and its estimate
size=$(curl -sSIL -K <(curlConfig) "$TSUMEGO_DB_DUMP_URL" | tr -d '\r' | awk 'tolower($1) == "content-length:" { size = $2 } END { print size }' || true)
progress() {
	if command -v pv > /dev/null; then
		pv ${size:+-s "$size"} -N download
	else
		cat
	fi
}
#curl's own progress bar when there's no pv
curlProgress='-sS'
command -v pv > /dev/null || curlProgress='--progress-bar'
decompress='gzip'
command -v pigz > /dev/null && decompress='pigz'

log "importing ${size:+$(numfmt --to=iec "$size")B }into $DATABASE ..."
start=$SECONDS
#gzip -f passes an uncompressed dump through as it is.
#The dump's own USE and CREATE DATABASE would send it elsewhere than into the fresh database, so they are dropped.
#An error of mysql quotes the statement or a value, which can be user data, so only its code and line number are kept.
set +e
curl -fL $curlProgress -K <(curlConfig) "$TSUMEGO_DB_DUMP_URL" \
	| progress \
	| "$decompress" -cdf \
	| sed -E '/^(USE |CREATE DATABASE )/d' \
	| sql --max-allowed-packet=1G \
		--init-command='SET SESSION foreign_key_checks = 0, unique_checks = 0, sql_log_bin = 0' \
		"$DATABASE" 2> >(sed -nE 's/^(ERROR [0-9]+ \([^)]*\)( at line [0-9]+)?).*/\1 (the rest of the message is not shown, it can contain user data)/p' >&2)
statuses=("${PIPESTATUS[@]}")
set -e
[[ "${statuses[0]}" == 0 ]] || fail "the download failed (curl exit ${statuses[0]}, 22 is an HTTP error such as 401 or 404)"
for status in "${statuses[@]}"; do
	[[ "$status" == 0 ]] || fail "the import failed (exit statuses of download | progress | decompress | filter | mysql: ${statuses[*]})"
done
log "imported in $(( (SECONDS - start) / 60 )) min"

#Anonymization
passwordHash=$(TEST_PASSWORD="$TEST_PASSWORD" php -r 'echo password_hash(getenv("TEST_PASSWORD"), PASSWORD_DEFAULT);')
[[ "$passwordHash" == '$2y$'* ]] || fail "password_hash() didn't return a bcrypt hash"

#Each column whose name looks private needs a rule: the rule is the expression it is set to, or KEEP for one that isn't private.
#The expressions can use the row's own id.
declare -A RULES=(
	[user.email]="CONCAT('user', id, '@example.invalid')"
	[user.unconfirmed_email]="IF(unconfirmed_email IS NULL, NULL, CONCAT('user', id, '-unconfirmed@example.invalid'))"
	[user.password_hash]="'$passwordHash'"
	[user.password_reset_token]="NULL"
	[user.password_reset_token_created]="NULL"
	[user.deletion_token]="NULL"
	[user.deletion_token_created]="NULL"
	[user.unconfirmed_email_token]="NULL"
	[user.unconfirmed_email_cancel_token]="NULL"
	[user.unconfirmed_email_created]="NULL"
	[user.external_id]="NULL"
	[user.deleted_external_id]="NULL"
	[user.email_on_new_message]=KEEP
	[user.uploaded_avatar_hash]=KEEP
	[phpbb_users.user_email]="IF(user_email = '', '', CONCAT('forum', user_id, '@example.invalid'))"
	[phpbb_users.user_password]="IF(user_password = '', '', '$passwordHash')"
	[phpbb_users.user_newpasswd]="''"
	[phpbb_users.user_actkey]="''"
	[phpbb_users.user_actkey_expiration]="0"
	[phpbb_users.reset_token]="''"
	[phpbb_users.reset_token_expiration]="0"
	[phpbb_users.user_last_confirm_key]="''"
	[phpbb_users.user_ip]="''"
	[phpbb_users.external_id]=KEEP #the id of the site's user, for the single sign-on
	[phpbb_users.user_form_salt]=KEEP
	[phpbb_users.user_passchg]=KEEP
	[phpbb_users.user_emailtime]=KEEP
	[phpbb_users.user_allow_viewemail]=KEEP
	[phpbb_users.user_allow_massemail]=KEEP
	[phpbb_banlist.ban_email]="IF(ban_email = '', '', CONCAT('banned', ban_id, '@example.invalid'))"
	[phpbb_banlist.ban_ip]="IF(ban_ip = '', '', '127.0.0.1')"
	[phpbb_posts.poster_ip]="''"
	[phpbb_privmsgs.author_ip]="''"
	[phpbb_poll_votes.vote_user_ip]="''"
	[phpbb_log.log_ip]="''"
	[phpbb_sitelist.site_ip]="''"
	[phpbb_bots.bot_ip]=KEEP
	[phpbb_bbcodes.first_pass_match]=KEEP
	[phpbb_bbcodes.first_pass_replace]=KEEP
	[phpbb_bbcodes.second_pass_match]=KEEP
	[phpbb_bbcodes.second_pass_replace]=KEEP
	[tsumego.pass]=KEEP
	[phpbb_forums.forum_password]=KEEP
)
#Tables that are only sessions, login attempts and tokens, emptied
EMPTIED_TABLES=(phpbb_sessions phpbb_sessions_keys phpbb_login_attempts phpbb_confirm phpbb_forums_access phpbb_oauth_tokens phpbb_oauth_states phpbb_oauth_accounts phpbb_search_results)
#Values of phpbb_config that are secrets of other services
PHPBB_SECRETS="'smtp_password', 'smtp_username', 'jab_password', 'ldap_password', 'recaptcha_v3_secret', 'auth_oauth_bitly_secret', 'auth_oauth_facebook_secret', 'auth_oauth_google_secret', 'auth_oauth_twitter_secret'"
#Without these the copy would not be anonymized at all, so their absence is an error, not a skip
REQUIRED_COLUMNS=(user.email user.password_hash)
PRIVATE_PATTERN='mail|pass|token|secret|external|actkey|confirm_key|newpasswd|salt|(^|_)ip$'

columns=$(sql -N -e "SELECT CONCAT(table_name, '.', column_name) FROM information_schema.columns
	WHERE table_schema = '$DATABASE' AND column_name REGEXP '$PRIVATE_PATTERN' ORDER BY 1")
unknown=()
for column in $columns; do
	[[ -v "RULES[$column]" ]] || unknown+=("$column")
done
if (( ${#unknown[@]} > 0 )); then
	#A column of an emptied table needs no rule
	remaining=()
	for column in "${unknown[@]}"; do
		table="${column%%.*}"
		[[ " ${EMPTIED_TABLES[*]} " == *" $table "* ]] || remaining+=("$column")
	done
	(( ${#remaining[@]} == 0 )) || fail "columns that look private and have no rule in RULES of this script: ${remaining[*]}; add the rules and run again"
fi
for column in "${REQUIRED_COLUMNS[@]}"; do
	grep -qx "$column" <<< "$columns" || fail "the dump has no $column, is it the tsumego database?"
done

log "anonymizing ..."
anonymization="SET SESSION sql_log_bin = 0;"
declare -A assignments=()
for column in $columns; do
	rule="${RULES[$column]:-}"
	[[ -n "$rule" && "$rule" != KEEP ]] || continue
	table="${column%%.*}"
	assignments[$table]+="${assignments[$table]:+, }\`${column#*.}\` = $rule"
done
for table in "${!assignments[@]}"; do
	anonymization+=" UPDATE \`$table\` SET ${assignments[$table]};"
done
tables=$(sql -N -e "SHOW TABLES FROM \`$DATABASE\`")
for table in "${EMPTIED_TABLES[@]}"; do
	grep -qx "$table" <<< "$tables" && anonymization+=" TRUNCATE TABLE \`$table\`;"
done
if grep -qx phpbb_config <<< "$tables"; then
	anonymization+=" UPDATE phpbb_config SET config_value = '' WHERE config_name IN ($PHPBB_SECRETS);"
fi
sql "$DATABASE" <<< "$anonymization"

#A check that reads only counts: no email or password of the dump is left
leftovers=$(sql -N "$DATABASE" <<< "SELECT
	(SELECT COUNT(*) FROM user WHERE email NOT LIKE '%@example.invalid' OR password_hash <> '$passwordHash' OR external_id IS NOT NULL)
	+ IF((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DATABASE' AND table_name = 'phpbb_users') = 0, 0,
		(SELECT COUNT(*) FROM phpbb_users WHERE user_email NOT IN ('') AND user_email NOT LIKE '%@example.invalid'))")
[[ "$leftovers" == 0 ]] || fail "$leftovers rows still have an original email, password or Google id"
anonymized=true
users=$(sql -N "$DATABASE" -e "SELECT COUNT(*) FROM user")
log "anonymized $users users"

#Migrations, with a Phinx config of their own, so config/database.php stays as it is
phinxConfig=$(mktemp --suffix=.php)
cat > "$phinxConfig" << PHP
<?php
return [
	'paths' => ['migrations' => '$PWD/db/migrations'],
	'environments' => [
		'default_migration_table' => 'phinxlog',
		'default_environment' => 'copy',
		'copy' => ['adapter' => 'mysql', 'host' => '127.0.0.1', 'port' => $PORT, 'name' => '$DATABASE', 'user' => 'root', 'pass' => '$ROOT_PASSWORD', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'],
	],
	'version_order' => 'creation',
];
PHP
log "migrating ..."
vendor/bin/phinx migrate -c "$phinxConfig" | grep -E '==|All Done' >&2 || fail "the migrations failed, run them again: vendor/bin/phinx migrate -c <config like the one in this script>"

sizeMegabytes=$(sql -N -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024) FROM information_schema.tables WHERE table_schema = '$DATABASE'")
cat << TEXT

Done: the anonymized copy is the database $DATABASE (${sizeMegabytes} MB, $users users)
in the container $CONTAINER, MariaDB 11.7 at 127.0.0.1:$PORT, user root, password $ROOT_PASSWORD.
The same server has an empty database 'test' for the tests, which empty it; never point them at $DATABASE.

SQL:   docker exec -it $CONTAINER mariadb -uroot -p$ROOT_PASSWORD $DATABASE
The app: config/database.php (a copy of config/database.example.php) with
	\$default: 'host' => '127.0.0.1', 'login' => 'root', 'password' => '$ROOT_PASSWORD', 'database' => '$DATABASE'
	\$test:    'host' => '127.0.0.1', 'login' => 'root', 'password' => '$ROOT_PASSWORD', 'database' => 'test'
Log in as any user at /users/login with the email user<id>@example.invalid (or the user's name)
and the password $TEST_PASSWORD, kovarex for one: user13282@example.invalid.
The id of a user: SELECT id FROM user WHERE name = '...';
Stop: docker stop $CONTAINER (the data stays in the volume $CONTAINER-data), start again: docker start $CONTAINER
TEXT

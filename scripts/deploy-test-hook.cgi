#!/bin/sh
# Deploys a pull request to the test site (test.tsumego.com) when the
# "Deploy to test" GitHub workflow asks for it.
#
# Installed once on the test site, outside of what git checks out, so deploying
# an older PR can't remove or change it; deploying master updates it (see
# "Deploying a PR to the test site" in README.md):
#
#	cp scripts/deploy-test-hook.cgi webroot/deploy-hook.cgi
#	chmod 4755 webroot/deploy-hook.cgi
#
# The suid bit makes NearlyFreeSpeech run it as the site owner instead of the
# "web" user, which can't modify the site's files. A deploy takes minutes, so the
# request only starts it in the background and the workflow polls for the log.
#
# Every request must send the token from $TOKEN_FILE in the X-Deploy-Token header.
#	POST ?pr=N	starts deploying PR N (0 = master), answers with the deploy id
#	GET		the state of the last deploy, then "---" and its log

REPO_DIR=/home/public
STATE_DIR=/home/private/deploy-test
TOKEN_FILE=/home/private/deploy-token
# Optional; restored whenever the test DB has a migration the deployed commit doesn't know.
DB_SNAPSHOT=/home/private/test-snapshot.sql.gz

# The same PATH as an ssh session: /home/private/bin holds the site's php 8.4, the
# system php is older.
PATH=/home/private/bin:/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin
export PATH
: "${HOME:=/home/private}"
export HOME

STATUS_FILE="$STATE_DIR/status"
LOG_FILE="$STATE_DIR/log"
LOCK_DIR="$STATE_DIR/lock"

write_status() {
	printf 'state=%s\nid=%s\npr=%s\ncommit=%s\n' "$1" "$2" "$3" "$4" > "$STATUS_FILE.tmp"
	mv "$STATUS_FILE.tmp" "$STATUS_FILE"
}

# Prints host, login, password and database of the site's DB, one per line.
read_db_config() {
	php -r 'class Configure { static function read($key) { return 0; } }
		require "config/database.php";
		$db = (new DATABASE_CONFIG())->default;
		echo $db["host"], "\n", $db["login"], "\n", $db["password"], "\n", $db["database"], "\n";'
}

# The migrations only go forward, so after a PR with a new migration the test DB
# is ahead of the next PR's schema. Phinx reports such a migration as missing.
reset_db_if_ahead() {
	missing=$(vendor/bin/phinx status | grep 'MISSING MIGRATION FILE' || true)
	if [ -z "$missing" ]; then
		return 0
	fi
	echo "The test DB has migrations this commit doesn't have:"
	echo "$missing"
	if [ ! -f "$DB_SNAPSHOT" ]; then
		echo "There is no $DB_SNAPSHOT to restore, so the DB stays as it is."
		return 0
	fi
	echo "=== Restoring the test DB from $DB_SNAPSHOT ==="
	read_db_config | {
		read -r db_host
		read -r db_user
		read -r db_password
		read -r db_name
		export MYSQL_PWD="$db_password"
		mysql -h "$db_host" -u "$db_user" -e "DROP DATABASE \`$db_name\`; CREATE DATABASE \`$db_name\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
		gzip -dc "$DB_SNAPSHOT" | mysql -h "$db_host" -u "$db_user" "$db_name"
	}
}

deploy_steps() {
	set -e
	echo "=== Deploying PR $1 as $(id -un) ==="
	# A CGI process gets less memory than an ssh session: raise the soft limits as far as
	# the hard ones allow, and keep git from mapping its packs in 1 GiB windows. The
	# GIT_CONFIG_* variables also reach the git calls made by deploy.sh.
	for limit in d m v; do
		ulimit -S -$limit "$(ulimit -H -$limit)" 2> /dev/null || true
	done
	ulimit -a
	export GIT_CONFIG_COUNT=4
	export GIT_CONFIG_KEY_0=core.packedGitWindowSize GIT_CONFIG_VALUE_0=16m
	export GIT_CONFIG_KEY_1=core.packedGitLimit GIT_CONFIG_VALUE_1=64m
	export GIT_CONFIG_KEY_2=pack.windowMemory GIT_CONFIG_VALUE_2=64m
	export GIT_CONFIG_KEY_3=pack.threads GIT_CONFIG_VALUE_3=1
	cd "$REPO_DIR"
	if [ "$1" = 0 ]; then
		git fetch origin master
	else
		git fetch origin "pull/$1/head"
	fi
	git checkout --force --detach FETCH_HEAD
	git log -1 --format='%h %s' > "$STATE_DIR/commit"
	cat "$STATE_DIR/commit"
	reset_db_if_ahead
	# DEPLOY_REEXEC makes deploy.sh skip its git pull, which can't work on a detached commit.
	DEPLOY_REEXEC=1 bash ./deploy.sh
	# Deploying master brings the hook's own latest version. It replaces this file by a
	# new one, so the copy running now isn't changed under it.
	if [ "$1" = 0 ] && ! cmp -s scripts/deploy-test-hook.cgi "$HOOK_FILE"; then
		cp scripts/deploy-test-hook.cgi "$HOOK_FILE.new"
		chmod 4755 "$HOOK_FILE.new"
		mv "$HOOK_FILE.new" "$HOOK_FILE"
		echo "=== Updated $HOOK_FILE ==="
	fi
}

run_deploy() {
	echo $$ > "$LOCK_DIR/pid"
	trap 'rm -rf "$LOCK_DIR"' EXIT
	: > "$STATE_DIR/commit"
	write_status running "$2" "$1" ""
	(deploy_steps "$1") > "$LOG_FILE" 2>&1
	result=$?
	commit=$(cut -d' ' -f1 "$STATE_DIR/commit")
	if [ "$result" = 0 ]; then
		write_status ok "$2" "$1" "$commit"
	else
		write_status failed "$2" "$1" "$commit"
	fi
}

respond() {
	printf 'Status: %s\r\nContent-Type: text/plain; charset=utf-8\r\nCache-Control: no-store\r\n\r\n' "$1"
	shift
	if [ $# -gt 0 ]; then
		printf '%s\n' "$*"
	fi
	exit 0
}

# Takes the lock, or answers 409 when another deploy still runs.
lock() {
	if ! mkdir "$LOCK_DIR" 2>/dev/null; then
		if kill -0 "$(cat "$LOCK_DIR/pid" 2>/dev/null)" 2>/dev/null; then
			respond "409 Conflict" "A deploy is already running."
		fi
		rm -rf "$LOCK_DIR"
		mkdir "$LOCK_DIR" || respond "409 Conflict" "A deploy is already running."
	fi
	# Replaced by the deploy's own pid once it starts.
	echo $$ > "$LOCK_DIR/pid"
}

if [ "$1" = __run ]; then
	HOOK_FILE=$0
	run_deploy "$2" "$3"
	exit 0
fi

mkdir -p "$STATE_DIR"
token=$(cat "$TOKEN_FILE" 2>/dev/null)
if [ -z "$token" ] || [ "${HTTP_X_DEPLOY_TOKEN:-}" != "$token" ]; then
	respond "403 Forbidden"
fi

case "${REQUEST_METHOD:-}" in
GET)
	respond "200 OK" "$(cat "$STATUS_FILE" 2>/dev/null)
---
$(cat "$LOG_FILE" 2>/dev/null)"
	;;
POST)
	pr=${QUERY_STRING#pr=}
	case "$pr" in
	'' | *[!0-9]*) respond "400 Bad Request" "Expected ?pr=<number>." ;;
	esac
	lock
	id="$(date +%Y%m%d%H%M%S)-$$"
	write_status queued "$id" "$pr" ""
	# Apache may pass only the bare file name in $0.
	self=${SCRIPT_FILENAME:-$0}
	# daemon(8) detaches the deploy from the request, so it survives the request ending.
	if command -v daemon > /dev/null; then
		daemon -f "$self" __run "$pr" "$id"
	else
		nohup "$self" __run "$pr" "$id" < /dev/null > /dev/null 2>&1 &
	fi
	respond "202 Accepted" "$id"
	;;
*)
	respond "405 Method Not Allowed"
	;;
esac

# A copy of the production database

For profiling (an account with ~70k attempts), testing migrations on real data and checking queries at real sizes,
any session (a Claude cloud session included) can import an anonymized copy of the production database into a local MariaDB 11.7:

```sh
scripts/import-prod-copy.sh
```

The script:
- starts MariaDB 11.7 in Docker (container `tsumego-db`, `127.0.0.1:3306`, user `root`, password `root`), tuned for a fast bulk import, and starts `dockerd` when it isn't running,
- streams the dump from `TSUMEGO_DB_DUMP_URL` into a fresh database `tsumego_prod` (gzipped or not, `USE` and `CREATE DATABASE` lines of the dump are ignored); the dump never lands on the disk,
- anonymizes it right away: emails become `user<id>@example.invalid`, every password hash becomes the hash of `tsumego-copy-login`, tokens, Google ids, forum sessions, IP addresses and the secrets in `phpbb_config` are cleared; a column that looks private and has no rule in the script stops it, and a failed run drops the database,
- runs the Phinx migrations, so the copy has the schema of the checkout,
- prints how to point the app at it and how to log in.

Then log in as any user with the email `user<id>@example.invalid` (or the user's name) and the password `tsumego-copy-login`.
kovarex (id 13282, ~70k attempts, the account to profile with) is `user13282@example.invalid`.
The tests keep using the database `test` of the same server, they empty it; never point them at `tsumego_prod`.

The script needs `php` and `vendor/bin/phinx`. Where `composer install` can't download from GitHub (a cloud session behind its proxy, PHP 8.3), this is enough for the migrations:

```sh
composer install --no-dev --prefer-source --ignore-platform-req=php
```

Optional settings: `TSUMEGO_DB_COPY_NAME` (database, default `tsumego_prod`), `TSUMEGO_DB_PORT` (default `3306`), `TSUMEGO_DB_CONTAINER` (default `tsumego-db`).

## Producing the dump (NearlyFreeSpeech)

Over ssh on the production site (the DB host, user and name are in `config/database.php`):

```sh
# --single-transaction: a consistent snapshot of InnoDB without locking the site
# --quick: rows streamed instead of buffered, needed for tsumego_attempt and tsumego_status
mysqldump -h <db host> -u <db user> -p \
	--single-transaction --quick --routines --triggers --hex-blob --no-tablespaces \
	<db name> | gzip -6 > /home/tmp/tsumego.sql.gz
```

It contains the forum's `phpbb_*` tables too, which the script anonymizes as well.

## Publishing it on the test site

Under a random directory of the test site, behind basic auth. Once, over ssh on the test site
(`/home/public/webroot` is the document root of test.tsumego.com; `/webroot/db-dump/` is in `.gitignore`):

```sh
DIRECTORY=$(openssl rand -hex 16)
mkdir -p /home/public/webroot/db-dump/$DIRECTORY
# The password file lives in /home/protected, which the web server can read but doesn't serve
htpasswd -c /home/protected/db-dump.htpasswd claude
cat > /home/public/webroot/db-dump/.htaccess << 'EOF'
AuthType Basic
AuthName "tsumego db dump"
AuthUserFile /home/protected/db-dump.htpasswd
Require valid-user
Options -Indexes
EOF
echo "https://test.tsumego.com/db-dump/$DIRECTORY/tsumego.sql.gz"
```

Then copy the dump there, from the production site:

```sh
scp /home/tmp/tsumego.sql.gz <test site ssh login>:/home/public/webroot/db-dump/<DIRECTORY>/tsumego.sql.gz
```

Check it: `curl -I -u claude https://test.tsumego.com/db-dump/<DIRECTORY>/tsumego.sql.gz` answers 200, and without `-u` 401.

**Refreshing**: run the mysqldump again and copy it over the same file; the URL and the password stay.
A session gets the new data by running `scripts/import-prod-copy.sh` again (it replaces `tsumego_prod`).

## The cloud environment

In the Claude Code cloud environment settings (the environment menu in the session's title bar → Edit),
add these under **Network secrets** (called **API credentials** in older apps), or as environment variables where that section isn't offered:

```
TSUMEGO_DB_DUMP_URL=https://test.tsumego.com/db-dump/<DIRECTORY>/tsumego.sql.gz
TSUMEGO_DB_DUMP_USER=claude
TSUMEGO_DB_DUMP_PASSWORD=<the htpasswd password>
```

Without basic auth, set only `TSUMEGO_DB_DUMP_URL`; then the URL itself is the secret (a long random path, nothing that lists it), so it belongs in the settings too, not in a chat.

Only sessions started after the change see them. test.tsumego.com has to be allowed by the environment's network access.

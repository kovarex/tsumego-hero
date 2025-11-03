#!/usr/bin/env bash

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
echo Working in $SCRIPT_DIR
cd $SCRIPT_DIR

rm db-dump.sql
echo Downloading db file
curl https://tsumego-hero.com/files/$1 > db-dump.sql
echo fixing sql file
sed 's/CHARSET=[a-z_0-9]*/CHARSET=utf8mb4/g' db-dump.sql > tmp.sql && mv tmp.sql db-dump.sql
sed 's/CHARACTER SET [a-z_0-9]*/CHARACTER SET utf8mb4/g' db-dump.sql > tmp.sql && mv tmp.sql db-dump.sql
sed 's/COLLATE=[a-z_0-9]*/COLLATE=utf8mb4_unicode_ci/g' db-dump.sql > tmp.sql && mv tmp.sql db-dump.sql
sed 's/COLLATE [a-z_0-9]*/COLLATE utf8mb4_unicode_ci/g' db-dump.sql > tmp.sql && mv tmp.sql db-dump.sql
export host_parameter="--host=$2"

if [ "localhost" = $2 ]; then
  export host_parameter=
fi
echo importing database
mysql $host_parameter -u $3 -p $4 < db-dump.sql
/usr/local/bin/php8.4 /usr/local/bin/composer migrate

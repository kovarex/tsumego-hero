#!/usr/bin/env bash

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
echo Working in $SCRIPT_DIR
cd $SCRIPT_DIR

rm db-dump.sql
echo Downloading db file
curl https://tsumego-hero.com/files/$1 > db-dump.sql
echo fixing sql file 1/4
sed 's/CHARSET=[a-z_0-9]*/CHARSET=utf8mb4/g' db-dump.sql > tmp.sql && mv tmp.sql db-dump.sql
echo fixing sql file 2/4
sed 's/CHARACTER SET [a-z_0-9]*/CHARACTER SET utf8mb4/g' db-dump.sql > tmp.sql && mv tmp.sql db-dump.sql
echo fixing sql file 3/4
sed 's/COLLATE=[a-z_0-9]*/COLLATE=utf8mb4_unicode_ci/g' db-dump.sql > tmp.sql && mv tmp.sql db-dump.sql
echo fixing sql file 4/4
sed 's/COLLATE [a-z_0-9]*/COLLATE utf8mb4_unicode_ci/g' db-dump.sql > tmp.sql && mv tmp.sql db-dump.sql
export host_parameter="--host=$2"

if [ "localhost" = $2 ]; then
  export host_parameter=
fi
echo creating database $4
echo CREATE DATABASE $4 | mysql $host_parameter -u $3 -p
echo importing database into database $4
mysql $host_parameter -u $3 -p $4 < db-dump.sql
cd ..
/usr/local/bin/php8.4 /usr/local/bin/composer migrate

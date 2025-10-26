unzip -p tsumego-hero-db-dump.zip > tsumego-hero-db-dump.sql
sed -i 's/CHARSET=[a-z_0-9]*/CHARSET=utf8mb4/g' tsumego-hero-db-dump.sql
sed -i 's/CHARACTER SET [a-z_0-9]*/CHARACTER SET utf8mb4/g' tsumego-hero-db-dump.sql
sed -i 's/COLLATE=[a-z_0-9]*/COLLATE=utf8mb4_unicode_ci/g' tsumego-hero-db-dump.sql
sed -i 's/COLLATE [a-z_0-9]*/COLLATE utf8mb4_unicode_ci/g' tsumego-hero-db-dump.sql
export host_parameter="--host=$1"

if [ "localhost" = $1 ]; then
  export host_parameter=
fi
mysql $host_parameter -u $2 -p $3 < tsumego-hero-db-dump.sql < sql-fix.sql

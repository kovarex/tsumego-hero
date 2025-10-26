unzip -p tsumego-hero-db-dump.zip > tsumego-hero-db-dump.sql
sed -i 's/CHARSET=[a-z_0-9]*/CHARSET=utf8mb4/g' tsumego-hero-db-dump.sql
sed -i 's/CHARACTER SET [a-z_0-9]*/CHARACTER SET utf8mb4/g' tsumego-hero-db-dump.sql
sed -i 's/COLLATE=[a-z_0-9]*/COLLATE=utf8mb4_unicode_ci/g' tsumego-hero-db-dump.sql
sed -i 's/COLLATE [a-z_0-9]*/COLLATE utf8mb4_unicode_ci/g' tsumego-hero-db-dump.sql
mysql -u root -p db < tsumego-hero-db-dump.sql
mysql -u root -p db < sql-fix.sql

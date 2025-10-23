cp ./cleanCodeStyleConvertor/.php-cs-fixer.php .php-cs-fixer.php
touch .php-cs-fixer.cache
rm .php-cs-fixer.cache
./vendor/bin/php-cs-fixer fix
rm .php-cs-fixer.php

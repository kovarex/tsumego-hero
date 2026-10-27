<?php
//Phinx configuration: loads the database settings from CakePHP's database.php and provides them
//to Phinx for migrations (https://book.cakephp.org/phinx/0/en/migrations.html).
//It loads database.php in a simplified way to avoid loading the full CakePHP bootstrap, which
//would cause conflicts in the CLI context.

// Define Configure class stub to allow database.php to load
if (!class_exists('Configure')) {
    class Configure {
        private static $debug = 0;
        public static function read($key) {
            if ($key === 'debug') {
                return self::$debug;
            }
            return null;
        }
    }
}

// Load database configuration
require_once __DIR__ . '/config/database.php';
$db = new DATABASE_CONFIG();

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $db->default['host'],
            'name' => $db->default['database'],
            'user' => $db->default['login'],
            'pass' => $db->default['password'],
            'port' => '3306',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'test' => [
            'adapter' => 'mysql',
            'host' => $db->test['host'],
            'name' => $db->test['database'],
            'user' => $db->test['login'],
            'pass' => $db->test['password'],
            'port' => '3306',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'version_order' => 'creation',
];

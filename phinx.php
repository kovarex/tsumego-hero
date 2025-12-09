<?php
/**
 * Phinx Configuration
 *
 * This configuration file loads database settings from CakePHP's database.php
 * and provides them to Phinx for migrations.
 *
 * Note: This loads database.php in a simplified way to avoid loading the full
 * CakePHP bootstrap, which would cause conflicts in the CLI context.
 *
 * @link https://book.cakephp.org/phinx/0/en/migrations.html
 */

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

// Number of parallel test workers (set TEST_WORKERS env var to override, default 8)
$testWorkers = (int)getenv('TEST_WORKERS') ?: 8;

// Generate test database environments dynamically (test_1, test_2, ..., test_N)
$environments = [
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
];

// Dynamically generate test_1 through test_N environments
for ($i = 1; $i <= $testWorkers; $i++) {
    $environments["test_$i"] = [
        'adapter' => 'mysql',
        'host' => $db->test['host'],
        'name' => "test_$i",
        'user' => $db->test['login'],
        'pass' => $db->test['password'],
        'port' => '3306',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ];
}
return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => $environments,
    'version_order' => 'creation',
];

<?php

/**
 * Bootstrap for PHPUnit
 */

use Composer\InstalledVersions;

if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}
define('ROOT', dirname(__DIR__));
define('VENDORS', ROOT . DS . 'vendor' . DS);

require_once InstalledVersions::getInstallPath('pieceofcake2/cakephp') . DS . 'tests' . DS . 'bootstrap.php';

// Ensure test database schema exists
$testDb = ConnectionManager::getDataSource('test');
try {
	$tables = $testDb->listSources();
	if (empty($tables)) {
		echo "Test database is empty. Importing schema from config/schema.sql...\n";
		$config = $testDb->config;
		$cmd = sprintf(
			'mysql -h%s -u%s -p%s %s < %s/config/schema.sql 2>&1',
			escapeshellarg($config['host']),
			escapeshellarg($config['login']),
			escapeshellarg($config['password']),
			escapeshellarg($config['database']),
			ROOT,
		);
		exec($cmd, $output, $returnCode);
		if ($returnCode !== 0) {
			echo "Warning: Failed to import schema. Error: " . implode("\n", $output) . "\n";
		} else {
			echo "Schema imported successfully.\n";
		}
	}
} catch (Exception $e) {
	echo "Warning: Could not check test database: " . $e->getMessage() . "\n";
}

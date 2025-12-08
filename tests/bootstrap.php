<?php

/**
 * Bootstrap for PHPUnit
 */

use Composer\InstalledVersions;

// Mark that we're running in test environment
// This is checked by config/database.php to switch to test database
putenv('PHPUNIT_RUNNING=1');
$_ENV['PHPUNIT_RUNNING'] = '1';

// ParaTest sets TEST_TOKEN for parallel workers
$testToken = getenv('TEST_TOKEN') ?: ($_ENV['TEST_TOKEN'] ?? null);
if (!$testToken && isset($_ENV['TEST_TOKEN']))
{
	$testToken = $_ENV['TEST_TOKEN'];
	putenv('TEST_TOKEN=' . $testToken);
}

if (!defined('DS'))
	define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__DIR__));
define('VENDORS', ROOT . DS . 'vendor' . DS);

require_once InstalledVersions::getInstallPath('pieceofcake2/cakephp') . DS . 'tests' . DS . 'bootstrap.php';

// Fix redirect URLs in controller tests - CakePHP 2 needs these set before Router initializes
// Without this, CakePHP detects /vendor/bin/phpunit as the base path and generates wrong redirect URLs
// NOTE: This file is ONLY loaded by PHPUnit (see phpunit.xml.dist), never in production
if (PHP_SAPI === 'cli' && !empty($_SERVER['argv']) && str_contains($_SERVER['argv'][0], 'phpunit'))
{
	$_SERVER['SCRIPT_NAME'] = '/index.php';
	$_SERVER['PHP_SELF'] = '/index.php';
	$_SERVER['REQUEST_URI'] = '/';
	Configure::write('App.base', '');
	Configure::write('App.baseUrl', '');
}

// Load test helpers globally to avoid repetition in every test file
require_once ROOT . '/tests/Browser.php';
require_once ROOT . '/tests/ContextPreparator.php';
require_once ROOT . '/tests/TestCase/Controller/TestCaseWithAuth.php';

// CRITICAL: For parallel testing, ensure TEST_TOKEN routing is applied to ConnectionManager.
// The database.php __construct() runs during CakePHP bootstrap and caches config.
// If bootstrap runs first without TEST_TOKEN, we need to retroactively fix both:
// 1) The cached config in ConnectionManager::$config->default
// 2) The active datasource connection in ConnectionManager::$_dataSources['default']
if ($testToken)
{
	$sources = ConnectionManager::sourceList();
	$hadConnection = in_array('default', $sources);

	if (ConnectionManager::$config && isset(ConnectionManager::$config->default))
	{
		$oldDb = ConnectionManager::$config->default['database'];
		$newDb = 'test_' . $testToken;

		if ($oldDb !== $newDb)
		{
			ConnectionManager::$config->default['database'] = $newDb;

			if ($hadConnection)
				ClassRegistry::init('User')->query("USE `{$newDb}`");
		}
	}
}

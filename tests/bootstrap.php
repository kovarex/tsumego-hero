<?php

/**
 * Bootstrap for PHPUnit
 */

use Composer\InstalledVersions;

// Mark that we're running in test environment
// This is checked by config/database.php to switch to test database
putenv('PHPUNIT_RUNNING=1');
$_ENV['PHPUNIT_RUNNING'] = '1';

if (!defined('DS'))
	define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__DIR__));
define('VENDORS', ROOT . DS . 'vendor' . DS);

require_once InstalledVersions::getInstallPath('pieceofcake2/cakephp') . DS . 'tests' . DS . 'bootstrap.php';

// Load test helpers globally to avoid repetition in every test file
require_once ROOT . '/tests/Browser.php';
require_once ROOT . '/tests/ContextPreparator.php';
require_once ROOT . '/tests/TestCase/Controller/TestCaseWithAuth.php';

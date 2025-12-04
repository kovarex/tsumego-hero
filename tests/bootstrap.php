<?php

/**
 * Bootstrap for PHPUnit
 */

use Composer\InstalledVersions;

if (!defined('DS'))
	define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__DIR__));
define('VENDORS', ROOT . DS . 'vendor' . DS);

require_once InstalledVersions::getInstallPath('pieceofcake2/cakephp') . DS . 'tests' . DS . 'bootstrap.php';

// Fix redirect URLs in tests - CakePHP 2 needs these set before Router initializes
// Without this, CakePHP detects /vendor/bin/phpunit as the base path
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';

// Force CakePHP to use correct base path
Configure::write('App.base', '');
Configure::write('App.baseUrl', '');

// Load test helpers globally to avoid repetition in every test file
require_once ROOT . '/tests/Browser.php';
require_once ROOT . '/tests/ContextPreparator.php';
require_once ROOT . '/tests/TestCase/Controller/TestCaseWithAuth.php';

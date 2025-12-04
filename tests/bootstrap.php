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

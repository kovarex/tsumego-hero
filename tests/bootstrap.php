<?php

//Bootstrap for PHPUnit

use Cake\Datasource\ConnectionManager;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

//Cake's ErrorTrap prints a PHP warning, notice or deprecation and lets the test go on, and PHPUnit
//only puts in its own handler when there is none. Without the trap PHPUnit gets them, and
//phpunit.xml.dist makes each one fail the run with its message and place.
restore_error_handler();

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';

ConnectionManager::alias('test', 'default');

// Load test helpers globally to avoid repetition in every test file
require_once ROOT . '/tests/Browser.php';
require_once ROOT . '/tests/ContextPreparator.php';
require_once ROOT . '/tests/TestEmailer.php';
require_once ROOT . '/tests/RetryOnBrowserFailure.php';

// Pin the server timezone to UTC. The Selenium browser runs in its own
// timezone (Europe/Prague), so displayed times shift, which browser tests assert.
date_default_timezone_set('UTC');
require_once ROOT . '/tests/TestCase/AppTestCase.php';
require_once ROOT . '/tests/TestCase/Controller/IntegrationTestCase.php';
require_once ROOT . '/tests/TestCase/Achievement/AchievementTestCase.php';
require_once ROOT . '/tests/TestCase/Json/JsonGoldenTestCase.php';
register_shutdown_function(fn() => Browser::shutdown());

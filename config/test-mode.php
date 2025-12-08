<?php
/**
 * Test Mode Detection
 * 
 * For PHPUnit tests, this detects test mode parameters and sets environment variables
 * BEFORE the database config is loaded, allowing tests to use isolated test databases.
 * 
 * Test mode is signaled via:
 * - Query parameters: ?PHPUNIT_TEST=1&TEST_TOKEN=X (initial page loads)
 * - POST parameters: PHPUNIT_TEST=1&TEST_TOKEN=X (htmx AJAX requests)
 * - Cookies: PHPUNIT_TEST=1, TEST_TOKEN=X (fallback/persistence)
 */

if (isset($_GET['PHPUNIT_TEST']) || isset($_POST['PHPUNIT_TEST']) || isset($_COOKIE['PHPUNIT_TEST']))
{
	putenv('PHPUNIT_TEST=1');
	$_ENV['PHPUNIT_TEST'] = '1';
	// Also set PHPUNIT_RUNNING for compatibility with database.php
	putenv('PHPUNIT_RUNNING=1');
	$_ENV['PHPUNIT_RUNNING'] = '1';
}

$testToken = $_GET['TEST_TOKEN'] ?? $_POST['TEST_TOKEN'] ?? $_COOKIE['TEST_TOKEN'] ?? null;
if ($testToken)
{
	putenv('TEST_TOKEN=' . $testToken);
	$_ENV['TEST_TOKEN'] = $testToken;
}

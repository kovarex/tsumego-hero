<?php
// Test mode detection - only loads when test parameters present (zero overhead in production)
if (isset($_GET['PHPUNIT_TEST']) || isset($_POST['PHPUNIT_TEST']) || isset($_COOKIE['PHPUNIT_TEST']) || isset($_GET['TEST_TOKEN']) || isset($_POST['TEST_TOKEN']) || isset($_COOKIE['TEST_TOKEN']))
	require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test-mode.php';

echo "Hello world!";

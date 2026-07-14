<?php

use PHPUnit\Runner\BeforeTestHook;
use PHPUnit\Runner\AfterTestHook;

/**
 * Clears PHP superglobals before and after each test to prevent state bleeding.
 */
final class SuperglobalCleanup implements BeforeTestHook, AfterTestHook
{
	private static function cleanup(): void
	{
		$_FILES = [];
		$_POST = [];
		$_GET = [];
		$_REQUEST = [];
		$_COOKIE = [];
		$_SESSION = [];
		if (session_status() === PHP_SESSION_ACTIVE)
			session_destroy();
		Auth::logout();
		JwtAuth::clearCache();
		CookieFlash::clearCache();
		Preferences::clearTestStorage();
	}

	public function executeBeforeTest(string $test): void
	{
		self::cleanup();
	}

	public function executeAfterTest(string $test, float $time): void
	{
		self::cleanup();
	}
}

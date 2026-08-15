<?php

use PHPUnit\Runner\BeforeTestHook;

/**
 * Clears PHP superglobals before each test to prevent state bleeding.
 */
final class SuperglobalCleanup implements BeforeTestHook
{
	private static function cleanup(): void
	{
		$_FILES = [];
		$_POST = [];
		$_GET = [];
		$_REQUEST = [];
		$_COOKIE = [];
		$_SESSION = [];
		Auth::logout();
		JwtAuth::clearCache();
		CookieFlash::clearCache();
		Preferences::clearTestStorage();
		foreach (Cache::configured() as $config)
		{
			if (str_starts_with($config, '_cake_'))
				continue;
			Cache::clear(false, $config);
		}
	}

	public function executeBeforeTest(string $test): void
	{
		self::cleanup();
	}
}

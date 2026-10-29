<?php

use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\JwtAuth;
use App\Utility\Preferences;
use PHPUnit\Framework\Attributes\Before;

//The base of every test case of the site.
//Clears the superglobals and the caches before each test, so no state bleeds from one test
//into the next. A before method runs ahead of setUp() on PHPUnit 9 (the annotation) and on 10
//and later (the attribute, which they read instead of the annotation).
abstract class AppTestCase extends CakeTestCase
{
	/** @before */
	#[Before]
	protected function clearState(): void
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
}

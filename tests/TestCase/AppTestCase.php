<?php

use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\JwtAuth;
use App\Utility\Preferences;
use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\IncompleteTest;
use PHPUnit\Framework\SkippedTest;

//The base of every test case of the site.
//Clears the superglobals and the caches before each test, so no state bleeds from one test
//into the next. The before method runs ahead of setUp().
abstract class AppTestCase extends TestCase
{
	#[Before]
	protected function clearState(): void
	{
		$_FILES = [];
		$_POST = [];
		$_GET = [];
		$_REQUEST = [];
		$_COOKIE = [];
		$_SESSION = [];
		Browser::startTest();
		Auth::logout();
		JwtAuth::clearCache();
		CookieFlash::clearCache();
		Preferences::clearTestStorage();
		foreach (Cache::configured() as $config)
		{
			if (str_starts_with($config, '_cake_'))
				continue;
			Cache::clear($config);
		}
	}

	//A failed browser test leaves a screenshot and the page in tmp/ (Browser::dumpFailure()).
	protected function onNotSuccessfulTest(Throwable $t): never
	{
		if (!$t instanceof SkippedTest && !$t instanceof IncompleteTest)
			Browser::dumpFailure(static::class . '-' . $this->name());
		parent::onNotSuccessfulTest($t);
	}
}

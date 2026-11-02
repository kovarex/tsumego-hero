<?php

use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\JwtAuth;
use App\Utility\Preferences;
use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\After;
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

	//The PHP warnings, notices and deprecations the web server of the browser tests raised while
	//the test ran (written by .github/ci/core.local.php) fail it. A request still running when the
	//test ends is counted for the next one.
	#[After]
	protected function failOnWebServerErrors(): void
	{
		$path = TMP . 'webserver-errors.log';
		clearstatcache(true, $path);
		if (!is_file($path) || filesize($path) === 0)
			return;
		$errors = (string) file_get_contents($path);
		file_put_contents($path, '');
		$this->fail("The web server raised PHP errors:\n" . $errors);
	}

	//A failed browser test leaves a screenshot and the page in tmp/ (Browser::dumpFailure()).
	protected function onNotSuccessfulTest(Throwable $t): never
	{
		if (!$t instanceof SkippedTest && !$t instanceof IncompleteTest)
			Browser::dumpFailure(static::class . '-' . $this->name());
		parent::onNotSuccessfulTest($t);
	}
}

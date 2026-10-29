<?php

use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
//Clears PHP superglobals before each test to prevent state bleeding.
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\JwtAuth;
use App\Utility\Preferences;
use Cake\Cache\Cache;

final class SuperglobalCleanup implements Extension
{
	public static function cleanup(): void
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
			Cache::clear($config);
		}
	}

	public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
	{
		$facade->registerSubscriber(new class implements PreparationStartedSubscriber
		{
			public function notify(PreparationStarted $event): void
			{
				SuperglobalCleanup::cleanup();
			}
		});
	}
}

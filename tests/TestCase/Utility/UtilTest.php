<?php


use App\Utility\Util;

class UtilTest extends AppTestCase
{
	private array $serverBackup = [];
	private array $cookieBackup = [];

	public function setUp(): void
	{
		parent::setUp();
		$this->serverBackup = $_SERVER;
		$this->cookieBackup = $_COOKIE;
	}

	public function tearDown(): void
	{
		$_SERVER = $this->serverBackup;
		$_COOKIE = $this->cookieBackup;
		parent::tearDown();
	}

	//The cases of scripts/formatSignificant.test.mjs, the same function in JS
	public static function significantCases(): array
	{
		return [
			[56, 2, '56'], [5.62, 2, '5.6'], [0.153, 2, '0.15'], [0.0153, 2, '0.015'], [123.4, 2, '123'],
			[2.0, 2, '2'], [0.10, 2, '0.1'], [9.96, 2, '10'], [0.999, 2, '1'], [99.5, 2, '100'], [0.0995, 2, '0.1'],
			[0, 2, '0'], [-5.62, 2, '-5.6'], [-0.153, 2, '-0.15'], [-9.96, 2, '-10'],
			[0.53, 1, '0.5'], [0.04, 1, '0.04'], [12.3, 1, '12'], [0.95, 1, '1'], [0.25, 1, '0.3'], [-0.25, 1, '-0.3'],
			[1.005, 3, '1.01'], [0.000123, 2, '0.00012'], [0.0004, 1, '0.0004'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('significantCases')]
	public function testFormatSignificant(float $value, int $significant, string $expected): void
	{
		$this->assertSame($expected, Util::formatSignificant($value, $significant));
	}

	public function testFormatSignificantHasTwoDigitsByDefault(): void
	{
		$this->assertSame('5.6', Util::formatSignificant(5.62));
	}

	public function testIsHttpUrl(): void
	{
		$this->assertTrue(Util::isHttpUrl('https://senseis.xmp.net/?Snapback'));
		$this->assertTrue(Util::isHttpUrl(' http://example.com '));
		$this->assertFalse(Util::isHttpUrl('javascript:alert(1)'));
		$this->assertFalse(Util::isHttpUrl('tag.example.com'));
		$this->assertFalse(Util::isHttpUrl(''));
	}

	public function testClearCookieReturnsNullWhenNotSet()
	{
		$result = Util::clearCookie('unknown');
		$this->assertNull($result);
		$this->assertArrayNotHasKey('unknown', $_COOKIE);
	}

	public function testGetPercentButAvoid100UntilComplete()
	{
		$this->assertSame(0, Util::getPercentButAvoid100UntilComplete(0, 5));
		$this->assertSame(80, Util::getPercentButAvoid100UntilComplete(4, 5));
		$this->assertSame(99, Util::getPercentButAvoid100UntilComplete(999, 1000));
		$this->assertSame(100, Util::getPercentButAvoid100UntilComplete(100, 100));
	}

	public function testAddSqlConditionWrapsOrCondition()
	{
		$condition = 'a = 1';
		Util::addSqlCondition($condition, 'b = 2 OR c = 3');
		$this->assertSame('a = 1 AND (b = 2 OR c = 3)', $condition);
	}

	public function testAddSqlOrConditionAppends()
	{
		$condition = 'a = 1';
		Util::addSqlOrCondition($condition, 'b = 2');
		$this->assertSame('a = 1 OR b = 2', $condition);
	}

	public function testExtractRemovesKey()
	{
		$array = ['name' => 'tsumego', 'id' => 1];
		$result = Util::extract('name', $array);
		$this->assertSame('tsumego', $result);
		$this->assertArrayNotHasKey('name', $array);
		$this->assertSame(1, $array['id']);
	}


	public function testGetInternalAddressGithub()
	{
		$_SERVER['TEST_ENVIRONMENT'] = 'github-ci';
		$this->assertSame('https://host.docker.internal:8443', Util::getInternalAddress());
	}

	public function testGetInternalAddressDefault()
	{
		unset($_SERVER['TEST_ENVIRONMENT']);
		unset($_SERVER['HTTP_HOST']);
		$this->assertSame('http://localhost', Util::getInternalAddress());
	}

	public function testIsInTestEnvironmentWithDdevUrl()
	{
		$_SERVER['DDEV_PRIMARY_URL'] = 'https://tsumego.ddev.site:33003';
		$this->assertTrue(Util::isInTestEnvironment());
	}

	public function testIsInTestEnvironmentFalseWhenNoFlagsSet()
	{
		unset($_SERVER['DDEV_PRIMARY_URL'], $_SERVER['TEST_ENVIRONMENT'], $_SERVER['HTTP_HOST']);
		$this->assertFalse(Util::isInTestEnvironment());
	}

	public function testGetMyAddressUsesForwardedHost()
	{
		unset($_SERVER['TEST_ENVIRONMENT']);
		$_SERVER['DDEV_PRIMARY_URL'] = 'https://tsumego.ddev.site:33003';
		$_SERVER['HTTP_X_FORWARDED_HOST'] = 'forwarded.example.test';
		$this->assertSame('https://forwarded.example.test', Util::getMyAddress());
	}

	//The dev site behind a proxy that does not say which host it was asked for
	public function testGetMyAddressWithoutForwardedHostIsTheTestSite()
	{
		unset($_SERVER['TEST_ENVIRONMENT'], $_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_FORWARDED_HOST']);
		$_SERVER['DDEV_PRIMARY_URL'] = 'https://tsumego.ddev.site:33003';
		$this->assertSame('https://test.tsumego.ddev.site', Util::getMyAddress());
	}

	public function testGetMyAddressInGithubCI()
	{
		$_SERVER['TEST_ENVIRONMENT'] = 'github-ci';
		$_SERVER['TEST_APP_URL'] = 'https://ci.example.test';
		$this->assertSame('https://ci.example.test', Util::getMyAddress());
	}

	public function testGetMyAddressDefault()
	{
		unset($_SERVER['TEST_ENVIRONMENT'], $_SERVER['HTTP_HOST']);
		$_SERVER['DDEV_PRIMARY_URL'] = null;
		$_SERVER['HTTP_X_FORWARDED_HOST'] = null;
		$this->assertSame('https://test.tsumego.ddev.site', Util::getMyAddress());
	}

	public function testSetCookieReturnsVoid()
	{
		$this->assertNull(Util::setCookie('simple', 'value'));
	}

	public function testClearCookieReturnsPreviousValueAndClears()
	{
		$_COOKIE['session'] = 'abc';
		$this->assertSame('abc', Util::clearCookie('session'));
		$this->assertArrayNotHasKey('session', $_COOKIE);
	}

	public function testCookieReturnsDefaultWhenMissing()
	{
		unset($_COOKIE['missing']);
		$this->assertSame('fallback', Util::cookie('missing', 'fallback'));
	}

	public function testGenerateRandomStringHasTheAskedLength()
	{
		$this->assertSame(10, strlen(Util::generateRandomString(10)));
		$this->assertSame(20, strlen(Util::generateRandomString()));
	}

	public function testGenerateRandomStringIsNotPredictable()
	{
		$this->assertNotSame(Util::generateRandomString(20), Util::generateRandomString(20));
		$this->assertMatchesRegularExpression('/^[0-9a-zA-Z]+$/', Util::generateRandomString(20));
	}


	public function testGetRatioHandlesZeroMax()
	{
		$this->assertSame(0.0, Util::getRatio(5, 0));
		$this->assertSame(0.25, Util::getRatio(1, 4));
	}

	public function testGetPercentUsesRatio()
	{
		$this->assertSame(25.0, Util::getPercent(1, 4));
		$this->assertSame(0.0, Util::getPercent(1, 0));
	}

	public function testIsInGithubCIFromTestEnvironment()
	{
		$_SERVER['TEST_ENVIRONMENT'] = 'github-ci';
		$this->assertTrue(Util::isInGithubCI());
	}

	public function testIsInGithubCIReturnsFalseForOtherEnvironment()
	{
		$_SERVER['TEST_ENVIRONMENT'] = 'local-dev';
		$this->assertFalse(Util::isInGithubCI());
	}

	public function testIsInGithubCIFromHost()
	{
		unset($_SERVER['TEST_ENVIRONMENT']);
		$_SERVER['HTTP_HOST'] = 'host.docker.internal';
		$this->assertTrue(Util::isInGithubCI());
	}

	public function testIsInGithubCIFalseWhenHostDoesNotMatch()
	{
		unset($_SERVER['TEST_ENVIRONMENT']);
		$_SERVER['HTTP_HOST'] = 'localhost';
		$this->assertFalse(Util::isInGithubCI());
	}

	public function testIsInGithubCIFalseWhenUnset()
	{
		unset($_SERVER['TEST_ENVIRONMENT'], $_SERVER['HTTP_HOST']);
		$this->assertFalse(Util::isInGithubCI());
	}

	public function testIsInTestEnvironmentFallsBackToGithubCI()
	{
		$_SERVER['TEST_ENVIRONMENT'] = 'github-ci';
		$this->assertTrue(Util::isInTestEnvironment());
	}

	public function testGetMyAddressUsesHostDockerWhenAvailable()
	{
		unset($_SERVER['TEST_ENVIRONMENT']);
		$_SERVER['HTTP_HOST'] = 'host.docker.internal';
		$_SERVER['TEST_APP_URL'] = 'https://hosted.example';
		$this->assertSame('https://hosted.example', Util::getMyAddress());
	}

	public function testAddSqlConditionNoChangeWhenConditionEmpty()
	{
		$condition = 'a = 1';
		Util::addSqlCondition($condition, '');
		$this->assertSame('a = 1', $condition);
	}

	public function testAddSqlConditionInitializesWhenExistingEmpty()
	{
		$condition = '';
		Util::addSqlCondition($condition, 'a = 1');
		$this->assertSame('a = 1', $condition);
	}

	public function testAddSqlConditionAppendsWithoutParentheses()
	{
		$condition = 'a = 1';
		Util::addSqlCondition($condition, 'b = 2');
		$this->assertSame('a = 1 AND b = 2', $condition);
	}

	public function testAddSqlOrConditionWhenExistingEmpty()
	{
		$condition = '';
		Util::addSqlOrCondition($condition, 'a = 1');
		$this->assertSame('a = 1', $condition);
	}


	public function testGetHealthBasedOnLevel()
	{
		$this->assertSame(10, Util::getHealthBasedOnLevel(0));
		$this->assertSame(11, Util::getHealthBasedOnLevel(9));
	}

	public function testToIso8601(): void
	{
		$original = date_default_timezone_get();

		date_default_timezone_set('UTC');
		$this->assertSame('2026-08-11T12:00:00+00:00', Util::toIso8601('2026-08-11 12:00:00'));

		date_default_timezone_set('Europe/Prague');
		$this->assertSame('2026-08-11T12:00:00+02:00', Util::toIso8601('2026-08-11 12:00:00'));

		date_default_timezone_set($original);
	}
}

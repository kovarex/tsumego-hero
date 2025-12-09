<?php

App::uses('Util', 'Utility');

class UtilTest extends CakeTestCase
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

	public function testClearNumericCookieReturnsInteger()
	{
		$_COOKIE['number'] = '42';
		$result = Util::clearNumericCookie('number');
		$this->assertSame(42, $result);
		$this->assertEmpty($_COOKIE['number']);
	}

	public function testClearNumericCookieThrowsOnNonNumeric()
	{
		$_COOKIE['not-number'] = 'abc';
		$this->expectException(Exception::class);
		$this->expectExceptionMessage("Cookie not-number should be numeric but has value 'abc'.");
		Util::clearNumericCookie('not-number');
	}

	public function testClearRequiredNumericCookieThrowsWhenMissing()
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage("Cookie missing is expected to be defined, but it isn't");
		Util::clearRequiredNumericCookie('missing');
	}

	public function testClearRequiredNumericCookieReturnsValue()
	{
		$_COOKIE['hits'] = '7';
		$this->assertSame(7, Util::clearRequiredNumericCookie('hits'));
		$this->assertEmpty($_COOKIE['hits']);
	}

	public function testClearCookieReturnsNullWhenNotSet()
	{
		$result = Util::clearCookie('unknown');
		$this->assertNull($result);
		$this->assertEmpty($_COOKIE['unknown']);
	}

	public function testClearNumericCookieReturnsNullWhenMissing()
	{
		$this->assertNull(Util::clearNumericCookie('missing-number'));
		$this->assertEmpty($_COOKIE['missing-number']);
	}

	public function testGetPercentButAvoid100UntilComplete()
	{
		$this->assertSame(0, Util::getPercentButAvoid100UntilComplete(0, 5));
		$this->assertSame(1, Util::getPercentButAvoid100UntilComplete(4, 5));
		$this->assertSame(1, Util::getPercentButAvoid100UntilComplete(100, 100));
		$this->assertSame(99, Util::getPercentButAvoid100UntilComplete(-100, -1));
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

	public function testIndexByID()
	{
		$array = [
			['item' => ['id' => 1, 'value' => 'a']],
			['item' => ['id' => 2, 'value' => 'b']],
		];
		$result = Util::indexByID($array, 'item', 'value');
		$this->assertSame([1 => 'a', 2 => 'b'], $result);
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
		$this->assertSame('https://test.tsumego.ddev.site:33003', Util::getMyAddress());
	}

	public function testSetCookieReturnsVoid()
	{
		$this->assertNull(Util::setCookie('simple', 'value'));
	}

	public function testClearCookieReturnsPreviousValueAndClears()
	{
		$_COOKIE['session'] = 'abc';
		$this->assertSame('abc', Util::clearCookie('session'));
		$this->assertEmpty($_COOKIE['session']);
	}

	public function testGetCookieReturnsDefaultWhenMissing()
	{
		unset($_COOKIE['missing']);
		$this->assertSame('fallback', Util::getCookie('missing', 'fallback'));
	}

	public function testGenerateRandomStringIsDeterministicWithSeed()
	{
		srand(1);
		$this->assertSame('Fp8eBdl3HU', Util::generateRandomString(10));
		srand(); // reset to random seed
	}

	public function testEncryptAndDecryptRoundTrip()
	{
		$encrypted = Util::encrypt('secret-message');
		$this->assertNotSame('secret-message', $encrypted);
		$this->assertSame('secret-message', Util::decrypt($encrypted));
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

	public function testBoolString()
	{
		$this->assertSame('true', Util::boolString(true));
		$this->assertSame('false', Util::boolString(false));
	}

	public function testGetHealthBasedOnLevel()
	{
		$this->assertSame(10, Util::getHealthBasedOnLevel(0));
		$this->assertSame(11, Util::getHealthBasedOnLevel(9));
	}
}

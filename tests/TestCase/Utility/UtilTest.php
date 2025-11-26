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
		$this->assertSame('', $_COOKIE['number']);
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

	public function testGetPercentButAvoid100UntilComplete()
	{
		$this->assertSame(0, Util::getPercentButAvoid100UntillComplete(0, 5));
		$this->assertSame(1, Util::getPercentButAvoid100UntillComplete(4, 5));
		$this->assertSame(1, Util::getPercentButAvoid100UntillComplete(100, 100));
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
		$this->assertSame('https://host.docker.internal:8443./vendor/bin', Util::getInternalAddress());
	}

	public function testGetInternalAddressDefault()
	{
		unset($_SERVER['TEST_ENVIRONMENT']);
		unset($_SERVER['HTTP_HOST']);
		$this->assertSame('http://localhost/var/www/html/vendor/bin', Util::getInternalAddress());
	}

	public function testIsInTestEnvironmentWithDdevUrl()
	{
		$_SERVER['DDEV_PRIMARY_URL'] = 'https://tsumego.ddev.site:33003';
		$this->assertTrue(Util::isInTestEnvironment());
	}

	public function testGetMyAddressUsesForwardedHost()
	{
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
}

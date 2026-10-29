<?php

//Tests for JwtAuth utility class.
//JwtAuth provides stateless authentication using signed JWT tokens.
use App\Utility\JwtAuth;
use App\Utility\Util;

class JwtAuthTest extends CakeTestCase
{
	private const TEST_SECRET = 'test-secret-key-for-unit-tests-only';

	public function setUp(): void
	{
		parent::setUp();
		Configure::write('Security.jwtSecret', self::TEST_SECRET);
	}

	public function testCreateTokenReturnsValidJwt(): void
	{
		$userId = 12345;
		$token = JwtAuth::createToken($userId);

		// Token should be a string with 3 parts separated by dots
		$this->assertIsString($token);
		$this->assertCount(3, explode('.', $token));
	}

	public function testValidateTokenReturnsUserIdForValidToken(): void
	{
		$userId = 12345;
		$token = JwtAuth::createToken($userId);

		$result = JwtAuth::validateToken($token);

		$this->assertEquals($userId, $result);
	}

	public function testValidateTokenReturnsNullForInvalidToken(): void
	{
		$result = JwtAuth::validateToken('invalid.token.here');

		$this->assertNull($result);
	}

	public function testValidateTokenReturnsNullForExpiredToken(): void
	{
		// Create a token that expired 1 second ago
		$userId = 12345;
		$token = JwtAuth::createToken($userId, -1);

		$result = JwtAuth::validateToken($token);

		$this->assertNull($result);
	}

	public function testValidateTokenReturnsNullForTamperedToken(): void
	{
		$userId = 12345;
		$token = JwtAuth::createToken($userId);

		// Tamper with the token by changing a character
		$parts = explode('.', $token);
		$parts[1] = 'tampered' . $parts[1];
		$tamperedToken = implode('.', $parts);

		$result = JwtAuth::validateToken($tamperedToken);

		$this->assertNull($result);
	}

	public function testGetUserIdFromCookieReturnsUserIdWhenValid(): void
	{
		$userId = 12345;
		$token = JwtAuth::createToken($userId);
		$_COOKIE['auth_token'] = $token;

		$result = JwtAuth::getUserIdFromCookie();

		$this->assertEquals($userId, $result);
	}

	public function testGetUserIdFromCookieReturnsNullWhenNoCookie(): void
	{
		unset($_COOKIE['auth_token']);

		$result = JwtAuth::getUserIdFromCookie();

		$this->assertNull($result);
	}

	public function testSetAuthCookieSetsValidCookie(): void
	{
		$userId = 12345;

		// This will attempt to set a cookie (won't work in CLI but we can test the token is generated)
		$token = JwtAuth::setAuthCookie($userId);

		$this->assertNotNull($token);
		$this->assertEquals($userId, JwtAuth::validateToken($token));
	}

	//The forum (phpBB) logs the player in from this cookie, so its name, attributes and value
	//must not change, whichever framework sets it.
	public function testTheAuthCookieKeepsWhatTheForumReads(): void
	{
		$before = time();
		$token = JwtAuth::setAuthCookie(12345);

		$cookie = Util::sentCookie('auth_token');
		$this->assertNotNull($cookie);
		$this->assertSame($token, $cookie['value']);
		// Characters that need no encoding, so the value is sent as it is.
		$this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $cookie['value']);

		$expires = $cookie['options']['expires'];
		unset($cookie['options']['expires']);
		$this->assertEqualsCanonicalizing(['path' => '/', 'domain' => '', 'secure' => true, 'httponly' => true], $cookie['options']);
		$this->assertGreaterThanOrEqual($before + 30 * 86400, $expires);
		$this->assertLessThanOrEqual(time() + 30 * 86400, $expires);
	}

	public function testLoggingOutClearsTheAuthCookieForTheWholeSite(): void
	{
		JwtAuth::setAuthCookie(12345);
		JwtAuth::clearAuthCookie();

		$cookie = Util::sentCookie('auth_token');
		$this->assertSame('', $cookie['value']);
		$this->assertLessThan(time(), $cookie['options']['expires']);
		unset($cookie['options']['expires']);
		$this->assertEqualsCanonicalizing(['path' => '/', 'secure' => false, 'httponly' => false], $cookie['options']);
		$this->assertNull(Util::cookie('auth_token'));
	}
}

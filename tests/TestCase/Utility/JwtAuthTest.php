<?php

App::uses('CakeTestCase', 'TestSuite');
App::uses('JwtAuth', 'Utility');

/**
 * Tests for JwtAuth utility class.
 *
 * JwtAuth provides stateless authentication using signed JWT tokens.
 */
class JwtAuthTest extends CakeTestCase
{
	private const TEST_SECRET = 'test-secret-key-for-unit-tests-only';

	public function setUp(): void
	{
		parent::setUp();
		// Override the secret for testing
		Configure::write('Security.jwtSecret', self::TEST_SECRET);
	}

	public function tearDown(): void
	{
		parent::tearDown();
		// Clear any cookies
		unset($_COOKIE['auth_token']);
		JwtAuth::clearCache();
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
}

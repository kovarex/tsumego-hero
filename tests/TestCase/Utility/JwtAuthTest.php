<?php

//Tests for JwtAuth utility class.
//JwtAuth provides stateless authentication using signed JWT tokens.
use Cake\Core\Configure;
use App\Utility\JwtAuth;
use App\Utility\Util;
use Firebase\JWT\JWT;

class JwtAuthTest extends AppTestCase
{
	private const TEST_SECRET = 'test-secret-key-for-unit-tests-only';
	private const PASSWORD_HASH = '$2y$10$abcdefghijklmnopqrstuuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0';

	//The secret of config/jwt_secret.php, which the other tests of the run sign in with
	private mixed $configuredSecret;

	public function setUp(): void
	{
		parent::setUp();
		$this->configuredSecret = Configure::read('Security.jwtSecret');
		Configure::write('Security.jwtSecret', self::TEST_SECRET);
	}

	public function tearDown(): void
	{
		Configure::write('Security.jwtSecret', $this->configuredSecret);
		parent::tearDown();
	}

	//A token of the site as anyone could sign it with the given key
	private static function tokenSignedWith(string $key, int $userId): string
	{
		return JWT::encode(['sub' => $userId, 'iat' => time(), 'exp' => time() + 3600, 'pwd' => JwtAuth::claimOf(self::PASSWORD_HASH)], $key, 'HS256');
	}

	public function testCreateTokenReturnsValidJwt(): void
	{
		$userId = 12345;
		$token = JwtAuth::createToken($userId, self::PASSWORD_HASH);

		// Token should be a string with 3 parts separated by dots
		$this->assertIsString($token);
		$this->assertCount(3, explode('.', $token));
	}

	public function testValidateTokenReturnsUserIdForValidToken(): void
	{
		$userId = 12345;
		$token = JwtAuth::createToken($userId, self::PASSWORD_HASH);

		$result = JwtAuth::validateToken($token);

		$this->assertEquals($userId, $result);
	}

	//A token signed with the JWT secret is taken, one signed with the salt is not: the salt was in
	//git, so whoever read it could sign a token for any player, admins included.
	public function testOnlyATokenSignedWithTheJwtSecretIsTaken(): void
	{
		$salt = (string) Configure::read('Security.salt');
		$this->assertGreaterThanOrEqual(32, strlen($salt), 'the salt of the run, from core.local.php');

		$this->assertSame(12345, JwtAuth::validateToken(self::tokenSignedWith(self::TEST_SECRET, 12345)));
		$this->assertNull(JwtAuth::validateToken(self::tokenSignedWith($salt, 12345)));
		$_COOKIE['auth_token'] = self::tokenSignedWith($salt, 12345);
		$this->assertNull(JwtAuth::getUserIdFromCookie());
	}

	//Without the secret no token can be signed or checked, which is an error of the server, not a
	//player who isn't signed in, and nothing else is used in its place.
	public function testAMissingSecretFailsLoudly(): void
	{
		$token = JwtAuth::createToken(12345, self::PASSWORD_HASH);
		foreach ([null, '', 'too-short-to-be-random'] as $secret)
		{
			Configure::write('Security.jwtSecret', $secret);
			foreach ([
				'signing' => fn() => JwtAuth::createToken(12345, self::PASSWORD_HASH),
				'checking' => fn() => JwtAuth::validateToken($token)] as $what => $call)
					try
					{
						$call();
						$this->fail("$what with the secret " . var_export($secret, true) . ' went through');
					}
			catch (RuntimeException $e)
			{
				$this->assertStringContainsString('config/jwt_secret.php', $e->getMessage());
			}
		}
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
		$token = JwtAuth::createToken($userId, self::PASSWORD_HASH, -1);

		$result = JwtAuth::validateToken($token);

		$this->assertNull($result);
	}

	public function testValidateTokenReturnsNullForTamperedToken(): void
	{
		$userId = 12345;
		$token = JwtAuth::createToken($userId, self::PASSWORD_HASH);

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
		$token = JwtAuth::createToken($userId, self::PASSWORD_HASH);
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
		$token = JwtAuth::setAuthCookie($userId, self::PASSWORD_HASH);

		$this->assertNotNull($token);
		$this->assertEquals($userId, JwtAuth::validateToken($token));
	}

	//The forum (phpBB) logs the player in from this cookie, so its name, attributes and value
	//must not change, whichever framework sets it.
	public function testTheAuthCookieKeepsWhatTheForumReads(): void
	{
		$before = time();
		$token = JwtAuth::setAuthCookie(12345, self::PASSWORD_HASH);

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
		JwtAuth::setAuthCookie(12345, self::PASSWORD_HASH);
		JwtAuth::clearAuthCookie();

		$cookie = Util::sentCookie('auth_token');
		$this->assertSame('', $cookie['value']);
		$this->assertLessThan(time(), $cookie['options']['expires']);
		unset($cookie['options']['expires']);
		$this->assertEqualsCanonicalizing(['path' => '/', 'secure' => false, 'httponly' => false], $cookie['options']);
		$this->assertNull(Util::cookie('auth_token'));
	}
}

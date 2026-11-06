<?php

namespace App\Utility;

use Cake\Core\Configure;
use RuntimeException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

//JWT-based stateless authentication.
//Replaces session-based authentication with signed JWT tokens stored in cookies.
//Tokens contain the user ID, the expiration and a claim of the password, signed with HMAC-SHA256 and
//the secret of config/jwt_secret.php.
//Usage:
//  JwtAuth::setAuthCookie($userId, $passwordHash) // Login: create token and set cookie
//  JwtAuth::getUserIdFromCookie()       // Check auth: get user ID from cookie
//  JwtAuth::clearAuthCookie()           // Logout: delete cookie
class JwtAuth
{
	private const COOKIE_NAME = 'auth_token';
	private const ALGORITHM = 'HS256';
	private const DEFAULT_EXPIRY_SECONDS = 86400 * 30; // 30 days

	//Cached user ID from token validation
	private static ?int $cachedUserId = null;

	//Cached password claim of the token in the cookie
	private static ?string $cachedPasswordClaim = null;

	//Whether the cookie was looked at already in this request
	private static bool $cookieRead = false;

	//The shortest secret taken: bin2hex(random_bytes(32)) makes 64 characters
	private const MINIMUM_SECRET_LENGTH = 32;

	//Create a JWT token for the given user ID.
	//$userId: The user ID to encode
	//$passwordHash: The password the session is opened with
	//$expirySeconds: Seconds until expiration (null = default 30 days)
	//Returns the JWT token
	public static function createToken(int $userId, string $passwordHash, ?int $expirySeconds = null): string
	{
		$expiry = $expirySeconds ?? self::DEFAULT_EXPIRY_SECONDS;

		$payload = [
			'sub' => $userId,
			'iat' => time(),
			'exp' => time() + $expiry,
			'pwd' => self::passwordClaim($passwordHash),
		];

		return JWT::encode($payload, self::getSecret(), self::ALGORITHM);
	}

	//The claim a token gets from the password it is issued with. A password change
	//changes the hash, so tokens of the old password no longer fit the account.
	public static function claimOf(string $passwordHash): string
	{
		return self::passwordClaim($passwordHash);
	}

	//The password claim of the token in the cookie, or null when it has none.
	public static function getPasswordClaimFromCookie(): ?string
	{
		self::readCookie();
		return self::$cachedPasswordClaim;
	}

	//Validate a JWT token and return the user ID.
	//$token: The JWT token to validate
	//Returns the user ID, or null if token is invalid/expired
	public static function validateToken(string $token): ?int
	{
		$payload = self::decodePayload($token);
		return $payload === null ? null : (int) $payload->sub;
	}

	//Get user ID from auth cookie.
	//Returns the user ID, or null if not authenticated
	public static function getUserIdFromCookie(): ?int
	{
		self::readCookie();
		return self::$cachedUserId;
	}

	//Read the token in the cookie once per request and keep what it says.
	private static function readCookie(): void
	{
		if (self::$cookieRead)
			return;
		self::$cookieRead = true;

		$token = Util::cookie(self::COOKIE_NAME);
		if (empty($token))
			return;

		$payload = self::decodePayload($token);
		if ($payload === null)
			return;

		self::$cachedUserId = (int) $payload->sub;
		self::$cachedPasswordClaim = $payload->pwd;
	}

	//Check the signature and the expiry of a token, the one place that does it. Every token is issued
	//with the password claim, so one without it is not taken. A token that does not check out is null
	//for every caller; a missing secret is an error of the server, not a bad token, so it is thrown.
	private static function decodePayload(string $token): ?object
	{
		$key = new Key(self::getSecret(), self::ALGORITHM);
		try
		{
			$payload = JWT::decode($token, $key);
		}
		catch (\Exception $e)
		{
			return null;
		}
		return isset($payload->pwd) && is_string($payload->pwd) ? $payload : null;
	}

	private static function passwordClaim(string $passwordHash): string
	{
		return substr(hash('sha256', $passwordHash), 0, 16);
	}

	//Set auth cookie with JWT token for the given user ID.
	//$passwordHash: The password the session is opened with
	//Returns the token that was set
	public static function setAuthCookie(int $userId, string $passwordHash): string
	{
		$token = self::createToken($userId, $passwordHash);
		// The forum (phpBB) reads this cookie too, so its attributes stay as they are: no SameSite,
		// unreadable by scripts, the whole site.
		Util::setCookie(self::COOKIE_NAME, $token, [
			'expires' => time() + self::DEFAULT_EXPIRY_SECONDS,
			'domain' => '',
			'httponly' => true,
			'samesite' => null]);
		self::$cachedUserId = $userId;
		self::$cachedPasswordClaim = self::claimOf($passwordHash);
		self::$cookieRead = true;
		return $token;
	}

	//Clear auth cookie (logout).
	public static function clearAuthCookie(): void
	{
		Util::clearCookie(self::COOKIE_NAME, ['secure' => false, 'samesite' => null]);
		self::$cachedUserId = null;
		self::$cachedPasswordClaim = null;
		self::$cookieRead = true;
	}

	//Clear cached user ID (for testing).
	public static function clearCache(): void
	{
		self::$cachedUserId = null;
		self::$cachedPasswordClaim = null;
		self::$cookieRead = false;
	}

	//The secret tokens are signed with: Security.jwtSecret, which config/bootstrap.php reads from
	//config/jwt_secret.php outside git. There is no fallback to anything else, as whatever is in git
	//is public, and anyone with the secret can sign in as any player.
	private static function getSecret(): string
	{
		$secret = (string) Configure::read('Security.jwtSecret');
		if (strlen($secret) < self::MINIMUM_SECRET_LENGTH)
			throw new RuntimeException('The JWT secret is not set (' . self::MINIMUM_SECRET_LENGTH . ' characters or more): create config/jwt_secret.php, see config/jwt_secret.example.php.');
		return $secret;
	}
}

<?php

namespace App\Utility;

use Cake\Core\Configure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

//JWT-based stateless authentication.
//Replaces session-based authentication with signed JWT tokens stored in cookies.
//Tokens contain user ID and expiration, signed with HMAC-SHA256.
//Usage:
//  JwtAuth::setAuthCookie($userId)      // Login: create token and set cookie
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

	//Create a JWT token for the given user ID.
	//$userId: The user ID to encode
	//$expirySeconds: Seconds until expiration (null = default 30 days)
	//$passwordHash: The password the session is opened with
	//Returns the JWT token
	public static function createToken(int $userId, ?int $expirySeconds = null, ?string $passwordHash = null): string
	{
		$expiry = $expirySeconds ?? self::DEFAULT_EXPIRY_SECONDS;

		$payload = [
			'sub' => $userId,
			'iat' => time(),
			'exp' => time() + $expiry,
		];
		if ($passwordHash !== null)
			$payload['pwd'] = self::passwordClaim($passwordHash);

		return JWT::encode($payload, self::getSecret(), self::ALGORITHM);
	}

	//The claim a token gets from the password it is issued with. A password change
	//changes the hash, so tokens of the old password no longer fit the account.
	public static function claimOf(?string $passwordHash): ?string
	{
		return $passwordHash === null ? null : self::passwordClaim($passwordHash);
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

		if (empty($_COOKIE[self::COOKIE_NAME]))
			return;

		$payload = self::decodePayload($_COOKIE[self::COOKIE_NAME]);
		if ($payload === null)
			return;

		self::$cachedUserId = (int) $payload->sub;
		self::$cachedPasswordClaim = $payload->pwd ?? null;
	}

	//Check the signature and the expiry of a token, the one place that does it.
	//A token that does not check out is null for every caller.
	private static function decodePayload(string $token): ?object
	{
		try
		{
			return JWT::decode($token, new Key(self::getSecret(), self::ALGORITHM));
		}
		catch (\Exception $e)
		{
			return null;
		}
	}

	private static function passwordClaim(string $passwordHash): string
	{
		return substr(hash('sha256', $passwordHash), 0, 16);
	}

	//Set auth cookie with JWT token for the given user ID.
	//$passwordHash: The password the session is opened with
	//Returns the token that was set
	public static function setAuthCookie(int $userId, ?string $passwordHash = null): string
	{
		$token = self::createToken($userId, null, $passwordHash);
		$_COOKIE[self::COOKIE_NAME] = $token;
		setcookie(
			self::COOKIE_NAME,
			$token,
			time() + self::DEFAULT_EXPIRY_SECONDS,
			'/',
			'',
			true, // secure
			true  // httpOnly
		);
		self::$cachedUserId = $userId;
		self::$cachedPasswordClaim = self::claimOf($passwordHash);
		self::$cookieRead = true;
		return $token;
	}

	//Clear auth cookie (logout).
	public static function clearAuthCookie(): void
	{
		unset($_COOKIE[self::COOKIE_NAME]);
		setcookie(self::COOKIE_NAME, '', time() - 3600, '/');
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

	//Get the JWT secret key.
	private static function getSecret(): string
	{
		$secret = Configure::read('Security.jwtSecret');
		if (!$secret)
		{
			// Fall back to Security.salt if jwtSecret not configured
			$secret = Configure::read('Security.salt');
		}
		if (!$secret)
			throw new Exception('No JWT secret configured. Set Security.jwtSecret or Security.salt.');

		return $secret;
	}
}

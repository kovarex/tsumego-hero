<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWT-based stateless authentication.
 *
 * Replaces session-based authentication with signed JWT tokens stored in cookies.
 * Tokens contain user ID and expiration, signed with HMAC-SHA256.
 *
 * Usage:
 *   JwtAuth::setAuthCookie($userId)      // Login: create token and set cookie
 *   JwtAuth::getUserIdFromCookie()       // Check auth: get user ID from cookie
 *   JwtAuth::clearAuthCookie()           // Logout: delete cookie
 */
class JwtAuth
{
	private const COOKIE_NAME = 'auth_token';
	private const ALGORITHM = 'HS256';
	private const DEFAULT_EXPIRY_SECONDS = 86400 * 30; // 30 days

	/**
	 * @var int|null Cached user ID from token validation
	 */
	private static ?int $cachedUserId = null;

	/**
	 * Create a JWT token for the given user ID.
	 *
	 * @param int $userId The user ID to encode
	 * @param int|null $expirySeconds Seconds until expiration (null = default 30 days)
	 * @return string The JWT token
	 */
	public static function createToken(int $userId, ?int $expirySeconds = null): string
	{
		$expiry = $expirySeconds ?? self::DEFAULT_EXPIRY_SECONDS;

		$payload = [
			'sub' => $userId,
			'iat' => time(),
			'exp' => time() + $expiry,
		];

		return JWT::encode($payload, self::getSecret(), self::ALGORITHM);
	}

	/**
	 * Validate a JWT token and return the user ID.
	 *
	 * @param string $token The JWT token to validate
	 * @return int|null The user ID, or null if token is invalid/expired
	 */
	public static function validateToken(string $token): ?int
	{
		try
		{
			$decoded = JWT::decode($token, new Key(self::getSecret(), self::ALGORITHM));
			return (int) $decoded->sub;
		}
		catch (\Exception $e)
		{
			return null;
		}
	}

	/**
	 * Get user ID from auth cookie.
	 *
	 * @return int|null The user ID, or null if not authenticated
	 */
	public static function getUserIdFromCookie(): ?int
	{
		if (self::$cachedUserId !== null)
			return self::$cachedUserId;

		if (!isset($_COOKIE[self::COOKIE_NAME]) || empty($_COOKIE[self::COOKIE_NAME]))
			return null;

		self::$cachedUserId = self::validateToken($_COOKIE[self::COOKIE_NAME]);
		return self::$cachedUserId;
	}

	/**
	 * Set auth cookie with JWT token for the given user ID.
	 *
	 * @param int $userId The user ID
	 * @return string The token that was set
	 */
	public static function setAuthCookie(int $userId): string
	{
		$token = self::createToken($userId);
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
		return $token;
	}

	/**
	 * Clear auth cookie (logout).
	 */
	public static function clearAuthCookie(): void
	{
		unset($_COOKIE[self::COOKIE_NAME]);
		setcookie(self::COOKIE_NAME, '', time() - 3600, '/');
		self::$cachedUserId = null;
	}

	/**
	 * Clear cached user ID (for testing).
	 */
	public static function clearCache(): void
	{
		self::$cachedUserId = null;
	}

	/**
	 * Get the JWT secret key.
	 */
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

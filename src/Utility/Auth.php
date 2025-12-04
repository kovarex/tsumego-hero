<?php

App::uses('Constants', 'Utility');
App::uses('JwtAuth', 'Utility');

class Auth
{
	/**
	 * Generate random login token for phpBB2 forum SSO
	 * The forum reads this cookie to authenticate users automatically
	 */
	private static function generateLoginToken(int $user_id): void
	{
		$token = Util::generateRandomString(50);
		Auth::getUser()['login_token'] = $token;
		Auth::saveUser();
		Util::setCookie('login_token', $token);
	}

	public static function init($user = null): void
	{
		// a hack to inject login in test environment
		if (Util::isInTestEnvironment() && !empty($_COOKIE["hackedLoggedInUserID"]))
		{
			$userData = ClassRegistry::init('User')->findById((int) $_COOKIE["hackedLoggedInUserID"]);
			if ($userData)
			{
				Auth::$user = $userData['User'];
				return;
			}
		}

		if ($user)
		{
			Auth::$user = $user['User'];
			// Set JWT cookie for stateless auth
			JwtAuth::setAuthCookie(Auth::$user['id']);
			self::generateLoginToken(Auth::getUserID()); // For phpBB2 forum SSO
			return;
		}

		// Try JWT cookie (stateless auth)
		$userIdFromJwt = JwtAuth::getUserIdFromCookie();
		if ($userIdFromJwt)
		{
			$userData = ClassRegistry::init('User')->findById($userIdFromJwt);
			if ($userData)
			{
				Auth::$user = $userData['User'];
				return;
			}
		}

		// Not logged in
		Auth::$user = null;
	}

	public static function isLoggedIn(): bool
	{
		return (bool) Auth::$user;
	}

	public static function getUserID(): int
	{
		return Auth::$user ? Auth::$user['id'] : 0;
	}

	public static function &getUser()
	{
		if (!Auth::$user)
			throw new Exception("Accessing user for writing when null");
		return Auth::$user;
	}

	public static function isAdmin(): bool
	{
		return Auth::isLoggedIn() && Auth::getUser()['isAdmin'];
	}

	public static function hasPremium(): bool
	{
		return Auth::isLoggedIn() && Auth::getUser()['premium'];
	}

	public static function premiumLevel(): int
	{
		return Auth::isLoggedIn() ? Auth::getUser()['premium'] : 0;
	}

	public static function saveUser(): void
	{
		assert(Auth::isLoggedIn());
		ClassRegistry::init('User')->save(Auth::getUser());
	}

	public static function logout(): void
	{
		// Clear JWT cookie and phpBB2 SSO token
		JwtAuth::clearAuthCookie();
		Util::clearCookie('login_token');
		Auth::$user = null;
	}

	public static function getWithDefault($key, $default)
	{
		if (!Auth::isLoggedIn())
			return $default;
		return Auth::getUser()[$key];
	}

	public static function getMode(): int
	{
		return Auth::isLoggedIn() ? (int) Auth::getUser()['mode'] : Constants::$LEVEL_MODE;
	}

	public static function isInLevelMode(): bool
	{
		return Auth::getMode() == Constants::$LEVEL_MODE;
	}

	public static function isInRatingMode(): bool
	{
		return Auth::getMode() == Constants::$RATING_MODE;
	}

	public static function isInTimeMode(): bool
	{
		return Auth::getMode() == Constants::$TIME_MODE;
	}

	public static function addSuspicion(): void
	{
		Auth::getUser()['penalty'] += 1;
		Auth::saveUser();
	}

	public static function XPisGainedInCurrentMode()
	{
		if (!Auth::isLoggedIn())
			return false;
		return Auth::isInLevelMode() || Auth::isInRatingMode();
	}

	public static function ratingisGainedInCurrentMode()
	{
		if (!Auth::isLoggedIn())
			return false;
		return Auth::isInLevelMode() || Auth::isInRatingMode();
	}

	private static $user = null;
}

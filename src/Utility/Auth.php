<?php

App::uses('Constants', 'Utility');
App::uses('JwtAuth', 'Utility');

class Auth
{
	/** Memoized identity (user + permissions), null when logged out. */
	private static $identity = null;

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
		self::$identity = null;

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
			JwtAuth::setAuthCookie(Auth::getUserID());
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

	/**
	 * Returns the identity (user array with a computed `permissions` list)
	 * for the current user, or null when not logged in. Memoized per request.
	 */
	public static function identity(): ?array
	{
		if (self::$identity !== null)
			return self::$identity;
		if (!Auth::isLoggedIn())
			return null;
		$user = Auth::$user;
		$user['permissions'] = self::computePermissions($user);
		self::$identity = $user;
		return $user;
	}

	/**
	 * Computes the permission list from the user row. Policies read only
	 * these permissions, never raw role booleans.
	 */
	private static function computePermissions(array $user): array
	{
		$perms = [];
		if ((bool) $user['isAdmin'])
			$perms[] = 'admin';
		if ((bool) $user['isAdmin'] || (bool) $user['premium'])
			$perms[] = 'sandbox';
		return $perms;
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
		self::$identity = null;
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

	public static function getRemainingHealth()
	{
		if (!Auth::isLoggedIn())
			return 1000;
		return Util::getHealthBasedOnLevel(Auth::getUser()['level']) - Auth::getUser()['damage'];
	}

	public static function lightMode()
	{
		if (Auth::isLoggedIn())
			return (Auth::getUser()['lastLight'] == 0) ? self::$LIGHT_MODE : self::$DARK_MODE;
		if  (!empty($_COOKIE['lightDark']))
			if ($_COOKIE['lightDark'] == 'dark')
				return self::$DARK_MODE;
		return self::$LIGHT_MODE;
	}

	private static $user = null;
	public static int $LIGHT_MODE = 1;
	public static int $DARK_MODE = 2;
}

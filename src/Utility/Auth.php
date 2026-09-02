<?php

App::uses('Constants', 'Utility');
App::uses('JwtAuth', 'Utility');

class Auth
{
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
			JwtAuth::setAuthCookie(Auth::getUserID());
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
	 * Returns the identity (user array), or null when not logged in.
	 * Matches CakePHP 5's $request->getAttribute('identity') contract.
	 */
	public static function getIdentity(): ?array
	{
		return Auth::$user;
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

	public static function getPrefPlayerColor(): int
	{
		return Auth::isLoggedIn() ? (int) Auth::getUser()['pref_player_color'] : User::PREF_PLAYER_COLOR_RANDOM;
	}

	public static function getPrefBoardOrientation(): int
	{
		return Auth::isLoggedIn() ? (int) Auth::getUser()['pref_board_orientation'] : User::PREF_BOARD_ORIENTATION_RANDOM;
	}

	/**
	 * Throws when $field is not an existing column on the user table, so a typo
	 * or schema mismatch fails loudly instead of being silently dropped.
	 */
	private static function assertUserFieldExists(string $field): void
	{
		$schema = ClassRegistry::init('User')->schema();
		if (!array_key_exists($field, $schema))
			throw new Exception("Cannot write unknown user column '{$field}' - it does not exist in the user table schema.");
	}

	public static function saveUserField(string $field, $value): void
	{
		assert(Auth::isLoggedIn());
		self::assertUserFieldExists($field);
		Auth::$user[$field] = $value;
		ClassRegistry::init('User')->save(
			['User' => ['id' => Auth::getUserID(), $field => $value]],
			['validate' => false, 'fieldList' => [$field]]
		);
	}

	public static function saveUserFields(array $fieldValues): void
	{
		assert(Auth::isLoggedIn());
		foreach ($fieldValues as $field => $value)
			self::assertUserFieldExists($field);
		foreach ($fieldValues as $field => $value)
			Auth::$user[$field] = $value;
		$data = ['User' => ['id' => Auth::getUserID()]];
		foreach ($fieldValues as $field => $value)
			$data['User'][$field] = $value;
		ClassRegistry::init('User')->save($data, [
			'validate' => false,
			'fieldList' => array_keys($fieldValues),
		]);
	}

	public static function incrementUserField(string $field, $delta): void
	{
		assert(Auth::isLoggedIn());
		self::assertUserFieldExists($field);
		Auth::$user[$field] = Auth::getUser()[$field] + $delta;
		ClassRegistry::init('User')->updateAll(
			[$field => $field . ' + ' . $delta],
			['id' => Auth::getUserID()]
		);
	}

	/**
	 * Atomically increments a field only while the row satisfies $conditions.
	 * Used for bounded counters (e.g. used_revelation) where the bound must be
	 * enforced by the database, not only by a PHP check that can race.
	 *
	 * Bypasses callbacks/validation/timestamps, like incrementUserField().
	 *
	 * @return bool Whether the increment was applied (condition still held).
	 */
	public static function incrementUserFieldIf(string $field, $delta, array $conditions): bool
	{
		assert(Auth::isLoggedIn());
		self::assertUserFieldExists($field);
		$conditions['id'] = Auth::getUserID();
		$model = ClassRegistry::init('User');
		$model->updateAll(
			[$field => $field . ' + ' . $delta],
			$conditions
		);
		$affected = $model->getDataSource()->lastAffected();
		if ($affected)
			Auth::$user[$field] = Auth::getUser()[$field] + $delta;
		return $affected > 0;
	}

	public static function logout(): void
	{
		// Clear JWT auth cookie
		JwtAuth::clearAuthCookie();
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

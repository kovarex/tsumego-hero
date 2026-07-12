<?php

App::uses('Auth', 'Utility');
App::uses('Util', 'Utility');

/**
 * Unified Preferences storage class.
 *
 * - For logged-in users: Uses UserContribution table in database
 * - For guests: Uses cookies (or in-memory storage during tests)
 *
 * Supported keys (matching TsumegoFilters/UserContribution columns):
 * - query
 * - collection_size
 * - filtered_sets
 * - filtered_ranks
 * - filtered_tags
 */
class Preferences
{
	/**
	 * In-memory storage for unit tests (where setcookie() won't work)
	 * @var array<string, mixed>
	 */
	private static array $testStorage = [];

	/**
	 * Database default values - used to detect if a value was explicitly set
	 */
	private static array $dbDefaults = [
		'query' => 'topics',
		'collection_size' => 200,
		'filtered_sets' => null,
		'filtered_ranks' => null,
		'filtered_tags' => null,
	];

	/**
	 * Valid preference keys (must match UserContribution columns)
	 */
	private static array $validKeys = [
		'query',
		'collection_size',
		'filtered_sets',
		'filtered_ranks',
		'filtered_tags',
	];

	/**
	 * Get a preference value
	 *
	 * @param string $key The preference key
	 * @param mixed $default Default value if not set
	 * @return mixed The preference value
	 */
	public static function get(string $key, $default = null)
	{
		self::validateKey($key);

		// Check cookie override first (applies to both guests and logged-in users)
		// For logged-in users: Check unprefixed cookie (set by layout.ctp JS)
		// For guests: Check prefixed cookie ("loggedOff_" + key)
		if (Auth::isLoggedIn())
		{
			// Logged-in users: unprefixed cookie override
			if (isset($_COOKIE[$key]) && $_COOKIE[$key] !== '')
				return $_COOKIE[$key];
			// Fall through to database
			return self::getFromDatabase($key, $default);
		}
		else
		{
			// Guests: check prefixed cookie first
			$cookieKey = 'loggedOff_' . $key;
			if (isset($_COOKIE[$cookieKey]) && $_COOKIE[$cookieKey] !== '')
				return $_COOKIE[$cookieKey];
			// Fall through to guest storage (which checks same cookie again, but that's OK)
			return self::getFromGuestStorage($key, $default);
		}
	}

	/**
	 * Set a preference value
	 *
	 * @param string $key The preference key
	 * @param mixed $value The value to set
	 */
	public static function set(string $key, $value): void
	{
		self::validateKey($key);

		if (Auth::isLoggedIn())
			self::setInDatabase($key, $value);
		else
			self::setInGuestStorage($key, $value);
	}

	/**
	 * Merge guest preferences into database on login.
	 * Should be called after successful login.
	 */
	public static function mergeGuestPreferencesOnLogin(): void
	{
		if (!Auth::isLoggedIn())
			return;

		foreach (self::$validKeys as $key)
		{
			$guestValue = self::getFromGuestStorage($key);
			if ($guestValue !== null)
			{
				// Only set if not already explicitly set in database
				// (i.e., current value is null, empty, or equals the database default)
				$currentDbValue = self::getFromDatabase($key);
				$isDefault = self::isDefaultValue($key, $currentDbValue);
				if ($currentDbValue === null || $currentDbValue === '' || $isDefault)
					self::setInDatabase($key, $guestValue);

				// Clear guest storage
				self::clearGuestStorageKey($key);
			}
		}
	}

	/**
	 * Check if a value equals the database default for a key
	 */
	private static function isDefaultValue(string $key, $value): bool
	{
		if (!isset(self::$dbDefaults[$key]))
			return false;

		$default = self::$dbDefaults[$key];
		// Handle numeric comparison (DB might return string "200" vs int 200)
		if (is_numeric($value) && is_numeric($default))
			return (int) $value === (int) $default;

		return $value === $default;
	}

	/**
	 * Clear all test storage (for use in test tearDown)
	 */
	public static function clearTestStorage(): void
	{
		self::$testStorage = [];
	}

	/**
	 * Get preference from database (for logged-in users)
	 */
	private static function getFromDatabase(string $key, $default = null)
	{
		$userContribution = ClassRegistry::init('UserContribution')->find('first', [
			'conditions' => ['user_id' => Auth::getUserID()],
		]);

		if (!$userContribution || !isset($userContribution['UserContribution'][$key]))
			return $default;

		$value = $userContribution['UserContribution'][$key];
		return ($value === '') ? $default : $value;
	}

	/**
	 * Set preference in database (for logged-in users)
	 */
	private static function setInDatabase(string $key, $value): void
	{
		$userId = Auth::getUserID();
		$db = ClassRegistry::init('UserContribution')->getDataSource();

		$quotedKey = '`' . $key . '`';

		/** @phpstan-ignore-next-line */
		$escapedValue = $db->value($value);
		/** @phpstan-ignore-next-line */
		$escapedUserId = $db->value($userId, 'integer');

		$sql = "INSERT INTO user_contribution (user_id, $quotedKey, created) 
		        VALUES ($escapedUserId, $escapedValue, NOW())
		        ON DUPLICATE KEY UPDATE $quotedKey = $escapedValue";

		/** @phpstan-ignore-next-line */
		$db->execute($sql);
	}

	/**
	 * Get preference from guest storage (cookies or test memory)
	 */
	private static function getFromGuestStorage(string $key, $default = null)
	{
		// In unit tests, use memory storage
		if (self::isInUnitTest())
			return self::$testStorage[$key] ?? $default;

		// Cookie key uses 'loggedOff_' prefix for backward compatibility
		$cookieKey = 'loggedOff_' . $key;
		return $_COOKIE[$cookieKey] ?? $default;
	}

	/**
	 * Set preference in guest storage (cookies or test memory)
	 */
	private static function setInGuestStorage(string $key, $value): void
	{
		// In unit tests, use memory storage
		if (self::isInUnitTest())
		{
			self::$testStorage[$key] = $value;
			return;
		}

		// Cookie key uses 'loggedOff_' prefix for backward compatibility
		$cookieKey = 'loggedOff_' . $key;
		Util::setCookie($cookieKey, $value);
	}

	/**
	 * Clear a single key from guest storage
	 */
	private static function clearGuestStorageKey(string $key): void
	{
		if (self::isInUnitTest())
		{
			unset(self::$testStorage[$key]);
			return;
		}

		$cookieKey = 'loggedOff_' . $key;
		Util::clearCookie($cookieKey);
	}

	/**
	 * Check if we're running in a unit test environment
	 *
	 * NOTE: Only checks PHPUNIT_RUNNING, not php_sapi_name() === 'cli'
	 * because CLI check would also trigger in production CLI commands
	 * (cron jobs, migrations, shells) where we want real cookies, not test storage.
	 */
	private static function isInUnitTest(): bool
	{
		return defined('PHPUNIT_RUNNING');
	}

	/**
	 * Validate that the key is allowed
	 */
	private static function validateKey(string $key): void
	{
		if (!in_array($key, self::$validKeys))
			throw new InvalidArgumentException("Invalid preference key: $key. Valid keys are: " . implode(', ', self::$validKeys));
	}
}

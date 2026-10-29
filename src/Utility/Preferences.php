<?php

namespace App\Utility;

use InvalidArgumentException;

//Unified Preferences storage class.
//- For logged-in users: Uses columns of the user table (see $userColumns)
//- For guests: Uses cookies (or in-memory storage during tests)
//Supported keys (the cookie names, used by TsumegoFilters):
//- query
//- collection_size
//- filtered_sets
//- filtered_ranks
//- filtered_tags
class Preferences
{
	//In-memory storage for unit tests (where setcookie() won't work)
	/** @var array<string, mixed> */
	private static array $testStorage = [];

	//Database default values - used to detect if a value was explicitly set
	private static array $dbDefaults = [
		'query' => 'topics',
		'collection_size' => 200,
		'filtered_sets' => null,
		'filtered_ranks' => null,
		'filtered_tags' => null,
	];

	//Valid preference keys, each with the user column that stores it
	private static array $userColumns = [
		'query' => 'collection_grouping',
		'collection_size' => 'collection_size',
		'filtered_sets' => 'filtered_sets',
		'filtered_ranks' => 'filtered_ranks',
		'filtered_tags' => 'filtered_tags',
	];

	//Get a preference value
	//$key: The preference key
	//$default: Default value if not set
	//Returns the preference value
	public static function get(string $key, mixed $default = null): mixed
	{
		self::validateKey($key);

		// Check cookie override first (applies to both guests and logged-in users)
		// For logged-in users: Check unprefixed cookie (set by the JS of templates/layout/default.php)
		// For guests: Check prefixed cookie ("loggedOff_" + key)
		if (Auth::isLoggedIn())
		{
			// Logged-in users: unprefixed cookie override
			if ((Util::cookie($key) ?? '') !== '')
				return Util::cookie($key);
			// Fall through to database
			return self::getFromDatabase($key, $default);
		}
		else
		{
			// Guests: check prefixed cookie first
			$cookieKey = 'loggedOff_' . $key;
			if ((Util::cookie($cookieKey) ?? '') !== '')
				return Util::cookie($cookieKey);
			// Fall through to guest storage (which checks same cookie again, but that's OK)
			return self::getFromGuestStorage($key, $default);
		}
	}

	//Set a preference value
	//$key: The preference key
	public static function set(string $key, mixed $value): void
	{
		self::validateKey($key);

		if (Auth::isLoggedIn())
			self::setInDatabase($key, $value);
		else
			self::setInGuestStorage($key, $value);
	}

	//Merge guest preferences into database on login. Should be called after successful login.
	public static function mergeGuestPreferencesOnLogin(): void
	{
		if (!Auth::isLoggedIn())
			return;

		foreach (array_keys(self::$userColumns) as $key)
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

	//Check if a value equals the database default for a key
	private static function isDefaultValue(string $key, mixed $value): bool
	{
		if (!isset(self::$dbDefaults[$key]))
			return false;

		$default = self::$dbDefaults[$key];
		// Handle numeric comparison (DB might return string "200" vs int 200)
		if (is_numeric($value) && is_numeric($default))
			return (int) $value === (int) $default;

		return $value === $default;
	}

	//Clear all test storage (for use in test tearDown)
	public static function clearTestStorage(): void
	{
		self::$testStorage = [];
	}

	//Get preference from database (for logged-in users), the user row is loaded with the login
	private static function getFromDatabase(string $key, mixed $default = null): mixed
	{
		$value = Auth::getUser()[self::$userColumns[$key]] ?? null;
		return ($value === null || $value === '') ? $default : $value;
	}

	//Set preference in database (for logged-in users). TsumegoFilters stores every filter on
	//every page, so an unchanged value isn't written again.
	private static function setInDatabase(string $key, mixed $value): void
	{
		$column = self::$userColumns[$key];
		if ((string) (Auth::getUser()[$column] ?? '') !== (string) ($value ?? ''))
			Auth::saveUserField($column, $value);
	}

	//Get preference from guest storage (cookies or test memory)
	private static function getFromGuestStorage(string $key, mixed $default = null): mixed
	{
		// In unit tests, use memory storage
		if (self::isInUnitTest())
			return self::$testStorage[$key] ?? $default;

		// Cookie key uses 'loggedOff_' prefix for backward compatibility
		$cookieKey = 'loggedOff_' . $key;
		return Util::cookie($cookieKey) ?? $default;
	}

	//Set preference in guest storage (cookies or test memory)
	private static function setInGuestStorage(string $key, mixed $value): void
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

	//Clear a single key from guest storage
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

	//Check if we're running in a unit test environment
	//NOTE: Only checks PHPUNIT_RUNNING, not php_sapi_name() === 'cli'
	//because CLI check would also trigger in production CLI commands
	//(cron jobs, migrations, shells) where we want real cookies, not test storage.
	private static function isInUnitTest(): bool
	{
		return defined('PHPUNIT_RUNNING');
	}

	//Validate that the key is allowed
	private static function validateKey(string $key): void
	{
		if (!isset(self::$userColumns[$key]))
			throw new InvalidArgumentException("Invalid preference key: $key. Valid keys are: " . implode(', ', array_keys(self::$userColumns)));
	}
}

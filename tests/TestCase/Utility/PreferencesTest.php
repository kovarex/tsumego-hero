<?php

App::uses('Preferences', 'Utility');
App::uses('JwtAuth', 'Utility');

class PreferencesTest extends CakeTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		// Clear any existing auth state using proper methods
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID'], $_COOKIE['auth_token']);
		JwtAuth::clearCache();
		Auth::init(); // Re-initialize Auth with no user
		// Clear cookies
		$_COOKIE = [];
		// Clear test storage
		Preferences::clearTestStorage();
	}

	public function tearDown(): void
	{
		parent::tearDown();
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID'], $_COOKIE['auth_token']);
		JwtAuth::clearCache();
		$_COOKIE = [];
		Preferences::clearTestStorage();
	}

	/**
	 * Test that preferences can be set and retrieved for guests
	 */
	public function testGuestCanSetAndGetPreference()
	{
		// Arrange - ensure no user is logged in
		$this->assertFalse(Auth::isLoggedIn());

		// Act
		Preferences::set('query', 'test_value');
		$result = Preferences::get('query');

		// Assert
		$this->assertEquals('test_value', $result);
	}

	/**
	 * Test that preferences return default value when not set
	 */
	public function testReturnsDefaultWhenNotSet()
	{
		// Arrange - ensure no user is logged in
		$this->assertFalse(Auth::isLoggedIn());

		// Act
		$result = Preferences::get('query', 'default_value');

		// Assert
		$this->assertEquals('default_value', $result);
	}

	/**
	 * Test that preferences can be set and retrieved for logged-in users
	 */
	public function testLoggedInUserCanSetAndGetPreference()
	{
		// Arrange - create and login a test user
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);
		$this->assertTrue(Auth::isLoggedIn());

		// Act
		Preferences::set('query', 'logged_in_value');
		$result = Preferences::get('query');

		// Assert
		$this->assertEquals('logged_in_value', $result);
	}

	/**
	 * Test that preferences persist in database for logged-in users
	 */
	public function testLoggedInUserPreferencesSavedToDatabase()
	{
		// Arrange - create and login a test user
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);
		$userId = Auth::getUserID();

		// Act
		Preferences::set('query', 'topics');

		// Assert - check it's in the database
		$userContribution = ClassRegistry::init('UserContribution')->find('first', [
			'conditions' => ['user_id' => $userId],
		]);
		$this->assertNotNull($userContribution);
		$this->assertEquals('topics', $userContribution['UserContribution']['query']);
	}

	/**
	 * Test that guest preferences use internal storage (cookies set via Util::setCookie)
	 */
	public function testGuestPreferencesUseInternalStorage()
	{
		// Arrange - ensure no user is logged in
		$this->assertFalse(Auth::isLoggedIn());

		// Act
		Preferences::set('query', 'cookie_value');

		// Assert - verify retrieval works through the same storage
		$result = Preferences::get('query');
		$this->assertEquals('cookie_value', $result);
	}

	/**
	 * Test merging guest preferences to database on login
	 */
	public function testMergeGuestPreferencesOnLogin()
	{
		// Arrange - set preferences as guest
		$this->assertFalse(Auth::isLoggedIn());
		Preferences::set('query', 'guest_query');
		Preferences::set('collection_size', '150');

		// Act - login (creates user and initializes auth)
		// Note: ContextPreparator doesn't create UserContribution unless filter options passed
		$context = new ContextPreparator(['user' => ['name' => 'newuser']]);
		$userId = Auth::getUserID();

		// Verify no UserContribution exists yet
		$existingContribution = ClassRegistry::init('UserContribution')->find('first', [
			'conditions' => ['user_id' => $userId],
		]);
		$this->assertEmpty($existingContribution, 'No UserContribution should exist before merge');

		// Merge should happen
		Preferences::mergeGuestPreferencesOnLogin();

		// Assert - preferences should now be in database
		$userContribution = ClassRegistry::init('UserContribution')->find('first', [
			'conditions' => ['user_id' => $userId],
		]);
		$this->assertNotEmpty($userContribution);
		$this->assertEquals('guest_query', $userContribution['UserContribution']['query']);
		$this->assertEquals('150', $userContribution['UserContribution']['collection_size']);
	}

	/**
	 * Test that guest preferences are NOT accessible via unprefixed cookie keys
	 *
	 * This is a regression test for a bug where:
	 * - Guest cookies are stored as "loggedOff_query"
	 * - But get() checks $_COOKIE[$key] directly (e.g., "query")
	 * - So guest preferences from cookies would never be found
	 *
	 * Expected behavior: get() should check "loggedOff_" prefixed cookie for guests
	 */
	public function testGuestCookiePrefixIsRespected()
	{
		// Arrange - ensure no user is logged in
		$this->assertFalse(Auth::isLoggedIn());

		// Simulate a guest preference cookie that was set in a real browser request
		// (not through unit test memory storage)
		$_COOKIE['loggedOff_query'] = 'cookie_value';

		// Act - get the preference
		$result = Preferences::get('query');

		// Assert - should retrieve the value from prefixed cookie
		$this->assertEquals('cookie_value', $result, 'get() should find guest preference from "loggedOff_query" cookie');
	}

	/**
	 * Test that unprefixed cookie override works for logged-in users
	 *
	 * This tests the "cookie override" feature where:
	 * - Logged-in users have DB preferences
	 * - But unprefixed cookies (set by layout.ctp JS) can temporarily override them
	 * - This is used for filters that change on each request
	 */
	public function testUnprefixedCookieOverrideForLoggedInUser()
	{
		// Arrange - create logged-in user with DB preference
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);
		Preferences::set('query', 'db_value');

		// Simulate unprefixed cookie override (set by layout.ctp JS)
		$_COOKIE['query'] = 'override_value';

		// Act
		$result = Preferences::get('query');

		// Assert - cookie should override DB value
		$this->assertEquals('override_value', $result, 'Unprefixed cookie should override DB preference for logged-in users');
	}

	/**
	 * Test collection_size type handling for both guests and logged-in users
	 *
	 * This documents the current behavior:
	 * - Guests: Preferences returns string (from cookie)
	 * - Logged-in users: Preferences returns int (from DB)
	 * - Consumers (TsumegoFilters) must handle both by casting to (int)
	 *
	 * This is acceptable because:
	 * 1. TsumegoFilters already does (int) cast
	 * 2. Both string "150" and int 150 work fine when cast
	 */
	public function testCollectionSizeTypeHandling()
	{
		// Arrange - create logged-in user
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);

		// Act - store as string (simulating cookie or form input)
		Preferences::set('collection_size', '150');
		$result = Preferences::get('collection_size');

		// Assert - DB returns int (CakePHP PDO behavior)
		$this->assertIsInt($result, 'DB returns collection_size as int');
		$this->assertEquals(150, $result);
		$this->assertEquals(150, (int) $result, 'Already int, cast is no-op');

		// Test with numeric int value
		Preferences::set('collection_size', 200);
		$result2 = Preferences::get('collection_size');
		$this->assertIsInt($result2);
		$this->assertEquals(200, $result2);

		// Now test guest behavior (string from cookie)
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID'], $_COOKIE['auth_token']);
		Preferences::clearTestStorage();

		Preferences::set('collection_size', '150');
		$guestResult = Preferences::get('collection_size');

		// Guest preferences use test storage in unit tests, which preserves types
		// In real browser, cookies are strings
		$this->assertEquals(150, (int) $guestResult, 'Guest result can be cast to int');
	}
}

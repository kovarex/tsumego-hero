<?php

App::uses('CakeTestCase', 'TestSuite');
App::uses('Progress', 'Utility');
App::uses('Auth', 'Utility');
App::uses('JwtAuth', 'Utility');

/**
 * Tests for Progress utility class.
 *
 * Progress provides unified tsumego progress storage:
 * - Logged-in users: tsumego_status table
 * - Guests: cookie-based storage (JSON array)
 */
class ProgressTest extends CakeTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		// Clear auth state - must clear both JWT and cookie
		Auth::logout();
		unset($_COOKIE['auth_token'], $_COOKIE['hackedLoggedInUserID']);
		JwtAuth::clearCache();
		Auth::init(); // Re-initialize with no user

		// Clear guest progress cookie
		unset($_COOKIE['guest_progress']);
		Progress::clearCache();
	}

	public function tearDown(): void
	{
		parent::tearDown();
		// Clear auth state between tests
		Auth::logout();
		unset($_COOKIE['auth_token'], $_COOKIE['hackedLoggedInUserID']);
		JwtAuth::clearCache();
		Auth::init(); // Re-initialize with no user

		// Clear guest progress cookie
		unset($_COOKIE['guest_progress']);
		Progress::clearCache();
	}

	public function testGetStatusForGuestReturnsNullWhenNoCookie(): void
	{
		// Ensure no user is logged in
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID']);

		// Ensure no cookie
		unset($_COOKIE['guest_progress']);

		$status = Progress::getStatus(12345);

		$this->assertNull($status);
	}

	public function testSetAndGetStatusForGuest(): void
	{
		// Ensure no user is logged in
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID']);

		// Set a status
		Progress::setStatus(12345, 'S');

		// Get it back
		$status = Progress::getStatus(12345);

		$this->assertEquals('S', $status);
	}

	public function testGetMapForGuestReturnsAllStatuses(): void
	{
		// Ensure no user is logged in
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID']);

		// Set multiple statuses
		Progress::setStatus(100, 'S');
		Progress::setStatus(200, 'F');
		Progress::setStatus(300, 'W');

		$map = Progress::getMap();

		$this->assertEquals(['100' => 'S', '200' => 'F', '300' => 'W'], $map);
	}

	public function testGetMapForGuestWithSpecificIds(): void
	{
		// Ensure no user is logged in
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID']);

		// Set multiple statuses
		Progress::setStatus(100, 'S');
		Progress::setStatus(200, 'F');
		Progress::setStatus(300, 'W');

		// Only request some of them
		$map = Progress::getMap([100, 300]);

		$this->assertEquals(['100' => 'S', '300' => 'W'], $map);
	}

	public function testGuestProgressPersistsAcrossRequests(): void
	{
		// Ensure no user is logged in
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID']);

		// Set a status
		Progress::setStatus(12345, 'S');

		// Get cookie value (simulating what would be sent to browser)
		$cookieValue = $_COOKIE['guest_progress'];

		// Clear internal cache
		Progress::clearCache();

		// Set cookie as if coming from new request
		$_COOKIE['guest_progress'] = $cookieValue;

		// Get status
		$status = Progress::getStatus(12345);

		$this->assertEquals('S', $status);
	}

	public function testUpdateExistingGuestStatus(): void
	{
		// Ensure no user is logged in
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID']);

		// Set initial status
		Progress::setStatus(12345, 'V');
		$this->assertEquals('V', Progress::getStatus(12345));

		// Update status
		Progress::setStatus(12345, 'S');
		$this->assertEquals('S', Progress::getStatus(12345));
	}
}

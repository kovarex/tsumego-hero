<?php

use App\Utility\CookieFlash;

/**
 * Tests for the registration endpoint: the e-mail and the login name a new account
 * gets, and the registrations it refuses.
 *
 * Uses testAction for controller testing without Selenium.
 */
class RegistrationEmailTest extends ControllerTestCase
{
	/**
	 * Test that registration fails when email is already used by a regular user.
	 */
	public function testRegistrationFailsWhenEmailUsedByRegularUser()
	{
		// Create an existing regular user with ContextPreparator
		$context = new ContextPreparator([
			'user' => null,  // Not logged in
			'other-users' => [
				[
					'name' => 'existinguser',
					'display_name' => 'Existing User',
					'email' => 'taken@example.com',
				],
			],
		]);

		// Try to register with the same email
		$this->testAction('users/add', [
			'data' => [
				'User' => [
					'name' => 'newuser',
					'email' => 'taken@example.com',
					'password1' => 'password123',
					'password2' => 'password123',
				]
			],
			'method' => 'POST',
			'return' => 'vars'
		]);

		// Check that no new user was created with this email/name
		$userModel = ClassRegistry::init('User');
		$newUser = $userModel->find('first', [
			'conditions' => ['name' => 'newuser']
		]);
		$this->assertEmpty($newUser, "New user should NOT have been created with duplicate email");

		// Original user should still exist
		$existingUserStill = $userModel->find('first', [
			'conditions' => ['email' => 'taken@example.com']
		]);
		$this->assertEquals('existinguser', $existingUserStill['User']['name'],
			"Original user should still exist with original name");
	}

	/**
	 * Test that registration fails when email is already used by a Google user.
	 */
	public function testRegistrationFailsWhenEmailUsedByGoogleUser()
	{
		// Create a Google user with ContextPreparator
		$context = new ContextPreparator([
			'user' => null,  // Not logged in
			'other-users' => [
				[
					'display_name' => 'Google User',
					'email' => 'google@gmail.com',
					'external_id' => '123456789012345678901',
				],
			],
		]);

		// Try to register with the same email
		$this->testAction('users/add', [
			'data' => [
				'User' => [
					'name' => 'newuser2',
					'email' => 'google@gmail.com',
					'password1' => 'password123',
					'password2' => 'password123',
				]
			],
			'method' => 'POST',
			'return' => 'vars'
		]);

		// Check that no new user was created with this email/name
		$userModel = ClassRegistry::init('User');
		$newUser = $userModel->find('first', [
			'conditions' => ['name' => 'newuser2']
		]);
		$this->assertEmpty($newUser, "New user should NOT have been created with Google user's email");

		// Original Google user should still exist
		$existingUserStill = $userModel->find('first', [
			'conditions' => ['email' => 'google@gmail.com']
		]);
		$this->assertEquals('Google User', $existingUserStill['User']['display_name'],
			"Google user should still exist with original display_name");
	}

	/**
	 * Test that registration succeeds with a unique email.
	 */
	public function testRegistrationSucceedsWithUniqueEmail()
	{
		// Clean state with no users
		new ContextPreparator(['user' => null]);

		$this->testAction('users/add', [
			'data' => [
				'User' => [
					'name' => 'uniqueuser',
					'email' => 'unique@example.com',
					'password1' => 'password123',
					'password2' => 'password123',
				]
			],
			'method' => 'POST',
			'return' => 'vars'
		]);

		// Check the user was created
		$user = ClassRegistry::init('User')->find('first', [
			'conditions' => ['email' => 'unique@example.com']
		]);
		$this->assertNotEmpty($user, "User should have been created");
		$this->assertEquals('uniqueuser', $user['User']['name']);

		// Check success message
		$this->assertTrue(CookieFlash::has(), "Flash message should be set");
		$this->assertEquals('success', CookieFlash::getType());
		$this->assertEquals('Registration successful.', CookieFlash::get());
	}

	/**
	 * Test that registration handles display_name conflicts with Google accounts.
	 *
	 * Scenario: a Google account has no login name and holds the display name
	 * "ConflictName". A new player registers with the login name "ConflictName",
	 * which is free, so registration succeeds with the display name "ConflictName (2)".
	 */
	public function testRegistrationHandlesDisplayNameConflictWithGoogleUser()
	{
		// Create a Google account that holds the display name
		$context = new ContextPreparator(['user' => null]);

		$userModel = ClassRegistry::init('User');
		$userModel->create();
		$userModel->save([
			'name' => null,
			'display_name' => 'ConflictName',  // This is the conflict point
			'email' => 'google@gmail.com',
			'password_hash' => 'google_oauth',
			'external_id' => '123456789',
		], false);

		// The new player wants the login name "ConflictName", which the Google account
		// does not use
		$this->testAction('users/add', [
			'data' => [
				'User' => [
					'name' => 'ConflictName',
					'email' => 'newuser@example.com',
					'password1' => 'password123',
					'password2' => 'password123',
				]
			],
			'method' => 'POST',
			'return' => 'vars'
		]);

		// Check the new user was created
		$newUser = $userModel->find('first', [
			'conditions' => ['email' => 'newuser@example.com']
		]);
		$this->assertNotEmpty($newUser, "New user should have been created");
		$this->assertEquals('ConflictName', $newUser['User']['name']);
		// Display name should be unique, so it should have a suffix
		$this->assertEquals('ConflictName (2)', $newUser['User']['display_name'],
			"display_name should be unique with suffix");
		// A suffixed display name is not the one the player chose, so they have to
		// pick their own before continuing (the same way a Google account is asked)
		$this->assertEquals(true, $newUser['User']['needs_display_name_change'],
			"a suffixed display name should send the player to the rename page");

		// Google account should still hold its display name
		$googleUser = $userModel->find('first', [
			'conditions' => ['external_id' => '123456789']
		]);
		$this->assertEquals('ConflictName', $googleUser['User']['display_name']);
	}
}

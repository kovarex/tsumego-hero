<?php

use App\Utility\Auth;

/**
 * Tests for email login behavior with Google/non-Google users.
 *
 * These tests verify that:
 * - Google users cannot log in with password (must use Google Sign-In)
 * - Regular users can log in with email
 * - When both exist with same email, only regular user is matched
 */
class EmailLoginTest extends IntegrationTestCase
{
	private const TEST_PASSWORD = 'test';  // ContextPreparator uses 'test' as default password

	/**
	 * Test: Regular user can log in with email.
	 */
	public function testRegularUserCanLoginWithEmail(): void
	{
		$context = new ContextPreparator([
			'user' => null,  // Not logged in
			'other-users' => [
				[
					'name' => 'RegularUser',
					'display_name' => 'Regular User',
					'email' => 'regular@example.com',
				],
			],
		]);

		$this->post('/users/login', [
			'username' => 'regular@example.com',
			'password' => self::TEST_PASSWORD,
		]);

		// Should redirect (successful login)
		$this->assertRedirect();
	}

	/**
	 * Test: Google user cannot log in with email (must use Google Sign-In).
	 */
	public function testGoogleUserCannotLoginWithEmail(): void
	{
		// Create Google user manually since ContextPreparator uses regular password
		$context = new ContextPreparator(['user' => null]);

		$User = ClassRegistry::init('User');
		$User->create();
		$User->save([
			'name' => 'GoogleUser',
			'display_name' => 'Google User',
			'email' => 'google@gmail.com',
			'password_hash' => 'google_oauth',
			'external_id' => '123456',
		], false);

		$this->post('/users/login', [
			'username' => 'google@gmail.com',
			'password' => 'anypassword',
		]);

		// Should NOT redirect (failed login - user not found by email)
		// The getUserFromNameOrEmail excludes Google users from email lookup
		$this->assertResponseOk();
	}

	/**
	 * Test: An address shared with a Google account logs into the local account.
	 */
	public function testSharedEmailLogsIntoTheLocalAccount(): void
	{
		new ContextPreparator(['user' => null]);

		// The Google account is older, so a lookup by address alone would pick it
		$User = ClassRegistry::init('User');
		$User->create();
		$User->save([
			'name' => null,
			'display_name' => 'Google First',
			'email' => 'shared@example.com',
			'password_hash' => 'google_oauth',
			'external_id' => '987654321',
		], false);

		$context = new ContextPreparator([
			'other-users' => [
				[
					'name' => 'LocalOwner',
					'display_name' => 'Local Owner',
					'email' => 'shared@example.com',
				],
			],
		]);

		$this->post('/users/login', [
			'username' => 'shared@example.com',
			'password' => self::TEST_PASSWORD,
		]);

		$this->assertRedirect();
		$this->assertEquals($context->otherUsers[0]['id'], Auth::getUserID());
	}

	/**
	 * Test: A Google account with the same address does not block the local account.
	 */
	public function testSharedEmailDoesNotBlockLoginByName(): void
	{
		new ContextPreparator(['user' => null]);

		$User = ClassRegistry::init('User');
		$User->create();
		$User->save([
			'name' => null,
			'display_name' => 'Google First',
			'email' => 'shared@example.com',
			'password_hash' => 'google_oauth',
			'external_id' => '987654321',
		], false);

		$context = new ContextPreparator([
			'other-users' => [
				[
					'name' => 'LocalOwner',
					'display_name' => 'Local Owner',
					'email' => 'shared@example.com',
				],
			],
		]);

		$this->post('/users/login', [
			'username' => 'LocalOwner',
			'password' => self::TEST_PASSWORD,
		]);

		$this->assertRedirect();
		$this->assertEquals($context->otherUsers[0]['id'], Auth::getUserID());
	}

	/**
	 * Test: When both Google and regular user have same email, only regular user can login.
	 */
	public function testSharedEmailLoginMatchesRegularUserOnly(): void
	{
		$sharedEmail = 'shared@example.com';

		// Create regular user via ContextPreparator
		$context = new ContextPreparator([
			'user' => null,
			'other-users' => [
				[
					'name' => 'RegularJohn',
					'display_name' => 'Regular John',
					'email' => $sharedEmail,
				],
			],
		]);

		// Create Google user with same email manually
		$User = ClassRegistry::init('User');
		$User->create();
		$User->save([
			'name' => 'GoogleJohn',
			'display_name' => 'Google John',
			'email' => $sharedEmail,
			'password_hash' => 'google_oauth',
			'external_id' => 'shared123',
		], false);

		$this->post('/users/login', [
			'username' => $sharedEmail,
			'password' => self::TEST_PASSWORD,
		]);

		// Should redirect (successful login as regular user)
		$this->assertRedirect();
	}

	/**
	 * Test: Google user can be found by username but login fails (no valid password).
	 */
	public function testGoogleUserByNameFailsPasswordValidation(): void
	{
		// Create Google user manually
		$context = new ContextPreparator(['user' => null]);

		$User = ClassRegistry::init('User');
		$User->create();
		$User->save([
			'name' => 'GoogleByName',
			'display_name' => 'Google By Name',
			'email' => 'byname@gmail.com',
			'password_hash' => 'google_oauth',
			'external_id' => 'byname123',
		], false);

		$this->post('/users/login', [
			'username' => 'GoogleByName', // Using name, not email
			'password' => 'anypassword',
		]);

		// Should NOT redirect (password validation fails - google_oauth hash doesn't match)
		$this->assertResponseOk();
	}
}

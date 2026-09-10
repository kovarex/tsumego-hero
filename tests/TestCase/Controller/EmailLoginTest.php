<?php

App::uses('ControllerTestCase', 'TestSuite');
App::uses('User', 'Model');

/**
 * Tests for email login behavior with Google/non-Google users.
 *
 * These tests verify that:
 * - Google users cannot log in with password (must use Google Sign-In)
 * - Regular users can log in with email
 * - When both exist with same email, only regular user is matched
 */
class EmailLoginTest extends ControllerTestCase
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

		$result = $this->testAction('/users/login', [
			'method' => 'post',
			'data' => [
				'username' => 'regular@example.com',
				'password' => self::TEST_PASSWORD,
			],
			'return' => 'vars',
		]);

		// Should redirect (successful login)
		$this->assertContains($this->controller->response->statusCode(), [301, 302]);
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

		$this->testAction('/users/login', [
			'method' => 'post',
			'data' => [
				'username' => 'google@gmail.com',
				'password' => 'anypassword',
			],
			'return' => 'view',
		]);

		// Should NOT redirect (failed login - user not found by email)
		// The getUserFromNameOrEmail excludes Google users from email lookup
		$this->assertEquals(200, $this->controller->response->statusCode());
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

		$result = $this->testAction('/users/login', [
			'method' => 'post',
			'data' => [
				'username' => $sharedEmail,
				'password' => self::TEST_PASSWORD,
			],
			'return' => 'vars',
		]);

		// Should redirect (successful login as regular user)
		$this->assertContains($this->controller->response->statusCode(), [301, 302]);
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

		$this->testAction('/users/login', [
			'method' => 'post',
			'data' => [
				'username' => 'GoogleByName', // Using name, not email
				'password' => 'anypassword',
			],
			'return' => 'view',
		]);

		// Should NOT redirect (password validation fails - google_oauth hash doesn't match)
		$this->assertEquals(200, $this->controller->response->statusCode());
	}
}

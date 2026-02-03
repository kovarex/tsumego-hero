<?php

App::uses('ControllerTestCase', 'TestSuite');
App::uses('FakeGoogleTokenVerifier', 'Utility');
App::uses('User', 'Model');
App::uses('UsersController', 'Controller');

/**
 * Tests for Google Sign-In functionality.
 *
 * Uses FakeGoogleTokenVerifier to simulate Google OAuth without network calls.
 * Uses ContextPreparator to ensure clean database state for each test.
 */
class GoogleSignInTest extends ControllerTestCase
{
	private FakeGoogleTokenVerifier $fakeVerifier;

	public function setUp(): void
	{
		parent::setUp();
		$this->fakeVerifier = new FakeGoogleTokenVerifier();
		// Set the static test verifier so it's used by any controller instance
		UsersController::setTestTokenVerifier($this->fakeVerifier);
	}

	public function tearDown(): void
	{
		// Clean up the static verifier
		UsersController::setTestTokenVerifier(null);
		$this->fakeVerifier->reset();
		parent::tearDown();
	}

	/**
	 * Helper to create a Google user (with google_oauth password_hash).
	 * Uses ContextPreparator for clean state, then creates Google user manually.
	 * Note: Google users have NULL name (they can't use password login).
	 */
	private function createGoogleUser(array $data): array
	{
		$defaults = [
			'password_hash' => 'google_oauth',
			'name' => null, // Google users don't have a login name
		];
		$userData = array_merge($defaults, $data);

		$User = ClassRegistry::init('User');
		$User->create();
		$User->save($userData, false);
		return $User->read()['User'];
	}

	/**
	 * Test that new Google user is created with correct data.
	 */
	public function testNewGoogleUserCreated(): void
	{
		// Clean DB state
		new ContextPreparator(['user' => null]);

		$this->fakeVerifier->addToken('valid_token_123', [
			'sub' => '12345678901234567890',
			'email' => 'john.doe@gmail.com',
			'name' => 'John Doe',
			'picture' => 'https://lh3.googleusercontent.com/photo.jpg',
		]);

		$this->testAction('/users/googlesignin', [
			'data' => ['credential' => 'valid_token_123'],
			'method' => 'post',
		]);

		// Verify user was created with correct data
		$User = ClassRegistry::init('User');
		$user = $User->find('first', [
			'conditions' => ['external_id' => '12345678901234567890'],
		]);

		$this->assertNotEmpty($user, 'Google user should be created');
		$this->assertNull($user['User']['name'], 'Google users should have NULL name');
		$this->assertEquals('John Doe', $user['User']['display_name']);
		$this->assertEquals('john.doe@gmail.com', $user['User']['email']);
		$this->assertEquals('https://lh3.googleusercontent.com/photo.jpg', $user['User']['picture']);
		$this->assertEquals('12345678901234567890', $user['User']['external_id']);
	}

	/**
	 * Test that existing Google user is logged in (not duplicated).
	 */
	public function testExistingGoogleUserLogin(): void
	{
		// Clean DB state
		new ContextPreparator(['user' => null]);

		// Create existing Google user
		$existingUser = $this->createGoogleUser([
			'display_name' => 'Jane Smith',
			'email' => 'jane@gmail.com',
			'external_id' => '98765432109876543210',
			'picture' => 'https://old-picture.jpg',
		]);

		$this->fakeVerifier->addToken('janes_token', [
			'sub' => '98765432109876543210',
			'email' => 'jane@gmail.com',
			'name' => 'Jane Smith',
			'picture' => 'https://new-picture.jpg',
		]);

		$this->testAction('/users/googlesignin', [
			'data' => ['credential' => 'janes_token'],
			'method' => 'post',
		]);

		// Verify no duplicate user was created
		$User = ClassRegistry::init('User');
		$users = $User->find('all', [
			'conditions' => ['external_id' => '98765432109876543210'],
		]);
		$this->assertEquals(1, count($users), 'Should not create duplicate user');

		// Verify picture was updated
		$updatedUser = $User->findById($existingUser['id']);
		$this->assertEquals('https://new-picture.jpg', $updatedUser['User']['picture']);
	}

	/**
	 * Test that email is updated on login if changed in Google.
	 */
	public function testEmailUpdatedOnLogin(): void
	{
		// Clean DB state
		new ContextPreparator(['user' => null]);

		$existingUser = $this->createGoogleUser([
			'display_name' => 'Email User',
			'email' => 'old-email@gmail.com',
			'external_id' => '66666666666666666666',
		]);

		$this->fakeVerifier->addToken('email_update_token', [
			'sub' => '66666666666666666666',
			'email' => 'new-email@gmail.com',
			'name' => 'Email User',
			'picture' => null,
		]);

		$this->testAction('/users/googlesignin', [
			'data' => ['credential' => 'email_update_token'],
			'method' => 'post',
		]);

		$User = ClassRegistry::init('User');
		$updatedUser = $User->findById($existingUser['id']);
		$this->assertEquals('new-email@gmail.com', $updatedUser['User']['email'], 'Email should be updated on login');
	}

	/**
	 * Test that name collision is handled with unique suffix.
	 */
	public function testNameCollisionHandledWithSuffix(): void
	{
		// Clean DB state
		new ContextPreparator(['user' => null]);

		// Create existing user with same name
		$User = ClassRegistry::init('User');
		$User->create();
		$User->save([
			'name' => 'TestUser',
			'display_name' => 'TestUser',
			'email' => 'existing@test.com',
			'password_hash' => 'hash123',
		], false);

		$this->fakeVerifier->addToken('collision_token', [
			'sub' => '11111111111111111111',
			'email' => 'newuser@gmail.com',
			'name' => 'TestUser',
			'picture' => 'https://photo.jpg',
		]);

		$this->testAction('/users/googlesignin', [
			'data' => ['credential' => 'collision_token'],
			'method' => 'post',
		]);

		// Verify new user has unique name with suffix
		$newUser = $User->find('first', [
			'conditions' => ['external_id' => '11111111111111111111'],
		]);

		$this->assertNotEmpty($newUser);
		// Google users have NULL name, but unique display_name with "(N)" suffix
		$this->assertNull($newUser['User']['name'], 'Google users should have NULL name');
		$this->assertEquals('TestUser (2)', $newUser['User']['display_name']);
	}

	/**
	 * Test that invalid token does not create user.
	 */
	public function testInvalidTokenNoUserCreated(): void
	{
		// Clean DB state
		new ContextPreparator(['user' => null]);

		// Count Google users before
		$User = ClassRegistry::init('User');
		$countBefore = $User->find('count', [
			'conditions' => ['external_id !=' => null],
		]);

		// Don't add any valid tokens - fakeVerifier will return null

		$this->testAction('/users/googlesignin', [
			'data' => ['credential' => 'invalid_token'],
			'method' => 'post',
		]);

		// Verify token was attempted
		$this->assertTrue($this->fakeVerifier->wasVerified('invalid_token'));

		// No new user should be created
		$countAfter = $User->find('count', [
			'conditions' => ['external_id !=' => null],
		]);
		$this->assertEquals($countBefore, $countAfter, 'No new Google user should be created with invalid token');
	}

	/**
	 * Test that picture URL is stored directly (not downloaded).
	 */
	public function testPictureStoredAsUrl(): void
	{
		// Clean DB state
		new ContextPreparator(['user' => null]);

		$googlePictureUrl = 'https://lh3.googleusercontent.com/a/ACg8ocK-photo123=s96-c';

		$this->fakeVerifier->addToken('pic_token', [
			'sub' => '22222222222222222222',
			'email' => 'user@gmail.com',
			'name' => 'Picture User',
			'picture' => $googlePictureUrl,
		]);

		$this->testAction('/users/googlesignin', [
			'data' => ['credential' => 'pic_token'],
			'method' => 'post',
		]);

		$User = ClassRegistry::init('User');
		$user = $User->find('first', [
			'conditions' => ['external_id' => '22222222222222222222'],
		]);

		// Picture should be stored as the Google URL, not downloaded
		$this->assertEquals($googlePictureUrl, $user['User']['picture']);
	}

	/**
	 * Test that missing picture is handled gracefully.
	 */
	public function testMissingPictureHandled(): void
	{
		// Clean DB state
		new ContextPreparator(['user' => null]);

		$this->fakeVerifier->addToken('no_pic_token', [
			'sub' => '33333333333333333333',
			'email' => 'nopic@gmail.com',
			'name' => 'No Picture User',
			// No picture field
		]);

		$this->testAction('/users/googlesignin', [
			'data' => ['credential' => 'no_pic_token'],
			'method' => 'post',
		]);

		$User = ClassRegistry::init('User');
		$user = $User->find('first', [
			'conditions' => ['external_id' => '33333333333333333333'],
		]);

		$this->assertNotEmpty($user);
		// Picture should be empty string, not cause errors
		$this->assertTrue(
			empty($user['User']['picture']) || $user['User']['picture'] === '',
			'Missing picture should be handled gracefully'
		);
	}

	/**
	 * Test that missing name falls back to "User {last6digits}".
	 */
	public function testMissingNameUsesSubFallback(): void
	{
		// Clean DB state
		new ContextPreparator(['user' => null]);

		$this->fakeVerifier->addToken('no_name_token', [
			'sub' => '44444444444444444444',
			'email' => 'noname@gmail.com',
			'name' => null, // No name provided
			'picture' => null,
		]);

		$this->testAction('/users/googlesignin', [
			'data' => ['credential' => 'no_name_token'],
			'method' => 'post',
		]);

		$User = ClassRegistry::init('User');
		$user = $User->find('first', [
			'conditions' => ['external_id' => '44444444444444444444'],
		]);

		$this->assertNotEmpty($user, 'User should be created even without name');
		$this->assertNull($user['User']['name'], 'Google users should have NULL name');
		$this->assertEquals('User 444444', $user['User']['display_name'], 'Should use last 6 digits of sub');
	}

	/**
	 * Test that empty name also falls back to "User {last6digits}".
	 */
	public function testEmptyNameUsesSubFallback(): void
	{
		// Clean DB state
		new ContextPreparator(['user' => null]);

		$this->fakeVerifier->addToken('empty_name_token', [
			'sub' => '55555555555555555555',
			'email' => 'emptyname@gmail.com',
			'name' => '', // Empty string name
			'picture' => null,
		]);

		$this->testAction('/users/googlesignin', [
			'data' => ['credential' => 'empty_name_token'],
			'method' => 'post',
		]);

		$User = ClassRegistry::init('User');
		$user = $User->find('first', [
			'conditions' => ['external_id' => '55555555555555555555'],
		]);

		$this->assertNotEmpty($user, 'User should be created even with empty name');
		$this->assertNull($user['User']['name'], 'Google users should have NULL name');
		$this->assertEquals('User 555555', $user['User']['display_name'], 'Should use last 6 digits of sub');
	}
}

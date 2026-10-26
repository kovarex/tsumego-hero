<?php

use App\Utility\FakeGoogleTokenVerifier;
use App\Utility\GoogleTokenVerifier;

/**
 * Tests for Google Sign-In functionality.
 *
 * Uses FakeGoogleTokenVerifier to simulate Google OAuth without network calls.
 * Uses ContextPreparator to ensure clean database state for each test.
 */
class GoogleSignInTest extends IntegrationTestCase
{
	private const CSRF_TOKEN = 'test-csrf-token';

	private FakeGoogleTokenVerifier $fakeVerifier;

	public function setUp(): void
	{
		parent::setUp();
		$this->fakeVerifier = new FakeGoogleTokenVerifier();
		// Set the static test verifier so it's used by any controller instance
		GoogleTokenVerifier::useForTests($this->fakeVerifier);
	}

	public function tearDown(): void
	{
		// Clean up the static verifier
		GoogleTokenVerifier::useForTests(null);
		$this->fakeVerifier->reset();
		parent::tearDown();
	}

	/**
	 * Run the sign-in action with a credential and the g_csrf_token cookie and
	 * post field that Google Sign-In sends and the CSRF check compares.
	 *
	 * @param string $credential The credential token to post
	 * @param string $csrfCookie The value of the g_csrf_token cookie
	 */
	private function googleSignIn(string $credential, string $csrfCookie = self::CSRF_TOKEN): void
	{
		$_COOKIE['g_csrf_token'] = $csrfCookie;
		$this->post('/users/googlesignin', ['credential' => $credential, 'g_csrf_token' => self::CSRF_TOKEN]);
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
	 * Test that a Google name longer than the column is shortened and the player
	 * is asked to pick their own name.
	 */
	public function testNameLongerThanTheColumnIsShortened(): void
	{
		new ContextPreparator(['user' => null]);
		$this->fakeVerifier->addToken('long-name-token', [
			'sub' => '123456789',
			'email' => 'longname@gmail.com',
			'name' => str_repeat('B', 70),
			'picture' => 'https://lh3.googleusercontent.com/a/longname',
		]);

		$this->googleSignIn('long-name-token');

		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['external_id' => '123456789']]);
		$this->assertNotEmpty($user, 'The Google sign in must create the account');
		$this->assertSame(str_repeat('B', 50), $user['User']['display_name']);
		$this->assertEquals(true, $user['User']['needs_display_name_change']);
	}

	/**
	 * A name Google brings with an emoji loses the character the forum cannot store. What is
	 * left is checked for uniqueness like any other name, so the account cannot take a name an
	 * existing account already has, and the player is asked to pick their own.
	 */
	public function testNameWithAnEmojiLosesItAndCannotTakeAnExistingName(): void
	{
		new ContextPreparator(['user' => ['display_name' => 'Player']]);
		$this->fakeVerifier->addToken('emoji-token', [
			'sub' => '987654321',
			'email' => 'emoji@gmail.com',
			'name' => 'Player😀',
		]);

		$this->googleSignIn('emoji-token');

		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['external_id' => '987654321']]);
		$this->assertNotEmpty($user, 'The Google sign in must create the account');
		$this->assertSame('Player (2)', $user['User']['display_name'], 'The name left without the emoji is made unique like any other name');
		$this->assertEquals(true, $user['User']['needs_display_name_change']);
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

		$this->googleSignIn('valid_token_123');

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
		$this->assertSame(User::AVATAR_GOOGLE, (int) $user['User']['pref_avatar']);
	}

	/**
	 * A sign in that brings the first picture to an account that has none makes it the
	 * avatar of that account.
	 */
	public function testSignInWithAPictureStartsShowingIt(): void
	{
		new ContextPreparator(['user' => null]);

		$existingUser = $this->createGoogleUser([
			'display_name' => 'No Picture Yet',
			'email' => 'nopicture@gmail.com',
			'external_id' => '11111111111111111111',
			'picture' => null,
		]);

		$this->fakeVerifier->addToken('nopicture_token', [
			'sub' => '11111111111111111111',
			'email' => 'nopicture@gmail.com',
			'name' => 'No Picture Yet',
			'picture' => 'https://lh3.googleusercontent.com/a/late-photo',
		]);

		$this->googleSignIn('nopicture_token');

		$user = ClassRegistry::init('User')->findById($existingUser['id']);
		$this->assertSame('https://lh3.googleusercontent.com/a/late-photo', $user['User']['picture']);
		$this->assertSame(User::AVATAR_GOOGLE, (int) $user['User']['pref_avatar']);
	}

	/**
	 * A picture that only changed leaves the avatar the player picked alone.
	 */
	public function testSignInWithAChangedPictureKeepsThePickedAvatar(): void
	{
		new ContextPreparator(['user' => null]);

		$existingUser = $this->createGoogleUser([
			'display_name' => 'Picked Gravatar',
			'email' => 'picked@gmail.com',
			'external_id' => '22222222222222222222',
			'picture' => 'https://lh3.googleusercontent.com/a/old-photo',
			'pref_avatar' => User::AVATAR_GRAVATAR,
		]);

		$this->fakeVerifier->addToken('picked_token', [
			'sub' => '22222222222222222222',
			'email' => 'picked@gmail.com',
			'name' => 'Picked Gravatar',
			'picture' => 'https://lh3.googleusercontent.com/a/new-photo',
		]);

		$this->googleSignIn('picked_token');

		$user = ClassRegistry::init('User')->findById($existingUser['id']);
		$this->assertSame('https://lh3.googleusercontent.com/a/new-photo', $user['User']['picture']);
		$this->assertSame(User::AVATAR_GRAVATAR, (int) $user['User']['pref_avatar']);
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

		$this->googleSignIn('janes_token');

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
	 * Test that the picture from a Google sign-in ends up in the comments of that player.
	 */
	public function testCommentBySignedInGoogleUserShowsTheirGooglePicture(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'status' => 'S']]);

		$this->fakeVerifier->addToken('commenters_token', [
			'sub' => '123456789012345678901',
			'email' => 'commenter@gmail.com',
			'name' => 'Google Commenter',
			'picture' => 'https://lh3.googleusercontent.com/a/photo',
		]);
		$this->googleSignIn('commenters_token');

		// The test auth cookie decides who is logged in, so point it at the account
		// the sign-in just created, like the session would in production
		$googleUser = ClassRegistry::init('User')->find('first', ['conditions' => ['external_id' => '123456789012345678901']]);
		$_COOKIE['hackedLoggedInUserID'] = $googleUser['User']['id'];
		$googleUserModel = ClassRegistry::init('User');
		$googleUserModel->id = $googleUser['User']['id'];
		$googleUserModel->saveField('pref_avatar', User::AVATAR_GOOGLE);

		$this->post('/tsumego-comments/add', json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'from a google account']));

		$body = json_decode($this->view, true);
		$this->assertSame('Google Commenter', $body['user_display_name']);
		$this->assertSame('https://lh3.googleusercontent.com/a/photo=s48', $body['user_avatar_url']);
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

		$this->googleSignIn('email_update_token');

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

		$this->googleSignIn('collision_token');

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

		$this->googleSignIn('invalid_token');

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

		$this->googleSignIn('pic_token');

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

		$this->googleSignIn('no_pic_token');

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

		$this->googleSignIn('no_name_token');

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

		$this->googleSignIn('empty_name_token');

		$User = ClassRegistry::init('User');
		$user = $User->find('first', [
			'conditions' => ['external_id' => '55555555555555555555'],
		]);

		$this->assertNotEmpty($user, 'User should be created even with empty name');
		$this->assertNull($user['User']['name'], 'Google users should have NULL name');
		$this->assertEquals('User 555555', $user['User']['display_name'], 'Should use last 6 digits of sub');
	}

	/**
	 * Test that a request whose g_csrf_token cookie does not match the posted
	 * one is rejected and creates no account.
	 */
	public function testMismatchingCsrfTokenCreatesNoUser(): void
	{
		// Clean DB state
		new ContextPreparator(['user' => null]);

		$this->fakeVerifier->addToken('csrf_token', [
			'sub' => '66666666666666666666',
			'email' => 'csrf@gmail.com',
			'name' => 'Csrf User',
		]);

		$this->googleSignIn('csrf_token', 'a-different-token');

		$User = ClassRegistry::init('User');
		$user = $User->find('first', [
			'conditions' => ['external_id' => '66666666666666666666'],
		]);

		$this->assertEmpty($user, 'No account should be created for a mismatching CSRF token');
	}
}

<?php

use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\FakeGoogleTokenVerifier;
use App\Utility\GoogleTokenVerifier;
use App\Utility\JwtAuth;

//Switching an account that signs in with Google only to email and password
//(AccountController::switchToPassword): the same account, now with a password, which the Google
//button keeps signing in.
class GoogleToPasswordAccountTest extends IntegrationTestCase
{
	private const PASSWORD = 'brnoHorse42';
	private const GOOGLE_ID = '109876543210';

	private FakeGoogleTokenVerifier $fakeVerifier;

	public function setUp(): void
	{
		parent::setUp();
		$this->fakeVerifier = new FakeGoogleTokenVerifier();
		GoogleTokenVerifier::useForTests($this->fakeVerifier);
	}

	public function tearDown(): void
	{
		GoogleTokenVerifier::useForTests(null);
		$this->fakeVerifier->reset();
		JwtAuth::clearCache();
		parent::tearDown();
	}

	//An account created by a Google sign-in: a Google id, no login name and no password
	private function googleAccount(string $email = 'player@gmail.com', string $displayName = 'Jana Novakova Full Name'): array
	{
		new ContextPreparator(['user' => null]);
		$table = $this->fetchTable('User');
		$user = $table->saveOrFail($table->newEntity([
			'name' => null,
			'display_name' => $displayName,
			'email' => $email,
			'password_hash' => UserTable::NO_PASSWORD,
			'external_id' => self::GOOGLE_ID], ['validate' => false]));
		return UserTable::row($user->id);
	}

	//A local account with its own password ('test')
	private function passwordAccount(string $name, string $email, string $displayName): array
	{
		$table = $this->fetchTable('User');
		$user = $table->saveOrFail($table->newEntity([
			'name' => $name,
			'display_name' => $displayName,
			'email' => $email,
			'password_hash' => password_hash('test', PASSWORD_DEFAULT)], ['validate' => false]));
		return UserTable::row($user->id);
	}

	//The session of the account, as the auth_token cookie of a sign-in $secondsAgo seconds ago
	private function signedIn(array $user, int $secondsAgo = 0): void
	{
		$this->logout();
		$_COOKIE['auth_token'] = JwtAuth::createToken($user['id'], $user['password_hash'], null, time() - $secondsAgo);
		JwtAuth::clearCache();
	}

	private function switchToPassword(array $data): void
	{
		JwtAuth::clearCache();
		$this->post('/users/switchToPassword', $data + ['password1' => self::PASSWORD, 'password2' => self::PASSWORD]);
	}

	private function stored(array $user): array
	{
		return UserTable::row($user['id']);
	}

	//The settings of an account that signs in with Google only lead to the switch; an account
	//with a password has nothing to switch.
	public function testTheSettingsOfAGoogleAccountOfferTheSwitch(): void
	{
		$user = $this->googleAccount();
		$this->signedIn($user);
		$this->get('/users/settings');
		$this->assertResponseOk();
		$this->assertResponseContains('id="switch-to-password-link"');

		$local = $this->passwordAccount('localPlayer', 'local@example.com', 'Local Player');
		$this->signedIn($local);
		$this->get('/users/settings');
		$this->assertResponseNotContains('id="switch-to-password-link"');
	}

	//The switch sets the password and nothing else: it is the same account, with its Google id,
	//its address and its name, which now signs in with the address and the password.
	public function testTheSwitchGivesTheAccountAPassword(): void
	{
		$user = $this->googleAccount();
		$this->signedIn($user);
		$this->registerTestEmailer();

		$this->switchToPassword(['email' => 'player@gmail.com', 'display_name' => 'Jana Novakova Full Name']);

		$this->assertRedirect('/users/settings');
		$stored = $this->stored($user);
		$this->assertTrue(password_verify(self::PASSWORD, $stored['password_hash']));
		$this->assertSame(self::GOOGLE_ID, $stored['external_id']);
		$this->assertSame('player@gmail.com', $stored['email']);
		$this->assertSame('Jana Novakova Full Name', $stored['display_name']);
		$this->assertFalse(UserTable::signsInOnlyWithGoogle($stored));
		$this->assertSame('player@gmail.com', TestEmailer::$lastEmail['to'], 'the account is told it has a password now');
		$this->assertStringNotContainsString(self::PASSWORD, TestEmailer::$lastEmail['body']);
		// the session that switched goes on, under the new password
		$this->assertSame($user['id'], JwtAuth::validateToken($this->browserCookies()['auth_token']));

		$this->logout();
		JwtAuth::clearCache();
		$this->post('/users/login', ['username' => 'player@gmail.com', 'password' => self::PASSWORD]);
		$this->assertSame($user['id'], JwtAuth::validateToken($this->browserCookies()['auth_token'] ?? ''), 'signs in with the address and the password');
	}

	//The Google button keeps signing the switched account in, and no longer moves it to the
	//address the Google account has now: the player signs in with the address they have.
	public function testGoogleKeepsSigningTheSwitchedAccountIn(): void
	{
		$user = $this->googleAccount();
		$this->signedIn($user);
		$this->switchToPassword(['email' => 'player@gmail.com']);

		$this->logout();
		JwtAuth::clearCache();
		$this->fakeVerifier->addToken('google-token', ['sub' => self::GOOGLE_ID, 'email' => 'renamed@gmail.com', 'name' => 'Jana Novakova Full Name']);
		$_COOKIE['g_csrf_token'] = 'csrf';
		$this->post('/users/googlesignin', ['credential' => 'google-token', 'g_csrf_token' => 'csrf']);

		$this->assertSame($user['id'], JwtAuth::validateToken($this->browserCookies()['auth_token'] ?? ''));
		$this->assertSame('player@gmail.com', $this->stored($user)['email']);
	}

	//A session opened long ago is not enough: the page asks to sign in with Google again, and a
	//post is refused.
	public function testTheSwitchNeedsARecentSignIn(): void
	{
		$user = $this->googleAccount();
		$this->signedIn($user, Auth::RECENT_SIGN_IN_SECONDS + 60);

		JwtAuth::clearCache();
		$this->get('/users/switchToPassword');
		$this->assertResponseOk();
		$this->assertResponseContains('id="switch-sign-in-again"');
		$this->assertResponseContains('g_id_signin');
		$this->assertResponseNotContains('UserSwitchToPasswordForm');

		$this->switchToPassword(['email' => 'player@gmail.com']);

		$this->assertSame(UserTable::NO_PASSWORD, $this->stored($user)['password_hash']);
		$this->assertSame('For your security, sign in with Google again first.', CookieFlash::get());
	}

	//A fresh sign-in shows the form, filled with the address and the name the account has
	public function testARecentSignInShowsTheForm(): void
	{
		$user = $this->googleAccount();
		$this->signedIn($user, 60);

		$this->get('/users/switchToPassword');

		$this->assertResponseContains('UserSwitchToPasswordForm');
		$this->assertResponseContains('value="player@gmail.com"');
		$this->assertResponseContains('value="Jana Novakova Full Name"');
	}

	//The address of another account is refused, the one of the Google account included, so the
	//switch can not take over the sign-in of another account.
	public function testAnAddressOfAnotherAccountIsRefused(): void
	{
		$user = $this->googleAccount();
		$this->passwordAccount('otherPlayer', 'taken@example.com', 'Other Player');
		$this->signedIn($user);

		$this->switchToPassword(['email' => 'taken@example.com']);

		$this->assertSame(UserTable::NO_PASSWORD, $this->stored($user)['password_hash']);
		$this->assertNull($this->stored($user)['unconfirmed_email']);
		$this->assertResponseContains('That address belongs to another account.');
	}

	public function testTheGoogleAddressUsedByAnotherAccountIsRefused(): void
	{
		$user = $this->googleAccount('shared@example.com');
		$this->passwordAccount('otherPlayer', 'shared@example.com', 'Other Player');
		$this->signedIn($user);

		$this->switchToPassword(['email' => 'shared@example.com']);

		$this->assertSame(UserTable::NO_PASSWORD, $this->stored($user)['password_hash']);
		$this->assertResponseContains('That address belongs to another account.');
	}

	//Another address is confirmed like any address change: the account keeps its address until
	//the link sent to the new one is opened.
	public function testAnotherAddressWaitsForItsConfirmation(): void
	{
		$user = $this->googleAccount();
		$this->signedIn($user);

		$this->switchToPassword(['email' => 'mine@example.com']);

		$stored = $this->stored($user);
		$this->assertTrue(password_verify(self::PASSWORD, $stored['password_hash']));
		$this->assertSame('player@gmail.com', $stored['email']);
		$this->assertSame('mine@example.com', $stored['unconfirmed_email']);

		JwtAuth::clearCache();
		$this->post('/users/confirmEmailChange/' . $stored['unconfirmed_email_token']);
		$this->assertSame('mine@example.com', $this->stored($user)['email']);
	}

	//The display name from Google can be changed in the same step, to a name nobody has
	public function testTheDisplayNameCanBeChangedInTheSameStep(): void
	{
		$user = $this->googleAccount();
		$this->signedIn($user);

		$this->switchToPassword(['email' => 'player@gmail.com', 'display_name' => 'Jana']);

		$this->assertSame('Jana', $this->stored($user)['display_name']);
		$this->assertTrue(password_verify(self::PASSWORD, $this->stored($user)['password_hash']));
	}

	public function testATakenDisplayNameIsRefused(): void
	{
		$user = $this->googleAccount();
		$this->passwordAccount('otherPlayer', 'other@example.com', 'Taken Name');
		$this->signedIn($user);

		$this->switchToPassword(['email' => 'player@gmail.com', 'display_name' => 'Taken Name']);

		$stored = $this->stored($user);
		$this->assertSame('Jana Novakova Full Name', $stored['display_name']);
		$this->assertSame(UserTable::NO_PASSWORD, $stored['password_hash']);
		$this->assertResponseContains('This display name is already taken.');
	}

	//The password follows the rules of every password of the site
	public function testThePasswordsHaveToMatch(): void
	{
		$user = $this->googleAccount();
		$this->signedIn($user);

		JwtAuth::clearCache();
		$this->post('/users/switchToPassword', ['email' => 'player@gmail.com', 'password1' => self::PASSWORD, 'password2' => 'somethingElse1']);

		$this->assertSame(UserTable::NO_PASSWORD, $this->stored($user)['password_hash']);
		$this->assertResponseContains('The new passwords do not match.');
	}

	//An account that has a password has nothing to switch
	public function testAnAccountWithAPasswordIsSentBack(): void
	{
		new ContextPreparator(['user' => null]);
		$local = $this->passwordAccount('localPlayer', 'local@example.com', 'Local Player');
		$this->signedIn($local);

		$this->switchToPassword(['email' => 'local@example.com']);

		$this->assertRedirect('/users/settings');
		$this->assertTrue(password_verify('test', $this->stored($local)['password_hash']));
	}
}

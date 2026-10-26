<?php

use App\Utility\Auth;
use App\Utility\JwtAuth;

/**
 * Changing the password of a local account from the settings page.
 */
class PasswordPreferenceTest extends IntegrationTestCase
{
	/**
	 * The password every fixture account starts with.
	 */
	private const CURRENT_PASSWORD = 'test';

	private function changePassword(array $data): void
	{
		$this->post('/users/passwordPreference', $data);
	}

	private function storedHash(int $userID): string
	{
		return ClassRegistry::init('User')->findById($userID)['User']['password_hash'];
	}

	private function prepareUser(string $name): array
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => $name]]);
		$this->login($name);
		return $context->user;
	}

	public function testChangesThePasswordWhenTheCurrentOneIsRight(): void
	{
		$user = $this->prepareUser('passwordChanged');

		$this->changePassword([
			'current' => self::CURRENT_PASSWORD,
			'new1' => 'brnoHorse42',
			'new2' => 'brnoHorse42',
		]);

		$hash = $this->storedHash($user['id']);
		$this->assertTrue(password_verify('brnoHorse42', $hash));
		$this->assertFalse(password_verify(self::CURRENT_PASSWORD, $hash));
	}

	public function testKeepsThePasswordWhenTheCurrentOneIsWrong(): void
	{
		$user = $this->prepareUser('passwordWrongCurrent');

		$this->changePassword([
			'current' => 'not-my-password',
			'new1' => 'brnoHorse42',
			'new2' => 'brnoHorse42',
		]);

		$hash = $this->storedHash($user['id']);
		$this->assertTrue(password_verify(self::CURRENT_PASSWORD, $hash));
		$this->assertFalse(password_verify('brnoHorse42', $hash));
	}

	public function testKeepsThePasswordWhenTheTypedNewPasswordsDiffer(): void
	{
		$user = $this->prepareUser('passwordMismatch');

		$this->changePassword([
			'current' => self::CURRENT_PASSWORD,
			'new1' => 'brnoHorse42',
			'new2' => 'brnoHorse43',
		]);

		$hash = $this->storedHash($user['id']);
		$this->assertTrue(password_verify(self::CURRENT_PASSWORD, $hash));
	}

	public function testKeepsThePasswordWhenTheNewOneIsTooShort(): void
	{
		$user = $this->prepareUser('passwordTooShort');

		$this->changePassword([
			'current' => self::CURRENT_PASSWORD,
			'new1' => 'short',
			'new2' => 'short',
		]);

		$hash = $this->storedHash($user['id']);
		$this->assertTrue(password_verify(self::CURRENT_PASSWORD, $hash));
	}

	/**
	 * A Google account signs in through Google and has no password to change.
	 */
	public function testKeepsTheGoogleAccountWithoutAPassword(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['external_id' => 'google-987654']]);
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();

		$this->changePassword([
			'current' => self::CURRENT_PASSWORD,
			'new1' => 'brnoHorse42',
			'new2' => 'brnoHorse42',
		]);

		$this->assertSame('google_oauth', $this->storedHash($context->user['id']));
	}

	public function testTellsTheAccountByMailAboutTheChange(): void
	{
		$user = $this->prepareUser('passwordMail');
		$this->registerTestEmailer();

		$this->changePassword([
			'current' => self::CURRENT_PASSWORD,
			'new1' => 'brnoHorse42',
			'new2' => 'brnoHorse42',
		]);

		$this->assertSame($user['email'], TestEmailer::$lastEmail['to']);
		$this->assertStringContainsString('password', strtolower(TestEmailer::$lastEmail['subject']));
		$this->assertStringNotContainsString('brnoHorse42', TestEmailer::$lastEmail['body']);
	}

	/**
	 * Session of another device, opened with the old password.
	 */
	public function testAnotherDeviceIsSignedOutAfterTheChange(): void
	{
		$user = $this->prepareUser('passwordOtherDevice');
		$oldToken = JwtAuth::createToken($user['id'], null, $this->storedHash($user['id']));

		$this->changePassword([
			'current' => self::CURRENT_PASSWORD,
			'new1' => 'brnoHorse42',
			'new2' => 'brnoHorse42',
		]);

		unset($_COOKIE['hackedLoggedInUserID']);
		$_COOKIE['auth_token'] = $oldToken;
		JwtAuth::clearCache();
		Auth::init();

		$this->assertFalse(Auth::isLoggedIn());
	}

	/**
	 * The mail only tells what already happened, so a mail server that does not answer
	 * leaves the new password in place.
	 */
	public function testThePasswordStaysChangedWhenTheMailCannotBeSent(): void
	{
		$user = $this->prepareUser('passwordMailFails');
		$this->registerTestEmailer();

		try
		{
			TestEmailer::$unreachable = true;
			$this->changePassword([
				'current' => self::CURRENT_PASSWORD,
				'new1' => 'brnoHorse42',
				'new2' => 'brnoHorse42',
			]);
		}
		finally
		{
			TestEmailer::$unreachable = false;
		}

		$this->assertTrue(password_verify('brnoHorse42', $this->storedHash($user['id'])));
	}

	public function testThisDeviceStaysSignedInAfterTheChange(): void
	{
		$this->prepareUser('passwordThisDevice');

		$this->changePassword([
			'current' => self::CURRENT_PASSWORD,
			'new1' => 'brnoHorse42',
			'new2' => 'brnoHorse42',
		]);

		// The browser keeps the cookies the change handed out, and the test hack cookie is
		// not one of them.
		$_COOKIE = $this->browserCookies();
		unset($_COOKIE['hackedLoggedInUserID']);
		JwtAuth::clearCache();
		Auth::init();

		$this->assertTrue(Auth::isLoggedIn());
	}

	/**
	 * Sessions opened before a token carried the password still work, so nobody is
	 * signed out by the change that introduced it.
	 */
	public function testASessionWithoutThePasswordClaimStillWorks(): void
	{
		$user = $this->prepareUser('passwordOldToken');

		unset($_COOKIE['hackedLoggedInUserID']);
		$_COOKIE['auth_token'] = JwtAuth::createToken($user['id']);
		JwtAuth::clearCache();
		Auth::init();

		$this->assertTrue(Auth::isLoggedIn());
	}
}

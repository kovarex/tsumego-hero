<?php

use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\FakePwnedPasswordChecker;
use App\Utility\PwnedPasswordChecker;

//A password that already appears in a public breach is refused wherever a password
//is set: when an account is created, changed and reset.
class PwnedPasswordTest extends IntegrationTestCase
{
	//The password every fixture account has.
	private const CURRENT_PASSWORD = 'test';

	//A password the fake list calls breached, and one it does not.
	private const LEAKED = 'password123';
	private const FRESH = 'lanternQuiet42';

	private const LEAKED_PASSWORD_MESSAGE = 'That password appeared in a public data breach, choose another one.';

	private FakePwnedPasswordChecker $checker;

	public function setUp(): void
	{
		parent::setUp();
		$this->checker = new FakePwnedPasswordChecker();
		PwnedPasswordChecker::useForTests($this->checker);
	}

	public function tearDown(): void
	{
		PwnedPasswordChecker::useForTests(null);
		parent::tearDown();
	}

	private function storedHash(int $userID): string
	{
		return UserTable::row((int) $userID)['password_hash'];
	}

	private function userCount(): int
	{
		return $this->fetchTable('User')->find()->count();
	}

	private function userNamed(string $name): array
	{
		$user = ContextPreparator::findUserRow(['name' => $name]);
		return $user ?: [];
	}

	private function register(string $name, string $password): string
	{
		$this->post('/users/add', [
			'name' => $name,
			'email' => $name . '@example.com',
			'password1' => $password,
			'password2' => $password,
		]);

		return $this->view;
	}

	private function changePassword(string $current, string $new): void
	{
		$this->post('/users/passwordPreference', [
			'current' => $current,
			'new1' => $new,
			'new2' => $new,
		]);
	}

	private function prepareUser(string $name): array
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => $name]]);
		$this->login($name);
		return $context->user;
	}

	public function testRegistrationRefusesALeakedPassword(): void
	{
		new ContextPreparator(['user' => null]);
		$this->checker->addBreachedPassword(self::LEAKED);
		$countBefore = $this->userCount();

		$page = $this->register('leakedSignUp', self::LEAKED);

		$this->assertSame($countBefore, $this->fetchTable('User')->find()->count());
		$this->assertStringContainsString(self::LEAKED_PASSWORD_MESSAGE, $page);
	}

	public function testRegistrationKeepsAPasswordThatIsNotInTheList(): void
	{
		new ContextPreparator(['user' => null]);
		$this->checker->addBreachedPassword(self::LEAKED);

		$this->register('freshSignUp', self::FRESH);

		$this->assertNotEmpty($this->userNamed('freshSignUp'));
		// the list is asked about the password as typed, never about the hash
		$this->assertContains(self::FRESH, $this->checker->getAskedPasswords());
	}

	public function testRegistrationRefusesAPasswordShorterThanTheMinimum(): void
	{
		new ContextPreparator(['user' => null]);

		$page = $this->register('shortSignUp', substr(self::FRESH, 0, Constants::$MINIMUM_PASSWORD_LENGTH - 1));

		$this->assertEmpty($this->userNamed('shortSignUp'));
		$this->assertStringContainsString('at least ' . Constants::$MINIMUM_PASSWORD_LENGTH . ' characters', $page);
	}

	public function testPasswordChangeRefusesALeakedPassword(): void
	{
		$user = $this->prepareUser('leakedChange');
		$this->checker->addBreachedPassword(self::LEAKED);

		$this->changePassword(self::CURRENT_PASSWORD, self::LEAKED);

		$this->assertTrue(password_verify(self::CURRENT_PASSWORD, $this->storedHash($user['id'])));
		$this->assertSame(self::LEAKED_PASSWORD_MESSAGE, CookieFlash::get());
	}

	public function testAWrongCurrentPasswordIsAnsweredWithoutAskingTheList(): void
	{
		$user = $this->prepareUser('leakedWrongCurrent');

		$this->changePassword('not-the-password', self::FRESH);

		$this->assertTrue(password_verify(self::CURRENT_PASSWORD, $this->storedHash($user['id'])));
		$this->assertSame([], $this->checker->getAskedPasswords());
	}

	public function testPasswordResetRefusesALeakedPassword(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'leakedReset']]);
		$user = UserTable::row((int) $context->user['id']);
		$this->fetchTable('User')->updateAll([
			'password_reset_token' => 'resetLinkForLeaked',
			'password_reset_token_created' => date('Y-m-d H:i:s'),
		], ['id' => $user['id']]);
		$this->logout();
		$this->checker->addBreachedPassword(self::LEAKED);

		$this->post('/users/newpassword/resetLinkForLeaked', [
			'password1' => self::LEAKED,
			'password2' => self::LEAKED,
		]);

		$this->assertTrue(password_verify(self::CURRENT_PASSWORD, $this->storedHash($context->user['id'])));
		$this->assertStringContainsString(self::LEAKED_PASSWORD_MESSAGE, $this->view);
	}
}

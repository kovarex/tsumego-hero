<?php

use App\Controller\UsersController;
use App\Model\Table\UserTable;
use Facebook\WebDriver\WebDriverBy;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Util;

class LoginComponentTest extends IntegrationTestCase
{
	public function testLogin(): void
	{
		new ContextPreparator(['other-users' => [['name' => 'kovarex']]]);
		$user = ContextPreparator::findUserRow(['name' => 'kovarex']);
		$this->assertNotEmpty($user);

		// making sure the password is what we expect it to be
		$this->fetchTable('User')->updateAll(['password_hash' => password_hash('test', PASSWORD_DEFAULT)], ['id' => $user['id']]);

		$this->assertFalse(Auth::isLoggedIn());
		$this->post('users/login/', ['username' => 'kovarex', 'password' => 'test']);
		$this->assertTrue(Auth::isLoggedIn());
	}

	public function testLoginWithEmail(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);
		$browser = Browser::instance();
		$browser->get('users/login');
		$browser->clickId('UserName');
		$browser->driver->getKeyboard()->sendKeys('kovarex@example.com');
		$browser->clickId('password');
		$browser->driver->getKeyboard()->sendKeys('test');
		$sumbitButton = $browser->find('#UserLoginForm button[type="submit"]');
		$sumbitButton->click();
		$this->assertSame($browser->find('.account-bar-user-class')->getText(), 'DN_kovarex');
	}

	public function testLoginWithWrongPassword(): void
	{
		new ContextPreparator(['other-users' => [['name' => 'kovarex']]]);

		$this->post('users/login/', ['username' => 'kovarex', 'password' => 'testx']);
		$page = $this->view;

		$this->assertFalse(Auth::isLoggedIn());
		$this->assertStringContainsString('Wrong username or password.', $page);
	}

	public function testAShortPasswordStillSignsIn(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'oldAccount']]]);
		$user = UserTable::row((int) $context->otherUsers[0]['id']);
		$this->fetchTable('User')->updateAll(['password_hash' => password_hash('abc', PASSWORD_DEFAULT)], ['id' => $user['id']]);

		$this->post('users/login/', ['username' => 'oldAccount', 'password' => 'abc']);

		$this->assertTrue(Auth::isLoggedIn());
	}

	public function testAnUnknownNameIsAnsweredLikeAWrongPassword(): void
	{
		new ContextPreparator(['other-users' => [['name' => 'kovarex']]]);

		$this->post('users/login/', ['username' => 'nobodyhere', 'password' => 'test']);
		$page = $this->view;

		$this->assertFalse(Auth::isLoggedIn());
		$this->assertStringContainsString('Wrong username or password.', $page);
	}

	//The hash a login compares against when the name has no account has to cost what a
	//stored hash costs, otherwise the quicker answer tells which names exist.
	public function testTheDummyHashCostsWhatAStoredHashCosts(): void
	{
		$this->assertSame(
			password_get_info(password_hash('anything', PASSWORD_DEFAULT))['options']['cost'],
			password_get_info(UsersController::_dummyPasswordHash())['options']['cost']);
	}

	public function testTheSignInPagePointsGoogleAccountsToTheGoogleButton(): void
	{
		new ContextPreparator(['user' => null]);

		$this->get('users/login/');
		$page = $this->view;

		$this->assertStringContainsString('class="g_id_signin"', $page);
	}

	public function testLogout(): void
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);
		$this->assertTrue(Auth::isLoggedIn());
		$this->get('users/logout/');
		$this->assertFalse(Auth::isLoggedIn());
	}

	public function testSignUp(): void
	{
		// Test that the signup form works correctly with matching passwords
		new ContextPreparator(['user' => null]);
		$userWithBiggestID = $this->fetchTable('User')->find()->select(['id'])->orderByDesc('id')->first()?->id;
		$newUsername = 'testuser' . strval($userWithBiggestID + 1);
		$userCountBefore = $this->fetchTable('User')->find()->count();

		$browser = Browser::instance();
		$browser->get('users/add');

		// Fill in the signup form
		$browser->findBy(WebDriverBy::name('data[User][name]'))->sendKeys($newUsername);
		$browser->findBy(WebDriverBy::name('data[User][email]'))->sendKeys($newUsername . '@email.com');
		$browser->findBy(WebDriverBy::name('data[User][password1]'))->sendKeys('hello123');
		$browser->findBy(WebDriverBy::name('data[User][password2]'))->sendKeys('hello123');

		// Submit the form
		$browser->find('.signin button[type="submit"]')->click();

		// Wait for form submission to process and redirect away from signup page
		$browser->waitUntilJs('!location.href.includes("users/add")');

		// Check if user was created successfully
		$userCountAfter = $this->fetchTable('User')->find()->count();
		$newUser = ContextPreparator::findUserRow(['name' => $newUsername]);

		// User should be created after signup
		$this->assertNotNull($newUser, 'User should be created after signup');
		$this->assertSame($userCountBefore + 1, $userCountAfter, 'User count should increase by 1');
		$this->assertSame($newUser['name'], $newUsername);
		$this->assertSame($newUser['email'], $newUsername . '@email.com');
	}

	public function testSignUpWithDupliciteName(): void
	{
		new ContextPreparator(['user' => null]);
		$this->assertFalse(Auth::isLoggedIn());
		$userWithBiggestID = $this->fetchTable('User')->find()->select(['id'])->orderByDesc('id')->first()?->id;
		$newUsername = 'kovarex';
		$this->post('users/add/', ['User' => ['name' => $newUsername,
			'password1' => 'helloThere42',
			'password2' => 'helloThere42',
			'email' => $newUsername . '@email.com']]);
		$userCount = $this->fetchTable('User')->find()->count();

		$newUser = ContextPreparator::findUserRow(['name' => $newUsername]);
		$this->assertSame($userCount, $this->fetchTable('User')->find()->count()); // no user was added
	}

	public function testSignUpWithNameThatOnlyDiffersInCase(): void
	{
		new ContextPreparator(['user' => null]);
		$this->assertFalse(Auth::isLoggedIn());
		$userWithBiggestID = $this->fetchTable('User')->find()->select(['id'])->orderByDesc('id')->first()?->id;
		$newUsername = 'Kovarex';
		$this->post('users/add/', ['User' => ['name' => $newUsername,
			'password1' => 'helloThere42',
			'password2' => 'helloThere42',
			'email' => $newUsername . '@email.com']]);
		$userCount = $this->fetchTable('User')->find()->count();

		$newUser = ContextPreparator::findUserRow(['name' => $newUsername]);
		$this->assertSame($userCount, $this->fetchTable('User')->find()->count()); // no user was added
	}

	public function testResetPassword(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);
		$user = $context->otherUsers[0];

		$this->assertFalse(Auth::isLoggedIn());
		$this->registerTestEmailer();
		$this->post('users/resetpassword/', ['User' => ['email' => $user['email']]]);

		$userNew = ContextPreparator::findUserRow(['name' => 'kovarex']);
		$this->assertNotEmpty($userNew['password_reset_token']);
		$this->assertNotEmpty($userNew['password_reset_token_created']);
		$this->assertNotNull(TestEmailer::$lastEmail);
		$this->assertSame(TestEmailer::$lastEmail['to'], $user['email']);
		$this->assertTextContains('Password reset', TestEmailer::$lastEmail['subject']);
		$this->assertStringContainsString('/users/newpassword/' . $userNew['password_reset_token'], TestEmailer::$lastEmail['body']);
	}

	//The reset link only exists in the mail, so a mail that never left leaves no link
	//behind and the next request is answered right away instead of being held back.
	public function testResetCanBeAskedForAgainWhenTheMailCouldNotBeSent(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);
		$user = $context->otherUsers[0];
		$this->registerTestEmailer();

		try
		{
			TestEmailer::$unreachable = true;
			$this->post('users/resetpassword/', ['User' => ['email' => $user['email']]]);
		}
		finally
		{
			TestEmailer::$unreachable = false;
		}

		$this->assertEmpty($this->resetFields()['password_reset_token']);

		$this->registerTestEmailer();
		$this->post('users/resetpassword/', ['User' => ['email' => $user['email']]]);
		$this->assertNotEmpty($this->resetFields()['password_reset_token']);
	}

	private function resetFields(): array
	{
		return ContextPreparator::findUserRow(['name' => 'kovarex']);
	}

	//A mail just sent is not followed by another one right away, so a bot can not fill somebody's inbox.
	public function testASecondResetMailIsNotSentRightAway(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);
		$user = $context->otherUsers[0];

		$this->registerTestEmailer();
		$this->post('users/resetpassword/', ['User' => ['email' => $user['email']]]);
		$this->assertNotEmpty(TestEmailer::$lastEmail['to']);

		TestEmailer::$lastEmail = null;
		$this->post('users/resetpassword/', ['User' => ['email' => $user['email']]]);
		$this->assertArrayNotHasKey('body', TestEmailer::$lastEmail ?? [], 'the second request sends no mail');
	}

	public function testNewPassword(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);

		$user = ContextPreparator::findUserRow(['name' => 'kovarex']);
		$resetSecret = 'reset_checksum_abc';
		$this->fetchTable('User')->updateAll([
			'password_reset_token' => $resetSecret,
			'password_reset_token_created' => date('Y-m-d H:i:s'),
		], ['id' => $user['id']]);

		$browser = Browser::instance();
		$this->assertFalse(Auth::isLoggedIn());
		$browser->get('users/newpassword/' . $resetSecret);
		$newPassword = Util::generateRandomString(20);
		$browser->clickId('password1');
		$browser->driver->getKeyboard()->sendKeys($newPassword);
		$browser->clickId('password2');
		$browser->driver->getKeyboard()->sendKeys($newPassword);
		$browser->clickId('newpassword-submit');

		// Wait for form submission to redirect to login page
		$browser->waitUntilJs('location.href.includes("users/login")');

		$this->assertSame(Util::getMyAddress() . '/users/login', $browser->driver->getCurrentURL());
		$this->assertTextContains("Password changed", $browser->driver->getPageSource());

		$newUser = ContextPreparator::findUserRow(['name' => 'kovarex']);
		$this->assertNull($newUser['password_reset_token']); // password reset was cleared
		$this->assertNull($newUser['password_reset_token_created']);
		$this->assertFalse(Auth::isLoggedIn());

		$this->post('users/login/', ['username' => 'kovarex', 'password' => $newPassword]);
		$this->assertTrue(Auth::isLoggedIn());

		// changing the password to test again to not break other tests
		$this->fetchTable('User')->updateAll(['password_hash' => password_hash('test', PASSWORD_DEFAULT)], ['id' => $newUser['id']]);
	}

	public function testAResetLinkStopsWorkingAfterItsTime(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);

		$user = ContextPreparator::findUserRow(['name' => 'kovarex']);
		$before = $user['password_hash'];
		$this->fetchTable('User')->updateAll([
			'password_reset_token' => 'old_reset_checksum',
			'password_reset_token_created' => date('Y-m-d H:i:s', strtotime('-3 hours')),
		], ['id' => $user['id']]);

		$this->post('users/newpassword/old_reset_checksum', ['User' => ['password1' => 'brnoHorse42', 'password2' => 'brnoHorse42']]);

		$after = ContextPreparator::findUserRow(['name' => 'kovarex']);
		$this->assertSame($before, $after['password_hash']);
		$this->assertSame('old_reset_checksum', $after['password_reset_token']);
	}

	public function testAResetKeepsThePasswordWhenItIsTypedTwiceDifferently(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);

		$user = ContextPreparator::findUserRow(['name' => 'kovarex']);
		$before = $user['password_hash'];
		$this->fetchTable('User')->updateAll([
			'password_reset_token' => 'reset_checksum_twice',
			'password_reset_token_created' => date('Y-m-d H:i:s'),
		], ['id' => $user['id']]);

		$this->post('users/newpassword/reset_checksum_twice', ['User' => ['password1' => 'brnoHorse42', 'password2' => 'brnoHorse43']]);

		$after = ContextPreparator::findUserRow(['name' => 'kovarex']);
		$this->assertSame($before, $after['password_hash']);
	}

	public function testInjectingLogin(): void
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$RATING_MODE]]);
		$this->assertTrue(Auth::isInRatingMode());
		$browser = Browser::instance();
		$browser->get('sets');
		$div = $browser->find('.account-bar-user-class');
		$links = $div->findElements(WebDriverBy::tagName('a')) ?: [];
		$this->assertSame(count($links), 1);
		$this->assertTextContains($context->user['name'], $links[0]->getText());
	}

	//The reset form answers the same for a Google address and for an address without an
	//account, so it can not be asked which addresses have one.
	public function testTheResetFormAnswersGoogleAndUnknownAddressesAlike(): void
	{
		new ContextPreparator([
			'user' => null,
			'other-users' => [[
				'display_name' => 'Google User',
				'email' => 'googleuser@gmail.com',
				'external_id' => '123456789012345678901',
			]],
		]);

		$this->registerTestEmailer();
		$this->post('users/resetpassword/', ['User' => ['email' => 'googleuser@gmail.com']]);
		$google = $this->view;
		$this->post('users/resetpassword/', ['User' => ['email' => 'nobodyhere@example.com']]);
		$unknown = $this->view;

		$this->assertSame($unknown, $google);
		$this->assertStringNotContainsString('Google Sign-In', $google);
		$this->assertEmpty(TestEmailer::$lastEmail['body'] ?? null, 'Email should not be sent for Google users');
	}

	//Test that password reset does not send email for non-existent users.
	public function testResetPasswordNoEmailForNonExistentUser(): void
	{
		new ContextPreparator(['user' => null]);

		$this->registerTestEmailer();
		$this->post('users/resetpassword/', ['User' => ['email' => 'nonexistent@example.com']]);

		// Should NOT send email
		$this->assertEmpty(TestEmailer::$lastEmail['body'] ?? null, 'Email should not be sent for non-existent users');
	}
}

<?php

use Facebook\WebDriver\WebDriverBy;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Util;

class LoginComponentTestWithAuth extends TestCaseWithAuth
{
	public function testLogin(): void
	{
		new ContextPreparator(['other-users' => [['name' => 'kovarex']]]);
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($user);

		// making sure the password is what we expect it to be
		$user['User']['password_hash'] = password_hash('test', PASSWORD_DEFAULT);
		ClassRegistry::init('User')->save($user);

		$this->assertFalse(Auth::isLoggedIn());
		$this->testAction('users/login/', ['data' => ['username' => 'kovarex', 'password' => 'test'], 'method' => 'POST']);
		$this->assertTrue(Auth::isLoggedIn());
	}

	public function testGoogleSignInRequiresPost(): void
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(MethodNotAllowedException::class);

		$this->testAction('users/googlesignin', ['method' => 'GET']);
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
		$sumbitButton = $browser->find('#UserLoginForm input[type="submit"]');
		$sumbitButton->click();
		$this->assertSame($browser->find('.account-bar-user-class')->getText(), 'DN_kovarex');
	}

	public function testLoginWithWrongPassword(): void
	{
		new ContextPreparator(['other-users' => [['name' => 'kovarex']]]);

		$page = $this->testAction('users/login/', ['data' => ['username' => 'kovarex', 'password' => 'testx'], 'method' => 'POST', 'return' => 'contents']);

		$this->assertFalse(Auth::isLoggedIn());
		$this->assertStringContainsString('Wrong username or password.', $page);
	}

	public function testAShortPasswordStillSignsIn(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'oldAccount']]]);
		$user = ClassRegistry::init('User')->findById($context->otherUsers[0]['id']);
		$user['User']['password_hash'] = password_hash('abc', PASSWORD_DEFAULT);
		ClassRegistry::init('User')->save($user);

		$this->testAction('users/login/', ['data' => ['username' => 'oldAccount', 'password' => 'abc'], 'method' => 'POST']);

		$this->assertTrue(Auth::isLoggedIn());
	}

	public function testAnUnknownNameIsAnsweredLikeAWrongPassword(): void
	{
		new ContextPreparator(['other-users' => [['name' => 'kovarex']]]);

		$page = $this->testAction('users/login/', ['data' => ['username' => 'nobodyhere', 'password' => 'test'], 'method' => 'POST', 'return' => 'contents']);

		$this->assertFalse(Auth::isLoggedIn());
		$this->assertStringContainsString('Wrong username or password.', $page);
	}

	/**
	 * The hash a login compares against when the name has no account has to cost what a
	 * stored hash costs, otherwise the quicker answer tells which names exist.
	 */
	public function testTheDummyHashCostsWhatAStoredHashCosts(): void
	{
		$this->assertSame(
			password_get_info(password_hash('anything', PASSWORD_DEFAULT))['options']['cost'],
			password_get_info(UsersController::dummyPasswordHash())['options']['cost']);
	}

	public function testTheSignInPagePointsGoogleAccountsToTheGoogleButton(): void
	{
		new ContextPreparator(['user' => null]);

		$page = $this->testAction('users/login/', ['method' => 'GET', 'return' => 'view']);

		$this->assertStringContainsString('Signed up with Google? Use the button below.', $page);
	}

	public function testLogout(): void
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);
		$this->assertTrue(Auth::isLoggedIn());
		$this->testAction('users/logout/');
		$this->assertFalse(Auth::isLoggedIn());
	}

	public function testSignUp(): void
	{
		// Test that the signup form works correctly with matching passwords
		new ContextPreparator(['user' => null]);
		$userWithBiggestID = ClassRegistry::init('User')->find('first', ['order' => 'id DESC'])['User']['id'];
		$newUsername = 'testuser' . strval($userWithBiggestID + 1);
		$userCountBefore = count(ClassRegistry::init('User')->find('all'));

		$browser = Browser::instance();
		$browser->get('users/add');

		// Fill in the signup form
		$browser->findBy(WebDriverBy::name('data[User][name]'))->sendKeys($newUsername);
		$browser->findBy(WebDriverBy::name('data[User][email]'))->sendKeys($newUsername . '@email.com');
		$browser->findBy(WebDriverBy::name('data[User][password1]'))->sendKeys('hello123');
		$browser->findBy(WebDriverBy::name('data[User][password2]'))->sendKeys('hello123');

		// Submit the form
		$browser->find('.signin input[type="submit"]')->click();

		// Wait for form submission to process and redirect away from signup page
		$browser->waitUntilJs('!location.href.includes("users/add")');

		// Check if user was created successfully
		$userCountAfter = count(ClassRegistry::init('User')->find('all'));
		$newUser = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $newUsername]]);

		// User should be created after signup
		$this->assertNotNull($newUser, 'User should be created after signup');
		$this->assertSame($userCountBefore + 1, $userCountAfter, 'User count should increase by 1');
		$this->assertSame($newUser['User']['name'], $newUsername);
		$this->assertSame($newUser['User']['email'], $newUsername . '@email.com');
	}

	public function testSignUpWithDupliciteName(): void
	{
		new ContextPreparator(['user' => null]);
		$this->assertFalse(Auth::isLoggedIn());
		$userWithBiggestID = ClassRegistry::init('User')->find('first', ['order' => 'id DESC'])['User']['id'];
		$newUsername = 'kovarex';
		$this->testAction('users/add/', ['data' => ['User' => ['name' => $newUsername,
			'password1' => 'helloThere42',
			'password2' => 'helloThere42',
			'email' => $newUsername . '@email.com']], 'method' => 'POST']);
		$userCount = count(ClassRegistry::init('User')->find('all'));

		$newUser = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $newUsername]]);
		$this->assertSame($userCount, count(ClassRegistry::init('User')->find('all'))); // no user was added
	}

	public function testSignUpWithNameThatOnlyDiffersInCase(): void
	{
		new ContextPreparator(['user' => null]);
		$this->assertFalse(Auth::isLoggedIn());
		$userWithBiggestID = ClassRegistry::init('User')->find('first', ['order' => 'id DESC'])['User']['id'];
		$newUsername = 'Kovarex';
		$this->testAction('users/add/', ['data' => ['User' => ['name' => $newUsername,
			'password1' => 'helloThere42',
			'password2' => 'helloThere42',
			'email' => $newUsername . '@email.com']], 'method' => 'POST']);
		$userCount = count(ClassRegistry::init('User')->find('all'));

		$newUser = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $newUsername]]);
		$this->assertSame($userCount, count(ClassRegistry::init('User')->find('all'))); // no user was added
	}

	public function testResetPassword(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);
		$user = $context->otherUsers[0];

		$this->assertFalse(Auth::isLoggedIn());
		$this->registerTestEmailer();
		$this->testAction('users/resetpassword/', ['data' => ['User' => ['email' => $user['email']]], 'method' => 'POST']);

		$userNew = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
		$this->assertNotEmpty($userNew['passwordreset']);
		$this->assertNotEmpty($userNew['passwordreset_created']);
		$this->assertNotNull(TestEmailer::$lastEmail);
		$this->assertSame(TestEmailer::$lastEmail['to'], $user['email']);
		$this->assertTextContains('Password reset', TestEmailer::$lastEmail['subject']);
		$this->assertStringContainsString('/users/newpassword/' . $userNew['passwordreset'], TestEmailer::$lastEmail['body']);
	}

	/**
	 * The reset link only exists in the mail, so a mail that never left leaves no link
	 * behind and the next request is answered right away instead of being held back.
	 */
	public function testResetCanBeAskedForAgainWhenTheMailCouldNotBeSent(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);
		$user = $context->otherUsers[0];
		$this->registerTestEmailer();

		try
		{
			TestEmailer::$unreachable = true;
			$this->testAction('users/resetpassword/', ['data' => ['User' => ['email' => $user['email']]], 'method' => 'POST']);
		}
		finally
		{
			TestEmailer::$unreachable = false;
		}

		$this->assertEmpty($this->resetFields()['passwordreset']);

		$this->registerTestEmailer();
		$this->testAction('users/resetpassword/', ['data' => ['User' => ['email' => $user['email']]], 'method' => 'POST']);
		$this->assertNotEmpty($this->resetFields()['passwordreset']);
	}

	private function resetFields(): array
	{
		return ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
	}

	/**
	 * A mail just sent is not followed by another one right away, so a bot can not
	 * fill somebody's inbox.
	 */
	public function testASecondResetMailIsNotSentRightAway(): void
	{
		$context = new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);
		$user = $context->otherUsers[0];

		$this->registerTestEmailer();
		$this->testAction('users/resetpassword/', ['data' => ['User' => ['email' => $user['email']]], 'method' => 'POST']);
		$this->assertNotEmpty(TestEmailer::$lastEmail['to']);

		TestEmailer::$lastEmail = null;
		$this->testAction('users/resetpassword/', ['data' => ['User' => ['email' => $user['email']]], 'method' => 'POST']);
		$this->assertNull(TestEmailer::$lastEmail['to'] ?? null);
	}

	public function testNewPassword(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);

		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
		$resetSecret = 'reset_checksum_abc';
		$user['passwordreset'] = $resetSecret;
		$user['passwordreset_created'] = date('Y-m-d H:i:s');
		ClassRegistry::init('User')->save($user);

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

		$newUser = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
		$this->assertNull($newUser['passwordreset']); // password reset was cleared
		$this->assertNull($newUser['passwordreset_created']);
		$this->assertFalse(Auth::isLoggedIn());

		$this->testAction('users/login/', ['data' => ['username' => 'kovarex', 'password' => $newPassword], 'method' => 'POST']);
		$this->assertTrue(Auth::isLoggedIn());

		// changing the password to test again to not break other tests
		$newUser['password_hash'] = password_hash('test', PASSWORD_DEFAULT);
		ClassRegistry::init('User')->save($newUser);
	}

	public function testAResetLinkStopsWorkingAfterItsTime(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);

		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
		$before = $user['password_hash'];
		$user['passwordreset'] = 'old_reset_checksum';
		$user['passwordreset_created'] = date('Y-m-d H:i:s', strtotime('-3 hours'));
		ClassRegistry::init('User')->save($user);

		$this->testAction('users/newpassword/old_reset_checksum', [
			'data' => ['User' => ['password1' => 'brnoHorse42', 'password2' => 'brnoHorse42']],
			'method' => 'POST']);

		$after = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
		$this->assertSame($before, $after['password_hash']);
		$this->assertSame('old_reset_checksum', $after['passwordreset']);
	}

	public function testAResetKeepsThePasswordWhenItIsTypedTwiceDifferently(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);

		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
		$before = $user['password_hash'];
		$user['passwordreset'] = 'reset_checksum_twice';
		$user['passwordreset_created'] = date('Y-m-d H:i:s');
		ClassRegistry::init('User')->save($user);

		$this->testAction('users/newpassword/reset_checksum_twice', [
			'data' => ['User' => ['password1' => 'brnoHorse42', 'password2' => 'brnoHorse43']],
			'method' => 'POST']);

		$after = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
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

	/**
	 * The reset form answers the same for a Google address and for an address without an
	 * account, so it can not be asked which addresses have one.
	 */
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
		$google = $this->testAction('users/resetpassword/', [
			'data' => ['User' => ['email' => 'googleuser@gmail.com']],
			'method' => 'POST', 'return' => 'contents']);
		$unknown = $this->testAction('users/resetpassword/', [
			'data' => ['User' => ['email' => 'nobodyhere@example.com']],
			'method' => 'POST', 'return' => 'contents']);

		$this->assertSame($unknown, $google);
		$this->assertStringNotContainsString('Google Sign-In', $google);
		$this->assertEmpty(TestEmailer::$lastEmail['body'] ?? null, 'Email should not be sent for Google users');
	}

	/**
	 * Test that password reset does not send email for non-existent users.
	 */
	public function testResetPasswordNoEmailForNonExistentUser(): void
	{
		new ContextPreparator(['user' => null]);

		$this->registerTestEmailer();
		$this->testAction('users/resetpassword/', ['data' => ['User' => ['email' => 'nonexistent@example.com']], 'method' => 'POST']);

		// Should NOT send email
		$this->assertEmpty(TestEmailer::$lastEmail['body'] ?? null, 'Email should not be sent for non-existent users');
	}
}

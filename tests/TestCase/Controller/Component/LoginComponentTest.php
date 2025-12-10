<?php

use Facebook\WebDriver\WebDriverBy;

// this is hack until nicer solution in newer cake is possible to be used
class TestEmailer
{
	public function __construct()
	{
		self::$lastEmail = [];
	}

	public function from($from)
	{
		self::$lastEmail['from'] = $from;
	}

	public function to($to)
	{
		self::$lastEmail['to'] = $to;
	}

	public function subject($subject)
	{
		self::$lastEmail['subject'] = $subject;
	}

	public function send($body)
	{
		self::$lastEmail['body'] = $body;
	}
	public static $lastEmail = null;
};

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

	public function testLoginWithEmail(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);
		$browser = Browser::instance();
		$browser->get('users/login');
		$browser->clickId('UserName');
		$browser->driver->getKeyboard()->sendKeys('kovarex@example.com');
		$browser->clickId('password');
		$browser->driver->getKeyboard()->sendKeys('test');
		$sumbitButton = $browser->driver->findElement(WebDriverBy::cssSelector('#UserLoginForm input[type="submit"]'));
		$sumbitButton->click();
		$this->assertSame('kovarex', $browser->driver->findElement(WebDriverBy::cssSelector(".account-bar-user-class"))->getText());
	}

	public function testLoginWithWrongPassword(): void
	{
		new ContextPreparator(['other-users' => [['name' => 'kovarex']]]);
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($user);

		$this->assertFalse(Auth::isLoggedIn());
		$this->testAction('users/login/', ['data' => ['User' => ['name' => 'kovarex', 'password' => 'testx']], 'method' => 'POST']);
		$this->assertFalse(Auth::isLoggedIn());
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
		$browser->driver->findElement(WebDriverBy::name('data[User][name]'))->sendKeys($newUsername);
		$browser->driver->findElement(WebDriverBy::name('data[User][email]'))->sendKeys($newUsername . '@email.com');
		$browser->driver->findElement(WebDriverBy::name('data[User][password1]'))->sendKeys('hello123');
		$browser->driver->findElement(WebDriverBy::name('data[User][password2]'))->sendKeys('hello123');

		// Submit the form
		$browser->driver->findElement(WebDriverBy::cssSelector('.signin input[type="submit"]'))->click();
		usleep(1000 * 100);

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
			'password1' => 'hello',
			'password2' => 'hello',
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
			'password1' => 'hello',
			'password2' => 'hello',
			'email' => $newUsername . '@email.com']], 'method' => 'POST']);
		$userCount = count(ClassRegistry::init('User')->find('all'));

		$newUser = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $newUsername]]);
		$this->assertSame($userCount, count(ClassRegistry::init('User')->find('all'))); // no user was added
	}

	public function registerTestEmailer()
	{
		$controller = $this->generate('Users', ['methods' => ['_getEmailer']]);
		$emailer = new TestEmailer();
		$controller
			->expects($this->any())
			->method('_getEmailer')
			->will($this->returnValue($emailer));
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
		$this->assertNotNull(TestEmailer::$lastEmail);
		$this->assertSame(TestEmailer::$lastEmail['to'], $user['email']);
		$this->assertTextContains('Password reset', TestEmailer::$lastEmail['subject']);
		$this->assertTextContains('https://' . $_SERVER['http_host'] . '/users/newpassword/' . $userNew['passwordreset'], TestEmailer::$lastEmail['body']);
	}

	public function testNewPassword(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'email' => 'kovarex@example.com']]]);

		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
		$resetSecret = 'reset_checksum_abc';
		$user['passwordreset'] = $resetSecret;
		ClassRegistry::init('User')->save($user);

		$browser = Browser::instance();
		$this->assertFalse(Auth::isLoggedIn());
		$browser->get('users/newpassword/' . $resetSecret);
		$browser->clickId("password");
		$newPassword = Util::generateRandomString(20);
		$browser->driver->getKeyboard()->sendKeys($newPassword);
		$sumbitButton = $browser->driver->findElement(WebDriverBy::cssSelector('#UserNewpasswordForm input[type="submit"]'));
		$sumbitButton->click();
		$this->assertSame(Util::getMyAddress() . '/users/login', $browser->getCurrentURL());
		$this->assertTextContains("Password changed", $browser->driver->getPageSource());

		$newUser = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
		$this->assertNull($newUser['passwordreset']); // password reset was cleared
		$this->assertFalse(Auth::isLoggedIn());
		$this->assertFalse(Auth::isLoggedIn());

		$this->testAction('users/login/', ['data' => ['username' => 'kovarex', 'password' => $newPassword], 'method' => 'POST']);
		$this->assertTrue(Auth::isLoggedIn());

		// changing the password to test again to not break other tests
		$newUser['password_hash'] = password_hash('test', PASSWORD_DEFAULT);
		ClassRegistry::init('User')->save($newUser);
	}

	public function testInjectingLogin(): void
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$RATING_MODE]]);
		$this->assertTrue(Auth::isInRatingMode());
		$browser = Browser::instance();
		$browser->get('sets');
		$div = $browser->driver->findElement(WebDriverBy::cssSelector('.account-bar-user-class'));
		$links = $div->findElements(WebDriverBy::tagName('a')) ?: [];
		$this->assertSame(count($links), 1);
		$this->assertTextContains($context->user['name'], $links[0]->getText());
	}
}

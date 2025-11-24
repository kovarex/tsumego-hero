<?php

use Facebook\WebDriver\WebDriverBy;

require_once(__DIR__ . '/../TestCaseWithAuth.php');

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

		$this->assertNull(CakeSession::read('loggedInUserID'));
		$this->testAction('users/login/', ['data' => ['User' => ['name' => 'kovarex', 'password' => 'test']], 'method' => 'POST']);
		$this->assertNotNull(CakeSession::read('loggedInUserID'));
	}

	public function testLoginWithWrongPassword(): void
	{
		new ContextPreparator(['other-users' => [['name' => 'kovarex']]]);
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($user);

		$this->assertNull(CakeSession::read('loggedInUserID'));
		$this->testAction('users/login/', ['data' => ['User' => ['name' => 'kovarex', 'password' => 'testx']], 'method' => 'POST']);
		$this->assertNull(CakeSession::read('loggedInUserID'));
	}

	public function testLogout(): void
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);
		$this->assertNotNull(CakeSession::read('loggedInUserID'));
		$this->testAction('users/logout/');
		$this->assertNull(CakeSession::read('loggedInUserID'));
	}

	public function testSignUp(): void
	{
		// Test that the signup form works correctly with matching passwords
		new ContextPreparator(['user' => null]);
		$userWithBiggestID = ClassRegistry::init('User')->find('first', ['order' => 'id DESC'])['User']['id'];
		$newUsername = 'testuser' . strval($userWithBiggestID + 1);
		$userCountBefore = count(ClassRegistry::init('User')->find('all'));

		$browser = Browser::instance();

		try
		{
			$browser->get('users/add');
		}
		catch (Exception $e)
		{
			if (str_contains($e->getMessage(), 'Unsecured login_uri provided'))
			{
				// Ignore this exception, CI is running without HTTPS
			}
			else
			{
				throw $e; // rethrow other exceptions
			}
		}

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

		$this->assertNull(CakeSession::read('loggedInUserID'));
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

		$this->assertNull(CakeSession::read('loggedInUserID'));
		$newPassword = Util::generateRandomString(20);

		$this->testAction('users/newpassword/' . $resetSecret, ['data' => ['User' => ['password' => $newPassword]], 'method' => 'POST']);

		$newUser = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']])['User'];
		$this->assertNull($newUser['passwordreset']); // password reset was cleared
		$this->assertNull(CakeSession::read('loggedInUserID'));
		$this->assertFalse(Auth::isLoggedIn());

		$this->testAction('users/login/', ['data' => ['User' => ['name' => 'kovarex', 'password' => $newPassword]], 'method' => 'POST']);
		$this->assertNotNull(CakeSession::read('loggedInUserID'));

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

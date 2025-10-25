<?php

App::uses('Auth', 'Utility');

class LoginComponentTest extends ControllerTestCase {
	public function testLogin(): void {
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($user);

		$this->assertNull(CakeSession::read('loggedInUserID'));
		$this->testAction('users/login/', ['data' => ['User' => ['name' => 'kovarex', 'password' => 'test']], 'method' => 'POST']);
		$this->assertNotNull(CakeSession::read('loggedInUserID'));
	}

	public function testLoginWithWrongPassword(): void {
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($user);
		CakeSession::destroy(); // so sessisions are persistent between tests, TODO: better solution than manual clear

		$this->assertNull(CakeSession::read('loggedInUserID'));
		$this->testAction('users/login/', ['data' => ['User' => ['name' => 'kovarex', 'password' => 'testx']], 'method' => 'POST']);
		$this->assertNull(CakeSession::read('loggedInUserID'));
	}

	public function testLogout(): void {
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($user);
		CakeSession::write('loggedInUserID', $user['User']['id']);

		$this->assertNotNull(CakeSession::read('loggedInUserID'));
		$this->testAction('users/logout/');
		$this->assertNull(CakeSession::read('loggedInUserID'));
	}

	public function testSignUp(): void {
		$this->assertFalse(Auth::isLoggedIn());
		$userWithBiggestID = ClassRegistry::init('User')->find('first', ['order' => 'id DESC'])['User']['id'];
		$newUsername = 'kovarex' . strval($userWithBiggestID);
		$this->testAction('users/add/', ['data' => ['User' => ['name' => $newUsername,
			'password1' => 'hello',
			'password2' => 'hello',
			'email' => $newUsername . '@email.com']], 'method' => 'POST']);
		$newUser = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $newUsername]]);
		$this->assertNotNull($newUser);
		$this->assertSame($newUser['User']['name'], $newUsername);
		$this->assertSame($newUser['User']['email'], $newUsername . "@email.com");
	}

	public function testSignUpWithDupliciteName(): void {
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

	public function testSignUpWithNameThatOnlyDiffersInCase(): void {
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

	public function testNewPassword(): void {
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($user);

		$resetSecret = 'reset_checksum_abc';
		$user['User']['passwordreset'] = $resetSecret;
		ClassRegistry::init('User')->save($user);

		$this->assertNull(CakeSession::read('loggedInUserID'));
		$newPassword = Util::generateRandomString(20);

		$this->testAction('users/newpassword/' . $resetSecret, ['data' => ['User' => ['password' => $newPassword]], 'method' => 'POST']);

		$this->assertNull(CakeSession::read('loggedInUserID'));
		$this->assertFalse(Auth::isLoggedIn());

		$this->testAction('users/login/', ['data' => ['User' => ['name' => 'kovarex', 'password' => $newPassword]], 'method' => 'POST']);
		$this->assertNotNull(CakeSession::read('loggedInUserID'));

    // changing the password to test again, to not break other tests
    $user['User']['password_hash'] = password_hash('test', PASSWORD_DEFAULT);
    ClassRegistry::init('User')->save($user);
	}
}

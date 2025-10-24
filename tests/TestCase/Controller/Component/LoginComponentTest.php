<?php

class LoginComponentTest extends ControllerTestCase {
	public function testLogin(): void {
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($user);

		$this->assertNull(CakeSession::read('loggedInUserID'));
		$this->testAction('users/login/', ['data' => ['User' => ['name' => 'kovarex', 'password' => 'test']], 'method' => 'POST']);
		$this->assertNotNull(CakeSession::read('loggedInUserID'));
	}

	public function testLogout(): void {
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($user);
		CakeSession::write('loggedInUserID', $user['User']['id']);

		$this->assertNotNull(CakeSession::read('loggedInUserID'));
		$this->testAction('users/logout/');
		$this->assertNull(CakeSession::read('loggedInUserID'));
	}
}

<?php

class LoginComponentTest extends ControllerTestCase {
	public function testLogin(): void {
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($user);

		$this->assertTrue(CakeSession::read('loggedInUserID') == null);
		$this->testAction('users/login/', ['data' => ['User' => ['name' => 'kovarex', 'password' => 'test']], 'method' => 'POST']);
		$this->assertTrue(CakeSession::read('loggedInUserID') != null);
	}
}

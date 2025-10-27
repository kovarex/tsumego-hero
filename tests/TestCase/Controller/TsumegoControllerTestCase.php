<?php

class TsumegoControllerTestCase extends ControllerTestCase {
	public function setUp(): void {
		parent::setUp();
		CakeSession::destroy();
	}

	public function login($username) {
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotNull($user);
		CakeSession::write('loggedInUserID', $user['User']['id']);
	}
}

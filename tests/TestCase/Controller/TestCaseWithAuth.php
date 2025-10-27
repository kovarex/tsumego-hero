<?php

use DOM\HTMLDocument as DOMDocument;

class TestCaseWithAuth extends ControllerTestCase {
	public function setUp(): void {
		parent::setUp();
		CakeSession::destroy();
	}

	public function login($username) {
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotNull($user);
		CakeSession::write('loggedInUserID', $user['User']['id']);
	}

	public function logout() {
		CakeSession::destroy();
	}

	public function setLoggedIn(bool $loggedIn) {
		if ($loggedIn) {
			$this->login('kovarex');
		} else {
			$this->logout();
		}
	}

	public function testLogin() {
		$this->assertFalse(CakeSession::check('loggedInUserID'));
		$this->login('kovarex');
		$this->assertTrue(CakeSession::check('loggedInUserID'));
	}

	public function getStringDom() {
		$dom = DOMDocument::createFromString($this->view, LIBXML_HTML_NOIMPLIED);
		return $dom;
	}
}

<?php

use Facebook\WebDriver\WebDriverBy;

require_once(__DIR__ . '/../../ContextPreparator.php');

class UesrsControllerTest extends ControllerTestCase {
	public function testUserView() {
		$context = new ContextPreparator(['user' => ['name' => 'kovarex']]);
		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);
		$this->assertTextContains('kovarex', $browser->driver->getPageSource());
	}
}

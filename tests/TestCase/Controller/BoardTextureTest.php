<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../Browser.php');
require_once(__DIR__ . '/../../ContextPreparator.php');
use Facebook\WebDriver\WebDriverBy;

class BoardTextureTest extends TestCaseWithAuth
{
	public function testBoardTexturePersistence()
	{
		$context = new ContextPreparator(); // defaults to user kovarex like other tests
		$browser = Browser::instance();
		$this->login('kovarex');

		// Go to profile or settings page where boards are selected
		// Based on AppController logic, board selection seems to happen via JS that sets a cookie,
		// but the UI for it is likely in the main layout or a specific settings page.
		// Looking at default.ctp, there are checkboxes with id "newCheckX".

		// Let's go to the home page which uses default.ctp
		$browser->get('/');

		// Find the "Board Settings" or similar dropdown that contains the checkboxes.
		// In default.ctp, there is a loop creating #newCheck1 to #newCheck51

		// Check board 9 (newCheck9). It is '1' (unchecked) by default.
		$checkbox9 = $browser->driver->findElement(WebDriverBy::id('newCheck9'));
		$this->assertFalse($checkbox9->isSelected(), "Board 9 should be unchecked by default.");

		$browser->driver->executeScript("arguments[0].click();", [$checkbox9]);
		usleep(1000 * 100);

		$browser->get('/');

		$checkbox9 = $browser->driver->findElement(WebDriverBy::id('newCheck9'));
		$this->assertTrue($checkbox9->isSelected(), "Board 9 should be selected after click and reload.");

		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$bitmask = (int) $user['User']['boards_bitmask'];

		// Board 9 is index 8. 1 << 8 = 256.
		$this->assertTrue(($bitmask & 256) > 0, "Bitmask should have the 9th bit set.");
	}
}

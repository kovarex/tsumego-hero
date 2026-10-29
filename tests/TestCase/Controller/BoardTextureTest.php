<?php

use App\Model\Table\UserTable;

class BoardTextureTest extends IntegrationTestCase
{
	public function testBoardTexturePersistence()
	{
		$context = new ContextPreparator(); // defaults to user kovarex like other tests
		$browser = Browser::instance();
		$this->login('kovarex');

		// Go to profile or settings page where boards are selected
		// Based on AppController logic, board selection seems to happen via JS that sets a cookie,
		// but the UI for it is likely in the main layout or a specific settings page.
		// Looking at templates/layout/default.php, there are checkboxes with id "newCheckX".

		// Any page with the default layout has the board selector
		$browser->get('sites/blank');

		// Find the "Board Settings" or similar dropdown that contains the checkboxes.
		// In templates/layout/default.php, there is a loop creating #newCheck1 to #newCheck51

		// Check board 9 (newCheck9). It is '1' (unchecked) by default.
		$checkbox9 = $browser->findById('newCheck9');
		$this->assertFalse($checkbox9->isSelected(), "Board 9 should be unchecked by default.");

		$browser->driver->executeScript("arguments[0].click();", [$checkbox9]);
		// Wait for AJAX to save the board texture preference
		$browser->waitUntilJs('document.getElementById("newCheck9").checked');

		$browser->get('sites/blank');

		$checkbox9 = $browser->findById('newCheck9');
		$this->assertTrue($checkbox9->isSelected(), "Board 9 should be selected after click and reload.");

		$user = UserTable::row((int) $context->user['id']);
		$bitmask = (int) $user['boards_bitmask'];

		// Board 9 is index 8. 1 << 8 = 256.
		$this->assertTrue(($bitmask & 256) > 0, "Bitmask should have the 9th bit set.");
	}
}

<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverWait;

class ProfilePreferencesTest extends ControllerTestCase
{
	public function testPlayerColorPreferenceViaDropdown()
	{
		$context = new ContextPreparator(['user' => ['name' => 'coloruser']]);
		$browser = Browser::instance();

		$browser->get('users/view/' . $context->user['id']);

		// Select "Always Black" using WebDriverSelect
		$select = new WebDriverSelect(
			$browser->driver->findElement(WebDriverBy::cssSelector('select'))
		);
		$select->selectByValue('1');

		// Wait for the fire-and-forget fetch to persist
		$wait = new WebDriverWait($browser->driver, 5);
		$wait->until(fn($d) => (int) ClassRegistry::init('User')->findById($context->user['id'])['User']['default_player_color'] === 1);

		$this->assertSame(1, (int) ClassRegistry::init('User')->findById($context->user['id'])['User']['default_player_color']);
	}

	public function testLevelBarPreferenceViaRadio()
	{
		$context = new ContextPreparator(['user' => ['name' => 'baruser']]);
		$browser = Browser::instance();

		$browser->get('users/view/' . $context->user['id']);

		// Click "Show rating" radio
		$browser->driver->findElement(WebDriverBy::id('levelBarDisplay2'))->click();

		// Wait for the radio to become selected
		$wait = new WebDriverWait($browser->driver, 5);
		$wait->until(fn($d) => $d->findElement(WebDriverBy::id('levelBarDisplay2'))->isSelected());

		// Reload and verify the preference persisted
		$browser->get('users/view/' . $context->user['id']);
		$this->assertTrue(
			$browser->driver->findElement(WebDriverBy::id('levelBarDisplay2'))->isSelected()
		);
	}
}

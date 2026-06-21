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

		// The onchange fires a fire-and-forget fetch() with no DOM change,
		// so we wait for the server to persist the update.
		sleep(1);

		// Reload and verify the preference persisted
		$browser->get('users/view/' . $context->user['id']);
		$select = new WebDriverSelect(
			$browser->driver->findElement(WebDriverBy::cssSelector('select'))
		);
		$this->assertSame('1', $select->getFirstSelectedOption()->getAttribute('value'));
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

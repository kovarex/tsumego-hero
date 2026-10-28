<?php

use App\Utility\Util;

//The layout every page has (src/View/Layouts/default.ctp)
/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class SiteLayoutTest extends IntegrationTestCase
{
	public function testScriptsComeFromOurOwnHost()
	{
		$browser = Browser::instance();
		new ContextPreparator();
		$browser->get('sites/index');

		$scriptHosts = $browser->driver->executeScript('return Array.from(document.scripts).filter(script => script.src).map(script => new URL(script.src).host);');
		$this->assertNotEmpty($scriptHosts);
		$this->assertSame([parse_url(Util::getMyAddress(), PHP_URL_HOST) . ':' . parse_url(Util::getMyAddress(), PHP_URL_PORT)], array_values(array_unique($scriptHosts)));
		$this->assertSame('2.1.3', $browser->driver->executeScript('return jQuery.fn.jquery;'));
	}

	public function testGuestHearsSoundUntilTurningItOff()
	{
		$browser = Browser::instance();
		new ContextPreparator(['user' => null]);
		$browser->driver->manage()->deleteAllCookies();
		$browser->getAnonymous('sites/index');
		$this->assertTrue($browser->driver->executeScript('return soundsEnabled;'));
		$this->assertStringEndsWith('/img/sound-icon1.png', $browser->find('#soundButtonImage')->getAttribute('src'));

		$browser->clickId('soundButton');
		$this->assertFalse($browser->driver->executeScript('return soundsEnabled;'));

		$browser->getAnonymous('sites/index');
		$this->assertFalse($browser->driver->executeScript('return soundsEnabled;'));
		$this->assertStringEndsWith('/img/sound-icon2.png', $browser->find('#soundButtonImage')->getAttribute('src'));
	}

	public function testPlayerSoundSettingIsUsed()
	{
		$browser = Browser::instance();
		new ContextPreparator(['user' => ['sound' => 0]]);
		$browser->get('sites/index');
		$this->assertFalse($browser->driver->executeScript('return soundsEnabled;'));
		$this->assertStringEndsWith('/img/sound-icon2.png', $browser->find('#soundButtonImage')->getAttribute('src'));
	}

	public function testBoardPickerOpensAndCloses()
	{
		$browser = Browser::instance();
		new ContextPreparator(['user' => ['sound' => 1]]);
		$browser->get('sites/index');
		$this->assertFalse($browser->find('#dropdowntable')->isDisplayed());

		$browser->clickId('boardsInMenu');
		$browser->waitUntilCssSelectorDisplayed('#dropdowntable');
		$this->assertTrue($browser->find('#dropdowntable2')->isDisplayed());

		//The overlay covers the rest of the page, a click there closes the picker
		$browser->driver->executeScript("document.querySelector('.board-picker .dropdown__overlay').click();");
		$browser->waitUntilCssSelectorNotDisplayed('#dropdowntable');
	}
}

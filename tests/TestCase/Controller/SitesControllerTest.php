<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../Browser.php');
require_once(__DIR__ . '/../../ContextPreparator.php');
use Facebook\WebDriver\WebDriverBy;

class SitesControllerTest extends ControllerTestCase
{
	public function testIndex()
	{
		// we init DayRecord, so the main page has something to show:
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'daily_xp' => 5, 'daily_solved' => 1],
			'other-users' => [['name' => 'Ivan Detkov', 'daily_xp' => 10, 'daily_solved' => 2]]]);
		$this->testAction('/cron/daily/' . CRON_SECRET);
		$browser = Browser::instance();
		$browser->get('/');
		$titles = $browser->driver->findElements(WebDriverBy::cssSelector('.title4'));
		$this->assertTrue(count($titles) > 3);
	}
}

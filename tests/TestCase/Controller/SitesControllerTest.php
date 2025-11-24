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
		try
		{
			$browser->get('/');
		}
		catch (Exception $e)
		{
		} // ignoring js errors on the main page for now
		$titles = $browser->driver->findElements(WebDriverBy::cssSelector('.title4'));
		$this->assertTrue(count($titles) > 3);
	}

	public function testShowPublishedTsumego()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 564]]]]]);

		ClassRegistry::init('Schedule')->create();
		$schedule = [];
		$schedule['tsumego_id'] = $context->otherTsumegos[0]['id'];
		$schedule['set_id'] = $context->otherTsumegos[0]['set-connections'][0]['set_id'];
		$schedule['date'] = date('Y-m-d');
		$schedule['published'] = 1;
		ClassRegistry::init('Schedule')->save($schedule);

		$browser = Browser::instance();
		try
		{
			$browser->get('/');
		}
		catch (Exception $e)
		{}
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('.setViewButtons1'));
		$this->assertSame(count($buttons), 1);
		$this->assertSame($buttons[0]->getText(), "564");

	}
}

<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../Browser.php');
require_once(__DIR__ . '/../../ContextPreparator.php');
use Facebook\WebDriver\WebDriverBy;

App::uses('Constants', 'Utility');

class ClientTimeDisplayTest extends TestCaseWithAuth
{
	public function testSolveHistoryDisplaysTimeInBrowserTimezone(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Time Test Set', 'num' => '1']]],
		]);
		$this->login($context->user['User']['name']);

		// Record an attempt so solve history has data
		ClassRegistry::init('TsumegoAttempt')->create();
		ClassRegistry::init('TsumegoAttempt')->save([
			'TsumegoAttempt' => [
				'user_id' => $context->user['id'],
				'tsumego_id' => $context->tsumegos[0]['id'],
				'solved' => 1,
				'misplays' => 0,
				'mode' => Constants::$LEVEL_MODE,
				'user_rating' => 1000,
				'gain' => 0,
				'seconds' => 10,
				'created' => '2026-08-15 12:00:00',
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/solveHistory/' . $context->user['id']);

		$timeElements = $browser->driver->findElements(WebDriverBy::tagName('time'));
		$this->assertGreaterThan(0, count($timeElements));

		$time = $timeElements[0];

		// The server renders the machine-readable datetime in UTC.
		$this->assertSame('2026-08-15T12:00:00+00:00', $time->getAttribute('datetime'));

		// The browser (Europe/Prague, UTC+2) shifts 12:00 UTC to 14:00 local.
		$localHour = (int) $browser->driver->executeScript(
			"return new Date(document.querySelector('time[datetime]').getAttribute('datetime')).getHours();"
		);
		$this->assertSame(14, $localHour, '12:00 UTC must render as hour 14 in the Europe/Prague browser');

		// The DOM text is the converted local-time rendering, not the raw UTC string.
		$displayed = $time->getText();
		$this->assertNotEmpty($displayed);
		$this->assertStringNotContainsString('2026-08-15 12:00:00', $displayed,
			'Displayed time should be converted from UTC to local timezone');
	}
}

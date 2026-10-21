<?php

use App\Utility\Constants;
use Facebook\WebDriver\WebDriverBy;

class ClientTimeDisplayTest extends TestCaseWithAuth
{
	public function testSolveHistoryDisplaysTimeInBrowserTimezone(): void
	{
		$context = $this->prepareSolveHistory(User::DATE_FORMAT_ISO);

		$browser = Browser::instance();
		$browser->get('users/solveHistory/' . $context->user['id']);

		$timeElements = $browser->getAllBy(WebDriverBy::tagName('time'));
		$this->assertGreaterThan(0, count($timeElements));

		$time = $timeElements[0];

		// The server renders the machine-readable datetime in UTC.
		$this->assertSame('2026-08-15T12:00:00+00:00', $time->getAttribute('datetime'));

		// The browser (Europe/Prague, UTC+2) shifts 12:00 UTC to 14:00 local.
		$localHour = (int) $browser->driver->executeScript(
			"return new Date(document.querySelector('time[datetime]').getAttribute('datetime')).getHours();"
		);
		$this->assertSame(14, $localHour, '12:00 UTC must render as hour 14 in the Europe/Prague browser');

		// The DOM text is the local time in the site's ISO format, whatever the browser locale.
		$this->assertSame('2026-08-15 14:00', $time->getText());
	}

	public function testDatesAreWrittenInTheFormatThePlayerPicked(): void
	{
		foreach ([
			User::DATE_FORMAT_DAY_MONTH_YEAR => '15. 8. 2026 14:00',
			User::DATE_FORMAT_MONTH_DAY_YEAR => '08/15/2026 2:00 PM'] as $dateFormat => $expected)
		{
			$context = $this->prepareSolveHistory($dateFormat);

			$browser = Browser::instance();
			$browser->get('users/solveHistory/' . $context->user['id']);

			$this->assertSame($expected, $browser->find('time[datetime]')->getText());
		}
	}

	/** A player with one attempt at 12:00 UTC, so the solve history shows one date. */
	private function prepareSolveHistory(int $dateFormat): ContextPreparator
	{
		$context = new ContextPreparator([
			'user' => ['pref_date_format' => $dateFormat],
			'tsumego' => ['sets' => [['name' => 'Time Test Set', 'num' => '1']]],
		]);

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
		return $context;
	}
}

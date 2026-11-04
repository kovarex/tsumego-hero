<?php

use App\Utility\DailyStreak;
use App\Utility\Util;

//The flame of the daily streak in the menu
#[RetryOnBrowserFailure]
class DailyStreakBrowserTest extends IntegrationTestCase
{
	public function testTheFlameLightsUpAfterASolveThatGivesXp(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'status' => 'V']]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$browser->get('sites/blank');

		//The streak counts in the zone of the browser: the first page put it in a cookie, the second stored it.
		//Yesterday there counted.
		$timeZone = $browser->driver->executeScript('return Intl.DateTimeFormat().resolvedOptions().timeZone;');
		$this->assertSame($timeZone, $context->reloadUser()['time_zone']);
		$yesterday = new DateTimeImmutable(DailyStreak::today($timeZone, new DateTimeImmutable()))->modify('-1 day')->format('Y-m-d');
		Util::execute('UPDATE user SET streak_length = 2, streak_best = 5, streak_last_day = ? WHERE id = ?', [$yesterday, $context->user['id']]);

		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$flame = $browser->find('#daily-streak');
		$this->assertTrue($flame->isDisplayed());
		$this->assertStringNotContainsString('daily-streak--done', $flame->getAttribute('class'));
		$this->assertSame('2', $browser->find('#daily-streak .daily-streak__length')->getText());

		$browser->playWithResult('S');
		$browser->waitUntilCssSelectorExistsWithText('#daily-streak .daily-streak__length', '3');
		$this->assertStringContainsString('daily-streak--done', $browser->find('#daily-streak')->getAttribute('class'));

		//The details show on hover, and the next page draws the flame lit from the stored streak
		$browser->get('sites/blank');
		$this->assertStringContainsString('daily-streak--done', $browser->find('#daily-streak')->getAttribute('class'));
		$browser->hover($browser->find('#daily-streak'));
		$details = $browser->find('#daily-streak-details');
		$this->assertTrue($details->isDisplayed());
		$this->assertStringContainsString('Daily streak: 3 days', $details->getText());
		$this->assertStringContainsString('Best streak: 5 days', $details->getText());
		$this->assertStringContainsString('Today is done.', $details->getText());
	}

	public function testAGuestHasNoFlame(): void
	{
		new ContextPreparator(['user' => null]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$this->assertFalse($browser->idExists('daily-streak'));
	}
}

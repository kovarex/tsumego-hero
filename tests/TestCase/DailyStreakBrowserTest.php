<?php

use App\Utility\DailyStreak;
use App\Utility\Util;

//The flame of the daily streak in the menu
#[RetryOnBrowserFailure]
class DailyStreakBrowserTest extends IntegrationTestCase
{
	//The solve that completes the day lights the flame with a burst
	public function testTheFlameLightsUpWhenASolveCompletesTheDay(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'status' => 'V']]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$browser->get('sites/blank');

		//The streak counts in the zone of the browser: the first page put it in a cookie, the second stored it.
		//Yesterday there counted, and today lacks one XP.
		$timeZone = $browser->driver->executeScript('return Intl.DateTimeFormat().resolvedOptions().timeZone;');
		$this->assertSame($timeZone, $context->reloadUser()['time_zone']);
		$today = DailyStreak::today($timeZone, new DateTimeImmutable());
		$yesterday = new DateTimeImmutable($today)->modify('-1 day')->format('Y-m-d');
		$required = DailyStreak::xpRequired((float) $context->reloadUser()['rating']);
		Util::execute('UPDATE user SET streak_length = 2, streak_best = 5, streak_last_day = ?, streak_day = ?, streak_day_xp = ?, streak_day_reviews_required = 0 WHERE id = ?',
			[$yesterday, $today, $required - 1, $context->user['id']]);

		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$flame = $browser->find('#daily-streak');
		$this->assertTrue($flame->isDisplayed());
		$this->assertStringNotContainsString('daily-streak--done', $flame->getAttribute('class'));
		$this->assertSame('2', $browser->find('#daily-streak .daily-streak__length')->getText());

		//What today still asks shows until the solve completes the day, and goes with the burst
		$browser->hover($browser->find('#daily-streak'));
		$this->assertTrue($browser->find('#daily-streak .daily-streak__progress-of-day')->isDisplayed());
		$browser->playWithResult('S');
		$browser->waitUntilCssSelectorExistsWithText('#daily-streak .daily-streak__length', '3');
		$browser->hover($browser->find('#daily-streak'));
		$this->assertFalse($browser->find('#daily-streak .daily-streak__progress-of-day')->isDisplayed());
		$class = $browser->find('#daily-streak')->getAttribute('class');
		$this->assertStringContainsString('daily-streak--done', $class);
		$this->assertStringContainsString('daily-streak--ignite', $class);
		$this->assertSame('daily-streak-ignite', $browser->driver->executeScript("return getComputedStyle(document.querySelector('#daily-streak .daily-streak__flame')).animationName;"));

		//The details show on hover, and the next page draws the flame lit from the stored streak, without the burst
		$browser->get('sites/blank');
		$class = $browser->find('#daily-streak')->getAttribute('class');
		$this->assertStringContainsString('daily-streak--done', $class);
		$this->assertStringNotContainsString('daily-streak--ignite', $class);
		$browser->hover($browser->find('#daily-streak'));
		$details = $browser->find('#daily-streak-details');
		$this->assertTrue($details->isDisplayed());
		$this->assertStringContainsString('Streak: 3 days, best 5 days', $details->getText());
		$this->assertStringContainsString('Today is done.', $details->getText());
		$this->assertStringNotContainsString('XP:', $details->getText(), 'A done day shows no progress');
		$this->assertStringNotContainsString('Mistake training reviews:', $details->getText());
		$this->assertFalse($browser->find('#daily-streak .daily-streak__progress')->isDisplayed());
	}

	//A solve that is not enough for the day fills the bar of the XP part of the way
	public function testTheDetailsShowTheProgressOfTheDay(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 2500], 'tsumego' => ['set_order' => 1, 'status' => 'V', 'rating' => 1000]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertSame('0', $browser->find('#daily-streak .daily-streak__progress')->getAttribute('value'));

		$browser->playWithResult('S');
		$user = $context->reloadUser();
		$xp = (int) $user['streak_day_xp'];
		$required = DailyStreak::xpRequired((float) $user['rating']);
		$this->assertGreaterThan(0, $xp);
		$browser->hover($browser->find('#daily-streak'));
		$browser->waitUntilCssSelectorExistsWithText('#daily-streak [data-streak-count="xp"]', (string) $xp);
		$details = $browser->find('#daily-streak-details')->getText();
		$this->assertStringContainsString('XP: ' . $xp . ' of ' . $required, $details);
		$this->assertStringContainsString('Mistake training reviews: 0 of 0', $details);
		$this->assertStringContainsString('To keep the streak today:', $details);
		$progress = $browser->find('#daily-streak .daily-streak__progress');
		$this->assertSame([(string) $xp, (string) $required], [$progress->getAttribute('value'), $progress->getAttribute('max')]);
		$this->assertStringNotContainsString('daily-streak--done', $browser->find('#daily-streak')->getAttribute('class'));
	}

	public function testAGuestHasNoFlame(): void
	{
		new ContextPreparator(['user' => null]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$this->assertFalse($browser->idExists('daily-streak'));
	}
}

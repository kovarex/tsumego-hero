<?php

use App\Utility\DailyStreak;
use App\Utility\Util;

class DailyStreakTest extends AppTestCase
{
	private static function at(string $time, string $timeZone = 'UTC'): DateTimeImmutable
	{
		return new DateTimeImmutable($time, new DateTimeZone($timeZone));
	}

	public function testTheDayEndsAtThreeInTheMorningLocalTime(): void
	{
		$this->assertSame('2026-11-03', DailyStreak::today('Europe/Prague', self::at('2026-11-04 02:59:59', 'Europe/Prague')));
		$this->assertSame('2026-11-04', DailyStreak::today('Europe/Prague', self::at('2026-11-04 03:00:00', 'Europe/Prague')));
		$this->assertSame('2026-11-04', DailyStreak::today('Europe/Prague', self::at('2026-11-04 23:59:59', 'Europe/Prague')));
	}

	public function testTheDayIsTheOneOfThePlayersTimeZone(): void
	{
		$now = self::at('2026-11-04 12:00:00', 'UTC');
		$this->assertSame('2026-11-04', DailyStreak::today('UTC', $now));
		$this->assertSame('2026-11-04', DailyStreak::today('Asia/Tokyo', $now)); //21:00 there
		$this->assertSame('2026-11-04', DailyStreak::today('America/Los_Angeles', $now)); //04:00 there
		$this->assertSame('2026-11-03', DailyStreak::today('America/Los_Angeles', self::at('2026-11-04 10:30:00', 'UTC'))); //02:30 there
		$this->assertSame('2026-11-05', DailyStreak::today('Pacific/Kiritimati', self::at('2026-11-04 14:00:00', 'UTC'))); //04:00 on the 5th there
	}

	public function testAnUnknownTimeZoneIsUtc(): void
	{
		$now = self::at('2026-11-04 02:00:00', 'UTC');
		$this->assertSame('2026-11-03', DailyStreak::today(null, $now));
		$this->assertSame('2026-11-03', DailyStreak::today('Mars/Olympus_Mons', $now));
		$this->assertSame('2026-11-03', DailyStreak::today('+05:00', $now));
		$this->assertFalse(DailyStreak::isValidTimeZone('CET'));
		$this->assertFalse(DailyStreak::isValidTimeZone('CET+1'));
		$this->assertTrue(DailyStreak::isValidTimeZone('Europe/Prague'));
		$this->assertTrue(DailyStreak::isValidTimeZone('UTC'));
		$this->assertFalse(DailyStreak::isValidTimeZone(''));
	}

	//On the night the clocks move, the day still turns at 3:00 on the wall clock
	public function testTheDayTurnsAtThreeOnTheWallClockAcrossDaylightSavingChanges(): void
	{
		//Spring forward in Prague: 2026-03-29, 02:00 CET becomes 03:00 CEST (01:00 UTC)
		$this->assertSame('2026-03-28', DailyStreak::today('Europe/Prague', self::at('2026-03-29 00:59:59', 'UTC')));
		$this->assertSame('2026-03-29', DailyStreak::today('Europe/Prague', self::at('2026-03-29 01:00:00', 'UTC')));
		//Fall back: 2026-10-25, 03:00 CEST becomes 02:00 CET (01:00 UTC), so 02:00-03:00 happens twice and both are the day before
		$this->assertSame('2026-10-24', DailyStreak::today('Europe/Prague', self::at('2026-10-25 00:30:00', 'UTC'))); //02:30 CEST
		$this->assertSame('2026-10-24', DailyStreak::today('Europe/Prague', self::at('2026-10-25 01:30:00', 'UTC'))); //02:30 CET
		$this->assertSame('2026-10-25', DailyStreak::today('Europe/Prague', self::at('2026-10-25 02:00:00', 'UTC'))); //03:00 CET
	}

	public function testANewPlayerHasNoStreak(): void
	{
		$streak = DailyStreak::evaluate(0, 0, 0, null, '2026-11-04');
		$this->assertSame(['length' => 0, 'best' => 0, 'freezes' => 0, 'done_today' => false], $streak->toArray());
	}

	public function testTheStreakHoldsWhileTodayIsStillToDo(): void
	{
		$this->assertSame(['length' => 5, 'best' => 9, 'freezes' => 1, 'done_today' => true], DailyStreak::evaluate(5, 9, 1, '2026-11-04', '2026-11-04')->toArray());
		$this->assertSame(['length' => 5, 'best' => 9, 'freezes' => 1, 'done_today' => false], DailyStreak::evaluate(5, 9, 1, '2026-11-03', '2026-11-04')->toArray());
	}

	public function testAMissedDayWithoutAFreezeEndsTheStreak(): void
	{
		$streak = DailyStreak::evaluate(5, 9, 0, '2026-11-02', '2026-11-04');
		$this->assertSame(['length' => 0, 'best' => 9, 'freezes' => 0, 'done_today' => false], $streak->toArray());
	}

	public function testAMissedDayUsesAFreezeAndKeepsTheStreak(): void
	{
		$streak = DailyStreak::evaluate(8, 8, 2, '2026-11-02', '2026-11-04');
		$this->assertSame(['length' => 8, 'best' => 8, 'freezes' => 1, 'done_today' => false], $streak->toArray());
		$this->assertSame('2026-11-03', $streak->lastDay);

		$streak = DailyStreak::evaluate(8, 8, 2, '2026-11-01', '2026-11-04');
		$this->assertSame(['length' => 8, 'best' => 8, 'freezes' => 0, 'done_today' => false], $streak->toArray());
	}

	//A frozen day keeps the streak but doesn't add to it
	public function testASolveAfterAFrozenDayAddsOnlyToday(): void
	{
		$streak = DailyStreak::evaluate(8, 8, 2, '2026-11-02', '2026-11-04')->afterSolve('2026-11-04');
		$this->assertSame(['length' => 9, 'best' => 9, 'freezes' => 1, 'done_today' => true], $streak->toArray());
	}

	public function testMoreMissedDaysThanFreezesEndTheStreakAndTakeTheFreezes(): void
	{
		$streak = DailyStreak::evaluate(20, 20, 2, '2026-11-01', '2026-11-05');
		$this->assertSame(['length' => 0, 'best' => 20, 'freezes' => 0, 'done_today' => false], $streak->toArray());
		$this->assertSame(['length' => 1, 'best' => 20, 'freezes' => 0, 'done_today' => true], $streak->afterSolve('2026-11-05')->toArray());
	}

	public function testReturningAfterALongBreakStartsAgain(): void
	{
		$streak = DailyStreak::evaluate(30, 45, 2, '2026-06-01', '2026-11-04');
		$this->assertSame(['length' => 0, 'best' => 45, 'freezes' => 0, 'done_today' => false], $streak->toArray());
		$this->assertSame(['length' => 1, 'best' => 45, 'freezes' => 0, 'done_today' => true], $streak->afterSolve('2026-11-04')->toArray());
	}

	public function testOnlyTheFirstSolveOfTheDayCounts(): void
	{
		$streak = DailyStreak::evaluate(3, 3, 0, '2026-11-03', '2026-11-04')->afterSolve('2026-11-04');
		$this->assertSame(4, $streak->length);
		$this->assertSame(4, $streak->afterSolve('2026-11-04')->length);
	}

	public function testAFreezeIsEarnedEverySevenDaysUpToTwo(): void
	{
		$streak = DailyStreak::evaluate(0, 0, 0, null, '2026-11-01');
		$day = new DateTimeImmutable('2026-11-01');
		$freezesByLength = [];
		for ($i = 0; $i < 28; $i++)
		{
			$today = $day->modify('+' . $i . ' days')->format('Y-m-d');
			$streak = DailyStreak::evaluate($streak->length, $streak->best, $streak->freezes, $streak->lastDay, $today)->afterSolve($today);
			$freezesByLength[$streak->length] = $streak->freezes;
		}
		$this->assertSame(0, $freezesByLength[6]);
		$this->assertSame(1, $freezesByLength[7]);
		$this->assertSame(1, $freezesByLength[13]);
		$this->assertSame(2, $freezesByLength[14]);
		$this->assertSame(2, $freezesByLength[21], 'Two freezes at most');
		$this->assertSame(2, $freezesByLength[28]);
		$this->assertSame(28, $streak->best);
	}

	//Moving west can make the last counted day lie ahead of today there: it is done, not missed
	public function testADayAheadOfTodayIsDone(): void
	{
		$this->assertTrue(DailyStreak::evaluate(4, 4, 0, '2026-11-05', '2026-11-04')->doneToday);
	}

	public function testTheDateColumnIsReadInAnyForm(): void
	{
		$now = self::at('2026-11-04 12:00:00');
		$user = ['streak_length' => 2, 'streak_best' => 2, 'streak_freezes' => 0, 'time_zone' => null];
		foreach (['2026-11-04', '2026-11-04 00:00:00', new \Cake\I18n\Date('2026-11-04')] as $lastDay)
			$this->assertTrue(DailyStreak::ofUser($user + ['streak_last_day' => $lastDay], $now)->doneToday);
		$this->assertFalse(DailyStreak::ofUser($user + ['streak_last_day' => null], $now)->doneToday);
	}

	public function testCountingASolveStoresTheStreakOnlyOncePerDay(): void
	{
		$context = new ContextPreparator(['user' => ['streak_length' => 6, 'streak_best' => 6, 'streak_last_day' => '2026-11-03', 'time_zone' => 'Europe/Prague']]);
		$user = $context->user;
		DailyStreak::countSolve($user, self::at('2026-11-04 10:00:00', 'Europe/Prague'));
		$this->assertSame(['streak_length' => 7, 'streak_best' => 7, 'streak_freezes' => 1, 'streak_last_day' => '2026-11-04'], $this->stored($user['id']));
		$this->assertSame(7, $user['streak_length']);

		//The second solve of the day, before 3 AM of the next one
		DailyStreak::countSolve($user, self::at('2026-11-05 02:00:00', 'Europe/Prague'));
		$this->assertSame(7, $this->stored($user['id'])['streak_length']);

		DailyStreak::countSolve($user, self::at('2026-11-05 03:00:00', 'Europe/Prague'));
		$this->assertSame(['streak_length' => 8, 'streak_best' => 8, 'streak_freezes' => 1, 'streak_last_day' => '2026-11-05'], $this->stored($user['id']));
	}

	/** @return array<string, mixed> */
	private function stored(int $userId): array
	{
		$row = Util::query('SELECT streak_length, streak_best, streak_freezes, streak_last_day FROM user WHERE id = ?', [$userId])[0];
		return ['streak_length' => (int) $row['streak_length'], 'streak_best' => (int) $row['streak_best'], 'streak_freezes' => (int) $row['streak_freezes'], 'streak_last_day' => $row['streak_last_day']];
	}
}

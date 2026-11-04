<?php

use App\Utility\DailyStreak;

class DailyStreakTest extends AppTestCase
{
	private static function at(string $time, string $timeZone = 'UTC'): DateTimeImmutable
	{
		return new DateTimeImmutable($time, new DateTimeZone($timeZone));
	}

	//The normal streak stored as of $lastDay, as of $today
	private static function evaluate(int $length, int $best, int $freezes, ?string $lastDay, string $today): DailyStreak
	{
		return new DailyStreak($length, $best, 0, 0, $freezes, $lastDay, false)->asOf($today);
	}

	/** @return array{length: int, best: int, freezes: int, done_today: bool} */
	private static function summary(DailyStreak $streak): array
	{
		return ['length' => $streak->length, 'best' => $streak->best, 'freezes' => $streak->freezes, 'done_today' => $streak->doneToday];
	}

	//A result for the player at $now, with the fields it changes applied to the row, as the component writes them
	private static function solve(array &$user, string $now, int $xp, bool $solvedReview = false, int $reviewsDue = 0): void
	{
		$user = DailyStreak::afterResult($user, $xp, $solvedReview, fn() => $reviewsDue, self::at($now, 'Europe/Prague')) + $user;
	}

	//A player in Prague of rating 1500, whose problem gives 45 XP, so a golden day asks 135
	private static function player(array $fields = []): array
	{
		return $fields + ['id' => 1, 'rating' => 1500, 'time_zone' => 'Europe/Prague', 'streak_mode_setting_id' => DailyStreak::MODE_NORMAL,
			'streak_length' => 0, 'streak_best' => 0, 'golden_streak_length' => 0, 'golden_streak_best' => 0, 'streak_freezes' => 0, 'streak_last_day' => null,
			'streak_day' => null, 'streak_day_xp' => 0, 'streak_day_reviews' => 0, 'streak_day_reviews_required' => null];
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

	public function testOnlyAValidNewTimeZoneIsAChange(): void
	{
		$user = ['time_zone' => 'Europe/Prague'];
		$this->assertSame(['time_zone' => 'Asia/Tokyo'], DailyStreak::timeZoneChange('Asia/Tokyo', $user));
		$this->assertSame([], DailyStreak::timeZoneChange('Europe/Prague', $user));
		$this->assertSame([], DailyStreak::timeZoneChange('Nowhere/Land', $user));
		$this->assertSame([], DailyStreak::timeZoneChange(null, $user));
		$this->assertSame(['time_zone' => 'UTC'], DailyStreak::timeZoneChange('UTC', ['time_zone' => null]));
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
		$streak = self::evaluate(0, 0, 0, null, '2026-11-04');
		$this->assertSame(['length' => 0, 'best' => 0, 'freezes' => 0, 'done_today' => false], self::summary($streak));
	}

	public function testTheStreakHoldsWhileTodayIsStillToDo(): void
	{
		$this->assertSame(['length' => 5, 'best' => 9, 'freezes' => 1, 'done_today' => true], self::summary(self::evaluate(5, 9, 1, '2026-11-04', '2026-11-04')));
		$this->assertSame(['length' => 5, 'best' => 9, 'freezes' => 1, 'done_today' => false], self::summary(self::evaluate(5, 9, 1, '2026-11-03', '2026-11-04')));
	}

	public function testAMissedDayWithoutAFreezeEndsTheStreak(): void
	{
		$streak = self::evaluate(5, 9, 0, '2026-11-02', '2026-11-04');
		$this->assertSame(['length' => 0, 'best' => 9, 'freezes' => 0, 'done_today' => false], self::summary($streak));
	}

	public function testAMissedDayUsesAFreezeAndKeepsTheStreak(): void
	{
		$streak = self::evaluate(8, 8, 2, '2026-11-02', '2026-11-04');
		$this->assertSame(['length' => 8, 'best' => 8, 'freezes' => 1, 'done_today' => false], self::summary($streak));
		$this->assertSame('2026-11-03', $streak->lastDay);

		$streak = self::evaluate(8, 8, 2, '2026-11-01', '2026-11-04');
		$this->assertSame(['length' => 8, 'best' => 8, 'freezes' => 0, 'done_today' => false], self::summary($streak));
	}

	//A frozen day keeps the streak but doesn't add to it
	public function testASolveAfterAFrozenDayAddsOnlyToday(): void
	{
		$streak = self::evaluate(8, 8, 2, '2026-11-02', '2026-11-04')->countDay(DailyStreak::MODE_NORMAL, '2026-11-04');
		$this->assertSame(['length' => 9, 'best' => 9, 'freezes' => 1, 'done_today' => true], self::summary($streak));
	}

	public function testMoreMissedDaysThanFreezesEndTheStreakAndTakeTheFreezes(): void
	{
		$streak = self::evaluate(20, 20, 2, '2026-11-01', '2026-11-05');
		$this->assertSame(['length' => 0, 'best' => 20, 'freezes' => 0, 'done_today' => false], self::summary($streak));
		$this->assertSame(['length' => 1, 'best' => 20, 'freezes' => 0, 'done_today' => true], self::summary($streak->countDay(DailyStreak::MODE_NORMAL, '2026-11-05')));
	}

	public function testReturningAfterALongBreakStartsAgain(): void
	{
		$streak = self::evaluate(30, 45, 2, '2026-06-01', '2026-11-04');
		$this->assertSame(['length' => 0, 'best' => 45, 'freezes' => 0, 'done_today' => false], self::summary($streak));
		$this->assertSame(['length' => 1, 'best' => 45, 'freezes' => 0, 'done_today' => true], self::summary($streak->countDay(DailyStreak::MODE_NORMAL, '2026-11-04')));
	}

	public function testOnlyTheFirstSolveOfTheDayCounts(): void
	{
		$streak = self::evaluate(3, 3, 0, '2026-11-03', '2026-11-04')->countDay(DailyStreak::MODE_NORMAL, '2026-11-04');
		$this->assertSame(4, $streak->length);
		$this->assertSame(4, $streak->countDay(DailyStreak::MODE_NORMAL, '2026-11-04')->length);
	}

	public function testAFreezeIsEarnedEverySevenDaysWithoutALimit(): void
	{
		$streak = self::evaluate(0, 0, 0, null, '2026-11-01');
		$day = new DateTimeImmutable('2026-11-01');
		$freezesByLength = [];
		for ($i = 0; $i < 28; $i++)
		{
			$today = $day->modify('+' . $i . ' days')->format('Y-m-d');
			$streak = self::evaluate($streak->length, $streak->best, $streak->freezes, $streak->lastDay, $today)->countDay(DailyStreak::MODE_NORMAL, $today);
			$freezesByLength[$streak->length] = $streak->freezes;
		}
		$this->assertSame(0, $freezesByLength[6]);
		$this->assertSame(1, $freezesByLength[7]);
		$this->assertSame(1, $freezesByLength[13]);
		$this->assertSame(2, $freezesByLength[14]);
		$this->assertSame(3, $freezesByLength[21], 'Freezes add up past two');
		$this->assertSame(4, $freezesByLength[28]);
		$this->assertSame(28, $streak->best);
	}

	public function testEnoughFreezesCoverALongBreak(): void
	{
		$streak = self::evaluate(40, 40, 5, '2026-11-01', '2026-11-06');
		$this->assertSame(['length' => 40, 'best' => 40, 'freezes' => 1, 'done_today' => false], self::summary($streak));
		$this->assertSame(0, self::evaluate(40, 40, 5, '2026-11-01', '2026-11-08')->length);
	}

	//Moving west can make the last counted day lie ahead of today there: it is done, not missed
	public function testADayAheadOfTodayIsDone(): void
	{
		$this->assertTrue(self::evaluate(4, 4, 0, '2026-11-05', '2026-11-04')->doneToday);
	}

	public function testTheDateColumnIsReadInAnyForm(): void
	{
		$now = self::at('2026-11-04 12:00:00');
		$user = ['streak_length' => 2, 'streak_best' => 2, 'streak_freezes' => 0, 'time_zone' => null];
		foreach (['2026-11-04', '2026-11-04 00:00:00', new \Cake\I18n\Date('2026-11-04')] as $lastDay)
			$this->assertTrue(DailyStreak::ofUser($user + ['streak_last_day' => $lastDay], $now)->doneToday);
		$this->assertFalse(DailyStreak::ofUser($user + ['streak_last_day' => null], $now)->doneToday);
	}

	public function testOnlyTheFirstSolveOfTheDayCountsTheDay(): void
	{
		$user = self::player(['streak_length' => 6, 'streak_best' => 6, 'streak_last_day' => '2026-11-03']);
		self::solve($user, '2026-11-04 10:00:00', 20);
		$this->assertSame([7, 7, 1, '2026-11-04'], [$user['streak_length'], $user['streak_best'], $user['streak_freezes'], $user['streak_last_day']]);

		//The second solve of the day, before 3 AM of the next one, only adds its XP to the day
		$fields = DailyStreak::afterResult($user, 15, false, fn() => 0, self::at('2026-11-05 02:00:00', 'Europe/Prague'));
		$this->assertSame(['streak_day' => '2026-11-04', 'streak_day_xp' => 35, 'streak_day_reviews' => 0, 'streak_day_reviews_required' => null], $fields);

		self::solve($user, '2026-11-05 03:00:00', 20);
		$this->assertSame([8, 8, 1, '2026-11-05', 20], [$user['streak_length'], $user['streak_best'], $user['streak_freezes'], $user['streak_last_day'], $user['streak_day_xp']]);
	}

	public function testAResultThatGivesNothingChangesNothing(): void
	{
		$this->assertSame([], DailyStreak::afterResult(self::player(), 0, false, fn() => 0, self::at('2026-11-04 10:00:00')));
	}

	public function testAGoldenDayAsksTheXpOfThreeProblemsOfTheRank(): void
	{
		$this->assertSame(135, DailyStreak::goldenXp(1500));
		$user = self::player(['streak_mode_setting_id' => DailyStreak::MODE_GOLDEN]);
		self::solve($user, '2026-11-04 10:00:00', 90);
		$this->assertSame(0, $user['golden_streak_length'], 'Two problems are not enough');
		$this->assertSame(0, $user['streak_length'], 'A golden day is not counted in the normal streak');
		self::solve($user, '2026-11-04 11:00:00', 45);
		$this->assertSame([0, 1, 1, '2026-11-04'], [$user['streak_length'], $user['golden_streak_length'], $user['golden_streak_best'], $user['streak_last_day']]);
	}

	public function testAGoldenDayAsksThreeReviewsOrAsManyAsAreDue(): void
	{
		$user = self::player(['streak_mode_setting_id' => DailyStreak::MODE_GOLDEN]);
		self::solve($user, '2026-11-04 10:00:00', 200, reviewsDue: 5);
		$this->assertSame([0, 3], [$user['golden_streak_length'], $user['streak_day_reviews_required']], 'The XP is there, the reviews are not');
		self::solve($user, '2026-11-04 10:10:00', 0, true, 4);
		self::solve($user, '2026-11-04 10:20:00', 0, true, 3);
		$this->assertSame(0, $user['golden_streak_length']);
		self::solve($user, '2026-11-04 10:30:00', 0, true, 2);
		$this->assertSame([1, 3], [$user['golden_streak_length'], $user['streak_day_reviews']]);

		$user = self::player(['streak_mode_setting_id' => DailyStreak::MODE_GOLDEN]);
		self::solve($user, '2026-11-04 10:00:00', 0, true, 0);
		$this->assertSame([0, 1], [$user['golden_streak_length'], $user['streak_day_reviews_required']], 'The only review that was due is done, the XP is not');
		self::solve($user, '2026-11-04 11:00:00', 135);
		$this->assertSame(1, $user['golden_streak_length']);

		$user = self::player(['streak_mode_setting_id' => DailyStreak::MODE_GOLDEN]);
		self::solve($user, '2026-11-04 10:00:00', 135);
		$this->assertSame(1, $user['golden_streak_length'], 'With nothing due, the XP alone makes a golden day');
	}

	//A golden day that met only the normal requirement is missed: it ends both streaks, unless a freeze covers it
	public function testAGoldenDayLeftShortIsMissed(): void
	{
		$user = self::player(['streak_mode_setting_id' => DailyStreak::MODE_GOLDEN, 'streak_length' => 4, 'streak_best' => 4, 'golden_streak_length' => 2, 'golden_streak_best' => 2, 'streak_last_day' => '2026-11-03']);
		self::solve($user, '2026-11-04 10:00:00', 45);
		$this->assertFalse(DailyStreak::ofUser($user, self::at('2026-11-04 22:00:00', 'Europe/Prague'))->doneToday);
		$streak = DailyStreak::ofUser($user, self::at('2026-11-05 10:00:00', 'Europe/Prague'));
		$this->assertSame([0, 0, 4, 2], [$streak->length, $streak->goldenLength, $streak->best, $streak->goldenBest], 'Both streaks end, both bests stay');

		$streak = DailyStreak::ofUser(['streak_freezes' => 1] + $user, self::at('2026-11-05 10:00:00', 'Europe/Prague'));
		$this->assertSame([4, 2, 0], [$streak->length, $streak->goldenLength, $streak->freezes], 'The freeze covers the day');
	}

	//Switching the mode moves the next days to the other streak; the day goes to the mode that is on when it counts
	public function testTheTwoStreaksCountSeparately(): void
	{
		$user = self::player();
		self::solve($user, '2026-11-01 10:00:00', 10);
		self::solve($user, '2026-11-02 10:00:00', 10);
		$user['streak_mode_setting_id'] = DailyStreak::MODE_GOLDEN;
		self::solve($user, '2026-11-03 10:00:00', 10);
		$this->assertSame([2, 0], [$user['streak_length'], $user['golden_streak_length']], 'Not a golden day yet');
		$user['streak_mode_setting_id'] = DailyStreak::MODE_NORMAL;
		self::solve($user, '2026-11-03 11:00:00', 10);
		$this->assertSame([3, 0], [$user['streak_length'], $user['golden_streak_length']], 'Switched back, the day counts in the normal streak');
		$user['streak_mode_setting_id'] = DailyStreak::MODE_GOLDEN;
		self::solve($user, '2026-11-04 10:00:00', 135);
		self::solve($user, '2026-11-04 11:00:00', 135);
		$this->assertSame([3, 1, 3, 1], [$user['streak_length'], $user['golden_streak_length'], $user['streak_best'], $user['golden_streak_best']]);
	}

	public function testFreezesAreEarnedEverySevenDaysOfBothStreaks(): void
	{
		$user = self::player();
		for ($day = 1; $day <= 7; $day++)
		{
			$user['streak_mode_setting_id'] = $day % 2 ? DailyStreak::MODE_NORMAL : DailyStreak::MODE_GOLDEN;
			self::solve($user, sprintf('2026-11-%02d 10:00:00', $day), 135);
		}
		$this->assertSame([4, 3, 1], [$user['streak_length'], $user['golden_streak_length'], $user['streak_freezes']]);
	}

	public function testTheProgressOfADayStartsOverTheNextDay(): void
	{
		$user = self::player(['streak_mode_setting_id' => DailyStreak::MODE_GOLDEN]);
		self::solve($user, '2026-11-04 10:00:00', 90, true, 2);
		$view = DailyStreak::view($user, self::at('2026-11-04 12:00:00', 'Europe/Prague'));
		$this->assertSame(['golden' => true, 'xp' => 90, 'xp_required' => 135, 'reviews' => 1, 'reviews_required' => 3],
			array_intersect_key($view, array_flip(['golden', 'xp', 'xp_required', 'reviews', 'reviews_required'])));

		$view = DailyStreak::view($user, self::at('2026-11-05 12:00:00', 'Europe/Prague'));
		$this->assertSame([0, 0, DailyStreak::GOLDEN_REVIEWS], [$view['xp'], $view['reviews'], $view['reviews_required']]);
		self::solve($user, '2026-11-05 12:00:00', 45);
		$this->assertSame(['2026-11-05', 45, 0], [$user['streak_day'], $user['streak_day_xp'], $user['streak_day_reviews']]);
	}
}

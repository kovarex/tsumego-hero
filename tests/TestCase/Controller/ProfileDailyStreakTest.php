<?php

use App\Utility\DailyStreak;

//The daily streak on a player's profile (element/profile_daily_streak.php), which anyone can open
class ProfileDailyStreakTest extends IntegrationTestCase
{
	private static function day(int $daysFromToday): string
	{
		return new DateTimeImmutable(DailyStreak::today(null, new DateTimeImmutable()))->modify($daysFromToday . ' days')->format('Y-m-d');
	}

	//The profile of 'target', with $streak on their row, as 'viewer' sees it; returns the streak table
	private function streakOfProfile(array $streak): string
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'target'] + $streak]]);
		$this->login('viewer');
		$this->get('/users/view/' . $context->otherUsers[0]['id']);
		$this->assertResponseOk();
		$this->assertSame(1, preg_match('~<table class="stat profile-streak" id="streak-info-table">.*?</table>~s', $this->view, $match), 'The profile shows the streak');
		return preg_replace('~\s+~', ' ', strip_tags($match[0]));
	}

	public function testAnotherPlayersProfileShowsTheirStreak(): void
	{
		$table = $this->streakOfProfile(['streak_length' => 12, 'streak_best' => 20, 'streak_freezes' => 2, 'streak_last_day' => self::day(-1)]);
		$this->assertStringContainsString('Daily streak: 12 days', $table);
		$this->assertStringContainsString('Best streak: 20 days', $table);
		$this->assertStringContainsString('Freezes: 2', $table);
		$this->assertStringContainsString('Today is not done yet', $this->view);
	}

	public function testTodayDoneLightsTheFlame(): void
	{
		$table = $this->streakOfProfile(['streak_length' => 1, 'streak_best' => 1, 'streak_last_day' => self::day(0)]);
		$this->assertStringContainsString('profile-streak__current--done', $this->view);
		$this->assertStringContainsString('Daily streak: 1 day ', $table);
	}

	//Days missed without the freezes to cover them end the streak; the best stays
	public function testABrokenStreakShowsNoneWithTheBestKept(): void
	{
		$table = $this->streakOfProfile(['streak_length' => 12, 'streak_best' => 20, 'streak_freezes' => 1, 'streak_last_day' => self::day(-5)]);
		$this->assertStringContainsString('Daily streak: 0 days', $table);
		$this->assertStringContainsString('Best streak: 20 days', $table);
		$this->assertStringContainsString('Freezes: 0', $table);
	}

	//A missed day a freeze covers keeps the streak, and the freeze is gone
	public function testAFreezeCoveredDayKeepsTheStreak(): void
	{
		$table = $this->streakOfProfile(['streak_length' => 12, 'streak_best' => 12, 'streak_freezes' => 1, 'streak_last_day' => self::day(-2)]);
		$this->assertStringContainsString('Daily streak: 12 days', $table);
		$this->assertStringContainsString('Freezes: 0', $table);
	}

	public function testAPlayerWithoutAStreakShowsAQuietLine(): void
	{
		$table = $this->streakOfProfile([]);
		$this->assertStringContainsString('Daily streak: No streak yet', $table);
		$this->assertStringNotContainsString('Best streak', $table);
	}
}

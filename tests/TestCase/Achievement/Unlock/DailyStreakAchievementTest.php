<?php

use App\Model\Table\AchievementTable;
use App\Utility\AchievementId;
use App\Utility\Auth;
use App\Utility\DailyStreak;
use App\Utility\Level;
use App\Utility\PlayOutcome;
use App\Utility\Util;

//The daily streak achievements (AchievementChecker::checkDailyStreakAchievements): 7, 14, 30, 182 and 365 days in a row
class DailyStreakAchievementTest extends AchievementTestCase
{
	private const array ACHIEVEMENTS = [
		[AchievementId::DAILY_STREAK_WEEK, AchievementTable::DAILY_STREAK_WEEK_DAYS],
		[AchievementId::DAILY_STREAK_TWO_WEEKS, AchievementTable::DAILY_STREAK_TWO_WEEKS_DAYS],
		[AchievementId::DAILY_STREAK_MONTH, AchievementTable::DAILY_STREAK_MONTH_DAYS],
		[AchievementId::DAILY_STREAK_HALF_YEAR, AchievementTable::DAILY_STREAK_HALF_YEAR_DAYS],
		[AchievementId::DAILY_STREAK_YEAR, AchievementTable::DAILY_STREAK_YEAR_DAYS]];

	private static function day(int $daysFromToday): string
	{
		return new DateTimeImmutable(DailyStreak::today(null, new DateTimeImmutable()))->modify($daysFromToday . ' days')->format('Y-m-d');
	}

	//A player whose streak of $length days was last counted $daysAgo days ago, and today lacks one XP of what it asks
	private static function playerOneXpShort(int $length, int $daysAgo = 1, int $freezes = 0): ContextPreparator
	{
		$context = new ContextPreparator([
			'user' => ['streak_length' => $length, 'streak_best' => $length, 'streak_freezes' => $freezes, 'streak_last_day' => self::day(-$daysAgo)],
			'tsumego' => ['set_order' => 1, 'status' => 'V']]);
		$required = DailyStreak::xpRequired((float) $context->reloadUser()['rating']);
		Util::execute('UPDATE user SET streak_day = ?, streak_day_xp = ?, streak_day_reviews_required = 0 WHERE id = ?', [self::day(0), $required - 1, $context->user['id']]);
		return $context;
	}

	//Solves the problem, which completes the day; returns the ids of the achievements the result unlocked
	private function solve(ContextPreparator $context): array
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
		$this->post('/tsumegos/result', ['tsumego_id' => $context->tsumegos[0]['id'], 'seconds' => 1, 'outcome' => PlayOutcome::SolvedClean->value]);
		$response = json_decode($this->view, true);
		$this->assertTrue($response['daily_streak']['done_today']);
		return array_column($response['achievement_updates'] ?? [], 'id');
	}

	//The day that makes the streak N days long unlocks the achievement of N right in the result, and not the next one
	public function testEachUnlocksOnTheDayTheStreakReachesItsDays(): void
	{
		foreach (self::ACHIEVEMENTS as $index => [$achievementId, $days])
		{
			$context = self::playerOneXpShort($days - 1);
			$this->assertAchievementNotUnlocked($achievementId);
			$unlocked = $this->solve($context);
			$this->assertSame($days, (int) $context->reloadUser()['streak_length']);
			$this->assertContains($achievementId->value, $unlocked, $achievementId->name . ' pops up after the solve that made ' . $days . ' days');
			$this->assertAchievementUnlocked($achievementId);
			if ($next = self::ACHIEVEMENTS[$index + 1][0] ?? null)
				$this->assertAchievementNotUnlocked($next);
		}
	}

	public function testSixDaysIsNotAWeek(): void
	{
		$context = self::playerOneXpShort(5);
		$this->solve($context);
		$this->assertSame(6, (int) $context->reloadUser()['streak_length']);
		$this->assertAchievementNotUnlocked(AchievementId::DAILY_STREAK_WEEK);
		$this->triggerAchievementCheck();
		$this->assertAchievementNotUnlocked(AchievementId::DAILY_STREAK_WEEK);
	}

	//A missed day a freeze covered keeps the streak, so the day after it still makes the seventh
	public function testAFreezeKeepsTheStreakCountingTowardsThem(): void
	{
		$context = self::playerOneXpShort(6, daysAgo: 2, freezes: 1);
		$this->assertContains(AchievementId::DAILY_STREAK_WEEK->value, $this->solve($context));
		$user = $context->reloadUser();
		$this->assertSame([7, 1], [(int) $user['streak_length'], (int) $user['streak_freezes']], 'The freeze covered the missed day, the seventh day earned a new one');
	}

	public function testABrokenStreakKeepsThem(): void
	{
		$context = self::playerOneXpShort(29);
		$this->solve($context);
		$this->assertAchievementUnlocked(AchievementId::DAILY_STREAK_MONTH);
		//Away for long enough that the freezes don't cover it: the streak is gone, the best stays
		Util::execute('UPDATE user SET streak_last_day = ?, streak_freezes = 0 WHERE id = ?', [self::day(-10), $context->user['id']]);
		$this->triggerAchievementCheck();
		$this->assertSame(0, DailyStreak::ofUser($context->reloadUser())->length);
		foreach ([AchievementId::DAILY_STREAK_WEEK, AchievementId::DAILY_STREAK_TWO_WEEKS, AchievementId::DAILY_STREAK_MONTH] as $achievementId)
			$this->assertAchievementUnlocked($achievementId);
	}

	//A streak reached before the achievements existed gets them on the next page load
	public function testAnExistingLongStreakGetsThemOnTheNextPageLoad(): void
	{
		new ContextPreparator(['user' => ['streak_length' => 40, 'streak_best' => 40, 'streak_last_day' => self::day(-1)]]);
		$this->triggerAchievementCheck();
		foreach ([AchievementId::DAILY_STREAK_WEEK, AchievementId::DAILY_STREAK_TWO_WEEKS, AchievementId::DAILY_STREAK_MONTH] as $achievementId)
			$this->assertAchievementUnlocked($achievementId);
		$this->assertAchievementNotUnlocked(AchievementId::DAILY_STREAK_HALF_YEAR);
	}

	//The longest streak counts, even when the one running now is short
	public function testTheLongestStreakCounts(): void
	{
		new ContextPreparator(['user' => ['streak_length' => 3, 'streak_best' => 200, 'streak_last_day' => self::day(-1)]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementUnlocked(AchievementId::DAILY_STREAK_HALF_YEAR);
		$this->assertAchievementNotUnlocked(AchievementId::DAILY_STREAK_YEAR);
	}

	//Checked on every page load, an achievement pays its XP once
	public function testTheXpIsPaidOnce(): void
	{
		$context = new ContextPreparator(['user' => ['streak_length' => 7, 'streak_best' => 7, 'streak_last_day' => self::day(-1)]]);
		$before = Level::getOverallXPGained($context->reloadUser());
		$this->triggerAchievementCheck();
		$this->assertAchievementUnlocked(AchievementId::DAILY_STREAK_WEEK);
		$xpOfUnlocked = (int) Util::query('SELECT SUM(achievement.xp) AS xp FROM achievement_status JOIN achievement ON achievement.id = achievement_status.achievement_id WHERE user_id = ?', [$context->user['id']])[0]['xp'];
		$this->assertGreaterThanOrEqual((int) $this->fetchTable('Achievement')->get(AchievementId::DAILY_STREAK_WEEK->value)->xp, $xpOfUnlocked);
		$this->triggerAchievementCheck();
		$this->assertSame($before + $xpOfUnlocked, Level::getOverallXPGained($context->reloadUser()));
		$this->assertSame(1, $this->fetchTable('AchievementStatus')->find()->where(['user_id' => $context->user['id'], 'achievement_id' => AchievementId::DAILY_STREAK_WEEK->value])->count());
	}
}

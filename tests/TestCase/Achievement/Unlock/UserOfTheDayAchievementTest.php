<?php

/**
 * Test Achievement::USER_OF_THE_DAY
 *
 * The cron writes a day_record for the user of the day, which is the full history
 * of the wins. The achievement is repeatable: it unlocks on the first win and its
 * value is the number of wins.
 */
class UserOfTheDayAchievementTest extends AchievementTestCase
{
	public function testUserOfTheDayAchievement()
	{
		new ContextPreparator(['day-records' => [['date' => '2026-01-01']]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementUnlocked(Achievement::USER_OF_THE_DAY);
	}

	public function testUserOfTheDayCountsEveryWin()
	{
		$context = new ContextPreparator(['day-records' => [
			['date' => '2026-01-01'],
			['date' => '2026-01-02'],
			['date' => '2026-01-03'],
		]]);
		$this->triggerAchievementCheck();

		$this->assertAchievementUnlocked(Achievement::USER_OF_THE_DAY);
		$status = ClassRegistry::init('AchievementStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'achievement_id' => Achievement::USER_OF_THE_DAY]]);
		$this->assertSame(3, (int) $status['AchievementStatus']['value']);
	}

	public function testUserOfTheDayDoesNotUnlockWithoutAWin()
	{
		new ContextPreparator();
		$this->triggerAchievementCheck();
		$this->assertAchievementNotUnlocked(Achievement::USER_OF_THE_DAY);
	}

	/**
	 * Raising the count must not rewrite when the achievement was unlocked.
	 */
	public function testUserOfTheDayKeepsTheUnlockDateWhenTheCountRises()
	{
		$context = new ContextPreparator([
			'user' => [
				'achievement-statuses' => [
					['id' => Achievement::USER_OF_THE_DAY, 'value' => 1, 'created' => '2020-05-05 12:00:00'],
				],
			],
			'day-records' => [
				['date' => '2026-01-01'],
				['date' => '2026-01-02'],
				['date' => '2026-01-03'],
			],
		]);
		$this->triggerAchievementCheck();

		$status = ClassRegistry::init('AchievementStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'achievement_id' => Achievement::USER_OF_THE_DAY]]);
		$this->assertSame(3, (int) $status['AchievementStatus']['value']);
		$this->assertSame('2020-05-05 12:00:00', $status['AchievementStatus']['created']);
	}
}

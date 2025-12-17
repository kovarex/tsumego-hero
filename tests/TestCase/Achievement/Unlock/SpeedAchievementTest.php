<?php
App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'Test');

/**
 * Test all 12 Speed achievements (IDs 24-35)
 *
 * Structure: 3 tiers (speed) × 4 difficulty ranges (11k, 5k, 10k, 1d)
 * Difficulty ranges: <1300, 1300-1499, 1500-1699, >=1700
 * Values are AVERAGE seconds per tsumego
 *
 * CRITICAL: Speed achievements require BOTH accuracy AND speed conditions!
 * checkSetAchievements() returns early if no accuracy condition exists.
 */
class SpeedAchievementTest extends AchievementTestCase
{
	/**
	 * Test all 12 Speed achievements in a loop
	 * Creates both accuracy (dummy) and speed conditions for each test
	 */
	public function testAllSpeedAchievements()
	{
		$context = new ContextPreparator([]);

		$testCases = [
			// Tier 1: fastest tier
			[Achievement::SPEED_I, 1200, 14, '11k'], // Speed I - <15s
			[Achievement::SPEED_IV, 1400, 17, '5k'],  // Speed IV - <18s
			[Achievement::SPEED_VII, 1600, 29, '10k'], // Speed VII - <30s
			[Achievement::SPEED_X, 1750, 29, '1d'],  // Speed X - <30s

			// Tier 2: medium tier
			[Achievement::SPEED_II, 1200, 9, '11k'], // Speed II - <10s
			[Achievement::SPEED_V, 1400, 12, '5k'],  // Speed V - <13s
			[Achievement::SPEED_VIII, 1600, 19, '10k'], // Speed VIII - <20s
			[Achievement::SPEED_XI, 1750, 19, '1d'],  // Speed XI - <20s

			// Tier 3: fast tier
			[Achievement::SPEED_III, 1200, 4, '11k'], // Speed III - <5s
			[Achievement::SPEED_VI, 1400, 7, '5k'],  // Speed VI - <8s
			[Achievement::SPEED_IX, 1600, 9, '10k'], // Speed IX - <10s
			[Achievement::SPEED_XII, 1750, 9, '1d'],  // Speed XII - <10s
		];

		foreach ($testCases as [$achievementId, $difficulty, $speed, $rank])
		{
			// Create set with 100 tsumegos + SetConnection records
			$setId = $this->createSetWithTsumegosAndConnections($difficulty, 100);

			$AchievementCondition = ClassRegistry::init('AchievementCondition');

			// CRITICAL: Create accuracy condition first (required to prevent early return)
			$AchievementCondition->create();
			$AchievementCondition->save([
					'user_id' => $context->user['id'],
					'set_id' => $setId,
					'category' => '%',
					'value' => 50]); // Dummy value, we're testing speed

			// Create speed condition
			$AchievementCondition->create();
			$AchievementCondition->save([
					'user_id' => $context->user['id'],
					'set_id' => $setId,
					'category' => 's',
					'value' => $speed]);

			new AchievementChecker()->checkSetAchievements($setId);
			$this->assertAchievementUnlocked($achievementId, "Speed $achievementId (<{$speed}s at $rank) should unlock");
		}
	}
}

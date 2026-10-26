<?php

use App\Utility\AchievementId;

/* Test all 12 Speed achievements (IDs 24-35)
 *
 * Structure: 3 tiers (speed) × 4 difficulty ranges (11k, 5k, 10k, 1d)
 * Difficulty ranges: <1300, 1300-1499, 1500-1699, >=1700
 * Values are AVERAGE seconds per tsumego */
class SpeedAchievementTest extends AchievementTestCase
{
	public function testAllSpeedAchievements()
	{
		$testCases = [
			// Tier 1: fastest tier
			[AchievementId::SPEED_I, 1200, 14, '11k'], // Speed I - <15s
			[AchievementId::SPEED_IV, 1400, 17, '5k'],  // Speed IV - <18s
			[AchievementId::SPEED_VII, 1600, 29, '10k'], // Speed VII - <30s
			[AchievementId::SPEED_X, 1750, 29, '1d'],  // Speed X - <30s

			// Tier 2: medium tier
			[AchievementId::SPEED_II, 1200, 9, '11k'], // Speed II - <10s
			[AchievementId::SPEED_V, 1400, 12, '5k'],  // Speed V - <13s
			[AchievementId::SPEED_VIII, 1600, 19, '10k'], // Speed VIII - <20s
			[AchievementId::SPEED_XI, 1750, 19, '1d'],  // Speed XI - <20s

			// Tier 3: fast tier
			[AchievementId::SPEED_III, 1200, 4, '11k'], // Speed III - <5s
			[AchievementId::SPEED_VI, 1400, 7, '5k'],  // Speed VI - <8s
			[AchievementId::SPEED_IX, 1600, 9, '10k'], // Speed IX - <10s
			[AchievementId::SPEED_XII, 1750, 9, '1d'],  // Speed XII - <10s
		];

		foreach ($testCases as [$achievementId, $difficulty, $speed, $rank])
		{
			$browser = Browser::instance();
			$context = new ContextPreparator();
			// Create set with 100 tsumegos + SetConnection records
			$setID = $this->createSetWithTsumegosAndConnections($difficulty, 100);

			$AchievementCondition = ClassRegistry::init('AchievementCondition');

			// Create speed condition
			$AchievementCondition->create();
			$AchievementCondition->save([
				'user_id' => $context->user['id'],
				'set_id' => $setID,
				'category' => 's',
				'value' => $speed]);

			$browser->get('/sets/view/' . $setID);
			$this->assertAchievementUnlocked($achievementId, "Speed {$achievementId->name} (<{$speed}s at $rank) should unlock");
		}
	}
}

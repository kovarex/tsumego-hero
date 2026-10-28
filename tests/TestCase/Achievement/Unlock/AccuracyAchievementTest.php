<?php

use App\Utility\AchievementId;

//Test all 12 Accuracy achievements (IDs 12-23)
//Structure: 3 tiers (75%, 85%, 95%) × 4 difficulty ranges (11k, 5k, 10k, 1d)
//Difficulty ranges: <1300, 1300-1499, 1500-1699, >=1700
class AccuracyAchievementTest extends AchievementTestCase
{
	//Test all 12 Accuracy achievements in a loop
	//Each iteration creates a unique set with the required difficulty and accuracy
	public function testAllAccuracyAchievements()
	{
		// Define all 12 accuracy achievements
		$testCases = [
			// Tier 1: 75% accuracy
			[AchievementId::ACCURACY_I, 1200, 75, '11k'], // Accuracy I - <1300
			[AchievementId::ACCURACY_IV, 1400, 75, '5k'],  // Accuracy IV - 1300-1499
			[AchievementId::ACCURACY_VII, 1600, 75, '10k'], // Accuracy VII - 1500-1699
			[AchievementId::ACCURACY_X, 1750, 75, '1d'],  // Accuracy X - >=1700

			// Tier 2: 85% accuracy
			[AchievementId::ACCURACY_II, 1200, 85, '11k'], // Accuracy II - <1300
			[AchievementId::ACCURACY_V, 1400, 85, '5k'],  // Accuracy V - 1300-1499
			[AchievementId::ACCURACY_VIII, 1600, 85, '10k'], // Accuracy VIII - 1500-1699
			[AchievementId::ACCURACY_XI, 1750, 85, '1d'],  // Accuracy XI - >=1700

			// Tier 3: 95% accuracy
			[AchievementId::ACCURACY_III, 1200, 95, '11k'], // Accuracy III - <1300
			[AchievementId::ACCURACY_VI, 1400, 95, '5k'],  // Accuracy VI - 1300-1499
			[AchievementId::ACCURACY_IX, 1600, 95, '10k'], // Accuracy IX - 1500-1699
			[AchievementId::ACCURACY_XII, 1750, 95, '1d'],  // Accuracy XII - >=1700
		];

		foreach ($testCases as [$achievementId, $rating, $accuracy, $rank])
		{
			$browser = Browser::instance();
			$context = new ContextPreparator();
			// Clean only achievement records from previous iteration (preserve sets/user)
			ClassRegistry::init('AchievementCondition')->deleteAll(['1 = 1']);
			$this->fetchTable('AchievementStatus')->deleteAll(['1 = 1']);

			// Create set with 100 tsumegos + SetConnection records
			$setId = $this->createSetWithTsumegosAndConnections($rating, 100);

			// Create accuracy condition
			ClassRegistry::init('AchievementCondition')->create();
			ClassRegistry::init('AchievementCondition')->save([
				'user_id' => $context->user['id'],
				'set_id' => $setId,
				'achievement_condition_category_id' => AchievementCondition::CATEGORY_SET_ACCURACY,
				'value' => $accuracy]);

			$browser->get('/sets/view/' . $setId);
			$this->assertAchievementUnlocked($achievementId, "Accuracy {$achievementId->name} ($accuracy% at $rank) should unlock");
		}
	}
}

<?php

use App\Model\Table\AchievementConditionTable;
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
			[AchievementId::ACCURACY_I, 1000, 75, '11k'], // Accuracy I - 11k or lower, below 1050
			[AchievementId::ACCURACY_IV, 1200, 75, '5k'],  // Accuracy IV - 10k to 8k, 1050-1349
			[AchievementId::ACCURACY_VII, 1500, 75, '10k'], // Accuracy VII - 7k to 5k, 1350-1649
			[AchievementId::ACCURACY_X, 1750, 75, '1d'],  // Accuracy X - 4k or higher, from 1650

			// Tier 2: 85% accuracy
			[AchievementId::ACCURACY_II, 1000, 85, '11k'], // Accuracy II - 11k or lower, below 1050
			[AchievementId::ACCURACY_V, 1200, 85, '5k'],  // Accuracy V - 10k to 8k, 1050-1349
			[AchievementId::ACCURACY_VIII, 1500, 85, '10k'], // Accuracy VIII - 7k to 5k, 1350-1649
			[AchievementId::ACCURACY_XI, 1750, 85, '1d'],  // Accuracy XI - 4k or higher, from 1650

			// Tier 3: 95% accuracy
			[AchievementId::ACCURACY_III, 1000, 95, '11k'], // Accuracy III - 11k or lower, below 1050
			[AchievementId::ACCURACY_VI, 1200, 95, '5k'],  // Accuracy VI - 10k to 8k, 1050-1349
			[AchievementId::ACCURACY_IX, 1500, 95, '10k'], // Accuracy IX - 7k to 5k, 1350-1649
			[AchievementId::ACCURACY_XII, 1750, 95, '1d'],  // Accuracy XII - 4k or higher, from 1650
		];

		foreach ($testCases as [$achievementId, $rating, $accuracy, $rank])
		{
			$browser = Browser::instance();
			$context = new ContextPreparator();
			// Clean only achievement records from previous iteration (preserve sets/user)
			$this->fetchTable('AchievementCondition')->deleteAll(['1 = 1']);
			$this->fetchTable('AchievementStatus')->deleteAll(['1 = 1']);

			// Create set with 100 tsumegos + SetConnection records
			$setId = $this->createSetWithTsumegosAndConnections($rating, 100);

			// Create accuracy condition
			$achievementConditionTable = $this->fetchTable('AchievementCondition');
			$achievementConditionTable->saveOrFail($achievementConditionTable->newEntity([
				'user_id' => $context->user['id'],
				'set_id' => $setId,
				'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_SET_ACCURACY,
				'value' => $accuracy]));

			$browser->get('/sets/view/' . $setId);
			$this->assertAchievementUnlocked($achievementId, "Accuracy {$achievementId->name} ($accuracy% at $rank) should unlock");
		}
	}
}

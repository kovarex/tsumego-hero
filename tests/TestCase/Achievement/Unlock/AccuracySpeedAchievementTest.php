<?php

App::uses('Achievement', 'Model');

/**
 * Accuracy and Speed Achievement Test
 *
 * Tests Accuracy achievements (IDs 12-23) and Speed achievements (IDs 24-35)
 * These are triggered by completing sets with specific accuracy % or speed thresholds
 */
class AccuracySpeedAchievementTest extends AchievementTestCase
{
	// Test Accuracy I (ID 12): Finish 11k or lower with 75%+ accuracy
	public function testAccuracyIAchievement()
	{
		// Arrange: User completes set with difficulty < 1300 and 75% accuracy
		$context = new ContextPreparator();

		// Create a set with difficulty < 1300 (11k or lower)
		$Set = ClassRegistry::init('Set');
		$Set->create();
		$Set->save(['title' => 'Test Set 11k', 'difficulty' => 1200, 'public' => 0]);
		$setId = $Set->getLastInsertID();

		// Add 100 tsumegos to the set (requirement: $tNum >= 100)
		$Tsumego = ClassRegistry::init('Tsumego');
		$SetConnection = ClassRegistry::init('SetConnection');
		for ($i = 0; $i < 100; $i++)
		{
			$Tsumego->create();
			$Tsumego->save(['set_id' => $setId, 'rating' => 1200, 'sgf' => '(;GM[1]FF[4])']);
			$tsumegoId = $Tsumego->id;

			// CRITICAL: Create SetConnection to link tsumego to set
			$SetConnection->create();
			$SetConnection->save(['set_id' => $setId, 'tsumego_id' => $tsumegoId, 'num' => $i + 1]);
		}

		// Add accuracy achievement condition (category='%', value=75 means 75% accuracy)
		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'user_id' => $context->user['id'],
			'set_id' => $setId,
			'category' => '%',
			'value' => 75]);

		new AchievementChecker()->checkSetAchievements($setId);
		$this->assertAchievementUnlocked(Achievement::ACCURACY_I, "Accuracy I should unlock at 75% on 11k set");
	}

	// Test that 74% accuracy does NOT unlock Achievement 12
	public function testAccuracyIDoesNotUnlockBelow75Percent()
	{
		$context = new ContextPreparator();

		$Set = ClassRegistry::init('Set');
		$Set->create();
		$Set->save([
			'title' => 'Test Set 11k',
			'difficulty' => 1200,
			'public' => 0]);
		$setId = $Set->getLastInsertID();

		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'user_id' => $context->user['id'],
			'set_id' => $setId,
			'category' => '%',
			'value' => 74]); // Just below 75%

		new AchievementChecker()->checkSetAchievements($setId);
		$this->assertAchievementNotUnlocked(Achievement::ACCURACY_I);
	}
}

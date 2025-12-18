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
		$browser = Browser::instance();
		// Arrange: User completes set with difficulty < 1300 and 75% accuracy
		$context = new ContextPreparator();
		$setID = $this->createSetWithTsumegosAndConnections(1200, 100);

		// Add accuracy achievement condition (category='%', value=75 means 75% accuracy)
		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'user_id' => $context->user['id'],
			'set_id' => $setID,
			'category' => '%',
			'value' => 75]);

		$browser->get('/sets/view/' . $setID);
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

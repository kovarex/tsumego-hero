<?php

App::uses('Achievement', 'Model');

class AccuracySpeedAchievementTest extends AchievementTestCase
{
	// Test Achievement::Accuracy_I: Finish 11k or lower with 75%+ accuracy
	public function testAccuracyIAchievement()
	{
		foreach ([75, 74] as $percent)
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
				'value' => $percent]);

			$browser->get('/sets/view/' . $setID);
			$this->assertAchievementUnlockedWhen($percent >= 75, Achievement::ACCURACY_I, "Accuracy I should unlock at 75% on 11k set");
		}
	}
}

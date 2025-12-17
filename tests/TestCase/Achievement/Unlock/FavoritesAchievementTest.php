<?php
App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'Test');

class FavoritesAchievementTest extends AchievementTestCase
{
	/* Test "Those are my favorites" achievement Achievement::FAVORITES
	 * Unlocks when user views their favorites collection (set_id = -1) */
	public function testFavoritesAchievement()
	{
		new ContextPreparator();

		// Trigger check with sid = -1 (favorites)
		// No need to create achievement_condition - favorites check happens before that query now
		new AchievementChecker()->checkSetAchievements(-1);
		$this->assertAchievementUnlocked(Achievement::FAVORITES, 'Favorites achievement should unlock when accessing favorites collection (sid = -1)');
	}
}

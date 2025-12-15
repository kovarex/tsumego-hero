<?php

App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'Test');

class FavoritesAchievementTest extends AchievementTestCase
{
	/**
	 * Test "Those are my favorites" achievement (ID 99)
	 * Unlocks when user views their favorites collection (set_id = -1)
	 */
	public function testFavoritesAchievement()
	{
		$context = new ContextPreparator([]);

		// Trigger check with sid = -1 (favorites)
		// No need to create achievement_condition - favorites check happens before that query now
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();

		$controller = new AppController();
		$controller->constructClasses();
		$controller->checkSetAchievements(-1);

		// Assert achievement 99 unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::FAVORITES, 'Favorites achievement should unlock when accessing favorites collection (sid = -1)');
	}
}

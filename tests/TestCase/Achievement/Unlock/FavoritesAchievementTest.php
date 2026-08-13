<?php

App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'Test');

class FavoritesAchievementTest extends AchievementTestCase
{
	public function testFavoritesAchievementUnlocksWhenFavoritingAProblem()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])'],
		]);
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];

		$this->testAction('/sets/addTsumego/favorites', [
			'data' => ['tsumego_id' => $tsumegoId],
			'method' => 'POST',
		]);

		$this->assertAchievementUnlocked(Achievement::FAVORITES, 'Favorites achievement should unlock when favoriting a problem');
	}

	public function testFavoritesAchievementDoesNotUnlockForRegularSet()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])'],
		]);
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];

		$set = ClassRegistry::init('Set');
		$set->create();
		$set->save(['title' => 'My Set', 'public' => 0, 'user_id' => $context->user['id'], 'order' => Constants::$DEFAULT_SET_ORDER]);
		$setId = $set->getInsertID();

		$this->testAction("/sets/addTsumego/{$setId}", [
			'data' => ['tsumego_id' => $tsumegoId],
			'method' => 'POST',
		]);

		$this->assertAchievementNotUnlocked(Achievement::FAVORITES);
	}
}

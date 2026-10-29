<?php

use App\Utility\AchievementId;
use App\Utility\Constants;

class FavoritesAchievementTest extends AchievementTestCase
{
	public function testFavoritesAchievementUnlocksWhenFavoritingAProblem()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])'],
		]);
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;

		$this->post('/sets/addTsumego/favorites', ['tsumego_id' => $tsumegoId]);

		$this->assertAchievementUnlocked(AchievementId::FAVORITES, 'Favorites achievement should unlock when favoriting a problem');
	}

	public function testFavoritesAchievementDoesNotUnlockForRegularSet()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])'],
		]);
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;

		$setTable = $this->fetchTable('Set');
		$set = $setTable->saveOrFail($setTable->newEntity(['title' => 'My Set', 'public' => 0, 'user_id' => $context->user['id'], 'order' => Constants::$DEFAULT_SET_ORDER]));
		$setId = $set->id;

		$this->post("/sets/addTsumego/{$setId}", ['tsumego_id' => $tsumegoId]);

		$this->assertAchievementNotUnlocked(AchievementId::FAVORITES);
	}
}

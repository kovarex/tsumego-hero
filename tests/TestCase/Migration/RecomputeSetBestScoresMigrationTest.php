<?php

use App\Model\Table\AchievementConditionTable;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261109160319_recompute_set_best_scores.php';

//The kept best scores of the collections are worked out again over the whole collection since its resets: a collection
//solved whole gets the scores of that run, one that isn't loses the ones kept
class RecomputeSetBestScoresMigrationTest extends AppTestCase
{
	public function testTheBestScoresAreOfTheWholeCollection(): void
	{
		$tsumegos = [];
		foreach (range(1, 4) as $num)
			$tsumegos[] = ['sets' => [['name' => 'solved whole', 'num' => $num]], 'status' => 'S',
				'attempts' => $num === 4 ? [['solved' => false], ['solved' => true, 'seconds' => 30]] : [['solved' => true, 'seconds' => 10]]];
		$tsumegos[] = ['sets' => [['name' => 'half solved', 'num' => 1]], 'status' => 'S', 'attempts' => [['solved' => true, 'seconds' => 5]]];
		$tsumegos[] = ['sets' => [['name' => 'half solved', 'num' => 2]], 'status' => 'F'];
		$context = new ContextPreparator(['user' => ['name' => 'trainer'], 'tsumegos' => $tsumegos]);
		$userID = (int) $context->user['id'];
		$solvedWhole = (int) $context->tsumegos[0]['sets'][0]['id'];
		$halfSolved = (int) $context->tsumegos[4]['sets'][0]['id'];
		foreach ([$solvedWhole, $halfSolved] as $setID)
		{
			AchievementConditionTable::keepHighest($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, 100, $setID);
			AchievementConditionTable::keepLowest($userID, AchievementConditionTable::CATEGORY_SET_TIME, 5, $setID);
		}

		self::migration()->up();

		$this->assertEquals(80, AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $solvedWhole), '4 solves against 1 fail');
		$this->assertEquals(15, AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_TIME, $solvedWhole), '(10 + 10 + 10 + 30) / 4');
		$this->assertNull(AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $halfSolved));
		$this->assertNull(AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_TIME, $halfSolved));
	}

	private static function migration(): RecomputeSetBestScores
	{
		$config = require ROOT . '/phinx.php';
		$migration = new RecomputeSetBestScores('test', 20261109160319);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}

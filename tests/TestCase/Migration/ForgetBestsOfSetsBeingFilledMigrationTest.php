<?php

use App\Model\Table\AchievementConditionTable;
use App\Utility\AchievementId;
use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;
use Symfony\Component\Console\Output\BufferedOutput;

require_once ROOT . '/db/migrations/20261121220203_forget_bests_of_sets_being_filled.php';

//The bests of a collection problems are still scheduled into are deleted; the bests of other collections, the other
//conditions and the achievements stay
class ForgetBestsOfSetsBeingFilledMigrationTest extends AppTestCase
{
	public function testTheBestsOfTheCollectionsBeingFilledAreDeleted(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]]],
			['sets' => [['name' => 'filled set', 'num' => 1]]],
			['sets' => [['name' => 'finished set', 'num' => 1]]],
			['sets' => [['name' => 'published set', 'num' => 1]]]],
			'schedule' => [
				['tsumego' => 0, 'set' => 'filled set', 'date' => date('Y-m-d', strtotime('+1 day'))],
				['tsumego' => 0, 'set' => 'published set', 'date' => date('Y-m-d', strtotime('-1 day')), 'published' => 1]]]);
		$userID = (int) $context->user['id'];
		$filled = (int) $context->tsumegos[1]['sets'][0]['id'];
		$finished = (int) $context->tsumegos[2]['sets'][0]['id'];
		$published = (int) $context->tsumegos[3]['sets'][0]['id'];
		foreach ([$filled, $finished, $published] as $setID)
			foreach ([0, 1] as $part)
			{
				AchievementConditionTable::put($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, 100, $setID, $part);
				AchievementConditionTable::put($userID, AchievementConditionTable::CATEGORY_SET_TIME, 5, $setID, $part);
			}
		AchievementConditionTable::put($userID, AchievementConditionTable::CATEGORY_COMPLETED_SETS, 3);
		Util::execute('INSERT INTO achievement_status (user_id, achievement_id, value) VALUES (?, ?, 1)', [$userID, AchievementId::SUPERIOR_ACCURACY->value]);
		$migration = self::migration();
		$output = new BufferedOutput();
		$migration->setOutput($output);

		$migration->up();

		$this->assertStringContainsString("Deleted 4 best times and accuracies of the collections $filled,", $output->fetch());
		foreach ([0, 1] as $part)
		{
			$this->assertNull(AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $filled, $part));
			$this->assertNull(AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_TIME, $filled, $part));
			foreach ([$finished, $published] as $setID)
				$this->assertEquals(100, AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $setID, $part));
		}
		$this->assertEquals(3, AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_COMPLETED_SETS));
		$this->assertCount(1, Util::query('SELECT id FROM achievement_status WHERE user_id = ?', [$userID]), 'the achievements stay');
	}

	public function testNothingIsDeletedWithoutCollectionsBeingFilled(): void
	{
		$context = new ContextPreparator(['tsumegos' => [['sets' => [['name' => 'finished set', 'num' => 1]]]]]);
		$setID = (int) $context->tsumegos[0]['sets'][0]['id'];
		AchievementConditionTable::put((int) $context->user['id'], AchievementConditionTable::CATEGORY_SET_ACCURACY, 100, $setID, 0);

		self::migration()->up();

		$this->assertEquals(100, AchievementConditionTable::value((int) $context->user['id'], AchievementConditionTable::CATEGORY_SET_ACCURACY, $setID, 0));
	}

	private static function migration(): ForgetBestsOfSetsBeingFilled
	{
		$config = require ROOT . '/phinx.php';
		$migration = new ForgetBestsOfSetsBeingFilled('test', 20261121220203);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}

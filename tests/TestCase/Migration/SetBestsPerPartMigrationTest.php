<?php

use App\Model\Table\AchievementConditionTable;
use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;
use Symfony\Component\Console\Output\BufferedOutput;

require_once ROOT . '/db/migrations/20261109221207_set_bests_per_part.php';

//Every kept best time and accuracy of a collection is deleted, the other conditions and the unlocked achievements stay,
//and the bests are kept per part from then on
class SetBestsPerPartMigrationTest extends AppTestCase
{
	public function testTheBestsOfCollectionsAreDeletedAndKeptPerPartAfter(): void
	{
		$context = new ContextPreparator(['tsumegos' => [['sets' => [['name' => 'old set', 'num' => 1]]]]]);
		$userID = $context->user['id'];
		$setID = $context->tsumegos[0]['sets'][0]['id'];
		$migration = self::migration();
		$migration->down();
		foreach ([[AchievementConditionTable::CATEGORY_SET_ACCURACY, $setID, 100], [AchievementConditionTable::CATEGORY_SET_TIME, $setID, 12],
			[AchievementConditionTable::CATEGORY_COMPLETED_SETS, null, 4]] as [$category, $set, $value])
				Util::execute('INSERT INTO achievement_condition (user_id, achievement_condition_category_id, set_id, value) VALUES (?, ?, ?, ?)', [$userID, $category, $set, $value]);
		Util::execute('INSERT INTO achievement_status (user_id, achievement_id, created) VALUES (?, 1, NOW())', [$userID]);
		$output = new BufferedOutput();
		$migration->setOutput($output);

		$migration->up();

		$this->assertStringContainsString('Deleted 2 best times and accuracies', $output->fetch());
		$this->assertSame([['category' => AchievementConditionTable::CATEGORY_COMPLETED_SETS, 'value' => 4.0]],
			array_map(fn($row) => ['category' => (int) $row['category'], 'value' => (float) $row['value']],
				Util::query('SELECT achievement_condition_category_id AS category, value FROM achievement_condition WHERE user_id = ?', [$userID])));
		$this->assertCount(1, Util::query('SELECT id FROM achievement_status WHERE user_id = ?', [$userID]), 'the unlocked achievement stays');

		// each part has a row of its own
		foreach ([0 => 90, 1 => 100] as $part => $value)
			AchievementConditionTable::keepHighest($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $value, $setID, $part);
		AchievementConditionTable::keepHighest($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, 80, $setID, 0);
		$this->assertSame([90.0, 100.0], [
			AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $setID, 0),
			AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $setID, 1)]);
	}

	private static function migration(): SetBestsPerPart
	{
		$config = require ROOT . '/phinx.php';
		$migration = new SetBestsPerPart('test', 20261109221207);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}

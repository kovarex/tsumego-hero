<?php

use App\Model\Table\AchievementConditionTable;
use App\Utility\AchievementId;
use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;
use Symfony\Component\Console\Output\BufferedOutput;

require_once ROOT . '/db/migrations/20261109221207_set_bests_per_part.php';

//Every kept best time and accuracy of a collection is deleted with the achievements they unlocked; the other conditions and achievements, the attempts and the resets stay, and the bests are kept per part from then on
class SetBestsPerPartMigrationTest extends AppTestCase
{
	public function testTheBestsAndTheirAchievementsAreDeletedAndKeptPerPartAfter(): void
	{
		$context = new ContextPreparator(['tsumegos' => [['sets' => [['name' => 'old set', 'num' => 1]]]]]);
		$userID = $context->user['id'];
		$setID = $context->tsumegos[0]['sets'][0]['id'];
		$migration = self::migration();
		$migration->down();
		foreach ([[AchievementConditionTable::CATEGORY_SET_ACCURACY, $setID, 100], [AchievementConditionTable::CATEGORY_SET_TIME, $setID, 12],
			[AchievementConditionTable::CATEGORY_COMPLETED_SETS, null, 4]] as [$category, $set, $value])
				Util::execute('INSERT INTO achievement_condition (user_id, achievement_condition_category_id, set_id, value) VALUES (?, ?, ?, ?)', [$userID, $category, $set, $value]);
		foreach ([[1, 1], [AchievementId::ACCURACY_I->value, 1], [AchievementId::SPEED_XII->value, 1], [AchievementId::SUPERIOR_ACCURACY->value, 3]] as [$achievement, $value])
			Util::execute('INSERT INTO achievement_status (user_id, achievement_id, value) VALUES (?, ?, ?)', [$userID, $achievement, $value]);
		Util::execute('INSERT INTO tsumego_attempt (user_id, tsumego_id, solved, seconds, misplays, gain, user_rating, tsumego_rating, created) VALUES (?, ?, 1, 3, 0, 0, 1000, 1000, NOW())',
			[$userID, $context->tsumegos[0]['id']]);
		Util::execute('INSERT INTO progress_deletion (user_id, set_id, set_partition, created) VALUES (?, ?, 0, NOW())', [$userID, $setID]);
		$output = new BufferedOutput();
		$migration->setOutput($output);

		$migration->up();

		$printed = $output->fetch();
		$this->assertStringContainsString('Deleted 2 best times and accuracies', $printed);
		foreach ([AchievementId::ACCURACY_I, AchievementId::SPEED_XII, AchievementId::SUPERIOR_ACCURACY] as $achievement)
			$this->assertStringContainsString("Achievement {$achievement->value} taken back from 1 players", $printed);
		$this->assertSame([['category' => AchievementConditionTable::CATEGORY_COMPLETED_SETS, 'value' => 4.0]],
			array_map(fn($row) => ['category' => (int) $row['category'], 'value' => (float) $row['value']],
				Util::query('SELECT achievement_condition_category_id AS category, value FROM achievement_condition WHERE user_id = ?', [$userID])));
		$this->assertSame([1], array_map(fn($row) => (int) $row['achievement_id'], Util::query('SELECT achievement_id FROM achievement_status WHERE user_id = ?', [$userID])),
			'the achievements of the bests are taken back, the others stay');
		$this->assertCount(1, Util::query('SELECT id FROM tsumego_attempt WHERE user_id = ?', [$userID]), 'the attempts stay');
		$this->assertCount(1, Util::query('SELECT id FROM progress_deletion WHERE user_id = ?', [$userID]), 'the resets stay');

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

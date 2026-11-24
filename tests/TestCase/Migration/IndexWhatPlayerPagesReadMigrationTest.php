<?php

use App\Model\Table\TsumegoStatusTable;
use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261124031836_index_what_player_pages_read.php';

//The statuses and the attempts of a player are read from indexes that hold what the pages want of them, so a page
//doesn't look every row up in the table; running the migration again changes nothing
class IndexWhatPlayerPagesReadMigrationTest extends AppTestCase
{
	/** @return list<string> The columns of the index, in their order */
	private static function columns(string $table, string $index): array
	{
		return array_column(Util::query("SHOW INDEX FROM $table WHERE Key_name = ?", [$index]), 'Column_name');
	}

	//How MariaDB reads the query, by the Extra of its plan
	private static function plan(string $sql, array $params = []): string
	{
		return implode('; ', array_column(Util::query('EXPLAIN ' . $sql, $params), 'Extra'));
	}

	public function testTheIndexesHoldWhatThePagesRead(): void
	{
		self::migration()->up();
		self::migration()->up();

		$this->assertSame(['user_id', 'tsumego_id', 'status', 'updated'], self::columns('tsumego_status', 'user_id_tsumego_id_status_updated'));
		$this->assertSame(['user_id', 'created', 'id', 'solved'], self::columns('tsumego_attempt', 'user_id_created_id_solved'));
		$this->assertSame([], self::columns('tsumego_attempt', 'user_id_created'));
		$this->assertSame(['user_id', 'tsumego_id'], self::columns('tsumego_status', 'user_id_and_tsumego_id_index'), 'the unique key stays');
	}

	//The queries of /sets, of a page of problems, of the settings and of the profile read the indexes alone, and the
	//solve history still pages through the attempts in their order without sorting them all
	public function testThePagesReadTheIndexesAlone(): void
	{
		$tsumegos = [];
		for ($index = 1; $index <= 5; $index++)
			$tsumegos[] = ['sets' => [['name' => 'set', 'num' => $index]], 'status' => 'S',
				'attempts' => [['solved' => true, 'created' => date('Y-m-d H:i:s', strtotime("-$index days"))]]];
		$context = new ContextPreparator(['user' => ['name' => 'player'], 'tsumegos' => $tsumegos]);
		$userID = (int) $context->user['id'];
		$tsumegoIDs = array_map(fn(array $tsumego) => (int) $tsumego['id'], $context->tsumegos);
		Util::query('ANALYZE TABLE tsumego_status, tsumego_attempt');

		$solved = TsumegoStatusTable::solvedCondition('status');
		$this->assertStringContainsString('Using index', self::plan("SELECT tsumego_id FROM tsumego_status WHERE user_id = ? AND $solved", [$userID]));
		$this->assertStringContainsString('Using index', self::plan(
			'SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ? AND tsumego_id IN (' . implode(',', $tsumegoIDs) . ')', [$userID]));
		$this->assertStringContainsString('Using index', self::plan(
			"SELECT COUNT(*) FROM tsumego_status WHERE user_id = ? AND $solved AND updated <= NOW() - INTERVAL 1 YEAR", [$userID]));
		$this->assertStringContainsString('Using index', self::plan(
			'SELECT DATE(created) AS day, SUM(solved) FROM tsumego_attempt WHERE user_id = ? AND created >= ? GROUP BY DATE(created)',
			[$userID, date('Y-m-d', strtotime('-3 months'))]));
		$this->assertStringNotContainsString('filesort', self::plan(
			'SELECT * FROM tsumego_attempt WHERE user_id = ? ORDER BY created DESC, id DESC LIMIT 500', [$userID]));
	}

	private static function migration(): IndexWhatPlayerPagesRead
	{
		$config = require ROOT . '/phinx.php';
		$migration = new IndexWhatPlayerPagesRead('test', 20261124031836);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}

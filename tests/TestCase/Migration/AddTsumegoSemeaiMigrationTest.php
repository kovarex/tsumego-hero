<?php

use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261101215915_add_tsumego_semeai.php';

//The move of the semeai values out of tsumego takes every row the old columns could hold, also those
//the old code could not show: no liberty count, no variance, a type the explanation doesn't know.
//The test takes the migration back, fills the old columns and runs it again.
class AddTsumegoSemeaiMigrationTest extends AppTestCase
{
	public function testEveryOldRowMoves(): void
	{
		new ContextPreparator();
		$migration = self::migration();
		$migration->down();
		try
		{
			$ids = [];
			foreach ([
				'complete' => [2, 6, 1, 3, 2, 3],
				'no liberty count' => [1, null, 0, 2, 2, null],
				'no variance' => [3, 5, null, null, null, null],
				'unknown type' => [9, 4, 1, 0, 0, null],
				'negative type' => [-1, 4, null, null, 0, null],
				'no semeai' => [0, 7, 1, 2, 1, 1],
				'nothing' => [null, null, null, null, null, null]] as $name => $values)
			{
				Util::execute('INSERT INTO tsumego (author, description, semeai_type, liberty_count, minimum_liberties, maximum_liberties, variance, inside_liberties)
					VALUES (\'\', ?, ?, ?, ?, ?, ?, ?)', array_merge([$name], $values));
				$ids[$name] = (int) Util::query('SELECT LAST_INSERT_ID() AS id')[0]['id'];
			}

			$migration->up();

			$rows = array_column(Util::query('SELECT * FROM tsumego_semeai'), null, 'tsumego_id');
			$this->assertCount(5, $rows, 'every problem with a type moves, the others not');
			$this->assertSame(['tsumego_id' => $ids['complete'], 'semeai_type_id' => 2, 'liberty_count' => 6, 'minimum_liberties' => 1,
				'maximum_liberties' => 3, 'variance' => 2, 'inside_liberties' => 3], $rows[$ids['complete']]);
			$this->assertNull($rows[$ids['no liberty count']]['liberty_count']);
			$this->assertNull($rows[$ids['no variance']]['variance']);
			$this->assertSame(9, $rows[$ids['unknown type']]['semeai_type_id']);
			$this->assertSame(-1, $rows[$ids['negative type']]['semeai_type_id']);
			$this->assertSame(['unknown -1', 'unknown 9'], array_column(Util::query("SELECT name FROM semeai_type WHERE name LIKE 'unknown%' ORDER BY id"), 'name'));
		}
		finally
		{
			// the schema the other tests run on comes back whatever failed
			if (!Util::query("SHOW TABLES LIKE 'tsumego_semeai'"))
			{
				Util::execute('DELETE FROM tsumego');
				$migration->up();
			}
		}
	}

	private static function migration(): AddTsumegoSemeai
	{
		$config = require ROOT . '/phinx.php';
		$adapter = AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']);
		$migration = new AddTsumegoSemeai('test', 20261101215915);
		$migration->setAdapter($adapter);
		return $migration;
	}
}

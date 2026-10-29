<?php

use App\Model\Table\AppTable;
use App\Utility\Util;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;

//MariaDB 11.6.2 and newer fail a transaction with 1020 "Record has changed since last read" when it
//updates a row another request changed after the transaction read something (forum#35, forum#102).
//A plain save isn't a transaction (AppTable::save), an explicit transaction still gets the check.
class SnapshotIsolationTest extends AppTestCase
{
	private const TABLE = 'snapshot_isolation_test';

	public function setUp(): void
	{
		parent::setUp();
		Util::connection()->exec('CREATE TABLE ' . self::TABLE . ' (id INT PRIMARY KEY, value INT NOT NULL, other INT NOT NULL) ENGINE=InnoDB');
		Util::connection()->exec('INSERT INTO ' . self::TABLE . ' VALUES (1, 0, 0)');
	}

	public function tearDown(): void
	{
		Util::connection()->exec('DROP TABLE IF EXISTS ' . self::TABLE);
		parent::tearDown();
	}

	//Another connection to the same database, like the one of a second request
	private static function otherConnection(): PDO
	{
		$config = Util::database()->config();
		return new PDO(
			'mysql:host=' . $config['host'] . ';port=' . ($config['port'] ?? 3306) . ';dbname=' . $config['database'],
			$config['username'],
			$config['password'],
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	}

	private static function row(): array
	{
		return Util::query('SELECT value, other FROM ' . self::TABLE . ' WHERE id = 1')[0];
	}

	//Another request changes the row while the save is between checking that the row exists and updating it.
	public function testASaveKeepsAChangeOfAnotherRequestInTheMeantime(): void
	{
		$table = new class (['table' => self::TABLE, 'alias' => 'SnapshotIsolationRow']) extends AppTable {
			public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
			{
				SnapshotIsolationTest::changeFromAnotherRequest();
			}
		};
		$row = $table->get(1);
		$row->set('value', 10);
		$this->assertNotFalse($table->save($row));
		$this->assertEquals(['value' => 10, 'other' => 1], self::row(), 'Both changes are kept');
	}

	public static function changeFromAnotherRequest(): void
	{
		self::otherConnection()->exec('UPDATE ' . self::TABLE . ' SET other = other + 1 WHERE id = 1');
	}

	//A transaction that read before another request changed the row still fails, so a lost update can't hide.
	public function testATransactionThatReadBeforeAnotherRequestChangedTheRowStillFails(): void
	{
		$version = Util::query('SELECT VERSION() AS version')[0]['version'];
		if (version_compare(explode('-', $version)[0], '11.6.2', '<'))
			$this->markTestSkipped('MariaDB ' . $version . ' has no snapshot isolation');

		$connection = Util::connection();
		$connection->beginTransaction();
		try
		{
			$connection->query('SELECT value FROM ' . self::TABLE . ' WHERE id = 1')->fetchAll();
			self::changeFromAnotherRequest();
			$this->expectExceptionMessage('Record has changed since last read');
			$connection->exec('UPDATE ' . self::TABLE . ' SET value = 10 WHERE id = 1');
		}
		finally
		{
			if ($connection->inTransaction())
				$connection->rollBack();
		}
	}
}

<?php

use App\Utility\Util;

/**
 * MariaDB 11.6.2 and newer fail a transaction with 1020 "Record has changed since last read" when it
 * updates a row another request changed after the transaction read something (forum#35, forum#102).
 * The connections of the site turn that check off (config/bootstrap.php).
 */
class SnapshotIsolationTest extends CakeTestCase
{
	private const TABLE = 'snapshot_isolation_test';

	public function tearDown(): void
	{
		Util::connection()->exec('DROP TABLE IF EXISTS ' . self::TABLE);
		parent::tearDown();
	}

	/** Another connection to the same database, like the one of a second request */
	private static function otherConnection(): PDO
	{
		/** @phpstan-ignore-next-line */
		$config = ClassRegistry::init('Tsumego')->getDataSource()->config;
		return new PDO(
			'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'],
			$config['login'],
			$config['password'],
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	}

	public function testATransactionCanUpdateARowChangedAfterItRead(): void
	{
		$connection = Util::connection();
		$connection->exec('CREATE TABLE ' . self::TABLE . ' (id INT PRIMARY KEY, value INT NOT NULL) ENGINE=InnoDB');
		$connection->exec('INSERT INTO ' . self::TABLE . ' VALUES (1, 0), (2, 0)');

		$connection->beginTransaction();
		try
		{
			// the read starts the snapshot of the transaction
			$connection->query('SELECT value FROM ' . self::TABLE . ' WHERE id = 2')->fetchAll();
			self::otherConnection()->exec('UPDATE ' . self::TABLE . ' SET value = value + 1 WHERE id = 1');
			$connection->exec('UPDATE ' . self::TABLE . ' SET value = value + 10 WHERE id = 1');
			$connection->commit();
		}
		finally
		{
			if ($connection->inTransaction())
				$connection->rollBack();
		}

		$this->assertSame(11, (int) Util::query('SELECT value FROM ' . self::TABLE . ' WHERE id = 1')[0]['value'], 'Both updates are kept');
	}
}

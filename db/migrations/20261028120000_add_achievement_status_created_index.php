<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The recent achievements on the home page are the latest rows of achievement_status, ordered by created.
//Without an index on created, every rebuild of their cache sorts the whole table, and the cache is dropped
//on every achievement gained. The index is built online (LOCK=NONE), so achievements go on meanwhile.
final class AddAchievementStatusCreatedIndex extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('ALTER TABLE achievement_status ADD INDEX IF NOT EXISTS created (created), ALGORITHM=INPLACE, LOCK=NONE');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE achievement_status DROP INDEX IF EXISTS created, ALGORITHM=INPLACE, LOCK=NONE');
	}
}

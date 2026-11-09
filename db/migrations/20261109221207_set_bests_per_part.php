<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The best accuracy and the best average time of a collection were wrong: taken from the set page as the player saw
//it (a part of the player's own size, the problems their filters let through), and before #291 with the misplays of a
//solved attempt left out, so many were 100% though the player made mistakes. They start over, now per part of a
//collection: the problems ordered by number in parts of 200 (ProgressDeletionTable::$PARTITION_SIZE), the part a reset
//clears, so each part has its own best (App\Utility\SetPartBestScores). Every kept best is deleted; the achievements
//already unlocked stay.
final class SetBestsPerPart extends AbstractMigration
{
	public function up(): void
	{
		$deleted = $this->execute('DELETE FROM achievement_condition WHERE achievement_condition_category_id IN (2, 3)');
		$this->execute("ALTER TABLE achievement_condition
			ADD set_partition int(10) unsigned NULL DEFAULT NULL COMMENT 'The part of the set of a set record, 0 based, in parts of ProgressDeletionTable::\$PARTITION_SIZE; NULL for the other categories' AFTER set_id_or_zero,
			ADD set_partition_or_zero int(10) unsigned AS (COALESCE(set_partition, 0)) VIRTUAL COMMENT 'set_partition for the unique key, which would let NULLs repeat' AFTER set_partition,
			DROP KEY user_category_set,
			ADD UNIQUE KEY user_category_set_partition (user_id, achievement_condition_category_id, set_id_or_zero, set_partition_or_zero)");
		$this->getOutput()?->writeln("Deleted $deleted best times and accuracies of collections, which start over per part");
	}

	public function down(): void
	{
		$this->execute('DELETE FROM achievement_condition WHERE set_partition > 0');
		$this->execute('ALTER TABLE achievement_condition
			DROP KEY user_category_set_partition,
			ADD UNIQUE KEY user_category_set (user_id, achievement_condition_category_id, set_id_or_zero),
			DROP COLUMN set_partition_or_zero,
			DROP COLUMN set_partition');
	}
}

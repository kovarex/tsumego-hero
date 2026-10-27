<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//A reset clears one part (200 problems) of a set, so the part is saved with it, and only the resets of
//that part reduce the XP of its problems. Resets saved before have no part (NULL) and keep counting
//for the whole set until they are a month old.
final class ProgressDeletionPartition extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `progress_deletion` ADD COLUMN `set_partition` INT UNSIGNED NULL DEFAULT NULL COMMENT 'The reset part of the set (0 based), NULL for resets saved before parts were recorded.' AFTER `set_id`");
	}

	public function down(): void
	{
		$this->execute("ALTER TABLE `progress_deletion` DROP COLUMN `set_partition`");
	}
}

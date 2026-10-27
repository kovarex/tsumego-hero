<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//Deleted problems and collections go to a trash first (see App\Utility\Trash): they are only marked
//with the time of the deletion, an admin can restore them, and the daily cron removes them from the
//database for good once they have been in the trash for Trash::$RETENTION_DAYS.
//Problems already had tsumego.deleted, collections get the same column. Both are indexed, as the
//cron looks for the expired ones every day.
final class AddTrash extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `set`
			ADD COLUMN `deleted` DATETIME NULL DEFAULT NULL COMMENT 'When the collection was moved to the trash, NULL when it is not in it.',
			ADD INDEX `deleted` (`deleted`)");
		$this->execute("ALTER TABLE `tsumego`
			MODIFY COLUMN `deleted` DATETIME NULL DEFAULT NULL COMMENT 'When the problem was moved to the trash, NULL when it is not in it.',
			ADD INDEX `deleted` (`deleted`)");
		$this->execute("INSERT IGNORE INTO `admin_activity_type` (`id`, `name`) VALUES
			(40, 'Problem Restore'),
			(41, 'Set Delete'),
			(42, 'Set Restore')");
	}

	public function down(): void
	{
		$this->execute("DELETE FROM `admin_activity_type` WHERE `id` IN (40, 41, 42)");
		$this->execute("ALTER TABLE `tsumego`
			DROP INDEX `deleted`,
			MODIFY COLUMN `deleted` DATETIME NULL DEFAULT NULL");
		$this->execute("ALTER TABLE `set`
			DROP INDEX `deleted`,
			DROP COLUMN `deleted`");
	}
}

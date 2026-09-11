<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScheduleCreatedBy extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `schedule`
			ADD COLUMN `created_by` INT UNSIGNED DEFAULT NULL AFTER `published`,
			ADD CONSTRAINT `fk_schedule_created_by`
				FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL");
	}

	public function down(): void
	{
		$this->execute("ALTER TABLE `schedule`
			DROP FOREIGN KEY `fk_schedule_created_by`,
			DROP COLUMN `created_by`");
	}
}

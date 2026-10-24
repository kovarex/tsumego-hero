<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScheduleCreatedByUserId extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `schedule`
			ADD COLUMN `created_by_user_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'The admin who scheduled it' AFTER `published`,
			ADD CONSTRAINT `schedule_created_by_user_id`
				FOREIGN KEY (`created_by_user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
	}

	public function down(): void
	{
		$this->execute("ALTER TABLE `schedule`
			DROP FOREIGN KEY `schedule_created_by_user_id`,
			DROP COLUMN `created_by_user_id`");
	}
}

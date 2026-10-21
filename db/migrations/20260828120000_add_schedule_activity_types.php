<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScheduleActivityTypes extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("INSERT IGNORE INTO `admin_activity_type` (`id`, `name`) VALUES
				(33, 'Add to Schedule'),
				(34, 'Cancel Schedule')");
	}

	public function down(): void
	{
			$this->execute("DELETE FROM `admin_activity_type` WHERE `id` IN (33, 34)");
	}
}

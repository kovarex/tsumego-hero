<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScheduleActivityTypes extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("INSERT IGNORE INTO `admin_activity_type` (`id`, `name`) VALUES
			(31, 'Schedule Add'),
			(32, 'Schedule Cancel')");
	}

	public function down(): void
	{
		$this->execute("DELETE FROM `admin_activity` WHERE type IN (31, 32)");
		$this->execute("DELETE FROM `admin_activity_type` WHERE id IN (31, 32)");
	}
}

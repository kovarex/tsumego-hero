<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * A forced display name change is recorded in the admin activity trail
 * as AdminActivityType::DISPLAY_NAME_CHANGE, which needs its type row.
 */
final class AddDisplayNameChangeActivityType extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("INSERT IGNORE INTO `admin_activity_type` (`id`, `name`) VALUES
			(32, 'Display Name Change')");
	}

	public function down(): void
	{
		$this->execute("DELETE FROM `admin_activity_type` WHERE `id` = 32");
	}
}

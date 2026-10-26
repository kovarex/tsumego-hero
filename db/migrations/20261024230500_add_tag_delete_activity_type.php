<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Deleting a tag is recorded in the admin activity trail as
 * AdminActivityType::DELETE_TAG, which needs its type row.
 */
final class AddTagDeleteActivityType extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("INSERT IGNORE INTO `admin_activity_type` (`id`, `name`) VALUES
			(44, 'Tag Delete')");
	}

	public function down(): void
	{
		$this->execute("DELETE FROM `admin_activity_type` WHERE `id` = 44");
	}
}

<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveSetDifficulty extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `set` DROP COLUMN difficulty");
		$this->execute("ALTER TABLE `progress_deletion` ADD `partition` INT UNSIGNED NOT NULL AFTER `set_id`");
		$this->execute("UPDATE progress_deletion SET `partition` = 1");
	}
}

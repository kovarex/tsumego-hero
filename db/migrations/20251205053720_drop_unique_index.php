<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUniqueIndex extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE `achievement_condition` DROP INDEX `user_id_set_id`");
    }
}

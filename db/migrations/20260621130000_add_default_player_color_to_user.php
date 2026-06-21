<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDefaultPlayerColorToUser extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `user` ADD `default_player_color` TINYINT NOT NULL DEFAULT 0 COMMENT '0=random, 1=black, 2=white';");
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE `user` DROP COLUMN `default_player_color`;');
    }
}

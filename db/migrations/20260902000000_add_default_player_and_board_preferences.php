<?php

use Phinx\Migration\AbstractMigration;

class AddDefaultPlayerAndBoardPreferences extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `user` ADD `pref_player_color` TINYINT NOT NULL DEFAULT 0 COMMENT '0=random, 1=from original';");
		$this->execute("ALTER TABLE `user` ADD `pref_board_orientation` TINYINT NOT NULL DEFAULT 0 COMMENT '0=random, 1=from original';");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `user` DROP COLUMN `pref_board_orientation`;');
		$this->execute('ALTER TABLE `user` DROP COLUMN `pref_player_color`;');
	}
}

<?php

use Phinx\Migration\AbstractMigration;

class AddReadingPreferences extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `user` ADD `pref_reading_played` TINYINT NOT NULL DEFAULT 2 COMMENT '0=normal, 1=glass, 2=hidden; applies once a move is played';");
		$this->execute("ALTER TABLE `user` ADD `pref_reading_setup` TINYINT NOT NULL DEFAULT 0 COMMENT '0=normal, 1=glass, 2=hidden';");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `user` DROP COLUMN `pref_reading_setup`;');
		$this->execute('ALTER TABLE `user` DROP COLUMN `pref_reading_played`;');
	}
}

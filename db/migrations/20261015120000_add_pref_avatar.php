<?php

use Phinx\Migration\AbstractMigration;

class AddPrefAvatar extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `user` ADD `pref_avatar` TINYINT NOT NULL DEFAULT 0 COMMENT '0=go board, 1=gravatar, 2=google picture';");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `user` DROP COLUMN `pref_avatar`;');
	}
}

<?php

use Phinx\Migration\AbstractMigration;

class AddDateFormatPreference extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `user` ADD `pref_date_format` TINYINT NOT NULL DEFAULT 0 COMMENT '0=ISO 2026-10-21, 1=21. 10. 2026, 2=10/21/2026, 3=browser language';");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `user` DROP COLUMN `pref_date_format`;');
	}
}

<?php

use Phinx\Migration\AbstractMigration;

//Whoever saves a new version of a problem in the board editor says what they changed
//and why, so the admin reviewing a proposal, and anyone reading the history later,
//knows what to look for.
class AddSgfDescription extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `sgf` ADD `description` VARCHAR(1000) NULL DEFAULT NULL COMMENT 'What the author changed in this version and why, NULL for versions saved before it was asked'");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `sgf` DROP `description`');
	}
}

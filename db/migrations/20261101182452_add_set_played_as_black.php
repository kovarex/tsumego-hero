<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The player is always black in the problems of a set with played_as_black, whatever the player's
//color setting, because their questions name a color. These were the sets of the constant
//Play::SETS_PLAYED_AS_BLACK, which this column replaces.
final class AddSetPlayedAsBlack extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('ALTER TABLE `set` ADD COLUMN played_as_black TINYINT(1) NOT NULL DEFAULT 0');
		$this->execute('UPDATE `set` SET played_as_black = 1 WHERE id IN (109, 233, 236, 239, 243, 244, 246, 251, 253)');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `set` DROP COLUMN played_as_black');
	}
}

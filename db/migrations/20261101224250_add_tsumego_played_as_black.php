<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The player is always black in a problem with played_as_black, whatever the player's color setting
//and whichever collection it is played from, because its question names a color. These were the
//problems of the sets in the constant Play::SETS_PLAYED_AS_BLACK, which this column replaces.
final class AddTsumegoPlayedAsBlack extends AbstractMigration
{
	private const SETS = [109, 233, 236, 239, 243, 244, 246, 251, 253];

	public function up(): void
	{
		$this->execute("ALTER TABLE tsumego ADD COLUMN played_as_black TINYINT(1) NOT NULL DEFAULT 0
			COMMENT 'The question names a color, so the player always plays black in the original colors.'");
		$this->execute('UPDATE tsumego SET played_as_black = 1
			WHERE id IN (SELECT tsumego_id FROM set_connection WHERE set_id IN (' . implode(', ', self::SETS) . '))');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE tsumego DROP COLUMN played_as_black');
	}
}

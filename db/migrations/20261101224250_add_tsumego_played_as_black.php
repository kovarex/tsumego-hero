<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//Playing as black is a property of the problem: a real game position or a fuseki problem, whose
//colors must not switch, whatever the player's color setting and whichever collection it is played
//from. These were the problems of the sets in the constant Play::SETS_PLAYED_AS_BLACK, which this
//column replaces.
final class AddTsumegoPlayedAsBlack extends AbstractMigration
{
	private const SETS = [109, 233, 236, 239, 243, 244, 246, 251, 253];

	public function up(): void
	{
		$this->execute("ALTER TABLE tsumego ADD COLUMN played_as_black TINYINT(1) NOT NULL DEFAULT 0
			COMMENT 'A real game position or fuseki problem whose colors must not switch: the player always plays black, in any collection.'");
		$this->execute('UPDATE tsumego SET played_as_black = 1
			WHERE id IN (SELECT tsumego_id FROM set_connection WHERE set_id IN (' . implode(', ', self::SETS) . '))');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE tsumego DROP COLUMN played_as_black');
	}
}

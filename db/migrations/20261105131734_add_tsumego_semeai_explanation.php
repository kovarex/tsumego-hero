<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//A semeai problem whose liberties aren't obvious says how to count them, after the explanation the
//play page computes (webroot/js/multipleChoice.js). Problem 29824 had its hint written in that code.
final class AddTsumegoSemeaiExplanation extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE tsumego_semeai ADD COLUMN explanation TEXT NULL DEFAULT NULL
			COMMENT 'Shown after the computed explanation, for problems whose liberties are not obvious; plain text'");
		$this->execute("UPDATE tsumego_semeai SET explanation = 'White can play 2 under the stones tesujis to gain 2 more liberties.' WHERE tsumego_id = 29824");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE tsumego_semeai DROP COLUMN explanation');
	}
}

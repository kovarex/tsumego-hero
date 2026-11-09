<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//A challenge can have a name its creator gives it ("Čajovna", "At home"), shown wherever the challenge is
//named instead of "Challenge #<id>"; NULL when it has none (ChallengeTable::title())
final class AddChallengeName extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE challenge ADD COLUMN IF NOT EXISTS name VARCHAR(100) NULL DEFAULT NULL
			COMMENT 'The name the creator gave the challenge, NULL when it has none' AFTER id");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE challenge DROP COLUMN IF EXISTS name');
	}
}

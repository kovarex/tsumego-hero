<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//Drops tsumego.pass, which said whether the play page showed the Pass button (now Tenuki) on a problem. The button
//is on every problem now, so that a problem doesn't tell by its button whether playing elsewhere is the answer.
//down() brings the column back with every problem off, as the choices made per problem are gone.
final class DropTsumegoPass extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('ALTER TABLE tsumego DROP COLUMN IF EXISTS pass');
	}

	public function down(): void
	{
		$this->execute("ALTER TABLE tsumego
			ADD COLUMN IF NOT EXISTS pass tinyint(1) NOT NULL DEFAULT 0 COMMENT 'is pass enabled'");
	}
}

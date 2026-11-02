<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The alternative response mode is on for every problem: the board makes the player answer every reply in
//the tree. The public problems that had it off were checked for how many lines it adds first.
final class RemoveAlternativeResponse extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('ALTER TABLE tsumego DROP COLUMN alternative_response');
	}

	//Every problem comes back with the mode on, the ones that had it off aren't known any more
	public function down(): void
	{
		$this->execute("ALTER TABLE tsumego ADD alternative_response tinyint(1) NOT NULL DEFAULT 1 COMMENT 'If the user has to counter all possible countrplays.'");
	}
}

<?php

use Phinx\Migration\AbstractMigration;

//The browser timings of #726 are reverted: their table goes, wherever it was made.
class DropRumSample extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('DROP TABLE IF EXISTS `rum_sample`');
	}

	//The timings are gone with their code, there is nothing to bring back
	public function down(): void
	{
	}
}

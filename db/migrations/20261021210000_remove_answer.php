<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The answer table was never about answers: two old cron routines used it as a
//scratch counter (sign-ups per day, a paging cursor), and an admin page listed it.
//All of that code is gone, so the table goes too. Guarded so it does nothing where
//the table never existed.
final class RemoveAnswer extends AbstractMigration
{
	public function up(): void
	{
		if ($this->hasTable('answer'))
			$this->table('answer')->drop()->save();
	}

	public function down(): void {}
}

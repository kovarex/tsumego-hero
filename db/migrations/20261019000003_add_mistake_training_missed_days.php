<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//A run of missed days of the mistake training is one row of mistake_training_day
//instead of a row per day: the row of its first day says how many days it lasted.
//Players often stay away for weeks, and a streak needs only to know that the run
//was there.
final class AddMistakeTrainingMissedDays extends AbstractMigration
{
	public function change(): void
	{
		$this->table('mistake_training_day')
			->addColumn('missed_days', 'smallinteger', [
				'signed' => false,
				'null' => false,
				'default' => 0,
				'after' => 'cleared_at',
				'comment' => 'Days missed in a row from this one, for a run that waited for a late review',
			])
			->update();
	}
}

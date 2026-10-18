<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The day of the last training review of a problem and how it went: 'S' solved,
 * 'F' failed and not solved yet, 'G' graduated. A training day is a fixed list of
 * problems, so a problem solved today stays in it as done, and a problem failed
 * today stays due until it is solved instead of disappearing until tomorrow.
 */
final class AddMistakeTrainingReviewDay extends AbstractMigration
{
	public function up(): void
	{
		$this->table('mistake_training_pool')
			->addColumn('last_review_day', 'date', ['null' => true, 'after' => 'next_due'])
			->addColumn('last_review_result', 'string', ['limit' => 1, 'null' => true, 'after' => 'last_review_day'])
			->update();
	}

	public function down(): void
	{
		$this->table('mistake_training_pool')
			->removeColumn('last_review_result')
			->removeColumn('last_review_day')
			->update();
	}
}

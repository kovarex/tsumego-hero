<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The day of the last training review of a problem and how it went: 'S' solved,
 * 'F' failed and not solved yet, 'G' graduated. A training day is a fixed list of
 * problems, so a problem solved today stays in it as done, and a problem failed
 * today stays due until it is solved instead of disappearing until tomorrow.
 *
 * And the number of lapses of a problem, the mistakes made on it since it came
 * into the training: each one makes its waits grow more slowly, so a problem that
 * keeps going wrong comes back more often than the ladder alone would bring it.
 */
final class AddMistakeTrainingReviewDay extends AbstractMigration
{
	public function up(): void
	{
		$this->table('mistake_training_pool')
			->addColumn('lapses', 'integer', ['signed' => false, 'default' => 0, 'after' => 'rung'])
			->addColumn('last_review_day', 'date', ['null' => true, 'after' => 'next_due'])
			->addColumn('last_review_result', 'string', ['limit' => 1, 'null' => true, 'after' => 'last_review_day'])
			->update();
	}

	public function down(): void
	{
		$this->table('mistake_training_pool')
			->removeColumn('last_review_result')
			->removeColumn('last_review_day')
			->removeColumn('lapses')
			->update();
	}
}

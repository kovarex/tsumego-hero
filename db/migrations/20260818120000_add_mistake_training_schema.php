<?php

use Phinx\Migration\AbstractMigration;

class AddMistakeTrainingSchema extends AbstractMigration
{
	public function change(): void
	{
		$table = $this->table('tsumego_status');
		$table->addColumn('mt_due', 'datetime', [
			'null' => true,
			'default' => null,
			'comment' => 'Next mistake training review, NULL = not in training',
			'after' => 'status',
		]);
		$table->addIndex(['user_id', 'mt_due'], ['name' => 'idx_mt_due']);
		$table->update();

		$attempts = $this->table('tsumego_attempt');
		$attempts->addColumn('mode', 'integer', [
			'null' => true,
			'default' => null,
			'comment' => 'Mode the attempt was made in (1 level, 2 rating, 3 time, 5 mistake training); NULL = legacy rows',
			'after' => 'misplays',
		]);
		$attempts->update();
	}
}

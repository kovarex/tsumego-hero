<?php

use Phinx\Migration\AbstractMigration;

class AddMtDueToTsumegoStatus extends AbstractMigration
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
	}
}

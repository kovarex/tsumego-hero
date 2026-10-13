<?php

use Phinx\Migration\AbstractMigration;

class AddMistakeTrainingSchema extends AbstractMigration
{
	public function up(): void
	{
		$table = $this->table('mistake_training_pool');
		$table->addColumn('user_id', 'integer', ['signed' => false])
			->addColumn('tsumego_id', 'integer', ['signed' => false])
			->addColumn('rung', 'integer', ['default' => 0])
			->addColumn('next_due', 'datetime')
			->addColumn('created', 'datetime')
			->addColumn('modified', 'datetime')
			->addIndex(['user_id', 'tsumego_id'], ['unique' => true, 'name' => 'idx_pool_user_tsumego'])
			->addIndex(['user_id', 'next_due'], ['name' => 'idx_pool_due'])
			->create();
	}

	public function down(): void
	{
		$this->table('mistake_training_pool')->drop()->save();
	}
}

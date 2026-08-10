<?php

use Phinx\Migration\AbstractMigration;

class SlimDayRecord extends AbstractMigration
{
	public function up(): void
	{
		$table = $this->table('day_record');
		$table->removeColumn('solved')
			->removeColumn('usercount')
			->removeColumn('visitedproblems')
			->removeColumn('tsumego_count')
			->save();
	}

	public function down(): void
	{
		$table = $this->table('day_record');
		$table->addColumn('solved', 'integer', ['null' => true, 'after' => 'date'])
			->addColumn('usercount', 'integer', ['null' => true, 'after' => 'solved'])
			->addColumn('visitedproblems', 'integer', ['null' => false, 'default' => 0, 'after' => 'usercount'])
			->addColumn('tsumego_count', 'integer', ['null' => false, 'signed' => false, 'after' => 'visitedproblems'])
			->save();
	}
}

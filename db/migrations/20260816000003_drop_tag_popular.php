<?php

use Phinx\Migration\AbstractMigration;

class DropTagPopular extends AbstractMigration
{
	public function up(): void
	{
		$this->table('tag')->removeColumn('popular')->save();
	}

	public function down(): void
	{
		$this->table('tag')->addColumn('popular', 'integer', ['null' => false, 'default' => 0, 'after' => 'name'])->save();
	}
}

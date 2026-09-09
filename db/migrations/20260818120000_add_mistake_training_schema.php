<?php

use Phinx\Migration\AbstractMigration;

class AddMistakeTrainingSchema extends AbstractMigration
{
	public function up(): void
	{
		$table = $this->table('tsumego_status');
		$table->addColumn('mistake_training_due', 'datetime', [
			'null' => true,
			'default' => null,
			'comment' => 'Next mistake training review, NULL = not in training',
			'after' => 'status',
		]);
		$table->addIndex(['user_id', 'mistake_training_due'], ['name' => 'idx_mistake_training_due']);
		$table->update();

		// Backfill pre-existing rows to level (the only mode that resumes its
		// buffer), then drop the default so the app is forced to supply mode.
		$this->execute('ALTER TABLE tsumego_attempt ADD COLUMN mode INT NOT NULL DEFAULT 1 AFTER misplays');
		$this->execute("ALTER TABLE tsumego_attempt MODIFY COLUMN mode INT NOT NULL COMMENT 'Mode the attempt was made in (1 level, 2 rating, 3 time, 5 mistake training); pre-column rows backfill to level' AFTER misplays");
		$this->execute('ALTER TABLE tsumego_attempt ALTER COLUMN mode DROP DEFAULT');
	}

	public function down(): void
	{
		$table = $this->table('tsumego_status');
		$table->removeIndexByName('idx_mistake_training_due');
		$table->removeColumn('mistake_training_due');
		$table->update();

		$this->execute('ALTER TABLE tsumego_attempt DROP COLUMN mode');
	}
}

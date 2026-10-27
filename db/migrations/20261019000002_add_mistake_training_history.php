<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The history of the mistake training, which the pool does not keep: it only knows
//where each problem stands now. mistake_training_review logs every review, a
//result on a problem that was due, with the rung it moved from and to and the XP
//it earned, which is also the data to fit the intervals on later.
//mistake_training_day sums up a player's training day for the profile calendar,
//and remembers that the day was cleared, so its bonus is paid once.
final class AddMistakeTrainingHistory extends AbstractMigration
{
	public function up(): void
	{
		$this->table('mistake_training_review', ['signed' => false])
			->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
			->addColumn('tsumego_id', 'integer', ['signed' => false, 'null' => false])
			->addColumn('day', 'date', ['null' => false, 'comment' => 'The training day of the review'])
			->addColumn('result', 'string', ['limit' => 1, 'null' => false, 'comment' => 'S solved, F missed, G graduated'])
			->addColumn('rung_before', 'smallinteger', ['signed' => false, 'null' => false])
			->addColumn('rung_after', 'smallinteger', ['signed' => false, 'null' => false])
			->addColumn('xp', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
			->addColumn('created', 'datetime', ['null' => false])
			->addIndex(['user_id', 'day'], ['name' => 'idx_review_user_day'])
			->addForeignKey('user_id', 'user', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'mistake_training_review_user_id'])
			->addForeignKey('tsumego_id', 'tsumego', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'mistake_training_review_tsumego_id'])
			->create();

		$this->table('mistake_training_day', ['signed' => false])
			->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
			->addColumn('day', 'date', ['null' => false])
			->addColumn('due', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 0, 'comment' => 'Problems in the day\'s training'])
			->addColumn('reviewed', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 0, 'comment' => 'Problems reviewed that day'])
			->addColumn('cleared', 'boolean', ['null' => false, 'default' => false])
			->addColumn('xp', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
			->addColumn('cleared_at', 'datetime', ['null' => true])
			->addIndex(['user_id', 'day'], ['unique' => true, 'name' => 'idx_day_user_day'])
			->addForeignKey('user_id', 'user', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'mistake_training_day_user_id'])
			->create();
	}

	public function down(): void
	{
		$this->table('mistake_training_day')->drop()->save();
		$this->table('mistake_training_review')->drop()->save();
	}
}

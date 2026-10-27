<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The pool table was created with the default of Phinx 0.16, which makes every
//column nullable, so a row could miss its player or its problem, and the unique
//index on (user_id, tsumego_id) does not stop duplicates once one of them is NULL.
//The columns become NOT NULL, except next_due, where NULL means graduated: the row
//stays as the memory that the training knows the problem. The rung and the lapses
//are small counts, and the rows follow their player and problem when those are
//deleted, like the other tables of a player.
final class TightenMistakeTrainingPool extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('DELETE p FROM mistake_training_pool p'
			. ' LEFT JOIN user u ON u.id = p.user_id'
			. ' LEFT JOIN tsumego t ON t.id = p.tsumego_id'
			. ' WHERE u.id IS NULL OR t.id IS NULL');
		$this->execute('UPDATE mistake_training_pool SET rung = 0 WHERE rung IS NULL OR rung < 0');
		$this->execute('UPDATE mistake_training_pool SET created = NOW() WHERE created IS NULL');
		$this->execute('UPDATE mistake_training_pool SET modified = created WHERE modified IS NULL');

		$this->table('mistake_training_pool')
			->changeColumn('user_id', 'integer', ['signed' => false, 'null' => false])
			->changeColumn('tsumego_id', 'integer', ['signed' => false, 'null' => false])
			->changeColumn('rung', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 0])
			->changeColumn('lapses', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 0])
			->changeColumn('next_due', 'datetime', ['null' => true, 'comment' => 'NULL: graduated, no next review'])
			->changeColumn('created', 'datetime', ['null' => false])
			->changeColumn('modified', 'datetime', ['null' => false])
			->addForeignKey('user_id', 'user', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'mistake_training_pool_user_id'])
			->addForeignKey('tsumego_id', 'tsumego', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'mistake_training_pool_tsumego_id'])
			->update();
	}

	public function down(): void
	{
		$this->table('mistake_training_pool')
			->dropForeignKey('user_id', 'mistake_training_pool_user_id')
			->dropForeignKey('tsumego_id', 'mistake_training_pool_tsumego_id')
			->update();
		$this->table('mistake_training_pool')
			->changeColumn('user_id', 'integer', ['signed' => false])
			->changeColumn('tsumego_id', 'integer', ['signed' => false])
			->changeColumn('rung', 'integer', ['default' => 0])
			->changeColumn('lapses', 'integer', ['signed' => false, 'default' => 0])
			->changeColumn('next_due', 'datetime')
			->changeColumn('created', 'datetime')
			->changeColumn('modified', 'datetime')
			->update();
	}
}

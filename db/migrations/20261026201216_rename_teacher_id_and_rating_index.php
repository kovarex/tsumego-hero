<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//classroom.teacher_id becomes teacher_user_id, as the rest of the schema names a reference to a
//player, and the index on tsumego.rating is named rating instead of elo_index.
final class RenameTeacherIdAndRatingIndex extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('ALTER TABLE classroom DROP FOREIGN KEY classroom_teacher_id');
		$this->execute('ALTER TABLE classroom
			CHANGE teacher_id teacher_user_id int(10) unsigned NOT NULL,
			RENAME INDEX teacher_id TO teacher_user_id,
			ADD CONSTRAINT classroom_teacher_user_id FOREIGN KEY (teacher_user_id) REFERENCES user (id) ON DELETE CASCADE ON UPDATE CASCADE');
		$this->execute('ALTER TABLE tsumego RENAME INDEX elo_index TO rating');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE tsumego RENAME INDEX rating TO elo_index');
		$this->execute('ALTER TABLE classroom DROP FOREIGN KEY classroom_teacher_user_id');
		$this->execute('ALTER TABLE classroom
			CHANGE teacher_user_id teacher_id int(10) unsigned NOT NULL,
			RENAME INDEX teacher_user_id TO teacher_id,
			ADD CONSTRAINT classroom_teacher_id FOREIGN KEY (teacher_id) REFERENCES user (id) ON DELETE CASCADE ON UPDATE CASCADE');
	}
}

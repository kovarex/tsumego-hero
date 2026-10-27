<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The last types the database review found out of line with the rest of the schema:
//- tsumego_comment.deleted and tsumego_issue.deleted hold the time of the deletion, like
//  tsumego.deleted, set.deleted and user.deleted, instead of a flag. The rows deleted before keep
//  the time of this migration, their real one is not known.
//- admin_activity.created is a DATETIME like every other time, not a TIMESTAMP (the year 2038 and
//  the conversion to the time zone of the connection). The conversion keeps the time the
//  connection showed.
//- tsumego.author has no default anymore: a problem saved without an author was credited to
//  Joschka Zimdars, now the database refuses it. Both ways of creating a problem fill it.
//- set_connection, achievement_status and progress_deletion get unsigned ids like the other
//  tables, and so does classroom_assignment_problem.set_connection_id, which references one.
final class UnifyDeletedTimesAndUnsignedIds extends AbstractMigration
{
	//table => what the column says
	private const DELETED_COLUMNS = [
		'tsumego_comment' => 'When the comment was deleted, NULL when it is not.',
		'tsumego_issue' => 'When the issue was deleted, NULL when it is not.',
	];

	//The tables whose id becomes unsigned
	private const UNSIGNED_IDS = ['set_connection', 'achievement_status', 'progress_deletion'];

	public function up(): void
	{
		foreach (self::DELETED_COLUMNS as $table => $comment)
		{
			$this->execute("ALTER TABLE $table
				CHANGE deleted deleted_flag tinyint(1) NOT NULL DEFAULT 0,
				ADD deleted datetime DEFAULT NULL COMMENT " . $this->getAdapter()->getConnection()->quote($comment) . ' AFTER deleted_flag');
			$this->execute("UPDATE $table SET deleted = NOW() WHERE deleted_flag");
			$this->execute("ALTER TABLE $table DROP COLUMN deleted_flag");
		}

		$this->execute('ALTER TABLE admin_activity MODIFY created datetime NOT NULL DEFAULT current_timestamp()');
		$this->execute('ALTER TABLE tsumego ALTER author DROP DEFAULT');

		$this->execute('ALTER TABLE classroom_assignment_problem DROP FOREIGN KEY classroom_assignment_problem_set_connection_id');
		foreach (self::UNSIGNED_IDS as $table)
			$this->execute("ALTER TABLE $table MODIFY id int(10) unsigned NOT NULL AUTO_INCREMENT");
		$this->execute('ALTER TABLE classroom_assignment_problem
			MODIFY set_connection_id int(10) unsigned NOT NULL,
			ADD CONSTRAINT classroom_assignment_problem_set_connection_id FOREIGN KEY (set_connection_id) REFERENCES set_connection (id) ON DELETE CASCADE ON UPDATE CASCADE');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE classroom_assignment_problem DROP FOREIGN KEY classroom_assignment_problem_set_connection_id');
		foreach (self::UNSIGNED_IDS as $table)
			$this->execute("ALTER TABLE $table MODIFY id int(11) NOT NULL AUTO_INCREMENT");
		$this->execute("ALTER TABLE classroom_assignment_problem
			MODIFY set_connection_id int(11) NOT NULL COMMENT 'Signed like set_connection.id.',
			ADD CONSTRAINT classroom_assignment_problem_set_connection_id FOREIGN KEY (set_connection_id) REFERENCES set_connection (id) ON DELETE CASCADE ON UPDATE CASCADE");

		$this->execute("ALTER TABLE tsumego ALTER author SET DEFAULT 'Joschka Zimdars'");
		$this->execute('ALTER TABLE admin_activity MODIFY created timestamp NOT NULL DEFAULT current_timestamp()');

		foreach (array_keys(self::DELETED_COLUMNS) as $table)
		{
			$this->execute("ALTER TABLE $table
				CHANGE deleted deleted_time datetime DEFAULT NULL,
				ADD deleted tinyint(1) NOT NULL DEFAULT 0 AFTER deleted_time");
			$this->execute("UPDATE $table SET deleted = 1 WHERE deleted_time IS NOT NULL");
			$this->execute("ALTER TABLE $table DROP COLUMN deleted_time");
		}
	}
}

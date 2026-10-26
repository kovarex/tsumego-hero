<?php

use Phinx\Migration\AbstractMigration;

/**
 * What a pupil did with each problem of an assignment, kept for the assignment itself. A problem
 * solved before keeps its status when it is played again, and the attempts don't record such a
 * replay, so the assignment counts its results on its own: the misplays and the first solve.
 *
 * The solves since an assignment was published, which were read from the attempts until now,
 * are copied in.
 */
class AddClassroomAssignmentResults extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `classroom_assignment_result` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `classroom_assignment_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tsumego_id` int(10) UNSIGNED NOT NULL,
  `misplays` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `solved` datetime DEFAULT NULL COMMENT 'The first solve for the assignment, NULL while not solved.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_assignment_id_user_id_tsumego_id` (`classroom_assignment_id`, `user_id`, `tsumego_id`),
  KEY `user_id` (`user_id`),
  KEY `tsumego_id` (`tsumego_id`),
  CONSTRAINT `classroom_assignment_result_assignment_id` FOREIGN KEY (`classroom_assignment_id`) REFERENCES `classroom_assignment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `classroom_assignment_result_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `classroom_assignment_result_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

		$this->execute('INSERT INTO classroom_assignment_result (classroom_assignment_id, user_id, tsumego_id, misplays, solved)
SELECT a.id, ta.user_id, ta.tsumego_id, SUM(ta.misplays), MIN(CASE WHEN ta.solved = 1 THEN ta.created END)
FROM classroom_assignment a
JOIN classroom_assignment_problem p ON p.classroom_assignment_id = a.id
JOIN set_connection sc ON sc.id = p.set_connection_id
JOIN classroom_member m ON m.classroom_id = a.classroom_id AND m.classroom_member_status_id = 3
JOIN tsumego_attempt ta ON ta.user_id = m.user_id AND ta.tsumego_id = sc.tsumego_id AND ta.created >= a.published
WHERE a.published IS NOT NULL
GROUP BY a.id, ta.user_id, ta.tsumego_id;');
	}

	public function down(): void
	{
		$this->execute('DROP TABLE `classroom_assignment_result`;');
	}
}

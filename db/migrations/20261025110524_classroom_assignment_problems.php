<?php

use Phinx\Migration\AbstractMigration;

/**
 * An assignment of a class becomes a pick of problems from any collections, which the teacher
 * builds up as a draft and publishes once happy with it, in place of one collection or a numbered
 * part of it. The problems are rows of classroom_assignment_problem; published is the moment the
 * pupils got the assignment, NULL while it is a draft.
 *
 * An assignment from before keeps its problems: the ones of its collection with the numbers it
 * gave, and it counts as published when it was made.
 */
class ClassroomAssignmentProblems extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `classroom_assignment_problem` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `classroom_assignment_id` int(10) UNSIGNED NOT NULL,
  `set_connection_id` int(10) UNSIGNED NOT NULL,
  `position` int(11) NOT NULL COMMENT 'The order of the problem in the assignment, from the smallest.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_assignment_id_set_connection_id` (`classroom_assignment_id`, `set_connection_id`),
  KEY `set_connection_id` (`set_connection_id`),
  CONSTRAINT `classroom_assignment_problem_assignment_id` FOREIGN KEY (`classroom_assignment_id`) REFERENCES `classroom_assignment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `classroom_assignment_problem_set_connection_id` FOREIGN KEY (`set_connection_id`) REFERENCES `set_connection` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

		$this->execute("INSERT INTO classroom_assignment_problem (classroom_assignment_id, set_connection_id, position)
SELECT a.id, sc.id, sc.num
FROM classroom_assignment a
JOIN set_connection sc ON sc.set_id = a.set_id
	AND (a.first_number IS NULL OR sc.num >= a.first_number)
	AND (a.last_number IS NULL OR sc.num <= a.last_number);");

		$this->execute("ALTER TABLE `classroom_assignment`
  ADD `title` varchar(100) DEFAULT NULL COMMENT 'The name the teacher gave it, NULL to name it by its collections.' AFTER `classroom_id`,
  ADD `published` datetime DEFAULT NULL COMMENT 'When the pupils got it, NULL while it is a draft.' AFTER `due`;");
		$this->execute('UPDATE classroom_assignment SET published = created;');
		$this->execute('ALTER TABLE `classroom_assignment`
  DROP FOREIGN KEY `classroom_assignment_set_id`,
  DROP KEY `set_id`,
  DROP `set_id`,
  DROP `first_number`,
  DROP `last_number`;');
	}

	/**
	 * Back to one collection per assignment: the collection of its first problem with the numbers
	 * it has there. A draft and an assignment without problems are gone.
	 */
	public function down(): void
	{
		$this->execute('DELETE FROM classroom_assignment WHERE published IS NULL
  OR NOT EXISTS (SELECT 1 FROM classroom_assignment_problem p WHERE p.classroom_assignment_id = classroom_assignment.id);');
		$this->execute("ALTER TABLE `classroom_assignment`
  ADD `set_id` int(10) UNSIGNED DEFAULT NULL AFTER `classroom_id`,
  ADD `first_number` int(11) DEFAULT NULL COMMENT 'The first problem number of the collection assigned, NULL from its start.' AFTER `set_id`,
  ADD `last_number` int(11) DEFAULT NULL COMMENT 'The last problem number of the collection assigned, NULL to its end.' AFTER `first_number`;");
		$this->execute('UPDATE classroom_assignment a SET a.set_id = (
  SELECT sc.set_id FROM classroom_assignment_problem p JOIN set_connection sc ON sc.id = p.set_connection_id
  WHERE p.classroom_assignment_id = a.id ORDER BY p.position, p.id LIMIT 1);');
		$this->execute('UPDATE classroom_assignment a SET
  a.first_number = (SELECT MIN(sc.num) FROM classroom_assignment_problem p JOIN set_connection sc ON sc.id = p.set_connection_id
    WHERE p.classroom_assignment_id = a.id AND sc.set_id = a.set_id),
  a.last_number = (SELECT MAX(sc.num) FROM classroom_assignment_problem p JOIN set_connection sc ON sc.id = p.set_connection_id
    WHERE p.classroom_assignment_id = a.id AND sc.set_id = a.set_id);');
		$this->execute('ALTER TABLE `classroom_assignment`
  MODIFY `set_id` int(10) UNSIGNED NOT NULL,
  ADD KEY `set_id` (`set_id`),
  ADD CONSTRAINT `classroom_assignment_set_id` FOREIGN KEY (`set_id`) REFERENCES `set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  DROP `title`,
  DROP `published`;');
		$this->execute('DROP TABLE `classroom_assignment_problem`;');
	}
}

<?php

use Phinx\Migration\AbstractMigration;

//Classes of a teacher. A teacher invites a player or a player asks to join, and the other side
//accepts; the state of each membership is a row of classroom_member_status. The teacher assigns
//a collection (or a part of it) to the class with a due date, and sees who solved what.
class AddClassrooms extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `classroom` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `classroom_teacher_id` FOREIGN KEY (`teacher_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

		$this->execute("CREATE TABLE `classroom_member_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
		$this->execute("INSERT INTO `classroom_member_status` (`id`, `name`) VALUES
(1, 'invited'),
(2, 'requested'),
(3, 'accepted');");

		$this->execute("CREATE TABLE `classroom_member` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `classroom_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `classroom_member_status_id` int(10) UNSIGNED NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_id_user_id` (`classroom_id`, `user_id`),
  KEY `user_id_status` (`user_id`, `classroom_member_status_id`),
  CONSTRAINT `classroom_member_classroom_id` FOREIGN KEY (`classroom_id`) REFERENCES `classroom` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `classroom_member_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `classroom_member_status_id` FOREIGN KEY (`classroom_member_status_id`) REFERENCES `classroom_member_status` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

		$this->execute("CREATE TABLE `classroom_assignment` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `classroom_id` int(10) UNSIGNED NOT NULL,
  `set_id` int(10) UNSIGNED NOT NULL,
  `first_number` int(11) DEFAULT NULL COMMENT 'The first problem number of the collection assigned, NULL from its start.',
  `last_number` int(11) DEFAULT NULL COMMENT 'The last problem number of the collection assigned, NULL to its end.',
  `due` date NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `classroom_id` (`classroom_id`),
  KEY `set_id` (`set_id`),
  CONSTRAINT `classroom_assignment_classroom_id` FOREIGN KEY (`classroom_id`) REFERENCES `classroom` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `classroom_assignment_set_id` FOREIGN KEY (`set_id`) REFERENCES `set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
	}

	public function down(): void
	{
		$this->execute('DROP TABLE `classroom_assignment`;');
		$this->execute('DROP TABLE `classroom_member`;');
		$this->execute('DROP TABLE `classroom_member_status`;');
		$this->execute('DROP TABLE `classroom`;');
	}
}

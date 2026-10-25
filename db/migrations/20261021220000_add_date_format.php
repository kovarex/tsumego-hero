<?php

use Phinx\Migration\AbstractMigration;

/**
 * The format a player picked for the dates on the site, see app/shared/dates.ts. The
 * formats are rows of their own table, so a user can only hold one that exists.
 */
class AddDateFormat extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `date_format` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
		$this->execute("INSERT INTO `date_format` (`id`, `name`) VALUES
(1, 'ISO'),
(2, 'day. month. year'),
(3, 'month/day/year'),
(4, 'browser language');");
		$this->execute('ALTER TABLE `user` ADD `date_format_id` int(10) UNSIGNED NOT NULL DEFAULT 1;');
		$this->execute('ALTER TABLE `user` ADD CONSTRAINT `user_date_format_id` FOREIGN KEY (`date_format_id`) REFERENCES `date_format` (`id`) ON UPDATE CASCADE;');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `user` DROP FOREIGN KEY `user_date_format_id`;');
		$this->execute('ALTER TABLE `user` DROP COLUMN `date_format_id`;');
		$this->execute('DROP TABLE `date_format`;');
	}
}

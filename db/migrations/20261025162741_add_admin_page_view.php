<?php

use Phinx\Migration\AbstractMigration;

/**
 * When an admin last opened a page of the admin area that counts what is new since then (see
 * AdminPage): the admin activity and the uploads. The pages are rows of their own table, see the
 * AdminPage constants.
 */
class AddAdminPageView extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `admin_page` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
		$this->execute("INSERT INTO `admin_page` (`id`, `name`) VALUES
(1, 'Admin activity'),
(2, 'Uploads');");
		$this->execute("CREATE TABLE `admin_page_view` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `admin_page_id` int(10) UNSIGNED NOT NULL,
  `viewed` datetime NOT NULL,
  PRIMARY KEY (`user_id`, `admin_page_id`),
  KEY `admin_page_id` (`admin_page_id`),
  CONSTRAINT `admin_page_view_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `admin_page_view_admin_page_id` FOREIGN KEY (`admin_page_id`) REFERENCES `admin_page` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
	}

	public function down(): void
	{
		$this->execute('DROP TABLE `admin_page_view`;');
		$this->execute('DROP TABLE `admin_page`;');
	}
}

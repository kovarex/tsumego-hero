<?php

use Phinx\Migration\AbstractMigration;

/**
 * What a user may do on the site: a player, an admin, or an owner, who on top of everything an
 * admin does decides who is an admin or an owner. Replaces the isAdmin flag, the roles are rows of
 * their own table (see the User::ROLE_ constants). The first owner is kovarex.
 */
class AddUserRole extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `user_role` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
		$this->execute("INSERT INTO `user_role` (`id`, `name`) VALUES
(1, 'player'),
(2, 'admin'),
(3, 'owner');");
		$this->execute('ALTER TABLE `user` ADD `user_role_id` int(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `isAdmin`;');
		$this->execute('UPDATE `user` SET `user_role_id` = 2 WHERE `isAdmin` <> 0;');
		$this->execute('UPDATE `user` SET `user_role_id` = 3 WHERE `id` = 13282;'); // kovarex
		$this->execute('ALTER TABLE `user` ADD CONSTRAINT `user_user_role_id` FOREIGN KEY (`user_role_id`) REFERENCES `user_role` (`id`) ON UPDATE CASCADE;');
		$this->execute('ALTER TABLE `user` DROP COLUMN `isAdmin`;');
		$this->execute("INSERT IGNORE INTO `admin_activity_type` (`id`, `name`) VALUES (43, 'Role Change');");
	}

	public function down(): void
	{
		$this->execute('DELETE FROM `admin_activity` WHERE `type` = 43;');
		$this->execute('DELETE FROM `admin_activity_type` WHERE `id` = 43;');
		$this->execute('ALTER TABLE `user` ADD `isAdmin` tinyint(1) NOT NULL DEFAULT 0 AFTER `user_role_id`;');
		$this->execute('UPDATE `user` SET `isAdmin` = 1 WHERE `user_role_id` <> 1;');
		$this->execute('ALTER TABLE `user` DROP FOREIGN KEY `user_user_role_id`;');
		$this->execute('ALTER TABLE `user` DROP COLUMN `user_role_id`;');
		$this->execute('DROP TABLE `user_role`;');
	}
}

<?php

use Phinx\Migration\AbstractMigration;

//admin_page becomes admin_overview_type, the overviews of the admin area that count what is new since
//an admin last saw them, and admin_page_view becomes admin_overview_seen, when each admin saw each of them.
final class RenameAdminPageToAdminOverviewType extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('ALTER TABLE `admin_page_view` DROP FOREIGN KEY `admin_page_view_user_id`, DROP FOREIGN KEY `admin_page_view_admin_page_id`;');
		$this->execute('RENAME TABLE `admin_page` TO `admin_overview_type`, `admin_page_view` TO `admin_overview_seen`;');
		$this->execute('ALTER TABLE `admin_overview_seen`
  RENAME COLUMN `admin_page_id` TO `admin_overview_type_id`,
  RENAME COLUMN `viewed` TO `seen`,
  RENAME INDEX `admin_page_id` TO `admin_overview_type_id`;');
		$this->execute('ALTER TABLE `admin_overview_seen`
  ADD CONSTRAINT `admin_overview_seen_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admin_overview_seen_admin_overview_type_id` FOREIGN KEY (`admin_overview_type_id`) REFERENCES `admin_overview_type` (`id`) ON UPDATE CASCADE;');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `admin_overview_seen` DROP FOREIGN KEY `admin_overview_seen_user_id`, DROP FOREIGN KEY `admin_overview_seen_admin_overview_type_id`;');
		$this->execute('RENAME TABLE `admin_overview_type` TO `admin_page`, `admin_overview_seen` TO `admin_page_view`;');
		$this->execute('ALTER TABLE `admin_page_view`
  RENAME COLUMN `admin_overview_type_id` TO `admin_page_id`,
  RENAME COLUMN `seen` TO `viewed`,
  RENAME INDEX `admin_overview_type_id` TO `admin_page_id`;');
		$this->execute('ALTER TABLE `admin_page_view`
  ADD CONSTRAINT `admin_page_view_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admin_page_view_admin_page_id` FOREIGN KEY (`admin_page_id`) REFERENCES `admin_page` (`id`) ON UPDATE CASCADE;');
	}
}

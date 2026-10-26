<?php

use Phinx\Migration\AbstractMigration;

/**
 * An SGF version is proposed, accepted (one of the versions the problem had) or rejected. Rejected
 * versions used to be deleted; they are kept now, so the proposer can still see what they proposed.
 * Replaces the accepted flag, the statuses are rows of their own table (see the Sgf::STATUS_ constants).
 */
class AddSgfStatus extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `sgf_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
		$this->execute("INSERT INTO `sgf_status` (`id`, `name`) VALUES
(1, 'proposed'),
(2, 'accepted'),
(3, 'rejected');");
		// created was also updated on every change, so accepting a version moved it to the time of the decision
		$this->execute('ALTER TABLE `sgf` MODIFY `created` datetime DEFAULT current_timestamp();');
		$this->execute('ALTER TABLE `sgf` ADD `sgf_status_id` int(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `tsumego_id`;');
		$this->execute('UPDATE `sgf` SET `sgf_status_id` = IF(`accepted`, 2, 1);');
		$this->execute('ALTER TABLE `sgf` ADD CONSTRAINT `sgf_sgf_status_id` FOREIGN KEY (`sgf_status_id`) REFERENCES `sgf_status` (`id`) ON UPDATE CASCADE;');
		// the current version of a problem is the latest accepted one, this finds it from the index alone
		$this->execute('ALTER TABLE `sgf` ADD KEY `tsumego_id_sgf_status_id` (`tsumego_id`, `sgf_status_id`);');
		$this->execute('ALTER TABLE `sgf` DROP COLUMN `accepted`;');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `sgf` ADD `accepted` tinyint(1) NOT NULL DEFAULT 0;');
		$this->execute('UPDATE `sgf` SET `accepted` = (`sgf_status_id` = 2);');
		$this->execute('DELETE FROM `sgf` WHERE `sgf_status_id` = 3;');
		$this->execute('ALTER TABLE `sgf` DROP FOREIGN KEY `sgf_sgf_status_id`;');
		$this->execute('ALTER TABLE `sgf` DROP KEY `tsumego_id_sgf_status_id`;');
		$this->execute('ALTER TABLE `sgf` DROP COLUMN `sgf_status_id`;');
		$this->execute('ALTER TABLE `sgf` ALTER `accepted` DROP DEFAULT;');
		$this->execute('ALTER TABLE `sgf` MODIFY `created` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp();');
		$this->execute('DROP TABLE `sgf_status`;');
	}
}

<?php

use Phinx\Migration\AbstractMigration;

//A rejection remembers who decided and what they wrote the proposer, and for a rejected SGF
//proposal the kept version, so an admin can reopen it (AdminActivityType::REOPEN_PROPOSAL).
//Rejections from before have none of it.
class AddRejectDecision extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `reject`
  ADD `sgf_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'The rejected version of an SGF proposal' AFTER `tsumego_id`,
  ADD `rejected_by_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'The admin who rejected it' AFTER `user_id`,
  ADD `comment` varchar(2000) DEFAULT NULL COMMENT 'What the admin wrote the proposer, see ProposalFeedback' AFTER `rejected_by_user_id`;");
		$this->execute('ALTER TABLE `reject`
  ADD CONSTRAINT `reject_sgf_id` FOREIGN KEY (`sgf_id`) REFERENCES `sgf` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `reject_rejected_by_user_id` FOREIGN KEY (`rejected_by_user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;');
		$this->execute("INSERT IGNORE INTO `admin_activity_type` (`id`, `name`) VALUES (35, 'Reopen Proposal')");
	}

	public function down(): void
	{
		$this->execute('DELETE FROM `admin_activity` WHERE `type` = 35');
		$this->execute('DELETE FROM `admin_activity_type` WHERE `id` = 35');
		$this->execute('ALTER TABLE `reject` DROP FOREIGN KEY `reject_sgf_id`, DROP FOREIGN KEY `reject_rejected_by_user_id`;');
		$this->execute('ALTER TABLE `reject` DROP COLUMN `sgf_id`, DROP COLUMN `rejected_by_user_id`, DROP COLUMN `comment`;');
	}
}

<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Accounts are no longer deleted from the database, only marked as deleted (user.deleted), so their
 * sets, comments, tags and SGF edits stay, and an admin can restore the account.
 *
 * The name and Google id of a deleted account move to deleted_name and deleted_external_id, so they
 * are free for a new account; restoring moves them back. The email stays, emails don't need to be
 * unique.
 *
 * Replaces the old dbstorage column, which was reused as the "deletion requested" flag (1111); the
 * pending requests are carried out by deleting those accounts.
 */
final class SoftDeleteUsers extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `user`
			ADD COLUMN `deleted` DATETIME NULL DEFAULT NULL COMMENT 'When the account was deleted, NULL for active accounts.',
			ADD COLUMN `deleted_name` VARCHAR(50) NULL DEFAULT NULL COMMENT 'The name of a deleted account, kept to restore it.',
			ADD COLUMN `deleted_external_id` VARCHAR(200) NULL DEFAULT NULL COMMENT 'The Google id of a deleted account, kept to restore it.',
			ADD COLUMN `deletion_token` VARCHAR(64) NULL DEFAULT NULL COMMENT 'Secret of the emailed link confirming the account deletion.',
			ADD COLUMN `deletion_token_created` DATETIME NULL DEFAULT NULL,
			ADD INDEX `deleted` (`deleted`),
			ADD INDEX `deletion_token` (`deletion_token`)");
		// same as User::deleteAccount
		$this->execute("UPDATE `user` SET
			`deleted` = NOW(),
			`deleted_name` = `name`,
			`deleted_external_id` = `external_id`,
			`name` = CONCAT('[deleted ', `id`, ']'),
			`external_id` = NULL
			WHERE `dbstorage` = 1111");
		$this->execute("ALTER TABLE `user` DROP COLUMN `dbstorage`");
		$this->execute("INSERT IGNORE INTO `admin_activity_type` (`id`, `name`) VALUES (31, 'Restore User')");
	}

	public function down(): void
	{
		$this->execute("ALTER TABLE `user` ADD COLUMN `dbstorage` INT NOT NULL DEFAULT 0");
		$this->execute("UPDATE `user` SET
			`dbstorage` = 1111,
			`name` = `deleted_name`,
			`external_id` = `deleted_external_id`
			WHERE `deleted` IS NOT NULL");
		$this->execute("ALTER TABLE `user`
			DROP INDEX `deleted`,
			DROP INDEX `deletion_token`,
			DROP COLUMN `deletion_token`,
			DROP COLUMN `deletion_token_created`,
			DROP COLUMN `deleted`,
			DROP COLUMN `deleted_name`,
			DROP COLUMN `deleted_external_id`");
		$this->execute("DELETE FROM `admin_activity_type` WHERE `id` = 31");
	}
}

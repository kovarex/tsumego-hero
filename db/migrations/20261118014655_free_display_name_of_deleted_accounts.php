<?php

declare(strict_types=1);

use App\Utility\PhpbbNames;
use Phinx\Migration\AbstractMigration;

//A deleted account kept its display name, which is unique, so a new account of the same player got
//"Name (2)" and was asked to rename. The display name of a deleted account moves to
//deleted_display_name (for restoring), and the account gets the placeholder its login name has,
//'[deleted <id>]', on the site and in the forum, whose usernames are unique as well.
final class FreeDisplayNameOfDeletedAccounts extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `user`
			ADD COLUMN `deleted_display_name` VARCHAR(50) NULL DEFAULT NULL COMMENT 'The display name of a deleted account, kept to restore it.' AFTER `deleted_name`");
		// same as UserTable::deleteAccount
		$this->execute("UPDATE `user` SET
			`deleted_display_name` = `display_name`,
			`display_name` = CONCAT('[deleted ', `id`, ']')
			WHERE `deleted` IS NOT NULL");
		$this->syncForum();
	}

	public function down(): void
	{
		// a name a new account took in the meantime stays with it, the deleted account keeps the placeholder
		$this->execute("UPDATE `user` `deleted_user`
			LEFT JOIN `user` `other_user` ON `other_user`.`display_name` = `deleted_user`.`deleted_display_name` AND `other_user`.`id` <> `deleted_user`.`id`
			SET `deleted_user`.`display_name` = `deleted_user`.`deleted_display_name`
			WHERE `deleted_user`.`deleted` IS NOT NULL AND `deleted_user`.`deleted_display_name` IS NOT NULL AND `other_user`.`id` IS NULL");
		$this->syncForum();
		$this->execute("ALTER TABLE `user` DROP COLUMN `deleted_display_name`");
	}

	//Writes the display names of the deleted accounts into their forum accounts.
	private function syncForum(): void
	{
		if (!$this->hasTable('phpbb_users'))
			return;
		$connection = $this->getAdapter()->getConnection();
		foreach ($this->fetchAll("SELECT `id`, `display_name` FROM `user`
			WHERE `deleted` IS NOT NULL AND `id` IN (SELECT `external_id` FROM `phpbb_users`)") as $row)
			PhpbbNames::sync($connection, (int) $row['id'], $row['display_name']);
	}
}

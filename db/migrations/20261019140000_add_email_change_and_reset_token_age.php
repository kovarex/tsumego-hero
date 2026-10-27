<?php

use Phinx\Migration\AbstractMigration;

//The columns behind the two flows that hand a link to an e-mail address:
//- a changed address waits in unconfirmed_email until the player confirms it from
//  the link sent there, while the address that is given up gets a token that
//  cancels the change,
//- a password reset remembers when it was asked for, so its link stops working
//  after a while and a second mail is not sent right away.
//A link is opened by the value of its column and nothing else, so each of the three tokens is indexed.
class AddEmailChangeAndResetTokenAge extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `user`
			ADD `unconfirmed_email` VARCHAR(50) NULL DEFAULT NULL AFTER `email`,
			ADD `unconfirmed_email_token` VARCHAR(64) NULL DEFAULT NULL,
			ADD `unconfirmed_email_cancel_token` VARCHAR(64) NULL DEFAULT NULL,
			ADD `unconfirmed_email_created` DATETIME NULL DEFAULT NULL,
			ADD `passwordreset_created` DATETIME NULL DEFAULT NULL;");

		$this->execute('ALTER TABLE `user`
			ADD INDEX `unconfirmed_email_token_index` (`unconfirmed_email_token`),
			ADD INDEX `unconfirmed_email_cancel_token_index` (`unconfirmed_email_cancel_token`),
			ADD INDEX `passwordreset_index` (`passwordreset`);');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `user`
			DROP INDEX `unconfirmed_email_token_index`,
			DROP INDEX `unconfirmed_email_cancel_token_index`,
			DROP INDEX `passwordreset_index`;');

		$this->execute('ALTER TABLE `user`
			DROP COLUMN `unconfirmed_email`,
			DROP COLUMN `unconfirmed_email_token`,
			DROP COLUMN `unconfirmed_email_cancel_token`,
			DROP COLUMN `unconfirmed_email_created`,
			DROP COLUMN `passwordreset_created`;');
	}
}

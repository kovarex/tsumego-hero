<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LoginToken extends AbstractMigration
{
    public function change(): void
	{
		$this->execute("ALTER TABLE `user` ADD `login_token` VARCHAR(64) NULL DEFAULT NULL COMMENT 'Used to identify the login in the forums' AFTER `sprint_start`, ADD INDEX `login_token` (`login_token`)");
    }
}

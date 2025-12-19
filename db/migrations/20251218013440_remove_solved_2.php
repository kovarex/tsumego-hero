<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveSolved2 extends AbstractMigration
{
    public function change(): void
    {
		$this->execute("ALTER TABLE user DROP COLUMN solved2");
		$this->execute("ALTER TABLE `achievement_status` CHANGE `value` `value` INT NOT NULL DEFAULT '1'");
		$this->execute("UPDATE `achievement_status` SET `value` = 1 WHERE `value` = 0");
    }
}

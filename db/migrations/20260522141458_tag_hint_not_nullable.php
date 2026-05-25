<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TagHintNotNullable extends AbstractMigration
{
    public function change(): void
    {
		$this->execute("ALTER TABLE `tag` CHANGE `created` `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;");
		$this->execute("ALTER TABLE `tag` CHANGE `hint` `hint` INT(11) NOT NULL DEFAULT '0';");
		$this->execute("ALTER TABLE `tag` CHANGE `approved` `approved` INT(11) NOT NULL DEFAULT '1';");
		$this->execute("ALTER TABLE `tag` CHANGE `name` `name` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;");
		$this->execute("ALTER TABLE `tag` CHANGE `description` `description` VARCHAR(5000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;");
    }
}

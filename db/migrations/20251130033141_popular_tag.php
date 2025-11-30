<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PopularTag extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE `tag` ADD `popular` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'First 10 most used tags has this setup to 1 in the cron' AFTER `created`, ADD INDEX `popular` (`popular`); ");
    }
}

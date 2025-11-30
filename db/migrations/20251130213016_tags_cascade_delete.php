<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TagsCascadeDelete extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE `tag_connection` DROP FOREIGN KEY `tag_connection_tsumego_id`; ALTER TABLE `tag_connection` ADD CONSTRAINT `tag_connection_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego`(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    }
}

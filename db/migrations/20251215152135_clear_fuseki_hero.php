<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ClearFusekiHero extends AbstractMigration
{
    public function change(): void
    {
		$this->execute("ALTER TABLE `schedule` DROP FOREIGN KEY `schedule_tsumego_id`; ALTER TABLE `schedule` ADD CONSTRAINT `schedule_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego`(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    }
}

<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ScheduleForeignKeyCascades extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("
ALTER TABLE `schedule` DROP FOREIGN KEY `schedule_set_id`;
ALTER TABLE `schedule` ADD CONSTRAINT `schedule_set_id` FOREIGN KEY (`set_id`) REFERENCES `set`(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    }
}

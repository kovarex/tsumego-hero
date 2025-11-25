<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ScheduleForeignKeys extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("DELETE `schedule`.* FROM `schedule` LEFT JOIN tsumego ON `schedule`.tsumego_id=tsumego.id WHERE tsumego.id is null");
		$this->execute("ALTER TABLE `schedule` CHANGE `tsumego_id` `tsumego_id` INT UNSIGNED NULL DEFAULT NULL");
		$this->execute("ALTER TABLE `schedule` ADD CONSTRAINT `schedule_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");

		$this->execute("DELETE `schedule`.* FROM `schedule` LEFT JOIN `set` ON `schedule`.set_id=`set`.id WHERE `set`.id is null");
		$this->execute("ALTER TABLE `schedule` CHANGE `set_id` `set_id` INT UNSIGNED NULL DEFAULT NULL");
		$this->execute("ALTER TABLE `schedule` ADD CONSTRAINT `schedule_set_id` FOREIGN KEY (`set_id`) REFERENCES `set`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
		$this->execute('ALTER TABLE `schedule` ADD INDEX `date` (`date`);');
    }
}

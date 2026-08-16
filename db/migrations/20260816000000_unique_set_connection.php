<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UniqueSetConnection extends AbstractMigration
{
	public function up(): void
	{
		// Keep only the lowest-id row for each (set_id, tsumego_id) pair.
		$this->execute(
			"DELETE sc1 FROM `set_connection` sc1 "
			. "INNER JOIN `set_connection` sc2 "
			. "ON sc2.set_id = sc1.set_id AND sc2.tsumego_id = sc1.tsumego_id AND sc2.id < sc1.id"
		);

		$this->execute("ALTER TABLE `set_connection` ADD UNIQUE KEY `uniq_set_tsumego` (`set_id`, `tsumego_id`)");
	}

	public function down(): void
	{
		$this->execute("ALTER TABLE `set_connection` DROP INDEX `uniq_set_tsumego`");
	}
}

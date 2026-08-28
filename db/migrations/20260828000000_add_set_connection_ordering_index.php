<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSetConnectionOrderingIndex extends AbstractMigration
{
	public function up(): void
	{
		// Lets the optimizer drive from `set` and read only a set's connections in
		// canonical (num, tsumego_id) order, so the /sets page never scans the
		// connections of the many private sets that share this join table.
		// The standalone `set_id` index is superseded by this one (and by
		// uniq_set_tsumego), so it is dropped after; the foreign key on `set_id`
		// stays satisfied because both keep `set_id` as the leading column.
		$this->execute(
			"ALTER TABLE `set_connection` ADD INDEX `set_connection_ordering` (`set_id`, `num`, `tsumego_id`)"
		);
		$this->execute("ALTER TABLE `set_connection` DROP INDEX `set_id`");
	}

	public function down(): void
	{
		// Restore the standalone index before removing the composite one, so the
		// foreign key on `set_id` keeps an index at every step.
		$this->execute("ALTER TABLE `set_connection` ADD INDEX `set_id` (`set_id`)");
		$this->execute("ALTER TABLE `set_connection` DROP INDEX `set_connection_ordering`");
	}
}

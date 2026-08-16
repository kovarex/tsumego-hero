<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSetIdFromToSchedule extends AbstractMigration
{
	public function up(): void
	{
		// Add nullable first: we backfill existing rows before enforcing NOT NULL.
		$this->execute("ALTER TABLE `schedule` ADD COLUMN `set_id_from` INT UNSIGNED NULL AFTER `set_id`");

		// Backfill the source sandbox set for pending rows.
		$this->execute("
UPDATE `schedule` s
JOIN (
	SELECT sc.tsumego_id, MIN(sc.set_id) AS set_id
	FROM `set_connection` sc
	JOIN `set` st ON st.id = sc.set_id
	WHERE st.public = 0 AND st.user_id IS NULL
	GROUP BY sc.tsumego_id
) x ON x.tsumego_id = s.tsumego_id
SET s.set_id_from = x.set_id
WHERE s.published = 0 AND s.set_id_from IS NULL");

		// Every other row (published, or pending without a sandbox source) has
		// no recoverable source set: use 0 as an explicit "unknown" sentinel so
		// the column can be NOT NULL.
		$this->execute("UPDATE `schedule` SET `set_id_from` = 0 WHERE `set_id_from` IS NULL");

		// Enforce NOT NULL.
		$this->execute("ALTER TABLE `schedule` MODIFY COLUMN `set_id_from` INT UNSIGNED NOT NULL");
	}

	public function down(): void
	{
		$this->execute("ALTER TABLE `schedule` DROP COLUMN `set_id_from`");
	}
}

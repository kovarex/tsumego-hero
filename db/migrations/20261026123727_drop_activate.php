<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The activate table held the keys that were emailed to beta testers of the first rating mode;
 * redeeming one on /activates stored the user in its row. Nothing has read or written it since
 * ActivatesController was removed, so it goes. Rolling back recreates it empty.
 */
final class DropActivate extends AbstractMigration
{
	public function up(): void
	{
		$this->table('activate')->drop()->save();
	}

	public function down(): void
	{
		$this->execute("CREATE TABLE `activate` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`user_id` int(11) NOT NULL,
			`string` varchar(100) DEFAULT NULL,
			`created` timestamp NOT NULL DEFAULT current_timestamp(),
			PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
	}
}

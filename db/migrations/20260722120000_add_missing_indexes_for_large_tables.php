<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddMissingIndexesForLargeTables extends AbstractMigration
{
	/**
	 * Adds covering indexes for tsumego_status (10M rows), tsumego_attempt (8.5M rows),
	 * and achievement_status (132K rows). Drops two redundant indexes on tsumego_attempt.
	 *
	 * Expected runtime: ~6-7 minutes on production (index builds on 10M/8.5M row tables).
	 */
	public function up(): void
	{
		// tsumego_status: fix profile page (covering index for status+updated filter)
		$this->execute("ALTER TABLE `tsumego_status` ADD INDEX `idx_user_status_updated` (`user_id`, `status`, `updated`);");

		// tsumego_attempt: fix profile daily chart + solve history
		$this->execute("ALTER TABLE `tsumego_attempt` ADD INDEX `idx_user_created` (`user_id`, `created`);");

		// Drop redundant indexes (verified unused — see audit doc)
		$this->execute("ALTER TABLE `tsumego_attempt` DROP INDEX `user_id_index`;");
		$this->execute("ALTER TABLE `tsumego_attempt` DROP INDEX `user_rating`;");

		// achievement_status: fix home page ORDER BY created
		$this->execute("ALTER TABLE `achievement_status` ADD INDEX `idx_created` (`created`);");
	}
}

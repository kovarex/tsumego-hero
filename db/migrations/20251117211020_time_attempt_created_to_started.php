<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TimeAttemptCreatedToStarted extends AbstractMigration {
    public function up(): void {
		$this->execute("ALTER TABLE `time_mode_attempt` CHANGE `created` `started` DATETIME NULL DEFAULT NULL");
    }
}

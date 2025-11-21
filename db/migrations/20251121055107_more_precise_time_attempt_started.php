<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MorePreciseTimeAttemptStarted extends AbstractMigration
{
    public function change(): void {
		$this->execute("ALTER TABLE `time_mode_attempt` CHANGE `started` `started` DATETIME(2) NULL DEFAULT NULL");
    }
}

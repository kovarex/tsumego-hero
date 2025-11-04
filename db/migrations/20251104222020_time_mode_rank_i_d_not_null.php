<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TimeModeRankIDNotNull extends AbstractMigration
{
	public function change(): void {
		$this->execute("ALTER TABLE `time_mode_session` CHANGE `time_mode_rank_id` `time_mode_rank_id` INT UNSIGNED NOT NULL;");
	}
}

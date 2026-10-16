<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AchievementStatusCreatedIsImmutable extends AbstractMigration
{
	public function up(): void
	{
		// created is the date the achievement was unlocked, so it must not change when
		// the row is updated. The old definition had ON UPDATE current_timestamp(),
		// which silently rewrote the unlock date for every raw-SQL update (the repeat
		// counters update value). CakePHP saves only survived it by writing the old
		// value back explicitly, so this was a landmine rather than a feature.
		$this->execute("ALTER TABLE `achievement_status` MODIFY `created` datetime NOT NULL DEFAULT current_timestamp()");
	}

	public function down(): void
	{
		$this->execute("ALTER TABLE `achievement_status` MODIFY `created` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()");
	}
}

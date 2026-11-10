<?php

declare(strict_types=1);

use App\Utility\DailyStreak;
use Phinx\Migration\AbstractMigration;

//The day of the player's last reset of the hearts and the hero powers (App\Utility\DailyReset), which now come
//back when the player's own day turns instead of in the daily cron. Every player starts with the day they are in
//now: the cron gave them their hearts last, so the next ones come when their day turns next.
//Once, for a player whose 03:00 comes between the cron's last run (midnight UTC) and the deploy's next midnight
//UTC, that next refill is less than a day after the cron's: a player in Prague deploying at 01:30 local is still
//in their previous day and gets hearts again at 03:00. Moving them to the next day instead would make the wait
//longer than a day for others (New York's cron refill is at 19:00 the evening before), so the earlier refill,
//once and only for some, is the smaller harm.
final class AddDailyResetDay extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE user ADD COLUMN IF NOT EXISTS daily_reset_day DATE NULL DEFAULT NULL
			COMMENT 'The player''s day (DailyStreak::today) of the last reset of the hearts and the hero powers' AFTER streak_day_xp_required");
		$now = new DateTimeImmutable();
		foreach ($this->fetchAll('SELECT DISTINCT time_zone FROM user') as $row)
		{
			$timeZone = $row['time_zone'];
			$day = DailyStreak::today($timeZone, $now);
			if ($timeZone === null)
				$this->execute('UPDATE user SET daily_reset_day = ' . $this->getAdapter()->getConnection()->quote($day) . ' WHERE time_zone IS NULL');
			else
				$this->execute('UPDATE user SET daily_reset_day = ' . $this->getAdapter()->getConnection()->quote($day)
					. ' WHERE time_zone = ' . $this->getAdapter()->getConnection()->quote($timeZone));
		}
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE user DROP COLUMN IF EXISTS daily_reset_day');
	}
}

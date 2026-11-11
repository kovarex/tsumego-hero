<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The players who solved a problem each day (App\Utility\ActivePlayers), stored once a day by the daily cron so the
//chart of the home page never counts user_day on a view. Filled here with the days so far, before today: a count of
//the solved days of user_day by day, through its index on (day, solved). A day nobody solved has no row, the chart
//counts it as 0.
final class AddActivePlayersDay extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE IF NOT EXISTS active_players_day (
			day DATE NOT NULL,
			players INT UNSIGNED NOT NULL COMMENT 'The players who solved at least one problem that day',
			PRIMARY KEY (day)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
		$today = $this->getAdapter()->getConnection()->quote(date('Y-m-d'));
		$days = $this->execute('INSERT INTO active_players_day (day, players)
			SELECT day, COUNT(*) FROM user_day WHERE solved = 1 AND day < ' . $today . ' GROUP BY day
			ON DUPLICATE KEY UPDATE players = VALUES(players)');
		$this->getOutput()?->writeln("active_players_day: $days rows");
	}

	public function down(): void
	{
		$this->execute('DROP TABLE IF EXISTS active_players_day');
	}
}

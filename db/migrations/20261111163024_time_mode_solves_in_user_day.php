<?php

declare(strict_types=1);

use App\Model\Table\TimeModeAttemptStatusTable;
use Phinx\Migration\AbstractMigration;

//A solve in the time mode makes the day one the player was active on (App\Utility\UserDay, the active players of the
//home page), as App\Utility\TimeMode now records with each solve. Here the solves so far: every day a player solved a
//time mode attempt, by the attempt's start (the session's when the attempt has none), gets a user_day row marked
//solved, its rating left as it is (NULL for a new row); then the stored active players of the days before today are
//counted again from user_day. One grouped scan of time_mode_attempt joined to its session; its plan and time go to
//the deploy log. Running it again changes nothing.
final class TimeModeSolvesInUserDay extends AbstractMigration
{
	private const string SOLVES = 'SELECT time_mode_session.user_id, DATE(COALESCE(time_mode_attempt.started, time_mode_session.created)) AS day
		FROM time_mode_attempt
		JOIN time_mode_session ON time_mode_session.id = time_mode_attempt.time_mode_session_id
		WHERE time_mode_attempt.time_mode_attempt_status_id = ' . TimeModeAttemptStatusTable::SOLVED . '
		GROUP BY 1, 2';

	public function up(): void
	{
		foreach ($this->fetchAll('EXPLAIN ' . self::SOLVES) as $row)
			$this->getOutput()?->writeln('time mode solves, EXPLAIN: ' . implode(' | ', array_map(
				fn($column) => $column . '=' . ($row[$column] ?? 'NULL'), ['table', 'type', 'key', 'rows', 'Extra'])));
		$started = microtime(true);
		$days = $this->execute('INSERT INTO user_day (user_id, day, rating, solved)
			SELECT user_id, day, NULL, 1 FROM (' . self::SOLVES . ') solves
			ON DUPLICATE KEY UPDATE solved = 1');
		$this->getOutput()?->writeln(sprintf('user_day: %d rows from the time mode solves, %.1f s', $days, microtime(true) - $started));

		$started = microtime(true);
		$today = $this->getAdapter()->getConnection()->quote(date('Y-m-d'));
		$stored = $this->execute('INSERT INTO active_players_day (day, players)
			SELECT day, COUNT(*) FROM user_day WHERE solved = 1 AND day < ' . $today . ' GROUP BY day
			ON DUPLICATE KEY UPDATE players = VALUES(players)');
		$this->getOutput()?->writeln(sprintf('active_players_day: %d rows counted again, %.1f s', $stored, microtime(true) - $started));
	}

	public function down(): void
	{
	}
}

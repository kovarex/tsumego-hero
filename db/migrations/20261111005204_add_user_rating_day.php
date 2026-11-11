<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The highest rating of each day a player played in level mode (App\Utility\UserRatingDay), which the rating chart of
//the profile reads instead of grouping the player's tsumego_attempt rows. From now on it is written with each
//attempt; here it is filled from the attempts so far: the highest user_rating of each day of each player, a day
//being DATE(created) on the server clock, as the chart grouped them before.
//The fill walks the user ids USERS_PER_STATEMENT at a time, so no statement groups more than their attempts, and
//keeps a day's higher rating where it already has one, so running it again changes nothing. The deploy shows the
//maintenance page while the migrations run, so no attempt is written meanwhile. It prints its progress every tenth
//of the ids, so the deploy log shows how long it took.
final class AddUserRatingDay extends AbstractMigration
{
	public const int USERS_PER_STATEMENT = 2000;

	public function up(): void
	{
		$this->execute("CREATE TABLE IF NOT EXISTS user_rating_day (
			user_id INT UNSIGNED NOT NULL,
			day DATE NOT NULL COMMENT 'DATE(created) of the attempts, on the server clock',
			rating INT NOT NULL COMMENT 'The highest user_rating of the day''s attempts',
			PRIMARY KEY (user_id, day),
			CONSTRAINT user_rating_day_user_id FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$ids = $this->fetchRow('SELECT MIN(id) AS first, MAX(id) AS last FROM user');
		if ($ids === false || $ids['first'] === null)
			return;
		$first = (int) $ids['first'];
		$last = (int) $ids['last'];
		$started = microtime(true);
		$days = 0;
		$reported = 0;
		for ($from = $first; $from <= $last; $from += self::USERS_PER_STATEMENT)
		{
			$until = min($last, $from + self::USERS_PER_STATEMENT - 1);
			$days += $this->execute('INSERT INTO user_rating_day (user_id, day, rating)
				SELECT user_id, DATE(created), MAX(user_rating)
				FROM tsumego_attempt
				WHERE user_id BETWEEN ' . $from . ' AND ' . $until . ' AND user_rating IS NOT NULL
				GROUP BY user_id, DATE(created)
				ON DUPLICATE KEY UPDATE rating = GREATEST(rating, VALUES(rating))');
			$tenths = (int) floor(($until - $first + 1) / ($last - $first + 1) * 10);
			if ($tenths > $reported)
			{
				$reported = $tenths;
				$this->getOutput()?->writeln(sprintf('user_rating_day: %d%% of the user ids, %d rows, %.1f s', $tenths * 10, $days, microtime(true) - $started));
			}
		}
	}

	public function down(): void
	{
		$this->execute('DROP TABLE IF EXISTS user_rating_day');
	}
}

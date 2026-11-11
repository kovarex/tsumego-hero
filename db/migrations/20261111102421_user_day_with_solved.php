<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//user_rating_day becomes user_day (App\Utility\UserDay): a row for each day a player played in level mode, not only
//the days with a rating. rating may be NULL, and solved says whether an attempt of the day was solved, which the
//active players of the home page (App\Utility\ActivePlayers) count through the index on (day, solved).
//It is filled from the attempts so far as AddUserRatingDay filled the ratings: the user ids USERS_PER_STATEMENT at a
//time, a day keeping its higher rating and its solve, so running it again changes nothing. The deploy shows the
//maintenance page while the migrations run, and the progress goes to its log every tenth of the ids.
final class UserDayWithSolved extends AbstractMigration
{
	public const int USERS_PER_STATEMENT = 2000;

	public function up(): void
	{
		if ($this->hasTable('user_rating_day'))
			$this->execute('RENAME TABLE user_rating_day TO user_day');
		$this->execute("ALTER TABLE user_day
			MODIFY rating INT NULL COMMENT 'The highest user_rating of the day''s attempts, NULL when none had one',
			ADD COLUMN IF NOT EXISTS solved TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether an attempt of the day was solved',
			ADD INDEX IF NOT EXISTS day_solved (day, solved)");

		$ids = $this->fetchRow('SELECT MIN(id) AS first, MAX(id) AS last FROM user');
		if ($ids === false || $ids['first'] === null)
			return;
		$first = (int) $ids['first'];
		$last = (int) $ids['last'];
		$started = microtime(true);
		$rows = 0;
		$reported = 0;
		for ($from = $first; $from <= $last; $from += self::USERS_PER_STATEMENT)
		{
			$until = min($last, $from + self::USERS_PER_STATEMENT - 1);
			// GREATEST of a NULL is NULL, so a day without a rating takes the attempts' one and a NULL keeps the day's
			$rows += $this->execute('INSERT INTO user_day (user_id, day, rating, solved)
				SELECT user_id, DATE(created), MAX(user_rating), MAX(solved)
				FROM tsumego_attempt
				WHERE user_id BETWEEN ' . $from . ' AND ' . $until . '
				GROUP BY user_id, DATE(created)
				ON DUPLICATE KEY UPDATE
					rating = COALESCE(GREATEST(rating, VALUES(rating)), rating, VALUES(rating)),
					solved = GREATEST(solved, VALUES(solved))');
			$tenths = (int) floor(($until - $first + 1) / ($last - $first + 1) * 10);
			if ($tenths > $reported)
			{
				$reported = $tenths;
				$this->getOutput()?->writeln(sprintf('user_day: %d%% of the user ids, %d rows, %.1f s', $tenths * 10, $rows, microtime(true) - $started));
			}
		}
	}

	public function down(): void
	{
		$this->execute('DELETE FROM user_day WHERE rating IS NULL');
		$this->execute('ALTER TABLE user_day DROP INDEX IF EXISTS day_solved, DROP COLUMN IF EXISTS solved, MODIFY rating INT NOT NULL');
		$this->execute('RENAME TABLE user_day TO user_rating_day');
	}
}

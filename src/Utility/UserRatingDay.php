<?php

namespace App\Utility;

//The highest rating of each day a player played in level mode, in user_rating_day: the rating chart of the profile
//(UserProfile::ratingHistory) reads it instead of grouping the player's tsumego_attempt rows, a few hundred rows a
//year instead of thousands. A day is DATE(created) of the attempts on the server clock, and its rating is the
//highest user_rating written to an attempt that day, as MAX(user_rating) grouped by DATE(created) gives it.
class UserRatingDay
{
	//Keeps the rating written to an attempt of the day as the day's rating if it is the highest so far; an attempt
	//without a rating keeps nothing
	public static function record(int $userID, string $day, int|float|string|null $rating): void
	{
		if ($rating === null)
			return;
		// the rating is a float that MySQL rounds into the integer column, as it does into tsumego_attempt.user_rating
		Util::execute('INSERT INTO user_rating_day (user_id, day, rating) VALUES (?, ?, ?)
			ON DUPLICATE KEY UPDATE rating = GREATEST(rating, VALUES(rating))', [$userID, $day, $rating]);
	}

	//Fills the days of the attempts written before the table was, player by player in batches of $batchSize
	//players by id: the highest user_rating of each day, kept where a day already has a higher one, so a second run
	//changes nothing. The days are read first and written after, so the read takes no locks on tsumego_attempt and
	//the site keeps writing attempts while it runs. Returns the players with a day and the days written.
	/** @return array{players: int, days: int} */
	public static function backfill(int $batchSize = 100): array
	{
		$players = 0;
		$days = 0;
		$lastID = 0;
		while ($ids = array_map('intval', array_column(Util::query('SELECT id FROM user WHERE id > ? ORDER BY id LIMIT ' . $batchSize, [$lastID]), 'id')))
		{
			$lastID = end($ids);
			$rows = Util::query('
				SELECT user_id, DATE(created) AS day, MAX(user_rating) AS rating
				FROM tsumego_attempt
				WHERE user_id IN (' . implode(',', $ids) . ') AND user_rating IS NOT NULL
				GROUP BY user_id, DATE(created)');
			if (!$rows)
				continue;
			$players += count(array_unique(array_column($rows, 'user_id')));
			$days += count($rows);
			foreach (array_chunk($rows, 1000) as $chunk)
				Util::execute('INSERT INTO user_rating_day (user_id, day, rating) VALUES '
					. implode(',', array_fill(0, count($chunk), '(?, ?, ?)'))
					. ' ON DUPLICATE KEY UPDATE rating = GREATEST(rating, VALUES(rating))',
					array_merge(...array_map(fn($row) => [(int) $row['user_id'], $row['day'], (int) $row['rating']], $chunk)));
		}
		return ['players' => $players, 'days' => $days];
	}
}

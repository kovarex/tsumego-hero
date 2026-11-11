<?php

namespace App\Utility;

//The days each player played, in user_day: a row for each day with an attempt in level mode or a solve in the time
//mode, with the highest rating written to a level mode attempt that day (null when none had one) and whether a problem
//was solved. The rating chart of the profile (UserProfile::ratingRanges) and the active players of the home page
//(ActivePlayers) read it instead of grouping tsumego_attempt. A day is DATE(created) of the attempts, or of the time
//mode solve, on the server clock.
class UserDay
{
	//Keeps an attempt of the day: its rating if it is the day's highest so far, and the day as solved if it is
	public static function record(int $userID, string $day, int|float|string|null $rating, bool $solved): void
	{
		// the rating is a float that MySQL rounds into the integer column, as it does into tsumego_attempt.user_rating;
		// GREATEST of a NULL is NULL, so a day without a rating takes the new one and a NULL keeps the day's
		Util::execute('INSERT INTO user_day (user_id, day, rating, solved) VALUES (?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE rating = COALESCE(GREATEST(rating, VALUES(rating)), rating, VALUES(rating)), solved = GREATEST(solved, VALUES(solved))',
			[$userID, $day, $rating, $solved ? 1 : 0]);
	}
}

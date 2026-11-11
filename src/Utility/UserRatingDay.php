<?php

namespace App\Utility;

//The highest rating of each day a player played in level mode, in user_rating_day: the rating chart of the profile
//(UserProfile::ratingRanges) reads it instead of grouping the player's tsumego_attempt rows, a few hundred rows a
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
}

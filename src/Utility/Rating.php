<?php

use function PHPUnit\Framework\isNull;

App::uses('RatingParseException', 'Utility');

class Rating
{
	public static function getReadableRank(int $rank): string
	{
		if ($rank <= 30)
			return (string) (31 - $rank) . 'k';

		return (string) ($rank - 30) . 'd';
	}

	public static function getRankFromReadableRank(string $readableRank): int
	{
		$suffix = substr($readableRank, -1);
		$number = substr($readableRank, 0, -1);
		if (!is_numeric($number))
			throw new RatingParseException($readableRank . " can't be parsed as go rank.");
		if ($suffix == 'k')
			return 31 - $number;
		elseif ($suffix == 'd')
			return 30 + $number;
		else
			throw new RatingParseException($readableRank . " can't be parsed as go rank.");
	}

	public static function getRankFromRating(float $rating): int
	{
		// Internal number for rank representation better than the textual "18k" etc, so it is just going to be integer like this
		// 30k   = rating [-950, -850) = rank  1
		// "20k" = rating [  50,  150) = rank 11
		// "10k" = rating [1050, 1150) = rank 21
		//  1k   = rating [1950, 2050) = rank 30
		//  1d   = rating [2050, 2150) = rank 31
		//  7d   = rating [2650, 2750) = rank 37 7d is equivalent of pro rank, and then it is custom to have 30 points per rank
		//  8d   = rating [2750, 2780) = rank 38
		//  9d   = rating [2780, 2810) = rank 39
		// 10d   = rating [2780, 2810) = rank 40
		// 11d   = rating [2810, 2840) = rank 41
		// .....
		if ($rating < 2750)
			return (int) floor(max(($rating + 1050) / 100, 1));

		return (int) floor(($rating - 2750) / 30) + 38;
	}

	public static function getReadableRankFromRating(float $rating): string
	{
		return static::getReadableRank(static::getRankFromRating($rating));
	}

	public static function getRankMinimalRating(int $rank): float
	{
		if ($rank <= 38)
			return 100 * $rank - 1050.0;
		return ($rank - 38) * 30 + 2750.0;
	}

	public static function getRankMinimalRatingFromReadableRank(string $readableRank): float
	{
		return Rating::getRankMinimalRating(Rating::getRankFromReadableRank($readableRank));
	}

	public static function getRankMiddleRatingFromRank(int $rank): float
	{
		return (Rating::getRankMinimalRating($rank) + Rating::getRankMinimalRating($rank + 1)) / 2;
	}

	public static function getRankMiddleRatingFromReadableRank(string $readableRank): float
	{
		return Rating::getRankMiddleRatingFromRank(Rating::getRankFromReadableRank($readableRank));
	}

	private static function beta($rating)
	{
		return -7 * log(3300 - $rating);
	}

	public static function calculateRatingChange($rating, $opponentRating, $result, $modifier)
	{
		$Se = 1.0 / (1.0 + exp(self::beta($opponentRating) - self::beta($rating)));
		$con = pow(((3300 - $rating) / 200), 1.6);
		$bonus = log(1 + exp((2300 - $rating) / 80)) / 5;
		return $modifier * ($con * ($result - $Se) + $bonus);
	}

	// changes should be reflected in util.js
	public static function ratingToXP($rating): float
	{
		// until 1200 rating, the old formula but with half of the values
		if ($rating < 1200)
			return max(10, pow($rating / 100, 1.55) - 6) / 2;

		// with higher ratings, it is important to have more aggressive exponential growth,
		return (pow(($rating - 500) / 100, 2) - 10) / 2;
	}

	public static function parseRatingOrReadableRank(string $input): float
	{
		if (is_numeric($input))
		{
			if (!self::isReasonableRating((float) $input))
				throw new RatingParseException("Rating of " . $input . "isn't reasonable");
			return (float) $input;
		}
		return self::getRankMiddleRatingFromReadableRank($input);
	}

	public static function isReasonableRating(float $rating)
	{
		return $rating >= -950 // 30k
				&& $rating < 3200; // the formula stops working at 3300
	}

	public static function getReadableRankFromRatingWhenPossible(?float $rating): string
	{
		if (is_null($rating))
			return '';
		$rank = self::getRankFromRating($rating);
		if (self::getRankMiddleRatingFromRank($rank) == $rating)
			return Rating::getReadableRank($rank);
		return strval($rating);
	}
}

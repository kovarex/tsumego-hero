<?php

namespace App\Utility;

class Rating
{
	public static function getReadableRank(int $rank): string
	{
		if ($rank <= 30)
			return (string) (31 - $rank) . 'k';

		return (string) ($rank - 30) . 'd';
	}

	public static function isValidReadableRank(string $readableRank): bool
	{
		if (strlen($readableRank) < 2)
			return false;
		$suffix = substr($readableRank, -1);
		$numberStr = substr($readableRank, 0, -1);
		if (!ctype_digit($numberStr))
			return false;
		$number = (int) $numberStr;
		if ($suffix === 'k')
			return $number >= 1 && $number <= 30;
		if ($suffix === 'd')
			return $number >= 1;
		return false;
	}

	public static function getRankFromReadableRank(string $readableRank): int
	{
		$suffix = substr($readableRank, -1);
		$number = substr($readableRank, 0, -1);
		if (!ctype_digit($number))
			throw new RatingParseException($readableRank . " can't be parsed as go rank.");
		if ($suffix === 'k')
			return 31 - (int) $number;
		elseif ($suffix === 'd')
			return 30 + (int) $number;
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

	private static function beta(float $rating): float
	{
		return -7 * log(3300 - $rating);
	}

	public static function calculateRatingChange(float $rating, float $opponentRating, float $result, float $modifier): float
	{
		$Se = 1.0 / (1.0 + exp(self::beta($opponentRating) - self::beta($rating)));
		$con = pow(((3300 - $rating) / 200), 1.6);
		$bonus = log(1 + exp((2300 - $rating) / 80)) / 5;
		return $modifier * ($con * ($result - $Se) + $bonus);
	}

	// changes should be reflected in util.js
	public static function ratingToXP(float $rating, float $multiplier): int
	{
		return intval(ceil(Rating::ratingToXPFloat($rating) * $multiplier));
	}

	public static function ratingToXPFloat(float $rating): float
	{
		if ($rating < 0)
			return 1 + max(0, ($rating / 1000 + 0.9) * 3);

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

	public static function isReasonableRating(float $rating): bool
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

	//All rank labels from 30k to 9d, weakest first. Colors are CSS tokens
	//(from --rank-30k to --rank-9d) in webroot/css/tokens.css.
	/** @return string[] */
	public static function ranks(): array
	{
		return [
			'30k', '29k', '28k', '27k', '26k',
			'25k', '24k', '23k', '22k', '21k',
			'20k', '19k', '18k', '17k', '16k',
			'15k', '14k', '13k', '12k', '11k',
			'10k', '9k', '8k', '7k', '6k',
			'5k', '4k', '3k', '2k', '1k',
			'1d', '2d', '3d', '4d', '5d',
			'6d', '7d', '8d', '9d',
		];
	}

	//The weakest rank of the rank list. Its band has no lower rating bound,
	//so every problem below it belongs to it as well.
	public static function weakestRank(): string
	{
		return self::ranks()[0];
	}

	//Splits a range label such as "20k-16k" into its weakest and strongest rank.
	//Returns null when the input is not exactly two valid ranks in that order.
	/** @return array{0: string, 1: string}|null */
	public static function parseReadableRankRange(string $input): ?array
	{
		$parts = explode('-', $input);
		if (count($parts) !== 2 || !self::isValidReadableRank($parts[0]) || !self::isValidReadableRank($parts[1]))
			return null;
		if (self::getRankFromReadableRank($parts[0]) > self::getRankFromReadableRank($parts[1]))
			return null;
		return [$parts[0], $parts[1]];
	}

	//The rank labels from the weakest to the strongest one, inclusive, in the order of ranks().
	/** @return string[] */
	public static function ranksBetween(string $weakest, string $strongest): array
	{
		$allRanks = self::ranks();
		$from = array_search($weakest, $allRanks, true);
		$to = array_search($strongest, $allRanks, true);
		if ($from === false || $to === false || $from > $to)
			return [];
		return array_slice($allRanks, $from, $to - $from + 1);
	}

	//Hex color for a rank label, referencing the CSS token (e.g. --rank-5k).
	public static function rankColorVar(string $rank): string
	{
		return 'var(--rank-' . $rank . ')';
	}

	//How far the difficulty setting of rating mode moves the player's rating: the selection
	//is centred on the adjusted value, so a higher setting picks stronger problems.
	public static function adjustmentForDifficulty(int|string $difficultySetting): int
	{
		$adjustments = [
			1 => -Constants::$RATING_MODE_DIFFERENCE_SETTING_3,
			2 => -Constants::$RATING_MODE_DIFFERENCE_SETTING_2,
			3 => -Constants::$RATING_MODE_DIFFERENCE_SETTING_1,
			4 => 0,
			5 => Constants::$RATING_MODE_DIFFERENCE_SETTING_1,
			6 => Constants::$RATING_MODE_DIFFERENCE_SETTING_2,
			7 => Constants::$RATING_MODE_DIFFERENCE_SETTING_3,
		];
		return $adjustments[$difficultySetting] ?? 0;
	}
}

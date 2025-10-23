<?php
class Rating {

	public static function getReadableRank(int $rank): string {
		if ($rank <= 30) {
			return (string)(31 - $rank) . 'k';
		}

		return (string)($rank - 30) . 'd';
	}

	public static function getRankFromRating(float $rating): int {
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
		if ($rating < 2750) {
			return (int)floor(max(($rating + 1050) / 100, 1));
		}

		return (int)floor(($rating - 2750) / 30) + 38;
	}

	public static function getReadableRankFromRating(float $rating): string {
		return static::getReadableRank(static::getRankFromRating($rating));
	}

}

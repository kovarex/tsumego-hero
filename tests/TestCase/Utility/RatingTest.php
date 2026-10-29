<?php

use App\Utility\Rating;

class RatingTest extends AppTestCase
{
	public function testRatingToReadableRank(): void
	{
		$this->assertSame(Rating::getReadableRankFromRating(-100), '22k');
		$this->assertSame(Rating::getReadableRankFromRating(0), '21k');
		$this->assertSame(Rating::getReadableRankFromRating(100), '20k');
		$this->assertSame(Rating::getReadableRankFromRating(200), '19k');
		$this->assertSame(Rating::getReadableRankFromRating(2049), '1k');
		$this->assertSame(Rating::getReadableRankFromRating(2050), '1d');
		$this->assertSame(Rating::getReadableRankFromRating(2051), '1d');
		$this->assertSame(Rating::getReadableRankFromRating(2150), '2d');
		$this->assertSame(Rating::getReadableRankFromRating(2700), '7d');
		$this->assertSame(Rating::getReadableRankFromRating(2749), '7d');
		$this->assertSame(Rating::getReadableRankFromRating(2750), '8d');
		$this->assertSame(Rating::getReadableRankFromRating(2779), '8d');
		$this->assertSame(Rating::getReadableRankFromRating(2780), '9d');
		$this->assertSame(Rating::getReadableRankFromRating(2809), '9d');
		$this->assertSame(Rating::getReadableRankFromRating(2810), '10d');
	}

	public function testRankMinimalRating(): void
	{
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRank("30k"), -950.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRank("20k"), 50.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRank("1k"), 1950.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRank("1d"), 2050.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRank("2d"), 2150.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRank("7d"), 2650.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRank("8d"), 2750.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRank("9d"), 2780.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRank("10d"), 2810.0);
	}

	public function testParseReadableRankRange(): void
	{
		$this->assertSame(['20k', '16k'], Rating::parseReadableRankRange('20k-16k'));
		$this->assertSame(['5k', '5k'], Rating::parseReadableRankRange('5k-5k'));
		$this->assertNull(Rating::parseReadableRankRange('16k-20k'));
		$this->assertNull(Rating::parseReadableRankRange('15k'));
		$this->assertNull(Rating::parseReadableRankRange('deleted'));
		$this->assertNull(Rating::parseReadableRankRange('30k-16k-10k'));
	}

	public function testIsValidReadableRank(): void
	{
		$this->assertTrue(Rating::isValidReadableRank('15k'));
		$this->assertTrue(Rating::isValidReadableRank('1k'));
		$this->assertTrue(Rating::isValidReadableRank('30k'));
		$this->assertTrue(Rating::isValidReadableRank('1d'));
		$this->assertTrue(Rating::isValidReadableRank('9d'));
		$this->assertTrue(Rating::isValidReadableRank('10d'));

		$this->assertFalse(Rating::isValidReadableRank('deleted'));
		$this->assertFalse(Rating::isValidReadableRank(''));
		$this->assertFalse(Rating::isValidReadableRank('abc'));
		$this->assertFalse(Rating::isValidReadableRank('k'));
		$this->assertFalse(Rating::isValidReadableRank('0k'));
		$this->assertFalse(Rating::isValidReadableRank('31k'));
		$this->assertFalse(Rating::isValidReadableRank('1.5k'));
		$this->assertFalse(Rating::isValidReadableRank('2.0d'));
	}
}

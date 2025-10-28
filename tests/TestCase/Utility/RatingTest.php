<?php

App::uses('Rating', 'Utility');

class RatingTest extends CakeTestCase {
	public function testRatingToReadableRank(): void {
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

	public function testRankMinimalRating(): void {
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRating("30k"), -950.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRating("20k"), 50.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRating("1k"), 1950.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRating("1d"), 2050.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRating("2d"), 2150.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRating("7d"), 2650.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRating("8d"), 2750.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRating("9d"), 2780.0);
		$this->assertSame(Rating::getRankMinimalRatingFromReadableRating("10d"), 2810.0);
	}
}

<?php

App::uses('Rating', 'Utility');

class RatingTest extends CakeTestCase {

	/**
	 * @return void
	 */
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

}

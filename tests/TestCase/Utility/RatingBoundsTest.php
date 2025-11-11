<?php

App::uses('RatingBounds', 'Utility');

class RatingBoundsTest extends CakeTestCase {
	public function testCoverRank1d() {
		$bounds = RatingBounds::coverRank('1d');
		$this->assertSame($bounds->min, 2050.0);
		$this->assertSame($bounds->max, 2150.0);
	}

	public function testCoverRank5k() {
		$bounds = RatingBounds::coverRank('5k');
		$this->assertSame($bounds->min, 1550.0);
		$this->assertSame($bounds->max, 1650.0);
	}

	public function testCoverRank5kWithLowest6k() {
		// the same as just 5k, as there is lower rank
		$bounds = RatingBounds::coverRank('5k', '6k');
		$this->assertSame($bounds->min, 1550.0);
		$this->assertSame($bounds->max, 1650.0);
	}

	public function testCoverRank5kWithLowest5k() {
		// the same as just 5k, as there is lower rank
		$bounds = RatingBounds::coverRank('5k', '5k');
		$this->assertNull($bounds->min);
		$this->assertSame($bounds->max, 1650.0);
	}

	public function testCreateSqlConditionFromEmpty() {
		$condition = '';
		new RatingBounds()->addSqlConditions($condition);
		$this->assertSame($condition, '');
	}

	public function testCreateConditionWithOnlyMin() {
		$condition = '';
		new RatingBounds(10)->addSqlConditions($condition);
		$this->assertSame($condition, 'tsumego.rating >= 10');
	}

	public function testCreateConditionWithOnlyMax() {
		$condition = '';
		new RatingBounds(null, 20)->addSqlConditions($condition);
		$this->assertSame($condition, 'tsumego.rating < 20');
	}

	public function testCreateConditionWithBothMinAndMax() {
		$condition = '';
		new RatingBounds(10, 20)->addSqlConditions($condition);
		$this->assertSame($condition, 'tsumego.rating >= 10 AND tsumego.rating < 20');
	}

	public function testCreateStructuredConditionFromEmpty() {
		$condition = new RatingBounds()->getConditions();
		$this->assertSame($condition, []);
	}

	public function testCreateStructuredConditionFromMin() {
		$condition = new RatingBounds(10)->getConditions();
		$this->assertSame(count($condition), 1);
		$this->assertSame($condition['rating >= '], 10.0);
	}

	public function testCreateStructuredConditionFromMax() {
		$condition = new RatingBounds(null, 20)->getConditions();
		$this->assertSame(count($condition), 1);
		$this->assertSame($condition['rating < '], 20.0);
	}

	public function testCreateStructuredConditionWithBothMinAndMax() {
		$condition = new RatingBounds(10, 20)->getConditions();
		$this->assertSame(count($condition), 2);
		$this->assertSame($condition['rating >= '], 10.0);
		$this->assertSame($condition['rating < '], 20.0);
	}

}

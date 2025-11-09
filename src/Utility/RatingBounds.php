<?php

class RatingBounds {
	public function getConditions(): array {
		$result = [];
		if ($this->min) {
			$result['rating >= '] = $this->min;
		}
		if ($this->max) {
			$result['rating < '] = $this->max;
		}
		return $result;
	}

	public function addSqlConditions(string &$condition): string {
		if ($this->min) {
			Util::addSqlCondition($condition, "tsumego.rating >= ".$this->min);
		}
		if ($this->max) {
			Util::addSqlCondition($condition, "tsumego.rating >= ". $this->max);
		}
		return $condition;
	}

	static public function coverRank(string $rank, string $minimalRank): RatingBounds {
		$result = new RatingBounds();
		if ($rank != $minimalRank) {
			$result->min = Rating::getRankMinimalRatingFromReadableRank($rank);
		}
		$result->max = Rating::getRankMinimalRating(Rating::getRankFromReadableRank($rank) + 1);
		return $result;
	}

	public ?float $min = null;
	public ?float $max = null;
}

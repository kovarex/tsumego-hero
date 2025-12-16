<?php

class RatingBounds
{
	public function __construct(?float $min = null, ?float $max = null)
	{
		$this->min = $min;
		$this->max = $max;
	}

	public function getConditions(): array
	{
		$result = [];
		if ($this->min)
			$result['rating >= '] = $this->min;
		if ($this->max)
			$result['rating < '] = $this->max;
		return $result;
	}

	public function addSqlConditions(string &$condition): string
	{
		if ($this->min)
			Util::addSqlCondition($condition, "tsumego.rating >= " . $this->min);
		if ($this->max)
			Util::addSqlCondition($condition, "tsumego.rating < " . $this->max);
		return $condition;
	}

	public function addQueryConditions(Query $query): string
	{
		if ($this->min)
			$query->conditions[] = 'tsumego.rating >= ' . $this->min;
		if ($this->max)
			$query->conditions[] = 'tsumego.rating < ' . $this->max;
	}

	public function textualDescription(): string
	{
		$result = '';
		if ($this->min)
			$result .= ' from ' . $this->min;
		if ($this->max)
			$result .= ' to ' . $this->max;
		return $result;
	}

	public static function coverRank(string $rank, ?string $minimalRank = null): RatingBounds
	{
		$result = new RatingBounds();
		if ($rank != $minimalRank)
			$result->min = Rating::getRankMinimalRatingFromReadableRank($rank);
		$result->max = Rating::getRankMinimalRating(Rating::getRankFromReadableRank($rank) + 1);
		return $result;
	}

	public ?float $min = null;
	public ?float $max = null;
}

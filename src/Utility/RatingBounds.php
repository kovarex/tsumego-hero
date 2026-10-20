<?php

namespace App\Utility;

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

	public function addQueryConditions(Query $query): void
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

	/**
	 * Bounds of a single rank or of a range label such as "20k-16k".
	 * Inputs that are not a range fall through to coverRank, which throws
	 * RatingParseException for invalid ranks.
	 */
	public static function coverRankSpec(string $spec, ?string $minimalRank = null): RatingBounds
	{
		$range = Rating::parseReadableRankRange($spec);
		if ($range === null)
			return self::coverRank($spec, $minimalRank);
		[$weakest, $strongest] = $range;
		$result = new RatingBounds();
		if ($weakest != $minimalRank)
			$result->min = Rating::getRankMinimalRatingFromReadableRank($weakest);
		$result->max = Rating::getRankMinimalRating(Rating::getRankFromReadableRank($strongest) + 1);
		return $result;
	}

	public ?float $min = null;
	public ?float $max = null;
}

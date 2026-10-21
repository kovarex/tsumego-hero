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

	/**
	 * How the range reads inside a sentence: "from 800 to 650", "up to 650" when nothing is below
	 * it, "from 800" when nothing is above it, and nothing at all when it covers everything.
	 * The leading space belongs to the phrase, the sentence around it just joins the parts.
	 */
	public function textualDescription(): string
	{
		if ($this->min && $this->max)
			return ' from ' . $this->min . ' to ' . $this->max;
		if ($this->max)
			return ' up to ' . $this->max;
		if ($this->min)
			return ' from ' . $this->min;
		return '';
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

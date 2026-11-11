<?php

namespace App\Utility;

//The ranges of a chart over time with the buttons 1M · 6M · 1Y · All (app/charts/RangeChart.tsx), every range printed
//with the page. A range has a point a day, or a week or a month once it would have more than POINTS days, dated by
//the first day of its period (Monday for a week).
class ChartRanges
{
	//How far back each range goes, null for all of it
	public const array RANGES = ['1M' => '-1 month', '6M' => '-6 months', '1Y' => '-1 year', 'All' => null];
	//The range shown when the page opens
	public const string DEFAULT = '6M';
	//The most points a range draws
	public const int POINTS = 200;

	//Every range of the values by day (day => value, oldest first), All from the first of them. A week or a month is
	//the highest value of its days, or with $average the average of its days, a day missing from $days counting as 0.
	//$today is a day still going on: it is a point of the ranges in days, marked as 'partial', and left out of the
	//weeks and months, whose averages would drop with its first hours.
	/**
	 * @param array<string, int|float> $days
	 * @return array<string, array{period: 'day'|'week'|'month', points: list<array{0: string, 1: int|float}>, partial: ?string}>
	 */
	public static function build(array $days, bool $average = false, ?string $today = null): array
	{
		$now = date('Y-m-d');
		$ranges = [];
		foreach (self::RANGES as $name => $back)
		{
			$from = $back !== null ? date('Y-m-d', strtotime($back)) : (string) (array_key_first($days) ?? $now);
			$length = (strtotime($now) - strtotime($from)) / 86400;
			$period = $length <= self::POINTS ? 'day' : ($length / 7 <= self::POINTS ? 'week' : 'month');
			$buckets = [];
			if ($average && $period !== 'day')
				// every day of the range counts, a day without a value as 0, up to the last day that is over
				for ($day = $from; $day < $now && $day !== $today; $day = date('Y-m-d', strtotime($day . ' +1 day')))
					$buckets[self::periodStart($day, $period)][] = $days[$day] ?? 0;
			else
				foreach ($days as $day => $value)
					if ((string) $day >= $from && ($period === 'day' || (string) $day !== $today))
						$buckets[self::periodStart((string) $day, $period)][] = $value;
			$points = [];
			foreach ($buckets as $start => $values)
				$points[] = [(string) $start, $average && $period !== 'day' ? round(array_sum($values) / count($values), 1) : max($values)];
			$ranges[$name] = ['period' => $period, 'points' => $points, 'partial' => $period === 'day' && $today !== null && isset($days[$today]) ? $today : null];
		}
		return $ranges;
	}

	//The first day of the day's week (Monday) or month, or the day itself
	private static function periodStart(string $day, string $period): string
	{
		if ($period === 'week')
			return date('Y-m-d', strtotime($day . ' -' . (date('N', strtotime($day)) - 1) . ' days'));
		if ($period === 'month')
			return substr($day, 0, 7) . '-01';
		return $day;
	}
}

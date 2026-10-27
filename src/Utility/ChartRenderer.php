<?php

namespace App\Utility;

//The charts of the site are drawn by app/charts (LineChart.tsx, BarChart.tsx); this prints the element they mount
//into, with the data as its props.
class ChartRenderer
{
	//A line chart over time; $period is 'month' or 'day', what one point stands for.
	/** @param array<int, array{label: string, color: string, axis: 'left'|'right', fill: bool, points: array<int, array{0: string, 1: int|float|null}>}> $series */
	public static function line(?string $title, array $series, string $period, int $height, bool $startAtZero, string $class = ''): string
	{
		return self::mount('data-line-chart-root', $class, [
			'title' => $title,
			'series' => $series,
			'period' => $period,
			'height' => $height,
			'startAtZero' => $startAtZero]);
	}

	//The rating by day, from rows with the columns day and Rating, as the profile and the problem history show it.
	public static function ratingLine(string $title, array $dailyResults): string
	{
		$points = array_map(fn($row) => [$row['day'], $row['Rating'] === null ? null : (int) $row['Rating']], $dailyResults);
		return self::line($title, [['label' => 'Rating', 'color' => '#74d14c', 'axis' => 'left', 'fill' => false, 'points' => $points]], 'day', 360, false, 'timeGraph');
	}

	//Horizontal stacked bars, one for each of the $rows, labeled by the date in its $categoryColumn; a series takes its
	//values from the column named as its label.
	/** @param array<int, array{label: string, color: string}> $series */
	public static function bar(string $title, array $rows, string $categoryColumn, array $series): string
	{
		return self::mount('data-bar-chart-root', '', [
			'title' => $title,
			'categories' => array_column($rows, $categoryColumn),
			'series' => array_map(fn($oneSeries) => $oneSeries + ['values' => array_map(fn($row) => (int) $row[$oneSeries['label']], $rows)], $series)]);
	}

	private static function mount(string $attribute, string $class, array $props): string
	{
		return '<div ' . $attribute . ($class !== '' ? ' class="' . $class . '"' : '') . ' data-props="' . h(json_encode($props)) . '"></div>';
	}
}

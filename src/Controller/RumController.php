<?php

namespace App\Controller;

use App\Attribute\HttpPost;
use App\Utility\Util;
use Cake\Http\Response;

//Keeps what the players' browsers measured on the play page (webroot/js/PlayTiming.js) in rum_sample,
//to compare the timings before and after a change (bin/cake rum_report). It needs no login and reads
//no session (AppController::filterRequest()), a sample says nothing about who sent it.
class RumController extends AppController
{
	//problem_open: from the start of the navigation to a problem until its board takes moves, when
	//    coming from another page
	//problem_next: the same from another problem (Next, the problem buttons, the arrow keys)
	//problem_next_in_place: the next problem shown without loading the page (webroot/js/PlayNavigation.js),
	//    from the click until it was painted
	//result_post: the request that records the result of a problem, until its answer arrived
	public const METRICS = ['problem_open', 'problem_next', 'problem_next_in_place', 'result_post'];

	//What one beacon may carry, more is dropped
	public const MAXIMUM_SAMPLES = 20;

	//A sample over ten minutes measured a tab in the background, not a wait
	public const MAXIMUM_MILLISECONDS = 600000;

	#[HttpPost]
	public function record(): Response
	{
		$rows = [];
		foreach (array_slice((array) $this->input('samples'), 0, self::MAXIMUM_SAMPLES) as $sample)
			if ($row = self::row($sample))
				$rows[] = $row;
		if ($rows)
			Util::execute('INSERT INTO rum_sample (metric, milliseconds, server_milliseconds, mode) VALUES '
				. implode(', ', array_fill(0, count($rows), '(?, ?, ?, ?)')), array_merge(...$rows));
		return $this->respond('', null, 204);
	}

	//The values of a valid sample, null for anything else
	/** @return array{string, int, int|null, int|null}|null */
	private static function row(mixed $sample): ?array
	{
		if (!is_array($sample) || !in_array($sample['metric'] ?? null, self::METRICS, true))
			return null;
		$milliseconds = self::milliseconds($sample['ms'] ?? null);
		if ($milliseconds === null)
			return null;
		$mode = $sample['mode'] ?? null;
		return [$sample['metric'], $milliseconds, self::milliseconds($sample['server_ms'] ?? null), is_int($mode) && $mode >= 0 && $mode < 256 ? $mode : null];
	}

	private static function milliseconds(mixed $value): ?int
	{
		if (!is_int($value) && !is_float($value))
			return null;
		return $value >= 0 && $value <= self::MAXIMUM_MILLISECONDS ? (int) round($value) : null;
	}
}

<?php

namespace App\Utility;

use App\Model\Table\ProgressDeletionTable;

//The scores a player's run through a whole collection makes, kept as the player's best of the collection
//(AchievementConditionTable::CATEGORY_SET_ACCURACY and CATEGORY_SET_TIME). They are over every problem of the
//collection, whatever part of it or ranks and tags the player's set page shows, and over the attempts since the
//latest reset of the part each problem is in: a reset starts a new run through that part. A run counts once every
//problem of the collection is solved, so 100% accuracy is the whole collection solved since its resets without a
//single mistake.
final class SetBestScores
{
	//$accuracy: The share of the attempts that solved their problem, in percent (TsumegoButtons::getAccuracy())
	//$averageSeconds: The average of the best solve time of each problem, a minute for one without a timed solve
	//$rating: The average rating of the problems, which the set achievements are banded by
	public function __construct(public readonly float $accuracy, public readonly float $averageSeconds, public readonly float $rating) {}

	//The scores of the player's run through the collection, null while a problem of it isn't solved.
	//$query: Runs a query and returns its rows; the site's database (Util::query) when null. The queries carry
	//their values in them, which are all numbers.
	public static function of(int $userID, int $setID, ?callable $query = null): ?self
	{
		$query ??= fn(string $sql): array => Util::query($sql);
		$problems = self::problems($setID, $query);
		if (empty($problems))
			return null;

		$statuses = [];
		foreach ($query('SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ' . $userID
			. ' AND tsumego_id IN (SELECT tsumego_id FROM set_connection WHERE set_id = ' . $setID . ')') as $row)
				$statuses[(int) $row['tsumego_id']] = (string) $row['status'];
		foreach (array_keys($problems) as $tsumegoID)
			if (!TsumegoUtil::isSolvedStatus($statuses[$tsumegoID] ?? null))
				return null;

		// the latest reset of each part; one saved before the parts were recorded is a reset of all of them
		$resets = [];
		foreach ($query('SELECT set_partition, MAX(created) AS created FROM progress_deletion WHERE user_id = ' . $userID
			. ' AND set_id = ' . $setID . ' GROUP BY set_partition') as $row)
				$resets[$row['set_partition'] === null ? 'all' : (int) $row['set_partition']] = (string) $row['created'];

		$solved = $failed = [];
		$bestSeconds = [];
		foreach ($query('SELECT tsumego_id, solved, seconds, misplays, created FROM tsumego_attempt WHERE user_id = ' . $userID
			. ' AND tsumego_id IN (SELECT tsumego_id FROM set_connection WHERE set_id = ' . $setID . ')') as $attempt)
		{
			$tsumegoID = (int) $attempt['tsumego_id'];
			if (!isset($problems[$tsumegoID]))
				continue;
			$since = max($resets[$problems[$tsumegoID]['partition']] ?? '', $resets['all'] ?? '');
			if ((string) $attempt['created'] <= $since)
				continue;
			$seconds = (int) $attempt['seconds'];
			if ($attempt['solved'])
			{
				$solved[$tsumegoID] = ($solved[$tsumegoID] ?? 0) + 1;
				if ($seconds > 0 && (!isset($bestSeconds[$tsumegoID]) || $seconds < $bestSeconds[$tsumegoID]))
					$bestSeconds[$tsumegoID] = $seconds;
			}
			// the misplays of an attempt are its fails, one at least for an attempt that failed
			$failed[$tsumegoID] = ($failed[$tsumegoID] ?? 0) + max((int) $attempt['misplays'], $attempt['solved'] ? 0 : 1);
		}

		$solvedSum = $failedSum = $secondsSum = $ratingSum = 0;
		foreach ($problems as $tsumegoID => $problem)
		{
			// a problem solved without an attempt since the reset (an attempt isn't kept in every mode) counts as a fail
			if (($solved[$tsumegoID] ?? 0) + ($failed[$tsumegoID] ?? 0) == 0)
				$failedSum++;
			$solvedSum += $solved[$tsumegoID] ?? 0;
			$failedSum += $failed[$tsumegoID] ?? 0;
			$secondsSum += $bestSeconds[$tsumegoID] ?? 60;
			$ratingSum += $problem['rating'];
		}
		return new self(
			$solvedSum + $failedSum == 0 ? 0 : round($solvedSum / ($solvedSum + $failedSum) * 100, 2),
			round($secondsSum / count($problems), 2),
			$ratingSum / count($problems));
	}

	//The problems of the collection, each with its rating and the part of the collection it is in: ordered by number
	//with the deleted ones left out, in parts of ProgressDeletionTable::$PARTITION_SIZE, as a reset clears them. A
	//problem in the collection twice is in the part of its first place.
	/** @return array<int, array{rating: float, partition: int}> By problem */
	private static function problems(int $setID, callable $query): array
	{
		$problems = [];
		$position = 0;
		foreach ($query('SELECT sc.tsumego_id, tsumego.rating FROM set_connection sc
			JOIN tsumego ON tsumego.id = sc.tsumego_id AND tsumego.deleted IS NULL
			WHERE sc.set_id = ' . $setID . ' ORDER BY sc.num, sc.id') as $row)
		{
			$tsumegoID = (int) $row['tsumego_id'];
			if (!isset($problems[$tsumegoID]))
				$problems[$tsumegoID] = ['rating' => (float) $row['rating'], 'partition' => intdiv($position, ProgressDeletionTable::$PARTITION_SIZE)];
			$position++;
		}
		return $problems;
	}
}

<?php

namespace App\Utility;

use App\Model\Table\AchievementConditionTable;
use App\Model\Table\ProgressDeletionTable;
use App\Model\Table\SetTable;

//The scores of a player's run through a part of a collection, kept as the player's best of that part
//(AchievementConditionTable::CATEGORY_SET_ACCURACY and CATEGORY_SET_TIME). A part is what a reset clears: the problems
//of the collection ordered by number, deleted ones left out, in parts of ProgressDeletionTable::$PARTITION_SIZE,
//whatever collection size the player picked. A run is the attempts since the latest reset of the part, and it counts
//once every problem of the part is solved, so 100% accuracy is the part solved since its reset without a mistake.
final class SetPartBestScores
{
	//$accuracy: The share of the attempts that solved their problem, in percent; the misplays of an attempt are its
	//fails, a solved one's too, and a problem solved without an attempt since the reset counts as a fail
	//$averageSeconds: The average of the best solve time of each problem, a minute for one without a timed solve
	//$rating: The average rating of the problems, which the set achievements are banded by
	public function __construct(public readonly float $accuracy, public readonly float $averageSeconds, public readonly float $rating) {}

	//The scores of the player's run through the part, null while a problem of it isn't solved
	public static function of(int $userID, int $setID, int $partition): ?self
	{
		$problems = self::problemsOf($setID, $partition);
		if (empty($problems))
			return null;
		$in = implode(',', array_keys($problems));

		foreach (Util::query('SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ? AND tsumego_id IN (' . $in . ')', [$userID]) as $row)
			$problems[(int) $row['tsumego_id']]['status'] = (string) $row['status'];
		foreach ($problems as $problem)
			if (!TsumegoUtil::isSolvedStatus($problem['status'] ?? null))
				return null;

		// the latest reset of the part; one saved before the parts were recorded was a reset of all of them
		$since = (string) (Util::query('SELECT MAX(created) AS created FROM progress_deletion
			WHERE user_id = ? AND set_id = ? AND (set_partition = ? OR set_partition IS NULL)', [$userID, $setID, $partition])[0]['created'] ?? '');

		$solved = $failed = $bestSeconds = [];
		foreach (Util::query('SELECT tsumego_id, solved, seconds, misplays FROM tsumego_attempt
			WHERE user_id = ? AND tsumego_id IN (' . $in . ') AND created > ?', [$userID, $since === '' ? '1000-01-01' : $since]) as $attempt)
		{
			$tsumegoID = (int) $attempt['tsumego_id'];
			$seconds = (int) $attempt['seconds'];
			if ($attempt['solved'])
			{
				$solved[$tsumegoID] = ($solved[$tsumegoID] ?? 0) + 1;
				if ($seconds > 0 && (!isset($bestSeconds[$tsumegoID]) || $seconds < $bestSeconds[$tsumegoID]))
					$bestSeconds[$tsumegoID] = $seconds;
			}
			$failed[$tsumegoID] = ($failed[$tsumegoID] ?? 0) + max((int) $attempt['misplays'], $attempt['solved'] ? 0 : 1);
		}

		$solvedSum = $failedSum = $secondsSum = $ratingSum = 0;
		foreach ($problems as $tsumegoID => $problem)
		{
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

	//Keeps the scores of the player's run through the part as their best when the part is solved whole and is of one of
	//the site's collections; the scores, or null when nothing was kept
	public static function record(int $userID, int $setID, int $partition): ?self
	{
		$set = Util::query('SELECT public, user_id, deleted FROM `set` WHERE id = ?', [$setID])[0] ?? null;
		if (!$set || !SetTable::isOfficial($set))
			return null;
		$scores = self::of($userID, $setID, $partition);
		if ($scores === null)
			return null;
		AchievementConditionTable::keepHighest($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $scores->accuracy, $setID, $partition);
		AchievementConditionTable::keepLowest($userID, AchievementConditionTable::CATEGORY_SET_TIME, $scores->averageSeconds, $setID, $partition);
		return $scores;
	}

	//Keeps the best of every part of a collection the problem just solved finished
	/** @return list<array{set_id: int, partition: int, scores: self}> The parts finished */
	public static function recordAfterSolve(int $userID, int $tsumegoID): array
	{
		$finished = [];
		foreach (Util::query('SELECT id FROM set_connection WHERE tsumego_id = ?', [$tsumegoID]) as $row)
		{
			$part = ProgressDeletionTable::partitionOf((int) $row['id'], $tsumegoID);
			if ($part === null)
				continue;
			$scores = self::record($userID, $part['set_id'], $part['partition']);
			if ($scores !== null)
				$finished[] = ['set_id' => $part['set_id'], 'partition' => $part['partition'], 'scores' => $scores];
		}
		return $finished;
	}

	//The problems of the part, each with its rating; a problem in the part twice is there once
	/** @return array<int, array{rating: float}> By problem */
	private static function problemsOf(int $setID, int $partition): array
	{
		$problems = [];
		$rows = Util::query('SELECT sc.tsumego_id, tsumego.rating FROM set_connection sc
			JOIN tsumego ON tsumego.id = sc.tsumego_id AND tsumego.deleted IS NULL
			WHERE sc.set_id = ? ORDER BY sc.num, sc.id', [$setID]);
		foreach (array_slice($rows, $partition * ProgressDeletionTable::$PARTITION_SIZE, ProgressDeletionTable::$PARTITION_SIZE) as $row)
			$problems[(int) $row['tsumego_id']] = ['rating' => (float) $row['rating']];
		return $problems;
	}
}

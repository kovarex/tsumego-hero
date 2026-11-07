<?php

namespace App\Controller;

use App\Model\Table\SetTable;
use App\Model\Table\TsumegoStatusTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\Rating;
use App\Utility\RatingBounds;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Utility\Util;

class RatingModeController extends AppController
{
	#[HttpGet]
	#[HttpPost]
	public function index(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		if (!Auth::isInRatingMode())
			Auth::saveUserField('mode_id', Constants::$RATING_MODE);
		$difficultyChange = (int) Util::clearCookie('difficulty');
		if ($difficultyChange >= 1 && $difficultyChange <= 7)
			Auth::saveUserField('rating_mode_difficulty_selection', $difficultyChange);

		$adjustedRating =  Auth::getUser()['rating'] + Rating::adjustmentForDifficulty(Auth::getUser()['rating_mode_difficulty_selection']);
		$ratingBounds = new RatingBounds(
			$adjustedRating - Constants::$RATING_MODE_SELECTION_INTERVAL / 2,
			$adjustedRating + Constants::$RATING_MODE_SELECTION_INTERVAL / 2);

		$inRange = "";
		$ratingBounds->addSqlConditions($inRange);
		$selectedTsumego = self::pick($inRange, 'priority, RAND()');
		// Nothing unsolved in range: an unsolved problem of a wider range, the narrowest that has one, is
		// better than a solved one, which gives no rating or XP. With every problem of the widest range
		// solved, the solved ones come, then the locked ones. Beyond the widest range there is still the
		// closest problem, so a rating far from every problem (a beginner on very easy, a strong player on
		// very difficult) still gets one, and keeps the slider to change the difficulty on.
		if (!$selectedTsumego || $selectedTsumego['priority'] > 0)
		{
			$half = Constants::$RATING_MODE_SELECTION_INTERVAL / 2;
			$distance = 'ABS(tsumego.rating - ' . (float) $adjustedRating . ')';
			$range = "CASE WHEN $distance < $half THEN 0 WHEN $distance < " . 2 * $half . " THEN 1 WHEN $distance < " . 4 * $half . " THEN 2 ELSE 3 END";
			$selectedTsumego = self::pick('', implode(', ', [
				"$range = 3", // what is in the widest range first, whatever its priority
				'priority', // unsolved, then solved, then locked
				$range, // the narrowest range that has one
				"CASE WHEN $range = 3 THEN $distance ELSE 0 END", // beyond the widest range, the closest
				'RAND()'])) ?? $selectedTsumego;
		}

		if (!$selectedTsumego)
		{
			CookieFlash::set("No problems found for your rating selection", 'failure');
			return $this->redirect('/sets');
		}

		$this->renderPlay((int) $selectedTsumego['id'], [
			'nextLink' => '/ratingMode',
			'difficulty' => Auth::getUser()['rating_mode_difficulty_selection']]);
		return null;
	}

	//The set connection of a problem of the site's collections meeting the condition, with its priority
	//for the player: 0 unsolved, 1 solved or mastered (no rating or XP for them), 2 locked (can't be
	//played today), in the order given. A solve older than a week turns into review, which counts as
	//unsolved again.
	/** @return array{id: int|string, priority: int|string}|null */
	private static function pick(string $condition, string $order): ?array
	{
		Util::addSqlCondition($condition, SetTable::officialCondition());
		Util::addSqlCondition($condition, 'tsumego.deleted IS NULL');
		$row = Util::query("
SELECT
	set_connection.id as id,
	CASE
		WHEN tsumego_status.status IN (?, ?) THEN 2
		WHEN tsumego_status.status IN (?, ?) THEN 1
		ELSE 0
	END AS priority
FROM
	tsumego
	JOIN set_connection ON set_connection.tsumego_id = tsumego.id
	JOIN `set` ON set_connection.set_id = set.id
	LEFT JOIN tsumego_status ON tsumego_status.tsumego_id = tsumego.id AND tsumego_status.user_id = ?
WHERE " . $condition . "
ORDER BY " . $order . "
LIMIT 1", [
			TsumegoStatusTable::$LOCKED, TsumegoStatusTable::$FORGOTTEN,
			TsumegoStatusTable::$SOLVED, TsumegoStatusTable::$MASTERED,
			Auth::getUserID()])[0] ?? null;
		return $row === null ? null : ['id' => $row['id'], 'priority' => (int) $row['priority']];
	}
}

<?php

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

		$queryCondition = "";
		Util::addSqlCondition($queryCondition, SetTable::officialCondition());
		Util::addSqlCondition($queryCondition, 'tsumego.deleted IS NULL');
		$ratingBounds->addSqlConditions($queryCondition);
		// Solved and mastered problems give no rating or XP, and locked ones can't be played today, so they
		// are only picked when nothing else is in range. A solve older than a week turns into review,
		// which counts again.
		$query = "
SELECT
	set_connection.id as id
FROM
	tsumego
	JOIN set_connection ON set_connection.tsumego_id = tsumego.id
	JOIN `set` ON set_connection.set_id = set.id
	LEFT JOIN tsumego_status ON tsumego_status.tsumego_id = tsumego.id AND tsumego_status.user_id = ?
WHERE " . $queryCondition . "
ORDER BY
	CASE
		WHEN tsumego_status.status IN (?, ?) THEN 2
		WHEN tsumego_status.status IN (?, ?) THEN 1
		ELSE 0
	END,
	RAND()
LIMIT 1";
		$selectedTsumego = Util::query($query, [
			Auth::getUserID(),
			TsumegoStatusTable::$LOCKED, TsumegoStatusTable::$FORGOTTEN,
			TsumegoStatusTable::$SOLVED, TsumegoStatusTable::$MASTERED])[0] ?? null;

		if (!$selectedTsumego)
		{
			CookieFlash::set("No problems found for your rating selection", 'failure');
			return $this->redirect('/sets');
		}

		$this->set('nextLink', '/ratingMode');
		$this->set('difficulty', Auth::getUser()['rating_mode_difficulty_selection']);

		$play  = new Play(function ($name, $value) {
			$this->set($name, $value);
		});
		$play->play((int) $selectedTsumego['id'], $this->request);
		$this->render('/Tsumegos/play');
		return null;
	}
}

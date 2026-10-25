<?php

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
			Auth::saveUserField('mode', Constants::$RATING_MODE);
		if ($difficultyChange = Util::clearCookie('difficulty'))
			Auth::saveUserField('t_glicko', $difficultyChange);

		$adjustedRating =  Auth::getUser()['rating'] + Rating::adjustmentForDifficulty(Auth::getUser()['t_glicko']);
		$ratingBounds = new RatingBounds(
			$adjustedRating - Constants::$RATING_MODE_SELECTION_INTERVAL / 2,
			$adjustedRating + Constants::$RATING_MODE_SELECTION_INTERVAL / 2);

		$queryCondition = "";
		Util::addSqlCondition($queryCondition, Set::officialCondition());
		Util::addSqlCondition($queryCondition, 'tsumego.deleted IS NULL');
		$ratingBounds->addSqlConditions($queryCondition);
		$query = "
SELECT
	set_connection.id as id
FROM
	tsumego
	JOIN set_connection ON set_connection.tsumego_id = tsumego.id
	JOIN `set` ON set_connection.set_id = set.id
WHERE " . $queryCondition;
		$relatedTsumegos = Util::query($query);

		if (empty($relatedTsumegos))
		{
			CookieFlash::set("No problems found for your rating selection", 'failure');
			return $this->redirect('/sets');
		}
		shuffle($relatedTsumegos);

		$this->set('nextLink', '/ratingMode');
		$this->set('difficulty', Auth::getUser()['t_glicko']);

		$play  = new Play(function ($name, $value) {
			$this->set($name, $value);
		});
		$play->play($relatedTsumegos[0]['id'], $this->request, $this->request->data);
		$this->render('/Tsumegos/play');
		return null;
	}
}

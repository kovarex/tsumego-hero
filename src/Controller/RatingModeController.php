<?php

class RatingModeController extends AppController
{
	public static function ratingAdjustment($difficultySetting)
	{
		$adjustments = [
			1 => -Constants::$RATING_MODE_DIFFERENCE_SETTING_3,
			2 => -Constants::$RATING_MODE_DIFFERENCE_SETTING_2,
			3 => -Constants::$RATING_MODE_DIFFERENCE_SETTING_1,
			4 => 0,
			5 => Constants::$RATING_MODE_DIFFERENCE_SETTING_1,
			6 => Constants::$RATING_MODE_DIFFERENCE_SETTING_2,
			7 => Constants::$RATING_MODE_DIFFERENCE_SETTING_3,
		];
		return $adjustments[$difficultySetting] ?? 0;
	}

	public function index(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		if (!Auth::isInRatingMode())
			Auth::saveUserField('mode', Constants::$RATING_MODE);
		if ($difficultyChange = Util::clearCookie('difficulty'))
			Auth::saveUserField('t_glicko', $difficultyChange);

		$adjustedRating =  Auth::getUser()['rating'] + self::ratingAdjustment(Auth::getUser()['t_glicko']);
		$ratingBounds = new RatingBounds(
			$adjustedRating - Constants::$RATING_MODE_SELECTION_INTERVAL / 2,
			$adjustedRating + Constants::$RATING_MODE_SELECTION_INTERVAL / 2);

		$queryCondition = "";
		Util::addSqlCondition($queryCondition, "`set`.public = true");
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
		$play->play($relatedTsumegos[0]['id'], $this->params, $this->data);
		$this->render('/Tsumegos/play');
		return null;
	}
}

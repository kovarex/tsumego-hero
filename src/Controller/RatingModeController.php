<?php

class RatingModeController extends AppController
{
	static function ratingAdjustment($difficultySetting)
	{
		if ($difficultySetting == 1)
			return -Constants::$RATING_MODE_DIFFERENCE_SETTING_3;
		if ($difficultySetting == 2)
			return -Constants::$RATING_MODE_DIFFERENCE_SETTING_2;
		if ($difficultySetting == 3)
			return -Constants::$RATING_MODE_DIFFERENCE_SETTING_1;
		if ($difficultySetting == 4)
			return 0;
		if ($difficultySetting == 5)
			return Constants::$RATING_MODE_DIFFERENCE_SETTING_1;
		if ($difficultySetting == 6)
			return Constants::$RATING_MODE_DIFFERENCE_SETTING_2;
		if ($difficultySetting == 7)
			return Constants::$RATING_MODE_DIFFERENCE_SETTING_3;
		return 0;
	}

	public function index(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		if (!Auth::isInRatingMode())
		{
			Auth::getUser()['mode'] = Constants::$RATING_MODE;
			Auth::saveUser();
		}
		if ($difficultyChange = Util::clearCookie('difficulty'))
			Auth::getUser()['t_glicko'] = $difficultyChange;

		$adjustedRating =  Auth::getUser()['rating'] + self::ratingAdjustment(Auth::getUser()['t_glicko']);
		$ratingBounds = new RatingBounds(
			$adjustedRating - Constants::$RATING_MODE_SELECTION_INTERVAL / 2,
			$adjustedRating + Constants::$RATING_MODE_SELECTION_INTERVAL / 2);

		$queryCondition = "";
		Util::addSqlCondition($queryCondition, "`set`.public = true");
		if (!Auth::hasPremium())
			Util::addSqlCondition($queryCondition, "`set`.premium = false");
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

		$play  = new Play(function ($name, $value) { $this->set($name, $value); });
		$play->play($relatedTsumegos[0]['id'], $this->params, $this->data);
		$this->render('/Tsumegos/play');
		return null;
	}
}

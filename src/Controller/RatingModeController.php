<?php

class RatingModeController extends AppController
{
	public function index(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		if (!Auth::isInRatingMode())
		{
			Auth::getUser()['mode'] = Constants::$RATING_MODE;
			Auth::saveUser();
		}
		$difficulty = Auth::getUser()['t_glicko'];
		if ($difficultyChange = Util::clearCookie('difficulty'))
			Auth::getUser()['t_glicko'] = $difficultyChange;

		if ($difficulty == 1)
			$adjustDifficulty = -450;
		elseif ($difficulty == 2)
			$adjustDifficulty = -300;
		elseif ($difficulty == 3)
			$adjustDifficulty = -150;
		elseif ($difficulty == 4)
			$adjustDifficulty = 0;
		elseif ($difficulty == 5)
			$adjustDifficulty = 150;
		elseif ($difficulty == 6)
			$adjustDifficulty = 300;
		elseif ($difficulty == 7)
			$adjustDifficulty = 450;
		else
			$adjustDifficulty = 0;

		$adjustedRating = Auth::getUser()['rating'] + $adjustDifficulty;
		$ratingBounds = new RatingBounds($adjustedRating - 240, $adjustedRating + 240);

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

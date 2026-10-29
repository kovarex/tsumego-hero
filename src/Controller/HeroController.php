<?php

use App\Model\Table\SetTable;
use App\Model\Table\TsumegoStatusTable;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Model\Table\AchievementConditionTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\HeroPowers;
use App\Utility\Util;

class HeroController extends AppController
{
	//Makes a random problem of the official collections golden and opens it. A problem near the
	//player's rating comes first, any other problem only when none is that close.
	#[HttpGet]
	public function refinement(): CakeResponse
	{
		$this->Authorization->authorize('Hero');

		$rating = Auth::getUser()['rating'];
		$setConnection = Util::query('SELECT set_connection.id AS set_connection_id, tsumego.id AS tsumego_id
			FROM tsumego
			JOIN set_connection ON set_connection.tsumego_id = tsumego.id
			JOIN `set` ON `set`.id = set_connection.set_id
			WHERE tsumego.deleted IS NULL AND ' . SetTable::officialCondition() . '
			ORDER BY tsumego.rating BETWEEN ? AND ? DESC, RAND()
			LIMIT 1', [
			$rating - Constants::$GOLDEN_TSUMEGO_LOWER_RELATIVE_RATING_LIMIT,
			$rating + Constants::$GOLDEN_TSUMEGO_UPPER_RELATIVE_RATING_LIMIT,
		])[0] ?? null;
		if (!$setConnection)
			throw new Exception('No valid tsumego to choose from.');

		TsumegoStatusTable::setStatus(Auth::getUserID(), $setConnection['tsumego_id'], TsumegoStatusTable::$GOLDEN);
		Auth::saveUserField('used_refinement', 1);
		return $this->redirect('/' . $setConnection['set_connection_id']);
	}

	#[HttpPost]
	public function sprint(): CakeResponse
	{
		$this->Authorization->authorize('Hero');
		Auth::saveUserFields([
			'sprint_start' => date('Y-m-d H:i:s'),
			'used_sprint' => 1,
		]);
		//The sprint run counts the problems solved during this sprint only
		AchievementConditionTable::put(Auth::getUserID(), AchievementConditionTable::CATEGORY_SPRINT, 0);
		return $this->respond('');
	}

	#[HttpPost]
	public function intuition(): CakeResponse
	{
		$this->Authorization->authorize('Hero');
		Auth::saveUserField('used_intuition', 1);
		return $this->respond('');
	}

	//Restores health and intuition, and unlocks the problems failed today: a locked problem
	//becomes visited again, a forgotten one goes back to review.
	#[HttpPost]
	public function rejuvenation(): CakeResponse
	{
		$this->Authorization->authorize('Hero');
		Auth::saveUserFields([
			'used_rejuvenation' => 1,
			'used_intuition' => 0,
			'damage' => 0,
		]);

		Util::execute('UPDATE tsumego_status SET status = CASE status WHEN ? THEN ? WHEN ? THEN ? END
			WHERE user_id = ? AND status IN (?, ?)', [
			TsumegoStatusTable::$LOCKED, TsumegoStatusTable::$VISITED,
			TsumegoStatusTable::$FORGOTTEN, TsumegoStatusTable::$REVIEW,
			Auth::getUserID(), TsumegoStatusTable::$LOCKED, TsumegoStatusTable::$FORGOTTEN,
		]);
		return $this->respond('');
	}

	//Marks the problem solved without any reward.
	#[HttpPost]
	public function revelation(string $tsumegoID): CakeResponse
	{
		$this->Authorization->authorize('Hero');

		if (!$this->fetchTable('Tsumego')->exists(['id' => (int) $tsumegoID]))
			throw new NotFoundException('Tsumego not found');

		//used_revelation is bounded by getRevelationUseCount(). Enforce the bound in the database with
		//a conditional atomic increment so two concurrent requests that both pass canUseRevelation()
		//cannot push it past the limit.
		if (!Auth::incrementUserFieldIf('used_revelation', 1, ['used_revelation <' => HeroPowers::getRevelationUseCount()]))
			throw new ForbiddenException('Revelation is used up today.');

		TsumegoStatusTable::setStatus(Auth::getUserID(), (int) $tsumegoID, TsumegoStatusTable::$SOLVED);
		return $this->respond('');
	}
}

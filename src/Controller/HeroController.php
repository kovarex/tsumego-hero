<?php

use App\Attribute\HttpPost;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\HeroPowers;
use App\Utility\Util;

class HeroController extends AppController
{
	public function refinement()
	{
		$this->Authorization->authorize('Hero');

		$queryWithoutRankLimit = "SELECT "
				. "set_connection.id as set_connection_id, tsumego.id as tsumego_id "
				. " FROM tsumego"
				. " JOIN set_connection ON set_connection.tsumego_id=tsumego.id"
				. " JOIN `set` ON `set`.id=set_connection.set_id"
				. " WHERE tsumego.deleted is null AND set.public = 1";

		// first we try to select golden tsumego with proper rating relative to user
		$setConnectionIDs = Util::query($queryWithoutRankLimit . " AND tsumego.rating >= ? AND tsumego.rating <= ?",
			[
				Auth::getUser()['rating'] - Constants::$GOLDEN_TSUMEGO_LOWER_RELATIVE_RATING_LIMIT,
				Auth::getUser()['rating'] + Constants::$GOLDEN_TSUMEGO_UPPER_RELATIVE_RATING_LIMIT
			]);

		// if it fails, we try to select "some" tsumego
		if (empty($setConnectionIDs))
			$setConnectionIDs = Util::query($queryWithoutRankLimit);
		if (empty($setConnectionIDs))
			throw new Exception("No valid tsumego to choose from.");
		$setConnection = $setConnectionIDs[rand(0, count($setConnectionIDs) - 1)];
		$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $setConnection['tsumego_id'],
			'user_id' => Auth::getUserID()]]);
		if (!$tsumegoStatus)
		{
			ClassRegistry::init('TsumegoStatus')->create();
			$tsumegoStatus = [];
			$tsumegoStatus['user_id'] = Auth::getUserID();
			$tsumegoStatus['tsumego_id'] = $setConnection['tsumego_id'];
		}
		else
			$tsumegoStatus = $tsumegoStatus['TsumegoStatus'];
		$tsumegoStatus['created'] = date('Y-m-d H:i:s');
		$tsumegoStatus['status'] = 'G';
		ClassRegistry::init('TsumegoStatus')->save($tsumegoStatus);
		Auth::saveUserField('used_refinement', 1);
		return $this->redirect('/' . $setConnection['set_connection_id']);
	}

	#[HttpPost]
	public function sprint()
	{
		$this->Authorization->authorize('Hero');
		Auth::saveUserFields([
			'sprint_start' => date('Y-m-d H:i:s'),
			'used_sprint' => 1,
		]);
		$this->response->statusCode(200);
		return $this->response;
	}

	#[HttpPost]
	public function intuition()
	{
		$this->Authorization->authorize('Hero');
		Auth::saveUserField('used_intuition', 1);
		$this->response->statusCode(200);
		return $this->response;
	}

	#[HttpPost]
	public function rejuvenation()
	{
		$this->Authorization->authorize('Hero');
		Auth::saveUserFields([
			'used_rejuvenation' => 1,
			'used_intuition' => 0,
			'damage' => 0,
		]);

		ClassRegistry::init('TsumegoStatus')->query("UPDATE tsumego_status SET status='V' WHERE status='F' AND user_id=" . Auth::getUserID());
		ClassRegistry::init('TsumegoStatus')->query("UPDATE tsumego_status SET status='W' WHERE status='X' AND user_id=" . Auth::getUserID());

		$this->response->statusCode(200);
		return $this->response;
	}

	#[HttpPost]
	public function revelation(string $tsumegoID)
	{
		$this->Authorization->authorize('Hero');

		// used_revelation is bounded by getRevelationUseCount(). Enforce the bound
		// in the database with a conditional atomic increment so two concurrent
		// requests that both pass canUseRevelation() cannot push it past the limit.
		if (!Auth::incrementUserFieldIf('used_revelation', 1, ['used_revelation <' => HeroPowers::getRevelationUseCount()]))
			throw new ForbiddenException('Revelation is used up today.');

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
			throw new ForbiddenException();

		$previousTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $tsumegoID]]);
		if (!$previousTsumegoStatus)
		{
			$previousTsumegoStatus = [];
			$previousTsumegoStatus['user_id'] = Auth::getUserID();
			$previousTsumegoStatus['tsumego_id'] = $tsumegoID;
			ClassRegistry::init('TsumegoStatus')->create();
		}
		else
			$previousTsumegoStatus = $previousTsumegoStatus['TsumegoStatus'];

		$previousTsumegoStatus['created'] = date('Y-m-d H:i:s');
		$previousTsumegoStatus['status'] = 'S';
		ClassRegistry::init('TsumegoStatus')->save($previousTsumegoStatus);

		$this->response->statusCode(200);
		return $this->response;
	}
}

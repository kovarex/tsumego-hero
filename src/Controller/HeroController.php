<?php

class HeroController extends AppController
{
	public function refinement()
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		if (Auth::getUser()['used_refinement'])
			throw new AppException("Refinment is already used up.");

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
		Auth::getUser()['used_refinement'] = 1;
		Auth::saveUser();
		return $this->redirect('/' . $setConnection['set_connection_id']);
	}

	public function sprint()
	{
		if (!HeroPowers::canUseSprint())
		{
			$this->response->statusCode(403);
			return $this->response;
		}
		Auth::getUser()['sprint_start'] = date('Y-m-d H:i:s');
		Auth::getUser()['used_sprint'] = 1;
		Auth::saveUser();
		$this->response->statusCode(200);
		return $this->response;
	}

	public function intuition()
	{
		if (!HeroPowers::canUseIntuition())
		{
			$this->response->statusCode(403);
			return $this->response;
		}
		Auth::getUser()['used_intuition'] = 1;
		Auth::saveUser();
		$this->response->statusCode(200);
		return $this->response;
	}

	public function rejuvenation()
	{
		if (!HeroPowers::canUseRejuvanation())
		{
			$this->response->statusCode(403);
			return $this->response;
		}
		Auth::getUser()['used_rejuvenation'] = 1;
		Auth::getUser()['used_intuition'] = 0;
		Auth::getUser()['damage'] = 0;
		Auth::saveUser();

		ClassRegistry::init('TsumegoStatus')->query("UPDATE tsumego_status SET status='V' WHERE status='F' AND user_id=" . Auth::getUserID());
		ClassRegistry::init('TsumegoStatus')->query("UPDATE tsumego_status SET status='W' WHERE status='X' AND user_id=" . Auth::getUserID());

		$this->response->statusCode(200);
		return $this->response;
	}

	public function revelation($tsumegoID)
	{
		if (!Auth::isLoggedIn())
		{
			$this->response->body('Not logged in.');
			$this->response->statusCode(403);
			return $this->response;
		}

		if (!HeroPowers::canUseRevelation())
		{
			$this->response->body('Revelation is used up today.');
			$this->response->statusCode(403);
			return $this->response;
		}
		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
		{
			$this->response->statusCode(403);
			return $this->response;
		}

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

		Auth::getUser()['used_revelation']++;
		Auth::saveUser();
	}
}

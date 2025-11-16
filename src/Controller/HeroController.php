<?php

class HeroController extends AppController {
	public function refinement() {
		if (!Auth::isLoggedIn()) {
			return $this->redirect('/users/login');
		}
		if (Auth::getUser()['used_refinement']) {
			throw new AppException("Refinment is already used up.");
		}

		$setConnectionIDs = ClassRegistry::init('Tsumego')->query(
			"SELECT "
					. "set_connection.id, tsumego.id "
				. " FROM tsumego"
				. " JOIN set_connection ON set_connection.tsumego_id=tsumego.id"
				. " JOIN `set` ON `set`.id=set_connection.set_id"
				. " WHERE tsumego.deleted is null AND set.public = 1");
		if (empty($setConnectionIDs)) {
			throw new Exception("No valid tsumego to choose from.");
		}
		$setConnection = $setConnectionIDs[rand(0, count($setConnectionIDs) - 1)];
		$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $setConnection['tsumego']['id'],
			'user_id' => Auth::getUserID()]]);
		if (!$tsumegoStatus) {
			ClassRegistry::init('TsumegoStatus')->create();
			$tsumegoStatus = [];
			$tsumegoStatus['user_id'] = Auth::getUserID();
			$tsumegoStatus['tsumego_id'] = $setConnection['tsumego']['id'];
		} else {
			$tsumegoStatus = $tsumegoStatus['TsumegoStatus'];
		}
		$tsumegoStatus['created'] = date('Y-m-d H:i:s');
		$tsumegoStatus['status'] = 'G';
		ClassRegistry::init('TsumegoStatus')->save($tsumegoStatus);
		Auth::getUser()['used_refinement'] = 1;
		Auth::saveUser();
		return $this->redirect('/' . $setConnection['set_connection']['id']);
	}

	public function sprint() {
		if (!HeroPowers::canUseSprint()) {
			$this->response->statusCode(403);
			return $this->response;
		}
		Auth::getUser()['sprint_start'] = date('Y-m-d H:i:s');
		Auth::getUser()['used_sprint'] = 1;
		Auth::saveUser();
		$this->response->statusCode(200);
		return $this->response;
	}

	public function intuition() {
		if (!HeroPowers::canUseIntuition()) {
			$this->response->statusCode(403);
			return $this->response;
		}
		Auth::getUser()['used_intuition'] = 1;
		Auth::saveUser();
		$this->response->statusCode(200);
		return $this->response;
	}
}

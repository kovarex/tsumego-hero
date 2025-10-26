<?php

class AchievementsController extends AppController {
	/**
	 * @return void
	 */
	public function index() {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'Tsumego Hero - Achievements');
		$this->loadModel('AchievementStatus');
		$existingAs = [];
		$unlockedCounter2 = 0;

		$a = $this->Achievement->find('all', ['order' => 'order ASC']);
		if (!$a) {
			$a = [];
		}

		if (Auth::isLoggedIn()) {
			$as = $this->AchievementStatus->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
			if (!$as) {
				$as = [];
			}

			foreach ($as as $item) {
				$existingAs[$item['AchievementStatus']['achievement_id']] = $item;
			}
		}

		$aCount = count($a);
		for ($i = 0; $i < $aCount; $i++) {
			$a[$i]['Achievement']['unlocked'] = false;
			$a[$i]['Achievement']['created'] = '';
			if (isset($existingAs[$a[$i]['Achievement']['id']])) {
				if ($a[$i]['Achievement']['id'] == 46) {
					$a[$i]['Achievement']['a46value'] = $existingAs[$a[$i]['Achievement']['id']]['AchievementStatus']['value'];
					$unlockedCounter2 = $existingAs[$a[$i]['Achievement']['id']]['AchievementStatus']['value'] - 1;
				}
				$a[$i]['Achievement']['unlocked'] = true;
				$a[$i]['Achievement']['created'] = $existingAs[$a[$i]['Achievement']['id']]['AchievementStatus']['created'];
				$date = date_create($a[$i]['Achievement']['created']);
				$a[$i]['Achievement']['created'] = date_format($date, 'd.m.Y H:i');
			}
		}
		$this->set('a', $a);
		$this->set('unlockedCounter2', $unlockedCounter2);
	}

	/**
	 * @param string|int|null $id
	 * @return void
	 */
	public function view($id = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'Tsumego Hero - Achievements');
		$this->loadModel('AchievementCondition');
		$this->loadModel('AchievementStatus');
		$this->loadModel('User');
		$a = $this->Achievement->findById($id);

		$as = [];
		$asAll = $this->AchievementStatus->find('all', ['order' => 'created DESC', 'conditions' => ['achievement_id' => $id]]);
		if (!$asAll) {
			$asAll = [];
		}
		$aCount = count($asAll);
		if (Auth::isLoggedIn()) {
			$as = $this->AchievementStatus->find('first', ['conditions' => ['achievement_id' => $id, 'user_id' => Auth::getUserID()]]);
		}
		if ($as) {
			$date = date_create($as['AchievementStatus']['created']);
			$as['AchievementStatus']['created'] = date_format($date, 'd.m.Y H:i');
		}
		$asAll2 = [];
		$count = 10;
		if (count($asAll) < 10) {
			$count = count($asAll);
		}
		if (count($asAll) > 10) {
			$andMore = ' and more.';
		} else {
			$andMore = '.';
		}
		for ($i = 0; $i < $count; $i++) {
			$u = $this->User->findById($asAll[$i]['AchievementStatus']['user_id']);
			$asAll[$i]['AchievementStatus']['name'] = $this->checkPicture($u);
			$asAll2[] = $asAll[$i];
		}
		$asAll = $asAll2;

		if (Auth::isLoggedIn()) {
			$acGolden = $this->AchievementCondition->find('all', ['conditions' => ['user_id' => Auth::getUserID(), 'category' => 'golden']]);
			if (!$acGolden) {
				$acGolden = [];
			}
			if (count($acGolden) == 0) {
				$acGoldenCount = 0;
			} else {
				$acGoldenCount = $acGolden[0]['AchievementCondition']['value'];
			}
			if ($as) {
				$acGoldenCount = 10;
			}
			if ($a['Achievement']['id'] == 97) {
				$a['Achievement']['additionalDescription'] = 'Progress: ' . $acGoldenCount . '/10';
			}
		}

		$this->set('a', $a);
		$this->set('as', $as);
		$this->set('asAll', $asAll);
		$this->set('aCount', $aCount);
		$this->set('andMore', $andMore);
	}

}

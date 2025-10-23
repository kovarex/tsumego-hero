<?php

class TsumegoRatingAttemptsController extends AppController {

	/**
	 * @param string|int|null $trid User ID for filtering
	 * @return void
	 */
	public function index($trid = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'TSUMEGO RECORDS');
		if ($trid == null) {
			$trs = $this->TsumegoRatingAttempt->find('all', ['limit' => 500, 'order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}
		} else {
			$trs = $this->TsumegoRatingAttempt->find('all', ['limit' => 500, 'order' => 'created DESC', 'conditions' => ['user_id' => $trid]]);
			if (!$trs) {
				$trs = [];
			}
		}
		$this->set('trs', $trs);
		//$this->set('trs2', $trs2);
		$this->set('x', '');
		if ($trid != null) {
			$this->set('x', '<a href="/tsumego_records/"><< back to overview</a>');
		}
	}

	/**
	 * @param string|int|null $type Type of JSON output
	 * @return void
	 */
	public function json($type = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'TSUMEGO RECORDS');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('User');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');

		if ($type == 0) {
			$trs = $this->TsumegoRatingAttempt->find('all', [
				'limit' => 12000,
				'order' => 'created DESC',
				'conditions' => [
					'tsumego_id >' => 17000,
				],
			]);
			if (!$trs) {
				$trs = [];
			}

			$header = ['user_id', 'user_elo', 'user_ip', 'user_country', 'user_country_code', 'tsumego_id', 'tsumego_elo', 'tsumego_set', 'status', 'seconds', 'created'];

			$posts = [];
			$trsCount = count($trs);
			for ($i = 0; $i < $trsCount; $i++) {
				$user = $this->User->findById($trs[$i]['TsumegoRatingAttempt']['user_id']);
				$tsumego = $this->Tsumego->findById($trs[$i]['TsumegoRatingAttempt']['tsumego_id']);
				$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tsumego['Tsumego']['id']]]);
				if (!$scT) {
					continue;
				}
				$tsumego['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
				$set = $this->Set->findById($tsumego['Tsumego']['set_id']);
				$values = [];
				$hash = substr($user['User']['ip'], 0, 1);
				foreach ($header as $headerItem) {
					$a = [];
					if ($headerItem == 'user_ip') {
						$a['name'] = $headerItem;
						$a['value'] = $user['User']['ip'];
					} elseif ($headerItem == 'user_country') {
						$a['name'] = $headerItem;
						$a['value'] = $user['User']['location'];
					} elseif ($headerItem == 'user_country_code') {
						$a['name'] = $headerItem;
						$a['value'] = $user['User']['location'];
					} elseif ($headerItem == 'tsumego_set') {
						$a['name'] = $headerItem;
						$a['value'] = $set['Set']['title'];
					} else {
						$a['name'] = $headerItem;
						$a['value'] = $trs[$i]['TsumegoRatingAttempt'][$headerItem];
					}
					$values[] = $a;
				}
				/*
				$values['user_id'] = $trs[$i]['TsumegoRatingAttempt']['user_id'];
				$values['user_elo'] = $trs[$i]['TsumegoRatingAttempt']['user_elo'];
				$values['user_ip'] = $user['User']['ip'];
				$values['user_country'] = 'Germany';
				$values['user_country_code'] = 'DEU';
				$values['tsumego_id'] = $trs[$i]['TsumegoRatingAttempt']['tsumego_id'];
				$values['tsumego_elo'] = $trs[$i]['TsumegoRatingAttempt']['tsumego_elo'];
				$values['tsumego_set'] = $set['Set']['title'];
				$values['status'] = $trs[$i]['TsumegoRatingAttempt']['status'];
				$values['seconds'] = $trs[$i]['TsumegoRatingAttempt']['seconds'];
				$values['created'] = $trs[$i]['TsumegoRatingAttempt']['created'];
				*/
				$posts[$i] = ['id' => $trs[$i]['TsumegoRatingAttempt']['id']];
				$posts[$i]['values'] = $values;
			}

			$response = $posts;
			$fp = fopen('files/tsumego-hero-user-activities.json', 'w');
			fwrite($fp, json_encode($response));
			fclose($fp);

		} else {
			$trs = $this->TsumegoRatingAttempt->find('all', ['order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}
		}

		$this->set('trs', $trs);
	}

	/**
	 * @param string|int|null $type Type of CSV export
	 * @return void
	 */
	public function csv($type = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'TSUMEGO RECORDS');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('User');

		$trs = [];
		if ($type == 0) {
			$trs = $this->TsumegoRatingAttempt->find('all', ['limit' => 1000, 'order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}

			$csv = [];
			$header = ['id', 'user_id', 'user_elo', 'user_deviation', 'tsumego_id', 'tsumego_elo', 'tsumego_deviation', 'status', 'seconds', 'sequence', 'recent', 'created'];
			$csv[] = $header;
			foreach ($trs as $tr) {
				$csv[] = $tr['TsumegoRatingAttempt'];
			}

			$file = fopen('files/tsumego-hero-user-activities.csv', 'w');

			foreach ($csv as $line) {
				fputcsv($file, $line);
			}

			fclose($file);
		} elseif ($type == 1) {
			$trs = $this->TsumegoRatingAttempt->find('all', ['order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}

			$csv = [];
			$header = ['id', 'user_id', 'user_elo', 'user_deviation', 'tsumego_id', 'tsumego_elo', 'tsumego_deviation', 'status', 'seconds', 'sequence', 'recent', 'created'];
			$csv[] = $header;
			foreach ($trs as $tr) {
				$csv[] = $tr['TsumegoRatingAttempt'];
			}

			$file = fopen('files/tsumego-hero-user-activities.csv', 'w');

			foreach ($csv as $line) {
				fputcsv($file, $line);
			}

			fclose($file);
		} elseif ($type == 2) {
			$trs = $this->TsumegoAttempt->find('all', ['limit' => 1000, 'order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}

			$csv = [];
			$header = ['id', 'user_id', 'tsumego_id', 'level', 'xp', 'gain', 'status', 'seconds', 'created'];
			$csv[] = $header;
			foreach ($trs as $tr) {
				$csv[] = $tr['TsumegoAttempt'];
			}

			$file = fopen('files/tsumego-hero-user-activities.csv', 'w');

			foreach ($csv as $line) {
				fputcsv($file, $line);
			}

			fclose($file);
		} elseif ($type == 3) {
			$trs = $this->TsumegoAttempt->find('all', ['limit' => 200000, 'order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}

			$csv = [];
			$header = ['id', 'user_id', 'tsumego_id', 'level', 'xp', 'gain', 'status', 'seconds', 'created'];
			$csv[] = $header;
			foreach ($trs as $tr) {
				$csv[] = $tr['TsumegoAttempt'];
			}

			$file = fopen('files/tsumego-hero-user-activities.csv', 'w');

			foreach ($csv as $line) {
				fputcsv($file, $line);
			}

			fclose($file);
		} elseif ($type == 4) {
			$u = $this->User->find('all');
			if (!$u) {
				$u = [];
			}

			$csv = [];
			$header = ['id', 'ip'];
			$csv[] = $header;
			foreach ($u as $user) {
				$a = [];
				$a[] = $user['User']['id'];
				$a[] = $user['User']['ip'];
				if ($user['User']['ip'] != null) {
					$csv[] = $a;
				}
			}

			$file = fopen('files/tsumego-hero-user-activities.csv', 'w');

			foreach ($csv as $line) {
				fputcsv($file, $line);
			}

			fclose($file);
		} else {
			$trs = $this->TsumegoRatingAttempt->find('all', ['order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}
		}

		$this->set('trs', $trs);
	}

	/**
	 * @param string|int $trid User ID
	 * @return void
	 */
	public function user($trid) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'History of ' . $this->Session->read('loggedInUser.User.name'));
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		$this->loadModel('TsumegoAttempt');
		if ($this->loggedInuserID() != $trid && $this->loggedInuserID() != 72) {
			$this->Session->write('redirect', 'sets');
		}

		$trs = $this->TsumegoAttempt->find('all', [
			'limit' => 200,
			'order' => 'created DESC',
			'conditions' => [
				'user_id' => $this->loggedInuserID(),
				'mode' => 2,
			],
		]);
		if (!$trs) {
			$trs = [];
		}
		/*
		$trsx = $this->TsumegoAttempt->find('all', array('limit' => 10, 'order' => 'created DESC', 'conditions' => array(
			'user_id' => $this->Session->read('loggedInUser.User.id')
		)));
		*/
		$trsCount = count($trs);
		for ($i = 0; $i < $trsCount; $i++) {
			if ($trs[$i]['TsumegoAttempt']['solved'] == 1) {
				$trs[$i]['TsumegoAttempt']['status'] = '<b style="color:#0cbb0c;">Solved</b>';
			} else {
				$trs[$i]['TsumegoAttempt']['status'] = '<b style="color:#e03c4b;">Failed</b>';
			}
			$t = $this->Tsumego->findById($trs[$i]['TsumegoAttempt']['tsumego_id']);
			$trs[$i]['TsumegoAttempt']['tsumego_elo'] = $t['Tsumego']['elo_rating_mode'];
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			if (!$scT) {
				continue;
			}
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$trs[$i]['TsumegoAttempt']['title'] = '<a target="_blank" href="/tsumegos/play/' . $trs[$i]['TsumegoAttempt']['tsumego_id'] . '?mode=1">' . $s['Set']['title'] . ' '
			. $s['Set']['title2'] . ' - ' . $t['Tsumego']['num'] . '</a>';

			$date = new DateTime($trs[$i]['TsumegoAttempt']['created']);
			$month = date('F', strtotime($trs[$i]['TsumegoAttempt']['created']));
			$tday = $date->format('d. ');
			$tyear = $date->format('Y');
			$tClock = $date->format('H:i');
			if ($tday[0] == 0) {
				$tday = substr($tday, -3);
			}
			$trs[$i]['TsumegoAttempt']['created'] = $tClock . ' | ' . $tday . $month . ' ' . $tyear;
			$seconds = $trs[$i]['TsumegoAttempt']['seconds'] % 60;
			$minutes = floor($trs[$i]['TsumegoAttempt']['seconds'] / 60);
			$hours = floor($trs[$i]['TsumegoAttempt']['seconds'] / 3600);
			$hours2 = $hours;
			while ($hours2 > 0) {
				$minutes -= 60;
				$hours2--;
			}

			if ($minutes == 0 && $hours == 0) {
				$minutes = '';
			} else {
				$minutes .= 'm ';
			}
			if ($hours == 0) {
				$hours = '';
			} else {
				$hours .= 'h ';
			}
			$trs[$i]['TsumegoAttempt']['seconds'] = $hours . $minutes . $seconds . 's';

		}

		$u = $this->User->findById($trid);
		$this->set('uname', $u['User']['name']);
		$this->set('trs', $trs);
	}

}

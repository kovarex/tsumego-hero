<?php
class TsumegoRecordsController extends AppController {

	/**
	 * @return void
	 */
	public function index($trid = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'TSUMEGO RECORDS');
		if ($trid == null) {
			$trs = $this->TsumegoRecord->find('all', ['limit' => 500, 'order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}
		} else {
			$trs = $this->TsumegoRecord->find('all', ['limit' => 500, 'order' => 'created DESC', 'conditions' => ['user_id' => $trid]]);
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
	 * @return void
	 */
	public function json($type = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'TSUMEGO RECORDS');
		$this->LoadModel('UserRecord');
		$this->LoadModel('User');
		$this->LoadModel('Tsumego');
		$this->LoadModel('Set');

		if ($type == 0) {
			$trs = $this->TsumegoRecord->find('all', [
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
				$user = $this->User->findById($trs[$i]['TsumegoRecord']['user_id']);
				$tsumego = $this->Tsumego->findById($trs[$i]['TsumegoRecord']['tsumego_id']);
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
						$a['value'] = $trs[$i]['TsumegoRecord'][$headerItem];
					}
					$values[] = $a;
				}
				/*
				$values['user_id'] = $trs[$i]['TsumegoRecord']['user_id'];
				$values['user_elo'] = $trs[$i]['TsumegoRecord']['user_elo'];
				$values['user_ip'] = $user['User']['ip'];
				$values['user_country'] = 'Germany';
				$values['user_country_code'] = 'DEU';
				$values['tsumego_id'] = $trs[$i]['TsumegoRecord']['tsumego_id'];
				$values['tsumego_elo'] = $trs[$i]['TsumegoRecord']['tsumego_elo'];
				$values['tsumego_set'] = $set['Set']['title'];
				$values['status'] = $trs[$i]['TsumegoRecord']['status'];
				$values['seconds'] = $trs[$i]['TsumegoRecord']['seconds'];
				$values['created'] = $trs[$i]['TsumegoRecord']['created'];
				*/
				$posts[$i] = ['id' => $trs[$i]['TsumegoRecord']['id']];
				$posts[$i]['values'] = $values;
			}

			$response = $posts;
			$fp = fopen('files/tsumego-hero-user-activities.json', 'w');
			fwrite($fp, json_encode($response));
			fclose($fp);

		} else {
			$trs = $this->TsumegoRecord->find('all', ['order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}
		}

		$this->set('trs', $trs);
	}

	/**
	 * @return void
	 */
	public function csv($type = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'TSUMEGO RECORDS');
		$this->LoadModel('UserRecord');
		$this->LoadModel('User');

		if ($type == 0) {
			$trs = $this->TsumegoRecord->find('all', ['limit' => 1000, 'order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}

			$csv = [];
			$header = ['id', 'user_id', 'user_elo', 'user_deviation', 'tsumego_id', 'tsumego_elo', 'tsumego_deviation', 'status', 'seconds', 'sequence', 'recent', 'created'];
			$csv[] = $header;
			foreach ($trs as $tr) {
				$csv[] = $tr['TsumegoRecord'];
			}

			$file = fopen('files/tsumego-hero-user-activities.csv', 'w');

			foreach ($csv as $line) {
				fputcsv($file, $line);
			}

			fclose($file);
		} elseif ($type == 1) {
			$trs = $this->TsumegoRecord->find('all', ['order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}

			$csv = [];
			$header = ['id', 'user_id', 'user_elo', 'user_deviation', 'tsumego_id', 'tsumego_elo', 'tsumego_deviation', 'status', 'seconds', 'sequence', 'recent', 'created'];
			$csv[] = $header;
			foreach ($trs as $tr) {
				$csv[] = $tr['TsumegoRecord'];
			}

			$file = fopen('files/tsumego-hero-user-activities.csv', 'w');

			foreach ($csv as $line) {
				fputcsv($file, $line);
			}

			fclose($file);
		} elseif ($type == 2) {
			$trs = $this->UserRecord->find('all', ['limit' => 1000, 'order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}

			$csv = [];
			$header = ['id', 'user_id', 'tsumego_id', 'level', 'xp', 'gain', 'status', 'seconds', 'created'];
			$csv[] = $header;
			foreach ($trs as $tr) {
				$csv[] = $tr['UserRecord'];
			}

			$file = fopen('files/tsumego-hero-user-activities.csv', 'w');

			foreach ($csv as $line) {
				fputcsv($file, $line);
			}

			fclose($file);
		} elseif ($type == 3) {
			$trs = $this->UserRecord->find('all', ['limit' => 200000, 'order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}

			$csv = [];
			$header = ['id', 'user_id', 'tsumego_id', 'level', 'xp', 'gain', 'status', 'seconds', 'created'];
			$csv[] = $header;
			foreach ($trs as $tr) {
				$csv[] = $tr['UserRecord'];
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
			$trs = $this->TsumegoRecord->find('all', ['order' => 'created DESC']);
			if (!$trs) {
				$trs = [];
			}
		}

		$this->set('trs', $trs);
	}

	/**
	 * @return void
	 */
	public function user($trid) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'History of ' . $this->Session->read('loggedInUser.User.name'));
		$this->LoadModel('Set');
		$this->LoadModel('Tsumego');
		if ($this->Session->read('loggedInUser.User.id') != $trid && $this->Session->read('loggedInUser.User.id') != 72) {
			$this->Session->write('redirect', 'sets');
		}
		$trs = $this->TsumegoRecord->find('all', [
			'limit' => 500,
			'order' => 'created DESC',
			'conditions' => [
				'user_id' => $trid,
				'OR' => [
					['status' => 'S'],
					['status' => 'F'],
				],
			],
		]);
		if (!$trs) {
			$trs = [];
		}

		$trsCount = count($trs);
		for ($i = 0; $i < $trsCount; $i++) {
			$t = $this->Tsumego->findById($trs[$i]['TsumegoRecord']['tsumego_id']);
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$trs[$i]['TsumegoRecord']['title'] = '<a target="_blank" href="/tsumegos/play/' . $trs[$i]['TsumegoRecord']['tsumego_id'] . '?mode=1">' . $s['Set']['title'] . ' ' . $s['Set']['title2'] . ' - ' . $t['Tsumego']['num'] . '</a>';

			$date = new DateTime($trs[$i]['TsumegoRecord']['created']);
			$month = date('F', strtotime($trs[$i]['TsumegoRecord']['created']));
			$tday = $date->format('d. ');
			$tyear = $date->format('Y');
			$tClock = $date->format('H:i');
			if ($tday[0] == 0) {
				$tday = substr($tday, -3);
			}
			$trs[$i]['TsumegoRecord']['created'] = $tClock . ' | ' . $tday . $month . ' ' . $tyear;
			$seconds = $trs[$i]['TsumegoRecord']['seconds'] % 60;
			$minutes = floor($trs[$i]['TsumegoRecord']['seconds'] / 60);
			$hours = floor($trs[$i]['TsumegoRecord']['seconds'] / 3600);
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
			$trs[$i]['TsumegoRecord']['seconds'] = $hours . $minutes . $seconds . 's';

		}

		$u = $this->User->findById($trid);
		$this->set('uname', $u['User']['name']);
		$this->set('trs', $trs);
	}

}

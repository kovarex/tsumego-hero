<?php

App::uses('TimeModeUtil', 'Utility');

class TimeModeController extends AppController {
	/**
	 * @return void
	 */
	public function overview() {
		$this->loadModel('Tsumego');
		$this->loadModel('User');
		$this->loadModel('TimeModeOverview');
		$this->loadModel('TimeModeSetting');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		$this->Session->write('title', 'Time Mode - Select');
		$this->Session->write('page', 'time mode');

		$lastMode = 3;
		$tsumegos = [];
		$settings = [];
		$settings['title'] = [];
		$settings['id'] = [];
		$settings['checked'] = [];
		$ro = $this->TimeModeOverview->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$ro) {
			$ro = [];
		}
		$sets = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		if (!$sets) {
			$sets = [];
		}

		$rs = $this->TimeModeSetting->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$rs) {
			$rs = [];
		}
		$rsIndexes = [];
		foreach ($rs as $item) {
			$rsIndexes[$item['TimeModeSetting']['set_id']] = $item['TimeModeSetting']['status'];
		}
		$rs = $this->checkForNewCollections($rsIndexes);

		if (Auth::isLoggedIn()) {
			$u = $this->User->findById(Auth::getUserID());
			$lastMode = $u['User']['lastMode'];
		}

		if ($rs == null) {
			$setsCount = count($sets);
			for ($i = 0; $i < $setsCount; $i++) {
				$this->TimeModeSetting->create();
				$rsNew = [];
				$rsNew['TimeModeSetting']['user_id'] = Auth::getUserID();
				$rsNew['TimeModeSetting']['set_id'] = $sets[$i]['Set']['id'];
				$y = $sets[$i]['Set']['id'];
				if ($y == 42 || $y == 109 || $y == 114 || $y == 143 || $y == 172 || $y == 29156 || $y == 33007 || $y == 74761) {
					$rsNew['TimeModeSetting']['status'] = 0;
				} else {
					$rsNew['TimeModeSetting']['status'] = 1;
				}
				$this->TimeModeSetting->save($rsNew);
			}
			$rs = $this->TimeModeSetting->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
			if (!$rs) {
				$rs = [];
			}
		}
		if (isset($this->data['Settings'])) {
			if (count($this->data['Settings']) >= 41) {
				$rds0 = $this->TimeModeSetting->find('all', [
					'conditions' => [
						'user_id' => Auth::getUserID(),
					],
				]);
				if (!$rds0) {
					$rds0 = [];
				}
				$rds0Count = count($rds0);
				for ($i = 0; $i < $rds0Count; $i++) {
					$rds0[$i]['TimeModeSetting']['status'] = 0;
					$this->TimeModeSetting->save($rds0[$i]);
				}
				foreach ($this->data['Settings'] as $ds) {
					$rds = $this->TimeModeSetting->find('first', [
						'conditions' => [
							'user_id' => Auth::getUserID(),
							'set_id' => $ds,
						],
					]);
					if ($rds) {
						$rds['TimeModeSetting']['status'] = 1;
						$this->TimeModeSetting->save($rds);
					}
				}
			}
		}
		$setsCount = count($sets);
		for ($i = 0; $i < $setsCount; $i++) {

			array_push($settings['title'], $sets[$i]['Set']['title'] . ' ' . $sets[$i]['Set']['title2']);
			array_push($settings['id'], $sets[$i]['Set']['id']);

			$settingsSingle = $this->TimeModeSetting->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'set_id' => $sets[$i]['Set']['id'],
				],
			]);
			if (!$settingsSingle) {
				$settingsSingle = [];
			}

			if (count($settingsSingle) > 1) {
				$settingsSingleCount = count($settingsSingle);
				for ($j = 0; $j < $settingsSingleCount; $j++) {
					if ($j != 0) {
						$this->TimeModeSetting->delete($settingsSingle[$j]['TimeModeSetting']['id']);
					}
				}
			}
			if (isset($settingsSingle[0]) && $settingsSingle[0]['TimeModeSetting']['status'] == 1) {
				array_push($settings['checked'], 'checked');
			} else {
				array_push($settings['checked'], '');
			}
		}

		$rx = [];
		$rx[] = '15k';
		$rx[] = '14k';
		$rx[] = '13k';
		$rx[] = '12k';
		$rx[] = '11k';
		$rx[] = '10k';
		$rx[] = '9k';
		$rx[] = '8k';
		$rx[] = '7k';
		$rx[] = '6k';
		$rx[] = '5k';
		$rx[] = '4k';
		$rx[] = '3k';
		$rx[] = '2k';
		$rx[] = '1k';
		$rx[] = '1d';
		$rx[] = '2d';
		$rx[] = '3d';
		$rx[] = '4d';
		$rx[] = '5d';

		$modes = [];
		$modes[0] = [];
		$modes[1] = [];
		$modes[2] = [];
		for ($i = 0; $i < 3; $i++) {
			$rank = 15;
			$j = 0;
			while ($rank > -5) {
				$kd = 'k';
				$rank2 = $rank;
				if ($rank >= 1) {
					$kd = 'k';
				} else {
					$rank2 = ($rank - 1) * (-1);
					$kd = 'd';
				}
				$modes[$i][$j] = $rank2 . $kd;
				$rank--;
				$j++;
			}
		}
		$locks = [];
		$locks[0] = [];
		$locks[1] = [];
		$locks[2] = [];

		$modesCount = count($modes);
		for ($h = 0; $h < $modesCount; $h++) {
			$modesHCount = count($modes[$h]);
			for ($i = 0; $i < $modesHCount; $i++) {
				$locks[$h][$i] = '';
			}
		}
		$locks[0][0] = 'x';
		$locks[1][0] = 'x';
		$locks[2][0] = 'x';
		$modesCount = count($modes);
		for ($h = 0; $h < $modesCount; $h++) {
			$modesHCount = count($modes[$h]);
			for ($i = 0; $i < $modesHCount; $i++) {
				$roCount = count($ro);
				for ($j = 0; $j < $roCount; $j++) {
					if ($modes[$h][$i] == $ro[$j]['TimeModeOverview']['rank'] && $ro[$j]['TimeModeOverview']['mode'] == $h && $ro[$j]['TimeModeOverview']['status'] == 's') {
						$locks[$h][$i] = 'x';
						$locks[$h][$i + 1] = 'x';
					}
				}
			}
		}

		$lowestMode = [];
		$locksCount = count($locks);
		for ($i = 0; $i < $locksCount; $i++) {
			$locksICount = count($locks[$i]);
			for ($j = 0; $j < $locksICount; $j++) {
				if ($locks[$i][$j] == 'x') {
					$lowestMode[$i] = $j;
				}
			}
		}
		foreach ($lowestMode as $i => $value) {
			$lowestMode[$i] = $rx[$value];
		}

		$achievementUpdate = $this->checkTimeModeAchievements();
		if (count($achievementUpdate) > 0) {
			$this->updateXP(Auth::getUserID(), $achievementUpdate);
		}

		$json = json_decode(file_get_contents('json/time_mode_overview.json'), true);

		$this->set('lastMode', $lastMode);
		$this->set('lowestMode', $lowestMode);
		$this->set('modes', $modes);
		$this->set('locks', $locks);
		$this->set('rxxCount', $json);
		$this->set('settings', $settings);
		$this->set('ro', $ro);
		$this->set('achievementUpdate', $achievementUpdate);
	}

	/**
	 * @param string|null $hash Hash value
	 * @return void
	 */
	public function result($hash = null) {
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('TimeModeOverview');
		$this->loadModel('SetConnection');
		$this->Session->write('title', 'Time Mode - Result');
		$this->Session->write('page', 'time mode');
		$sess = Auth::getUser()['activeRank'];

		$ranks = $this->TimeModeAttempt->find('all', ['conditions' => ['session' => $sess]]);
		if (!$ranks) {
			$ranks = [];
		}
		$solved = 0;
		$c = 0;
		$points = [];
		$ro = [];
		$roxBefore = [];

		$stopParameter = 0;
		$stopParameterNum = 10;
		$stopParameterPass = 0;
		$stopParameterSec = 0;
		if (strlen($sess) == 15) {
			$stopParameter = 0;
			$stopParameterNum = 10;
			$stopParameterPass = 8;
			$stopParameterSec = 30;
		} elseif (strlen($sess) == 16) {
			$stopParameter = 1;
			$stopParameterNum = 10;
			$stopParameterPass = 8;
			$stopParameterSec = 60;
		} elseif (strlen($sess) == 17) {
			$stopParameter = 2;
			$stopParameterNum = 10;
			$stopParameterPass = 8;
			$stopParameterSec = 240;
		}

		$modes = [];
		$modes[0] = [];
		$modes[1] = [];
		$modes[2] = [];
		for ($i = 0; $i < 3; $i++) {
			$rank = 15;
			$j = 0;
			while ($rank > -5) {
				$kd = 'k';
				$rank2 = $rank;
				if ($rank >= 1) {
					$kd = 'k';
				} else {
					$rank2 = ($rank - 1) * (-1);
					$kd = 'd';
				}
				$modes[$i][$j] = $rank2 . $kd;
				$rank--;
				$j++;
			}
		}

		$openCard1 = -1;
		$openCard2 = -1;
		if ($ranks != null) {
			$cardV = 0;
			if ($stopParameter == 0) {
				$cardV = 0;
			} elseif ($stopParameter == 1) {
				$cardV = 1;
			} elseif ($stopParameter == 2) {
				$cardV = 2;
			}

			$modesCount = count($modes[$cardV]);
			for ($i = 0; $i < $modesCount; $i++) {
				if ($ranks[0]['Rank']['rank'] == $modes[$cardV][$i]) {
					$openCard1 = $cardV;
					$openCard2 = $i;
				}
			}
			$ranksCount = count($ranks);
			for ($i = 0; $i < $ranksCount; $i++) {
				$t = $this->Tsumego->findById($ranks[$i]['Rank']['tsumego_id']);
				$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
				if (!$scT) {
					continue;
				}
				$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
				$s = $this->Set->findById($t['Tsumego']['set_id']);

				$ranks[$i]['Rank']['tsumegoNum'] = $t['Tsumego']['num'];
				$ranks[$i]['Rank']['set1'] = $s['Set']['title'];
				$ranks[$i]['Rank']['set2'] = $s['Set']['title2'];

				$ranks[$i]['Rank']['seconds'] = round($ranks[$i]['Rank']['seconds'], 1);
				$rx = $stopParameterSec - $ranks[$i]['Rank']['seconds'];

				$ranksMinutes = floor($rx / 60);
				$ranksSeconds = $rx % 60;

				$ranksDecimal = $rx - floor($rx);
				$ranksDecimal = round($ranksDecimal, 1);
				$ranksDecimal *= 10;

				$points[$i] = $this->calculatePoints($rx, $stopParameterSec);
				if ($rx >= 10) {
					$rx1 = '';
				} else {
					$rx1 = '0';
					if ($ranksMinutes > 0) {
						$rx1 = '00';
					}
				}

				$ranks[$i]['Rank']['seconds'] = $ranksMinutes . ':' . $rx1 . $ranksSeconds . '.' . $ranksDecimal;
				$ranks[$i]['Rank']['points'] = $points[$i];

				if ($ranks[$i]['Rank']['result'] == 'failed' || $ranks[$i]['Rank']['result'] == 'timeout' || $ranks[$i]['Rank']['result'] == 'skipped') {
					$c++;
					$points[$i] = 0;
					$ranks[$i]['Rank']['points'] = 0;
					if ($ranks[$i]['Rank']['result'] == 'timeout' || $ranks[$i]['Rank']['result'] == 'skipped') {
						$ranks[$i]['Rank']['seconds'] = '0:00.0';
					}
				} elseif ($ranks[$i]['Rank']['result'] == 'solved') {
					$solved++;
				} else {
					$c++;
				}
				$rSingle = $this->TimeModeAttempt->findById($ranks[$i]['Rank']['id']);
				$rSingle['Rank']['points'] = $ranks[$i]['Rank']['points'];
				$this->TimeModeAttempt->save($rSingle);
			}

			$sum = 0;
			$pointsCount = count($points);
			for ($i = 0; $i < $pointsCount; $i++) {
				$sum += $points[$i];
			}

			$roxBefore = $this->TimeModeOverview->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'rank' => $ranks[0]['Rank']['rank'],
					'mode' => $stopParameter,
					'status' => 's',
				],
			]);
			if (!$roxBefore) {
				$roxBefore = [];
			}

			$ro = [];
			$ro['TimeModeOverview']['user_id'] = Auth::getUserID();
			$ro['TimeModeOverview']['session'] = $sess;
			$ro['TimeModeOverview']['rank'] = $ranks[0]['Rank']['rank'];
			if ($solved >= $stopParameterPass) {
				$ro['TimeModeOverview']['status'] = 's';
			} else {
				$ro['TimeModeOverview']['status'] = 'f';
			}
			$ro['TimeModeOverview']['mode'] = $stopParameter;
			$ro['TimeModeOverview']['points'] = $sum;
			$this->TimeModeOverview->create();
			$this->TimeModeOverview->save($ro);
		}

		$sessArray = [];
		$sessArray[0] = [];
		$sessArray[1] = [];
		$sessArray[2] = [];
		for ($i = 0; $i < 3; $i++) {
			$rank = 15;
			$j = 0;
			while ($rank > -5) {
				$kd = 'k';
				$rank2 = $rank;
				if ($rank >= 1) {
					$kd = 'k';
				} else {
					$rank2 = ($rank - 1) * (-1);
					$kd = 'd';
				}
				$sessArray[$i][$j] = $rank2 . $kd;
				$rank--;
				$j++;
			}
		}

		$allR = $this->TimeModeAttempt->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$allR) {
			$allR = [];
		}

		for ($i = 0; $i < 3; $i++) {
			$modesCount = count($modes[$i]);
			for ($j = 0; $j < $modesCount; $j++) {
				$rox = $this->TimeModeOverview->find('all', [
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'rank' => $modes[$i][$j],
						'mode' => $i,
					],
				]);
				if (!$rox) {
					$rox = [];
				}
				$highest = 0;
				$highestId = 0;
				$roxCount = count($rox);
				for ($k = 0; $k < $roxCount; $k++) {
					if ($rox[$k]['TimeModeOverview']['points'] > $highest) {
						$sessArray[$i][$j] = $rox[$k]['TimeModeOverview']['session'];
						$highest = $rox[$k]['TimeModeOverview']['points'];
						$highestId = $rox[$k]['TimeModeOverview']['id'];
						$modes[$i][$j] = $rox[$k];
						if ($modes[$i][$j]['TimeModeOverview']['status'] == 's') {
							$modes[$i][$j]['TimeModeOverview']['status'] = 'passed';
						} elseif ($modes[$i][$j]['TimeModeOverview']['status'] == 'f') {
							$modes[$i][$j]['TimeModeOverview']['status'] = 'failed';
						}
						if ($modes[$i][$j]['TimeModeOverview']['mode'] == 2) {
							$modes[$i][$j]['TimeModeOverview']['mode'] = 'Slow';
						} elseif ($modes[$i][$j]['TimeModeOverview']['mode'] == 1) {
							$modes[$i][$j]['TimeModeOverview']['mode'] = 'Fast';
						} elseif ($modes[$i][$j]['TimeModeOverview']['mode'] == 0) {
							$modes[$i][$j]['TimeModeOverview']['mode'] = 'Blitz';
						}
						$date = new DateTime($modes[$i][$j]['TimeModeOverview']['created']);
						$month = $date->format('m.');
						$tday = $date->format('d.');
						$tyear = $date->format('Y');
						$tClock = $date->format('H:i');
						if ($tday[0] == 0) {
							$tday = substr($tday, -3);
						}
						$modes[$i][$j]['TimeModeOverview']['created'] = $tClock . ' ' . $tday . $month . $tyear;
					}
				}
				$roxCount = count($rox);
				for ($k = 0; $k < $roxCount; $k++) {
					if ($rox[$k]['TimeModeOverview']['id'] != $highestId && $sess != $rox[$k]['TimeModeOverview']['session']) {
						$this->TimeModeOverview->delete($rox[$k]['TimeModeOverview']['id']);
						$rDel = $this->TimeModeAttempt->find('all', ['conditions' => ['session' => $rox[$k]['TimeModeOverview']['session']]]);
						if (!$rDel) {
							$rDel = [];
						}
						$rDelCount = count($rDel);
						for ($l = 0; $l < $rDelCount; $l++) {
							//$this->TimeModeAttempt->delete($rDel[$l]['Rank']['id']);
						}
					}
				}
			}
		}

		$allR = [];
		$modesCount = count($modes);
		for ($h = 0; $h < $modesCount; $h++) {
			$allR[$h] = [];
			$modesHCount = count($modes[$h]);
			for ($i = 0; $i < $modesHCount; $i++) {
				if ($h == $openCard1 && $i == $openCard2) {
					$allR[$h][$i] = $this->TimeModeAttempt->find('all', ['order' => 'num ASC', 'conditions' => ['session' => $modes[$h][$i]['TimeModeOverview']['session']]]);
					if (!$allR[$h][$i]) {
						$allR[$h][$i] = [];
					}
					$allRCount = count($allR[$h][$i]);
					for ($j = 0; $j < $allRCount; $j++) {
						$tx = $this->Tsumego->findById($allR[$h][$i][$j]['Rank']['tsumego_id']);
						$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
						if (!$scT) {
							continue;
						}
						$tx['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
						$sx = $this->Set->findById($tx['Tsumego']['set_id']);
						$foundSkipped = false;
						$timeFieldColor = '#e03c4b';
						if ($h == 0) {
							$ss = 30;
						} elseif ($h == 1) {
							$ss = 60;
						} elseif ($h == 2) {
							$ss = 240;
						}
						$allR[$h][$i][$j]['Rank']['tsumego'] = $sx['Set']['title'] . ' ' . $sx['Set']['title2'] . ' - ' . $tx['Tsumego']['num'];
						if ($allR[$h][$i][$j]['Rank']['result'] == 'solved') {
							$hh = 'green';
						} else {
							$hh = '#e03c4b';
						}
						if ($allR[$h][$i][$j]['Rank']['result'] == 'timeout') {
							$allR[$h][$i][$j]['Rank']['seconds'] = $ss;
						}
						if ($allR[$h][$i][$j]['Rank']['result'] == 'skipped') {
							$foundSkipped = true;
						}
						if ($allR[$h][$i][$j]['Rank']['result'] == 'solved') {
							$timeFieldColor = 'green';
						}
						$allR[$h][$i][$j]['Rank']['result'] = '<b style="color:' . $hh . ';">' . $allR[$h][$i][$j]['Rank']['result'] . '</b>';
						$allR[$h][$i][$j]['Rank']['seconds'] = round($allR[$h][$i][$j]['Rank']['seconds'], 1);
						$rx = $ss - $allR[$h][$i][$j]['Rank']['seconds'];
						$ranksMinutes = floor($rx / 60);
						$ranksSeconds = $rx % 60;
						$ranksDecimal = $rx - floor($rx);
						$ranksDecimal = round($ranksDecimal, 1);
						$ranksDecimal *= 10;
						if ($rx >= 10) {
							$rx1 = '';
						} else {
							$rx1 = '0';
							if ($ranksMinutes > 0) {
								$rx1 = '00';
							}
						}
						$allR[$h][$i][$j]['Rank']['seconds'] = $ranksMinutes . ':' . $rx1 . $ranksSeconds . '.' . $ranksDecimal;
						if ($foundSkipped) {
							$allR[$h][$i][$j]['Rank']['seconds'] = '0:00.0';
						}
						$allR[$h][$i][$j]['Rank']['seconds'] = '<font style="color:' . $timeFieldColor . ';">' . $allR[$h][$i][$j]['Rank']['seconds'] . '</font>';
					}
				}
			}
		}

		$lastModeIndex = Auth::getUser()['lastMode'] - 1;
		$lastModeV = null;
		$modesCount = count($modes[$lastModeIndex]);
		for ($h = 0; $h < $modesCount; $h++) {
			if (isset($modes[$lastModeIndex][$h]['TimeModeOverview'])) {
				$lastModeV = $modes[$lastModeIndex][$h]['TimeModeOverview']['rank'];
			}
		}

		$sessionFound = false;
		$sessArrayCount = count($sessArray);
		for ($i = 0; $i < $sessArrayCount; $i++) {
			$sessArrayICount = count($sessArray[$i]);
			for ($j = 0; $j < $sessArrayICount; $j++) {
				if ($sessArray[$i][$j] == $ranks[0]['Rank']['session']) {
					$sessionFound = true;
				}
			}
		}

		if (count($roxBefore) > 0) {
			$newUnlock = false;
		} else {
			$newUnlock = true;
		}

		$this->set('c', $c);
		$this->set('solved', $solved);
		$this->set('ranks', $ranks);
		$this->set('points', $points);
		$this->set('stopParameterNum', $stopParameterNum);
		$this->set('stopParameterPass', $stopParameterPass);
		$this->set('modes', $modes);
		$this->set('allR', $allR);
		$this->set('openCard1', $openCard1);
		$this->set('openCard2', $openCard2);
		$this->set('lastModeV', $lastModeV);
		$this->set('sessionFound', $sessionFound);
		$this->set('ro', $ro);
		$this->set('newUnlock', $newUnlock);
	}

	private function calculatePoints($time = null, $max = null) {
		$rx = 0;
		if ($max == 240) {
			$rx = 20 + round($time / 3);
		} elseif ($max == 60) {
			$rx = 40 + round($time);
		} elseif ($max == 30) {
			$rx = 40 + round($time * 2);
		}

		return $rx;
	}

	private function checkForNewCollections($indexes) {
		$check = [186, 187, 190, 192, 193, 195, 196, 197, 198, 200, 203, 204, 214, 216, 226, 227, 231];
		foreach ($check as $checkId) {
			if (!isset($indexes[$checkId])) {
				$newRsx = [];
				$newRsx['TimeModeSetting']['user_id'] = Auth::getUserID();
				$newRsx['TimeModeSetting']['set_id'] = $checkId;
				$newRsx['TimeModeSetting']['status'] = '1';
				$this->TimeModeSetting->create();
				$this->TimeModeSetting->save($newRsx);
			}
		}
		$rs = $this->TimeModeSetting->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$rs) {
			$rs = [];
		}

		return $rs;
	}

}

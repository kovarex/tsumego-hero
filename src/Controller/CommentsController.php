<?php

class CommentsController extends AppController {
	/**
	 * @return void
	 */
	public function index() {
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Set');
		$this->loadModel('User');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->Session->write('title', 'Tsumego Hero - Discuss');
		$this->Session->write('page', 'discuss');
		$c = [];
		$setsWithPremium = [];
		$index = 0;
		$counter = 0;
		$reverseOrder = false;
		$lastEntry = true;
		$firstPage = false;
		$paramcommentid = 0;
		$paramdirection = 0;
		$paramindex = 0;
		$paramyourcommentid = 0;
		$paramyourdirection = 0;
		$paramyourindex = 0;
		$unresolvedSet = 'true';
		if (!isset($this->params['url']['unresolved'])) {
			$unresolved = 'false';
			$unresolvedSet = 'false';
		} else {
			$unresolved = $this->params['url']['unresolved'];
		}
		if (!isset($this->params['url']['filter'])) {
			$filter1 = 'true';
		} else {
			$filter1 = $this->params['url']['filter'];
		}

		$hasPremium = Auth::hasPremium();
		$swp = $this->Set->find('all', ['conditions' => ['premium' => 1]]);
		if (!$swp) {
			$swp = [];
		}
		foreach ($swp as $item) {
			$setsWithPremium[] = $item['Set']['id'];
		}

		if ($filter1 == 'true') {
			$userTsumegos = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'OR' => [
						['status' => 'S'],
						['status' => 'C'],
						['status' => 'W'],
					],
				],
			]);
			if (!$userTsumegos) {
				$userTsumegos = [];
			}
		} else {
			$userTsumegos = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
				],
			]);
			if (!$userTsumegos) {
				$userTsumegos = [];
			}
		}

		$keyList = [];
		$keyListStatus = [];
		$userTsumegosCount = count($userTsumegos);
		for ($i = 0; $i < $userTsumegosCount; $i++) {
			$keyList[$i] = $userTsumegos[$i]['TsumegoStatus']['tsumego_id'];
			$keyListStatus[$i] = $userTsumegos[$i]['TsumegoStatus']['status'];
		}

		$comments = [];
		if (!isset($this->params['url']['comment-id'])) {
			if ($unresolved == 'false') {
				$comments = $this->Comment->find('all', [
					'limit' => 500,
					'order' => 'created DESC',
					'conditions' => [
						'NOT' => ['status' => 99],
					],
				]);
				if (!$comments) {
					$comments = [];
				}
			} elseif ($unresolved == 'true') {
				$comments = $this->Comment->find('all', [
					'limit' => 500,
					'order' => 'created DESC',
					'conditions' => [
						'status' => 0,
						[
							'NOT' => ['user_id' => 0],
						],
					],
				]);
				if (!$comments) {
					$comments = [];
				}
				$filter1 = 'falsex';
			}
			$firstPage = true;
		} else {
			$paramcommentid = $this->params['url']['comment-id'];
			$paramdirection = $this->params['url']['direction'];
			$paramindex = $this->params['url']['index'];
			if ($unresolved == 'false') {
				if ($this->params['url']['direction'] == 'next') {
					$comments = $this->Comment->find('all', [
						'limit' => 500,
						'order' => 'created DESC',
						'conditions' => [
							'Comment.id <' => $this->params['url']['comment-id'],
							'NOT' => ['status' => 99],
						],
					]);
					if (!$comments) {
						$comments = [];
					}
				} elseif (($this->params['url']['direction'] == 'prev')) {
					$comments = $this->Comment->find('all', [
						'limit' => 500,
						'order' => 'created ASC',
						'conditions' => [
							'Comment.id >' => $this->params['url']['comment-id'],
							'NOT' => ['status' => 99],
						],
					]);
					if (!$comments) {
						$comments = [];
					}
					$reverseOrder = true;
				}
			} elseif ($unresolved == 'true') {
				if ($this->params['url']['direction'] == 'next') {
					$comments = $this->Comment->find('all', [
						'limit' => 500,
						'order' => 'created DESC',
						'conditions' => [
							'Comment.id <' => $this->params['url']['comment-id'],
							'Comment.status ' => 0,
						],
					]);
					if (!$comments) {
						$comments = [];
					}
				} elseif (($this->params['url']['direction'] == 'prev')) {
					$comments = $this->Comment->find('all', [
						'limit' => 500,
						'order' => 'created ASC',
						'conditions' => [
							'Comment.id >' => $this->params['url']['comment-id'],
							'Comment.status ' => 0,
						],
					]);
					if (!$comments) {
						$comments = [];
					}
					$reverseOrder = true;
				}
			}
			$index = $this->params['url']['index'];
		}
		if ($filter1 == 'true') {
			$commentsBuffer = [];
			$commentsCount = count($comments);
			for ($i = 0; $i < $commentsCount; $i++) {
				$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
				$premiumLock = false;
				if (!$hasPremium) {
					if (in_array($t['Tsumego']['set_id'], $setsWithPremium)) {
						$premiumLock = true;
					}
				}
				if ($t != null && !$premiumLock) {
					if (in_array($t['Tsumego']['id'], $keyList)) {
						if ($counter < 11) {
							if (!in_array($t['Tsumego']['id'], $keyList)) {
								$solved = 0;
							} else {
								if ($keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'S'
									|| $keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'C'
									|| $keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'W'
								) {
									$solved = 1;
								} else {
									$solved = 0;
								}
							}
							$u = $this->User->findById($comments[$i]['Comment']['user_id']);
							if ($comments[$i]['Comment']['set_id'] == null) {
								$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
							} else {
								$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id'], 'set_id' => $comments[$i]['Comment']['set_id']]]);
							}

							if ($scT && isset($scT['SetConnection']['set_id'])) {
								$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
								$s = $this->Set->findById($t['Tsumego']['set_id']);
								if ($s && isset($s['Set']['title'])) {
									$counter++;
									$comments[$i]['Comment']['counter'] = $counter + $index;
									$comments[$i]['Comment']['user_name'] = $this->checkPicture($u);
									$comments[$i]['Comment']['set'] = $s['Set']['title'];
									$comments[$i]['Comment']['set2'] = $s['Set']['title2'];
									$comments[$i]['Comment']['num'] = $scT['SetConnection']['num'];

									if (!in_array($t['Tsumego']['id'], $keyList)) {
										$comments[$i]['Comment']['user_tsumego'] = 'N';
									} else {
										$comments[$i]['Comment']['user_tsumego'] = $keyListStatus[array_search($t['Tsumego']['id'], $keyList)];
									}
									$comments[$i]['Comment']['solved'] = $solved;
									if ($comments[$i]['Comment']['admin_id'] != null) {
										$au = $this->User->findById($comments[$i]['Comment']['admin_id']);
										if ($au && isset($au['User']['name'])) {
											if ($au['User']['name'] == 'Morty') {
												$au['User']['name'] = 'Admin';
											}
											$comments[$i]['Comment']['admin_name'] = $au['User']['name'];
										}
									}

									$date = new DateTime($comments[$i]['Comment']['created']);
									$month = date('F', strtotime($comments[$i]['Comment']['created']));
									$tday = $date->format('d. ');
									$tyear = $date->format('Y');
									$tClock = $date->format('H:i');
									if ($tday[0] == 0) {
										$tday = substr($tday, -3);
									}
									$comments[$i]['Comment']['created'] = $tday . $month . ' ' . $tyear . '<br>' . $tClock;
									array_push($c, $comments[$i]);
								}
							}
						}
					}
				}
			}
		} else {
			$commentsBuffer = [];
			$commentsCount = count($comments);
			for ($i = 0; $i < $commentsCount; $i++) {
				if ($counter < 11) {
					$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
					$premiumLock = false;
					if (!$hasPremium) {
						if (in_array($t['Tsumego']['set_id'], $setsWithPremium)) {
							$premiumLock = true;
						}
					}
					if (!$premiumLock) {
						if (!in_array($t['Tsumego']['id'], $keyList)) {
							$solved = 0;
						} else {
							if ($keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'S'
							|| $keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'C'
							|| $keyListStatus[array_search($t['Tsumego']['id'], $keyList)] == 'W') {
								$solved = 1;
							} else {
								$solved = 0;
							}
						}
						$u = $this->User->findById($comments[$i]['Comment']['user_id']);
						if ($comments[$i]['Comment']['set_id'] == null) {
							$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
						} else {
							$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id'], 'set_id' => $comments[$i]['Comment']['set_id']]]);
						}

						if ($scT && isset($scT['SetConnection']['set_id'])) {
							$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
							$s = $this->Set->findById($t['Tsumego']['set_id']);
							if ($s && isset($s['Set']['title'])) {
								$counter++;
								$comments[$i]['Comment']['counter'] = $counter + $index;
								$comments[$i]['Comment']['user_name'] = $this->checkPicture($u);
								$comments[$i]['Comment']['set'] = $s['Set']['title'];
								$comments[$i]['Comment']['set2'] = $s['Set']['title2'];
								$comments[$i]['Comment']['num'] = $scT['SetConnection']['num'];
								if (!in_array($t['Tsumego']['id'], $keyList)) {
									$comments[$i]['Comment']['user_tsumego'] = 'N';
								} else {
									$comments[$i]['Comment']['user_tsumego'] = $keyListStatus[array_search($t['Tsumego']['id'], $keyList)];
								}
								$comments[$i]['Comment']['solved'] = $solved;
								if ($comments[$i]['Comment']['admin_id'] != null) {
									$au = $this->User->findById($comments[$i]['Comment']['admin_id']);
									if ($au && isset($au['User']['name'])) {
										if ($au['User']['name'] == 'Morty') {
											$au['User']['name'] = 'Admin';
										}
										$comments[$i]['Comment']['admin_name'] = $au['User']['name'];
									}
								}
								$date = new DateTime($comments[$i]['Comment']['created']);
								$month = date('F', strtotime($comments[$i]['Comment']['created']));
								$tday = $date->format('d. ');
								$tyear = $date->format('Y');
								$tClock = $date->format('H:i');
								if ($tday[0] == 0) {
									$tday = substr($tday, -3);
								}
								$comments[$i]['Comment']['created'] = $tday . $month . ' ' . $tyear . '<br>' . $tClock;
								array_push($c, $comments[$i]);
							}
						}
					}
				}
			}
		}

		if ($reverseOrder) {
			$c = array_reverse($c);
			$counter = 0;
			$cCount = count($c);
			for ($i = 0; $i < $cCount; $i++) {
				$counter++;
				$c[$i]['Comment']['counter'] = $counter + $index;
			}
		}
		///////////////////////////////////
		$yourc = [];
		$yourindex = 0;
		$yourcounter = 0;
		$yourreverseOrder = false;
		$yourlastEntry = true;
		$yourfirstPage = false;
		$yourComments = [];
		if (!isset($this->params['url']['your-comment-id'])) {
			$yourComments = $this->Comment->find('all', [
				'limit' => 500,
				'order' => 'created DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
				],
			]);
			if (!$yourComments) {
				$yourComments = [];
			}
			$yourfirstPage = true;
		} else {
			$paramyourcommentid = $this->params['url']['your-comment-id'];
			$paramyourdirection = $this->params['url']['your-direction'];
			$paramyourindex = $this->params['url']['your-index'];
			if ($this->params['url']['your-direction'] == 'next') {
				$yourComments = $this->Comment->find('all', [
					'limit' => 500,
					'order' => 'created DESC',
					'conditions' => [
						'Comment.id <' => $this->params['url']['your-comment-id'],
						'user_id' => Auth::getUserID(),
					],
				]);
				if (!$yourComments) {
					$yourComments = [];
				}
			} elseif (($this->params['url']['your-direction'] == 'prev')) {
				$yourComments = $this->Comment->find('all', [
					'limit' => 500,
					'order' => 'created ASC',
					'conditions' => [
						'Comment.id >' => $this->params['url']['your-comment-id'],
						'user_id' => Auth::getUserID(),
					],
				]);
				if (!$yourComments) {
					$yourComments = [];
				}
				$yourreverseOrder = true;
			}
			$yourindex = $this->params['url']['your-index'];
		}

		$yourCommentsCount = count($yourComments);
		for ($i = 0; $i < $yourCommentsCount; $i++) {
			if ($yourcounter < 11) {
				$t = $this->Tsumego->findById($yourComments[$i]['Comment']['tsumego_id']);
				if (!isset($t['Tsumego']['id'])) {
					$t['Tsumego']['id'] = 0;
				}
				if (in_array($t['Tsumego']['id'], $keyList)) {
					$u = $this->User->findById($yourComments[$i]['Comment']['user_id']);
					if ($yourComments[$i]['Comment']['set_id'] == null) {
						$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
					} else {
						$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id'], 'set_id' => $yourComments[$i]['Comment']['set_id']]]);
					}

					if ($scT && isset($scT['SetConnection']['set_id'])) {
						$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
						$s = $this->Set->findById($t['Tsumego']['set_id']);
						if ($s && isset($s['Set']['title'])) {
							$yourcounter++;
							$yourComments[$i]['Comment']['counter'] = $yourcounter + $yourindex;
							$yourComments[$i]['Comment']['user_name'] = $this->checkPicture($u);
							$yourComments[$i]['Comment']['set'] = $s['Set']['title'];
							$yourComments[$i]['Comment']['set2'] = $s['Set']['title2'];
							$yourComments[$i]['Comment']['num'] = $scT['SetConnection']['num'];
							$yourComments[$i]['Comment']['user_tsumego'] = $keyListStatus[array_search($t['Tsumego']['id'], $keyList)];
							if ($yourComments[$i]['Comment']['admin_id'] != null) {
								$au = $this->User->findById($yourComments[$i]['Comment']['admin_id']);
								if ($au && isset($au['User']['name'])) {
									if ($au['User']['name'] == 'Morty') {
										$au['User']['name'] = 'Admin';
									}
									$yourComments[$i]['Comment']['admin_name'] = $au['User']['name'];
								}
							}
							$date = new DateTime($yourComments[$i]['Comment']['created']);
							$month = date('F', strtotime($yourComments[$i]['Comment']['created']));
							$tday = $date->format('d. ');
							$tyear = $date->format('Y');
							$tClock = $date->format('H:i');
							if ($tday[0] == 0) {
								$tday = substr($tday, -3);
							}
							$yourComments[$i]['Comment']['created'] = $tday . $month . ' ' . $tyear . '<br>' . $tClock;

							array_push($yourc, $yourComments[$i]);
						}
					}
				}
			}
			if (strpos($yourComments[$i]['Comment']['message'], '<a href="/files/ul1/') === false) {
				$yourComments[$i]['Comment']['message'] = htmlspecialchars($yourComments[$i]['Comment']['message']);
			}
		}
		if ($yourreverseOrder) {
			$yourc = array_reverse($yourc);
			$yourcounter = 0;
			$yourcCount = count($yourc);
			for ($i = 0; $i < $yourcCount; $i++) {
				$yourcounter++;
				$yourc[$i]['Comment']['counter'] = $yourcounter + $yourindex;
			}
		}

		$yourComments2 = [];
		$yourCommentsCount2 = count($yourComments);
		for ($i = 0; $i < $yourCommentsCount2; $i++) {
			if ($counter < 10) {
				$u = $this->User->findById($yourComments[$i]['Comment']['user_id']);
				$t = $this->Tsumego->findById($yourComments[$i]['Comment']['tsumego_id']);
				if ($yourComments[$i]['Comment']['set_id'] == null) {
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
				} else {
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id'], 'set_id' => $yourComments[$i]['Comment']['set_id']]]);
				}

				if ($scT && isset($scT['SetConnection']['set_id'])) {
					$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					$s = $this->Set->findById($t['Tsumego']['set_id']);
					if ($s && isset($s['Set']['title'])) {
						$counter++;
						$yourComments[$i]['Comment']['counter'] = $counter;
						$yourComments[$i]['Comment']['user_name'] = $this->checkPicture($u);
						$yourComments[$i]['Comment']['set'] = $s['Set']['title'];
						$yourComments[$i]['Comment']['set2'] = $s['Set']['title2'];
						$yourComments[$i]['Comment']['num'] = $scT['SetConnection']['num'];
						array_push($yourComments2, $yourComments[$i]);
					}
				}
			}
		}

		if (isset($this->params['url']['filter'])) {
			$this->set('filter1', $filter1);
		}
		if ($filter1 == 'falsex') {
			$this->set('filter1', 'false');
		}
		if ($unresolvedSet == 'true') {
			$this->set('unresolved', $unresolved);
			$this->set('comments3', count($this->Comment->find('all', [
				'order' => 'created DESC',
				'conditions' => [
					'status' => 0,
					[
						'NOT' => ['user_id' => 0],
					],
				],
			]) ?: []));
		}

		$currentPositionPlaceholder = '<img src="/img/positionIcon1.png" class="positionIcon1" style="cursor:context-menu;">';

		$tooltipSgfs = [];
		$tooltipInfo = [];
		$tooltipBoardSize = [];
		$tooltipSgfs2 = [];
		$tooltipInfo2 = [];
		$tooltipBoardSize2 = [];
		$cCount2 = count($c);
		for ($i = 0; $i < $cCount2; $i++) {
			if (strpos($c[$i]['Comment']['message'], '<a href="/files/ul1/') === false) {
				$c[$i]['Comment']['message'] = htmlspecialchars($c[$i]['Comment']['message']);
			}
			$c[$i]['Comment']['message'] = str_replace('[current position]', $currentPositionPlaceholder, $c[$i]['Comment']['message']);

			$tBuffer = $this->Tsumego->findById($c[$i]['Comment']['tsumego_id']);
			$tts = $this->Sgf->find('all', ['limit' => 1, 'order' => 'version DESC', 'conditions' => ['tsumego_id' => $tBuffer['Tsumego']['id']]]);
			if (count($tts) > 0) {
				$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
				array_push($tooltipSgfs, $tArr[0]);
				array_push($tooltipInfo, $tArr[2]);
				array_push($tooltipBoardSize, $tArr[3]);
			}
		}
		$yourcCount2 = count($yourc);
		for ($i = 0; $i < $yourcCount2; $i++) {
			$yourc[$i]['Comment']['message'] = str_replace('[current position]', $currentPositionPlaceholder, $yourc[$i]['Comment']['message']);

			$tBuffer = $this->Tsumego->findById($yourc[$i]['Comment']['tsumego_id']);
			$tts2 = $this->Sgf->find('all', ['limit' => 1, 'order' => 'version DESC', 'conditions' => ['tsumego_id' => $tBuffer['Tsumego']['id']]]);
			if (count($tts2) > 0) {
				$tArr2 = $this->processSGF($tts2[0]['Sgf']['sgf']);
				$tooltipSgfs2[$i] = $tArr2[0];
				$tooltipInfo2[$i] = $tArr2[2];
				array_push($tooltipBoardSize2, $tArr2[3]);
			}
		}
		$admins = $this->User->find('all', ['conditions' => ['isAdmin' => 1]]);

		if (Auth::isAdmin()) {
			$uc = $this->Comment->find('all', [
				'conditions' => [
					'user_id' => 0,
					'tsumego_id' => 0,
				],
			]);
			if (!$uc) {
				$uc = [];
			}
			foreach ($uc as $item) {
				$this->Comment->delete($item['Comment']['id']);
			}
		}

		$this->set('admins', $admins);
		$this->set('paramindex', $paramindex);
		$this->set('paramdirection', $paramdirection);
		$this->set('paramcommentid', $paramcommentid);
		$this->set('paramyourindex', $paramyourindex);
		$this->set('paramyourdirection', $paramyourdirection);
		$this->set('paramyourcommentid', $paramyourcommentid);
		$this->set('index', $index);
		$this->set('yourindex', $yourindex);
		$this->set('comments', $c);
		$this->set('yourComments', $yourc);
		$this->set('userTsumegos', $keyList);
		$this->set('firstPage', $firstPage);
		$this->set('yourfirstPage', $yourfirstPage);
		$this->set('tooltipSgfs', $tooltipSgfs);
		$this->set('tooltipInfo', $tooltipInfo);
		$this->set('tooltipSgfs2', $tooltipSgfs2);
		$this->set('tooltipInfo2', $tooltipInfo2);
		$this->set('tooltipBoardSize', $tooltipBoardSize);
		$this->set('tooltipBoardSize2', $tooltipBoardSize2);
	}

	/**
	 * @param int $id Comment ID
	 * @return void
	 */
	public function remove($id) {
		$token = true;
		if (!Auth::isAdmin()) {
			$token = false;
		} elseif ($this->params['url']['token'] != md5((string) $id)) {
			$token = false;
		}
		if ($token) {
			$this->Comment->delete($id);
		}
		$this->set('token', $token);
	}

}

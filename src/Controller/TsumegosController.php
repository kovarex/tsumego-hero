<?php

App::uses('TsumegoStatusHelper', 'Utility');

class TsumegosController extends AppController {

	public $helpers = ['Html', 'Form'];

	/**
	 * @param string|int|null $id Tsumego ID
	 * @return void
	 */
	public function play($id = null) {
		$this->Session->write('page', 'play');
		$this->loadModel('User');
		$this->loadModel('Set');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Comment');
		$this->loadModel('UserBoard');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Favorite');
		$this->loadModel('AdminActivity');
		$this->loadModel('Activate');
		$this->loadModel('Joseki');
		$this->loadModel('Reputation');
		$this->loadModel('Rank');
		$this->loadModel('RankSetting');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');
		$this->loadModel('ProgressDeletion');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->loadModel('TsumegoVariant');
		$this->loadModel('Signature');
		$this->loadModel('Tag');
		$this->loadModel('TagName');
		$this->loadModel('UserContribution');

		$onlineMode = true;
		$noUser = null;
		$noLogin = [];
		$preTsumego = null;
		$ut = null;
		$ts = [];
		$anzahl2 = 0;
		$tsTsumegosMap = [];
		$tsFirst = null;
		$nextMode = null;
		$rejuvenation = false;
		$doublexp = null;
		$exploit = null;
		$dailyMaximum = false;
		$suspiciousBehavior = false;
		$refinementUT = null;
		$half = '';
		$inFavorite = false;
		$lastInFav = 0;
		$isSandbox = false;
		$goldenTsumego = false;
		$refresh = null;
		$mode = 1;
		$noTr = false;
		$range = [];
		$difficulty = 4;
		$oldmin = -600;
		$oldmax = 500;
		$newmin = 0.1;
		$newmax = 2;
		$potion = 0;
		$potionPercent = 0;
		$potionPercent2 = 0;
		$potionSuccess = false;
		$potionActive = false;
		$reviewCheat = false;
		$commentCoordinates = [];
		$josekiLevel = 1;
		$rankTs = [];
		$ranks = [];
		$firstRanks = 0;
		$currentRank = null;
		$currentRankNum = 0;
		$r10 = 0;
		$stopParameter = 0;
		$stopParameter2 = 0;
		$mode3ScoreArray = [];
		$trs = [];
		$potionAlert = false;
		$ui = 2;
		$eloScore = 0;
		$eloScore2 = 0;
		$requestProblem = '';
		$achievementUpdate = [];
		$pdCounter = 0;
		$duplicates = [];
		$preSc = [];
		$tRank = '15k';
		$requestSolution = false;
		$currentRank2 = null;
		$nothingInRange = false;
		$avActive = true;
		$avActive2 = true;
		$utsMap = [];
		$allUts = [];
		$setsWithPremium = [];
		$queryTitle = '';
		$queryTitleSets = '';
		$amountOfOtherCollection = 200;
		$partition = -1;

		if (isset($this->params['url']['sid'])) {
			if (strpos($this->params['url']['sid'], '?') > 0) {
				$id = 15352;
			}
		}

		$hasPremium = $this->hasPremium();
		$swp = $this->Set->find('all', ['conditions' => ['premium' => 1]]);
		if (!$swp) {
			$swp = [];
		}
		foreach ($swp as $item) {
			$setsWithPremium[] = $item['Set']['id'];
		}

		$hasDuplicateGroup = count($this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $id]]) ?: []) > 1;
		if ($hasDuplicateGroup) {
			$duplicates = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $id]]);
			if (!$duplicates) {
				$duplicates = [];
			}
			$duplicatesCount = count($duplicates);
			for ($i = 0; $i < $duplicatesCount; $i++) {
				$duplicateSet = $this->Set->findById($duplicates[$i]['SetConnection']['set_id']);
				$duplicates[$i]['SetConnection']['title'] = $duplicateSet['Set']['title'] . ' ' . $duplicates[$i]['SetConnection']['num'];
			}
		}
		$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
		if ($scT == null) {
			$id = 15352;
			$hasDuplicateGroup = count($this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $id]]) ?: []) > 1;
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
		}
		$tv = $this->TsumegoVariant->find('first', ['conditions' => ['tsumego_id' => $id]]);

		if (isset($this->params['url']['requestSolution'])) {
			$requestSolutionUser = $this->User->findById($this->params['url']['requestSolution']);
			if ($requestSolutionUser['User']['isAdmin'] >= 1) {
				$requestSolution = true;
				$adminActivity = [];
				$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
				$adminActivity['AdminActivity']['tsumego_id'] = $id;
				$adminActivity['AdminActivity']['file'] = 'settings';
				$adminActivity['AdminActivity']['answer'] = 'requested solution';
				$this->AdminActivity->create();
				$this->AdminActivity->save($adminActivity);
			}
		}

		if (isset($this->params['url']['potionAlert'])) {
			$potionAlert = true;
		}
		if (isset($_COOKIE['ui']) && $_COOKIE['ui'] != '0') {
			$ui = $_COOKIE['ui'];
			unset($_COOKIE['ui']);
		}
		if (isset($this->params['url']['modelink'])) {
			$tlength = 15;
			if ($this->params['url']['modelink'] == 1) {
				$tlength = 15;
			} elseif ($this->params['url']['modelink'] == 2) {
				$tlength = 16;
			} elseif ($this->params['url']['modelink'] == 3) {
				$tlength = 17;
			}
			if (isset($this->params['url']['modelink'])) {
				$_COOKIE['mode'] = 3;
			}

			$tcharacters = '0123456789abcdefghijklmnopqrstuvwxyz';
			$tcharactersLength = strlen($tcharacters);
			$trandomString = '';
			for ($i = 0; $i < $tlength; $i++) {
				$trandomString .= $tcharacters[rand(0, $tcharactersLength - 1)];
			}
			if ($this->loggedInUser) {
				$this->loggedInUser['User']['activeRank'] = $trandomString;
			}
			$this->User->save($this->loggedInUser);
		}
		$searchPatameters = $this->processSearchParameters($this->loggedInUserID());
		$query = $searchPatameters[0];
		$collectionSize = $searchPatameters[1];
		$search1 = $searchPatameters[2];
		$search2 = $searchPatameters[3];
		$search3 = $searchPatameters[4];

		if (isset($this->params['url']['search'])) {
			if ($this->params['url']['search'] == 'topics') {
				$query = $this->params['url']['search'];
				$_COOKIE['query'] = $this->params['url']['search'];
				$this->processSearchParameters($this->loggedInUserID());
			}
		}
		if ($this->loggedInUser) {
			$this->loggedInUser['User']['mode'] = 1;
			if (isset($_COOKIE['mode']) && $_COOKIE['mode'] != '0') {
				if (strlen($this->loggedInUser['User']['activeRank']) >= 15) {
					if ($_COOKIE['mode'] != 3) {//switch 3=>2, 3=>1
						$ranks = $this->Rank->find('all', ['conditions' => ['session' => $this->loggedInUser['User']['activeRank']]]);
						if (!$ranks) {
							$ranks = [];
						}
						if (count($ranks) != 10) {
							foreach ($ranks as $item) {
								$this->Rank->delete($item['Rank']['id']);
							}
						}
						$this->loggedInUser['User']['activeRank'] = 0;
						$this->User->save($this->loggedInUser);
					}
				} elseif ($_COOKIE['mode'] == 3) {
						$_COOKIE['mode'] = 1;
				}
				$mode = $_COOKIE['mode'];
			}
			unset($_COOKIE['mode']);
		} else {
			$nextMode = $this->Tsumego->findById(15352);
			$mode = 1;
		}
		if ($this->loggedInUser) {
			if (strlen($this->loggedInUser['User']['activeRank']) >= 15) {
				if (strlen($this->loggedInUser['User']['activeRank']) == 15) {
					$stopParameter = 10;
					$stopParameter2 = 0;
				} elseif (strlen($this->loggedInUser['User']['activeRank']) == 16) {
					$stopParameter = 10;
					$stopParameter2 = 1;
				} elseif (strlen($this->loggedInUser['User']['activeRank']) == 17) {
					$stopParameter = 10;
					$stopParameter2 = 2;
				}
				$mode = 3;
				$this->loggedInUser['User']['mode'] = 3;
				$ranks = $this->Rank->find('all', ['conditions' => ['session' => $this->loggedInUser['User']['activeRank']]]);
				if (!$ranks) {
					$ranks = [];
				}
				if (count($ranks) == 0) {
					$r = $this->params['url']['rank'];
					if ($r == '5d') {
						$r1 = 2500;
						$r2 = 10000; } elseif ($r == '4d') {
						$r1 = 2400;
						$r2 = 2500; } elseif ($r == '3d') {
							$r1 = 2300;
							$r2 = 2400; } elseif ($r == '2d') {
							$r1 = 2200;
							$r2 = 2300; } elseif ($r == '1d') {
								$r1 = 2100;
								$r2 = 2200; } elseif ($r == '1k') {
								$r1 = 2000;
								$r2 = 2100; } elseif ($r == '2k') {
									$r1 = 1900;
									$r2 = 2000; } elseif ($r == '3k') {
									$r1 = 1800;
									$r2 = 1900; } elseif ($r == '4k') {
										$r1 = 1700;
										$r2 = 1800; } elseif ($r == '5k') {
										$r1 = 1600;
										$r2 = 1700; } elseif ($r == '6k') {
											$r1 = 1500;
											$r2 = 1600; } elseif ($r == '7k') {
											$r1 = 1400;
											$r2 = 1500; } elseif ($r == '8k') {
												$r1 = 1300;
												$r2 = 1400; } elseif ($r == '9k') {
												$r1 = 1200;
												$r2 = 1300; } elseif ($r == '10k') {
													$r1 = 1100;
													$r2 = 1200; } elseif ($r == '11k') {
													$r1 = 1000;
													$r2 = 1100; } elseif ($r == '12k') {
														$r1 = 900;
														$r2 = 1000; } elseif ($r == '13k') {
														$r1 = 800;
														$r2 = 900; } elseif ($r == '14k') {
															$r1 = 700;
															$r2 = 800; } elseif ($r == '15k') {
															$r1 = 0;
															$r2 = 700; } else {
																$r1 = 0;
																$r2 = 700; }

															$rs = $this->RankSetting->find('all', ['conditions' => ['user_id' => $$this->loggedInUserID()]]);
															if (!$rs) {
																$rs = [];
															}
															$allowedRs = [];
															$rankTs = [];
															$rsCount = count($rs);
															for ($i = 0; $i < $rsCount; $i++) {
																$timeSc = $this->findTsumegoSet($rs[$i]['RankSetting']['set_id']);
																$timeScCount = count($timeSc);
																for ($g = 0; $g < $timeScCount; $g++) {
																	if ($timeSc[$g]['Tsumego']['elo_rating_mode'] >= $r1 && $timeSc[$g]['Tsumego']['elo_rating_mode'] < $r2) {
																		if (!in_array($timeSc[$g]['Tsumego']['set_id'], $setsWithPremium) || $hasPremium) {
																			array_push($rankTs, $timeSc[$g]);
																		}
																	}
																}
															}
															shuffle($rankTs);
															for ($i = 0; $i < $stopParameter; $i++) {
																$rm = [];
																$rm['Rank']['session'] = $this->loggedInUser['User']['activeRank'];
																$rm['Rank']['user_id'] = $this->loggedInUser['User']['id'];
																$rm['Rank']['tsumego_id'] = $rankTs[$i]['Tsumego']['id'];
																if ($rm['Rank']['tsumego_id'] == null) {
																	$rm['Rank']['tsumego_id'] = 5127;
																}
																$rm['Rank']['rank'] = $r;
																$rm['Rank']['num'] = $i + 1;
																$rm['Rank']['currentNum'] = 1;
																$this->Rank->create();
																$this->Rank->save($rm);
															}
															$currentRankNum = 1;
															$firstRanks = 1;
				} else {
					$ranksCount = count($ranks);
					for ($i = 0; $i < $ranksCount; $i++) {
						$ranks[$i]['Rank']['currentNum']++;
						$this->Rank->save($ranks[$i]);
					}
					$currentNum = $ranks[0]['Rank']['currentNum'];
					$tsid = null;
					$tsid2 = null;
					$ranksCount = count($ranks);
					for ($i = 0; $i < $ranksCount; $i++) {
						if ($ranks[$i]['Rank']['num'] == $currentNum) {
							$tsid = $ranks[$i]['Rank']['tsumego_id'];
							if ($currentNum < 10) {
								$tsid2 = $ranks[$i + 1]['Rank']['tsumego_id'];
							} else {
								$tsid2 = $ranks[$i]['Rank']['tsumego_id'];
							}
						}
					}
					$currentRank = $this->Tsumego->findById($tsid);
					$currentRank2 = $this->Tsumego->findById($tsid2);
					$firstRanks = 2;
					if ($currentNum == $stopParameter + 1) {
						$r10 = 1;
					}
					$currentRankNum = $currentNum;
				}
			}
		}
		if (isset($this->params['url']['refresh'])) {
			$refresh = $this->params['url']['refresh'];
		}

		if (!is_numeric($id)) {
			$id = 15352;
		}
		if ($rankTs) {
			$id = $rankTs[0]['Tsumego']['id'];
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
			if (!$scT) {
				$id = 15352;
				$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
			}
			$mode = 3;
			$currentRank2 = $rankTs[1]['Tsumego']['id'];
		} elseif ($firstRanks == 2) {
			$id = $currentRank['Tsumego']['id'];
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
			if (!$scT) {
				$id = 15352;
				$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
			}
			$mode = 3;
		}
		if ($this->loggedInUser) {
			if ($this->loggedInUser['User']['mode'] == 0) {
				$this->loggedInUser['User']['mode'] = 1;
			}
			if (isset($this->params['url']['mode'])) {
				$this->loggedInUser['User']['mode'] = $this->params['url']['mode'];
				$mode = $this->params['url']['mode'];
			}
			$difficulty = $this->loggedInUser['User']['t_glicko'];
			if (isset($_COOKIE['difficulty']) && $_COOKIE['difficulty'] != '0') {
				$difficulty = $_COOKIE['difficulty'];
				$this->loggedInUser['User']['t_glicko'] = $_COOKIE['difficulty'];
				unset($_COOKIE['difficulty']);
			}
			if ($mode == 2) {
				if ($difficulty == 1) {
					$adjustDifficulty = -450;
				} elseif ($difficulty == 2) {
					$adjustDifficulty = -300;
				} elseif ($difficulty == 3) {
					$adjustDifficulty = -150;
				} elseif ($difficulty == 4) {
					$adjustDifficulty = 0;
				} elseif ($difficulty == 5) {
					$adjustDifficulty = 150;
				} elseif ($difficulty == 6) {
					$adjustDifficulty = 300;
				} elseif ($difficulty == 7) {
					$adjustDifficulty = 450;
				} else {
					$adjustDifficulty = 0;
				}

				$eloRange = $this->loggedInUser['User']['elo_rating_mode'] + $adjustDifficulty;
				$eloRangeMin = $eloRange - 240;
				$eloRangeMax = $eloRange + 240;

				$range = $this->Tsumego->find('all', [
					'conditions' => [
						'elo_rating_mode >=' => $eloRangeMin,
						'elo_rating_mode <=' => $eloRangeMax,
					],
				]);
				if (!$range) {
					$range = [];
				}
				shuffle($range);
				$ratingFound = false;
				$nothingInRange = false;
				$ratingFoundCounter = 0;
				while (!$ratingFound) {
					if ($ratingFoundCounter < count($range)) {
						$rafSc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $range[$ratingFoundCounter]['Tsumego']['id']]]);
						if (!$rafSc) {
							$ratingFoundCounter++;

							continue;
						}
						$raS = $this->Set->findById($rafSc['SetConnection']['set_id']);
						if ($raS['Set']['public'] == 1) {
							if ($raS['Set']['id'] != 210 && $raS['Set']['id'] != 191 && $raS['Set']['id'] != 181 && $raS['Set']['id'] != 207 && $raS['Set']['id'] != 172 &&
							$raS['Set']['id'] != 202 && $raS['Set']['id'] != 237 && $raS['Set']['id'] != 81578 && $raS['Set']['id'] != 74761 && $raS['Set']['id'] != 71790 && $raS['Set']['id'] != 33007 &&
							$raS['Set']['id'] != 31813 && $raS['Set']['id'] != 29156 && $raS['Set']['id'] != 11969 && $raS['Set']['id'] != 6473) {
								if (!in_array($raS['Set']['id'], $setsWithPremium) || $hasPremium) {
									$ratingFound = true;
								}
							}
						}
						if ($ratingFound == false) {
							$ratingFoundCounter++;
						}
					} else {
						$nothingInRange = 'No problem found.';

						break;
					}
				}
				$nextMode = $range[$ratingFoundCounter];

				$ratingCookieScore = false;
				$ratingCookieMisplay = false;
				if (!empty($_COOKIE['score'])) {
					$ratingCookieScore = true;
				}
				if (!empty($_COOKIE['misplay'])) {
					$ratingCookieMisplay = true;
				}
				if (!empty($_COOKIE['ratingModePreId']) && !$ratingCookieScore && !$ratingCookieMisplay) {
					$nextMode = $this->Tsumego->findById($_COOKIE['ratingModePreId']);
					unset($_COOKIE['ratingModePreId']);
				}
				$id = $nextMode['Tsumego']['id'];
				$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
				if (!$scT) {
					$id = 15352;
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
				}
			}
		}

		$t = $this->Tsumego->findById($id);//the tsumego
		$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

		if ($t['Tsumego']['elo_rating_mode'] < 1000) {
			$t = $this->checkEloAdjust($t);
		}

		$activityValue = $this->getActivityValue($this->loggedInUserID(), $t['Tsumego']['id']);
		$eloDifference = abs($this->loggedInUser['User']['elo_rating_mode'] - $t['Tsumego']['elo_rating_mode']);

		if ($this->loggedInUser['User']['elo_rating_mode'] > $t['Tsumego']['elo_rating_mode']) {
			$eloBigger = 'u';
			if ($eloDifference > 1000) {
				$avActive2 = false;
			}
		} else {
			$eloBigger = 't';
		}
		$newUserEloW = $this->getNewElo($eloDifference, $eloBigger, $activityValue[0], $t['Tsumego']['id'], 'w');
		$newUserEloL = $this->getNewElo($eloDifference, $eloBigger, $activityValue[0], $t['Tsumego']['id'], 'l');

		if ($activityValue[1] == 0 && $avActive2) {
			$eloScore = $newUserEloW['user'];
			$eloScore2 = $newUserEloL['user'];
			$avActive = true;
		} else {
			$eloScore = 0;
			$eloScore2 = 0;
			$avActive = false;
		}
		if (isset($this->params['url']['sid'])) {
			$sc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id, 'set_id' => $this->params['url']['sid']]]);
			if ($sc) {
				$t['Tsumego']['set_id'] = $this->params['url']['sid'];
				$t['Tsumego']['num'] = $sc['SetConnection']['num'];
				if (!$hasDuplicateGroup) {
					$t['Tsumego']['duplicateLink'] = '';
				} else {
					$t['Tsumego']['duplicateLink'] = '?sid=' . $t['Tsumego']['set_id'];
				}
			}
		}
		if ($t['Tsumego']['elo_rating_mode']) {
			$tRank = Rating::getReadableRankFromRating($t['Tsumego']['elo_rating_mode']);
		}

		if ($t['Tsumego']['duplicate'] > 9) {//duplicate and not main
			$tDuplicate = $this->Tsumego->findById($t['Tsumego']['duplicate']);
			$t['Tsumego']['difficulty'] = $tDuplicate['Tsumego']['difficulty'];
			$t['Tsumego']['description'] = $tDuplicate['Tsumego']['description'];
			$t['Tsumego']['hint'] = $tDuplicate['Tsumego']['hint'];
			$t['Tsumego']['author'] = $tDuplicate['Tsumego']['author'];
			$t['Tsumego']['solved'] = $tDuplicate['Tsumego']['solved'];
			$t['Tsumego']['failed'] = $tDuplicate['Tsumego']['failed'];
			$t['Tsumego']['userWin'] = $tDuplicate['Tsumego']['userWin'];
			$t['Tsumego']['userLoss'] = $tDuplicate['Tsumego']['userLoss'];
			$t['Tsumego']['alternative_response'] = $tDuplicate['Tsumego']['alternative_response'];
			$t['Tsumego']['virtual_children'] = $tDuplicate['Tsumego']['virtual_children'];
		}

		$fSet = $this->Set->find('first', ['conditions' => ['id' => $t['Tsumego']['set_id']]]);
		if (!$fSet) {
			$fSet = $this->Set->findById(1);
		}
		if ($t == null) {
			$t = $this->Tsumego->findById($this->Session->read('lastVisit'));
		}

		if ($mode == 1 || $mode == 3) {
			$nextMode = $t;
		}
		if (isset($this->params['url']['rcheat']) && $this->params['url']['rcheat'] == 1) {
			$reviewCheat = true;
		}
		$this->Session->write('lastVisit', $id);
		if (!empty($this->data)) {
			if (isset($this->data['Comment']['status']) && !isset($this->data['Study2'])) {
				$adminActivity = [];
				$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
				$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
				$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
				$adminActivity['AdminActivity']['answer'] = $this->data['Comment']['status'];
				$this->AdminActivity->save($adminActivity);
				$this->Comment->save($this->data, true);
			} elseif (isset($this->data['Comment']['modifyDescription'])) {
				$adminActivity = [];
				$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
				$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
				$adminActivity['AdminActivity']['file'] = 'description';
				$adminActivity['AdminActivity']['answer'] = 'Description: ' . $this->data['Comment']['modifyDescription'] . ' ' . $this->data['Comment']['modifyHint'];
				$t['Tsumego']['description'] = $this->data['Comment']['modifyDescription'];
				$t['Tsumego']['hint'] = $this->data['Comment']['modifyHint'];
				$t['Tsumego']['author'] = $this->data['Comment']['modifyAuthor'];
				if ($this->data['Comment']['modifyElo'] < 2900) {
					$t['Tsumego']['elo_rating_mode'] = $this->data['Comment']['modifyElo'];
				}
				if ($t['Tsumego']['elo_rating_mode'] > 100) {
					$this->Tsumego->save($t, true);
				}

				if ($this->data['Comment']['deleteProblem'] == 'delete') {
					$adminActivity['AdminActivity']['answer'] = 'Problem deleted. (' . $t['Tsumego']['set_id'] . '-' . $t['Tsumego']['id'] . ')';
					$adminActivity['AdminActivity']['file'] = '/delete';
				}
				if ($this->data['Comment']['deleteTag'] != null) {
					$tagsToDelete = $this->Tag->find('all', ['conditions' => ['tsumego_id' => $id]]);
					if (!$tagsToDelete) {
						$tagsToDelete = [];
					}
					$tagsToDeleteCount = count($tagsToDelete);
					for ($i = 0; $i < $tagsToDeleteCount; $i++) {
						$tagNameForDelete = $this->TagName->findById($tagsToDelete[$i]['Tag']['tag_name_id']);
						if ($tagNameForDelete['TagName']['name'] == $this->data['Comment']['deleteTag']) {
							$this->Tag->delete($tagsToDelete[$i]['Tag']['id']);
						}
					}
				}
				$this->AdminActivity->save($adminActivity);
			} elseif (isset($this->data['Study'])) {
				$tv['TsumegoVariant']['answer1'] = $this->data['Study']['study1'];
				$tv['TsumegoVariant']['answer2'] = $this->data['Study']['study2'];
				$tv['TsumegoVariant']['answer3'] = $this->data['Study']['study3'];
				$tv['TsumegoVariant']['answer4'] = $this->data['Study']['study4'];
				$tv['TsumegoVariant']['explanation'] = $this->data['Study']['explanation'];
				$tv['TsumegoVariant']['numAnswer'] = $this->data['Study']['studyCorrect'];
				$this->TsumegoVariant->save($tv);
			} elseif (isset($this->data['Study2'])) {
				$tv['TsumegoVariant']['winner'] = $this->data['Study2']['winner'];
				$tv['TsumegoVariant']['answer1'] = $this->data['Study2']['answer1'];
				$tv['TsumegoVariant']['answer2'] = $this->data['Study2']['answer2'];
				$tv['TsumegoVariant']['answer3'] = $this->data['Study2']['answer3'];
				$this->TsumegoVariant->save($tv);
			} elseif (isset($this->data['Settings'])) {
				if ($this->data['Settings']['r38'] == 'on' && $t['Tsumego']['virtual_children'] != 1) {
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Turned on merge recurring positions';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity);
				}
				if ($this->data['Settings']['r38'] == 'off' && $t['Tsumego']['virtual_children'] != 0) {
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Turned off merge recurring positions';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity);
				}
				if ($this->data['Settings']['r39'] == 'on' && $t['Tsumego']['alternative_response'] != 1) {
					$adminActivity2 = [];
					$adminActivity2['AdminActivity']['user_id'] = $this->loggedInUserID();
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Turned on alternative response mode';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
				}
				if ($this->data['Settings']['r39'] == 'off' && $t['Tsumego']['alternative_response'] != 0) {
					$adminActivity2 = [];
					$adminActivity2['AdminActivity']['user_id'] = $this->loggedInUserID();
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Turned off alternative response mode';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
				}
				if ($this->data['Settings']['r43'] == 'no' && $t['Tsumego']['pass'] != 0) {
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Disabled passing';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity);
				}
				if ($this->data['Settings']['r43'] == 'yes' && $t['Tsumego']['pass'] != 1) {
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'settings';
					$adminActivity['AdminActivity']['answer'] = 'Enabled passing';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity);
				}
				if ($this->data['Settings']['r41'] == 'yes' && $tv == null) {
					$adminActivity2 = [];
					$adminActivity2['AdminActivity']['user_id'] = $this->loggedInUserID();
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Changed problem type to multiple choice';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
					$tv1 = [];
					$tv1['TsumegoVariant']['tsumego_id'] = $id;
					$tv1['TsumegoVariant']['type'] = 'multiple_choice';
					$tv1['TsumegoVariant']['answer1'] = 'Black is dead';
					$tv1['TsumegoVariant']['answer2'] = 'White is dead';
					$tv1['TsumegoVariant']['answer3'] = 'Ko';
					$tv1['TsumegoVariant']['answer4'] = 'Seki';
					$tv1['TsumegoVariant']['numAnswer'] = '1';
					$this->TsumegoVariant->create();
					$this->TsumegoVariant->save($tv1);
				}
				if ($this->data['Settings']['r41'] == 'no' && $tv != null) {
					$adminActivity2 = [];
					$adminActivity2['AdminActivity']['user_id'] = $this->loggedInUserID();
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Deleted multiple choice problem type';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
					$this->TsumegoVariant->delete($tv['TsumegoVariant']['id']);
					$tv = null;
				}
				if ($this->data['Settings']['r42'] == 'yes' && $tv == null) {
					$adminActivity2 = [];
					$adminActivity2['AdminActivity']['user_id'] = $this->loggedInUserID();
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Changed problem type to score estimating';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
					$tv1 = [];
					$tv1['TsumegoVariant']['tsumego_id'] = $id;
					$tv1['TsumegoVariant']['type'] = 'score_estimating';
					$tv1['TsumegoVariant']['numAnswer'] = '0';
					$this->TsumegoVariant->create();
					$this->TsumegoVariant->save($tv1);
				}
				if ($this->data['Settings']['r42'] == 'no' && $tv != null) {
					$adminActivity2 = [];
					$adminActivity2['AdminActivity']['user_id'] = $this->loggedInUserID();
					$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity2['AdminActivity']['file'] = 'settings';
					$adminActivity2['AdminActivity']['answer'] = 'Deleted score estimating problem type';
					$this->AdminActivity->create();
					$this->AdminActivity->save($adminActivity2);
					$this->TsumegoVariant->delete($tv['TsumegoVariant']['id']);
					$tv = null;
				}
				if ($this->data['Settings']['r38'] == 'on') {
					$t['Tsumego']['virtual_children'] = 1;
				} else {
					$t['Tsumego']['virtual_children'] = 0;
				}
				if ($this->data['Settings']['r39'] == 'on') {
					$t['Tsumego']['alternative_response'] = 1;
				} else {
					$t['Tsumego']['alternative_response'] = 0;
				}
				if ($this->data['Settings']['r43'] == 'yes') {
					$t['Tsumego']['pass'] = 1;
				} else {
					$t['Tsumego']['pass'] = 0;
				}
				if ($this->data['Settings']['r40'] == 'on') {
					$t['Tsumego']['duplicate'] = -1;
				} else {
					$t['Tsumego']['duplicate'] = 0;
				}
				if ($t['Tsumego']['elo_rating_mode'] > 100) {
					$this->Tsumego->save($t, true);
				}
			} else {
				if ($this->data['Comment']['user_id'] != 33) {
					$this->Comment->create();
					if ($this->checkCommentValid($this->loggedInUserID())) {
						$this->Comment->save($this->data, true);
					}
				}
			}
			$this->set('formRedirect', true);
		}
		if ($this->isAdmin()) {
			$aad = $this->AdminActivity->find('first', ['order' => 'id DESC']);
			if ($aad && $aad['AdminActivity']['file'] == '/delete') {
				$this->set('deleteProblem2', true);
			}
		}
		if (isset($this->params['url']['favorite'])) {
			$inFavorite = true;
		}
		if (isset($this->params['url']['deleteComment'])) {
			$deleteComment = $this->Comment->findById($this->params['url']['deleteComment']);
			if (isset($this->params['url']['changeComment'])) {
				if ($this->params['url']['changeComment'] == 1) {
					$deleteComment['Comment']['status'] = 97;
				} elseif ($this->params['url']['changeComment'] == 2) {
					$deleteComment['Comment']['status'] = 98;
				} elseif ($this->params['url']['changeComment'] == 3) {
					$deleteComment['Comment']['status'] = 96;
				} elseif ($this->params['url']['changeComment'] == 4) {
					$deleteComment['Comment']['status'] = 0;
				}
			} else {
				$deleteComment['Comment']['status'] = 99;
			}
			$adminActivity = [];
			$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
			$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
			$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
			$adminActivity['AdminActivity']['answer'] = $deleteComment['Comment']['status'];
			$this->AdminActivity->save($adminActivity);
			$this->Comment->save($deleteComment);
		}
		if (isset($_FILES['game'])) {
			$errors = [];
			$file_name = $_FILES['game']['name'];
			$file_size = $_FILES['game']['size'];
			$file_tmp = $_FILES['game']['tmp_name'];
			$file_type = $_FILES['game']['type'];
			$array2 = explode('.', $_FILES['game']['name']);
			$file_ext = strtolower(end($array2));
			$extensions = ['sgf'];
			if (in_array($file_ext, $extensions) === false) {
				$errors[] = 'Only SGF files are allowed.';
			}
			if ($file_size > 2097152) {
				$errors[] = 'The file is too large.';
			}
			$cox = count($this->Comment->find('all', ['conditions' => (['tsumego_id' => $id])]) ?: []);
			if (empty($fSet['Set']['title2'])) {
				$title2 = '';
			} else {
				$title2 = '-';
			}
			$file_name = $fSet['Set']['title'] . $title2 . $fSet['Set']['title2'] . '-' . $t['Tsumego']['num'] . '-' . $cox . '.sgf';
			$sgfComment = [];
			$this->Comment->create();
			$sgfComment['user_id'] = $this->loggedInUserID();
			$sgfComment['tsumego_id'] = $t['Tsumego']['id'];
			$file_name = str_replace('#', 'num', $file_name);
			$sgfComment['message'] = '<a href="/files/ul1/' . $file_name . '">SGF</a>';
			$sgfComment['created'] = date('Y-m-d H:i:s');
			$this->Comment->save($sgfComment);
			if (empty($errors) == true) {
				$uploadfile = $_SERVER['DOCUMENT_ROOT'] . '/app/webroot/files/ul1/' . $file_name;
				move_uploaded_file($file_tmp, $uploadfile);
			}
		}
		if (isset($_FILES['adminUpload'])) {
			$errors = [];
			$file_name = $_FILES['adminUpload']['name'];
			$file_size = $_FILES['adminUpload']['size'];
			$file_tmp = $_FILES['adminUpload']['tmp_name'];
			$file_type = $_FILES['adminUpload']['type'];
			$array1 = explode('.', $_FILES['adminUpload']['name']);
			$file_ext = strtolower(end($array1));
			$extensions = ['sgf'];
			if (in_array($file_ext, $extensions) === false) {
				$errors[] = 'Only SGF files are allowed.';
			}
			if ($file_size > 2097152) {
				$errors[] = 'The file is too large.';
			}
			$fSet = $this->Set->find('first', ['conditions' => ['id' => $t['Tsumego']['set_id']]]);
			$this->AdminActivity->create();
			$adminActivity = [];
			$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
			$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
			$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
			$adminActivity['AdminActivity']['answer'] = $file_name;
			$this->AdminActivity->save($adminActivity);
			$t['Tsumego']['variance'] = 0;
			if ($t['Tsumego']['elo_rating_mode'] > 100) {
				$this->Tsumego->save($t, true);
			}

			if (empty($errors) == true) {
				if ($t['Tsumego']['duplicate'] <= 9) {
					$lastV = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $id]]);
				} else {
					$lastV = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $t['Tsumego']['duplicate']]]);
				}
				if (!$lastV) {
					$lastV = ['Sgf' => ['version' => 0]];
				}
				$sgf = [];
				$sgf['Sgf']['sgf'] = file_get_contents($_FILES['adminUpload']['tmp_name']);
				$sgf['Sgf']['user_id'] = $this->loggedInUserID();

				if ($t['Tsumego']['duplicate'] <= 9) {
					$sgf['Sgf']['tsumego_id'] = $id;
				} else {
					$sgf['Sgf']['tsumego_id'] = $t['Tsumego']['duplicate'];
				}

				$sgf['Sgf']['version'] = $this->createNewVersionNumber($lastV, $this->loggedInUserID());
				$this->handleContribution($this->loggedInUserID(), 'made_proposal');
				$this->Sgf->save($sgf);
			}
		}
		$t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty'] * $fSet['Set']['multiplier']);

		if ($this->isLoggedIn()) {
			$this->Session->delete('noUser');
			$this->Session->delete('noLogin');
			$pd = $this->ProgressDeletion->find('all', [
				'conditions' => [
					'user_id' => $this->loggedInUserID(),
					'set_id' => $t['Tsumego']['set_id'],
				],
			]);
			if (!$pd) {
				$pd = [];
			}
			$pdCount = count($pd);
			for ($i = 0; $i < $pdCount; $i++) {
				$date = date_create($pd[$i]['ProgressDeletion']['created']);
				$pd[$i]['ProgressDeletion']['d'] = $date->format('Y') . '-' . $date->format('m');
				if (date('Y-m') == $pd[$i]['ProgressDeletion']['d']) {
					$pdCounter++;
				}
			}
		}

		if (isset($_COOKIE['skip']) && $_COOKIE['skip'] != '0' && $this->loggedInUser) {
			$this->loggedInUser['User']['readingTrial']--;
			unset($_COOKIE['skip']);
		}
		$sandboxSets = $this->Set->find('all', ['conditions' => ['public' => 0]]);
		if (!$sandboxSets) {
			$sandboxSets = [];
		}
		$sandboxSetsCount = count($sandboxSets);
		for ($i = 0; $i < $sandboxSetsCount; $i++) {
			if ($t['Tsumego']['set_id'] == $sandboxSets[$i]['Set']['id']) {
				$isSandbox = true;
			}
		}
		if ($t['Tsumego']['set_id'] == 161) {
			$isSandbox = false;
		}

		$co = $this->Comment->find('all', ['conditions' => (['tsumego_id' => $id])]);
		if (!$co) {
			$co = [];
		}
		$counter1 = 1;
		$coCount = count($co);
		for ($i = 0; $i < $coCount; $i++) {
			if (strpos($co[$i]['Comment']['message'], '<a href="/files/ul1/') === false) {
				$co[$i]['Comment']['message'] = htmlspecialchars($co[$i]['Comment']['message']);
			}
			$cou = $this->User->findById($co[$i]['Comment']['user_id']);
			if ($cou == null) {
				$cou['User']['name'] = '[deleted user]';
			}
			$co[$i]['Comment']['user'] = $this->checkPicture($cou);
			$cad = $this->User->findById($co[$i]['Comment']['admin_id']);
			if ($cad != null) {
				if ($cad['User']['id'] == 73) {
					$cad['User']['name'] = 'Admin';
				}
				$co[$i]['Comment']['admin'] = $cad['User']['name'];
			}
			$date = new DateTime($co[$i]['Comment']['created']);
			$month = date('F', strtotime($co[$i]['Comment']['created']));
			$tday = $date->format('d. ');
			$tyear = $date->format('Y');
			$tClock = $date->format('H:i');
			if ($tday[0] == 0) {
				$tday = substr($tday, -3);
			}
			$co[$i]['Comment']['created'] = $tday . $month . ' ' . $tyear . '<br>' . $tClock;
			$array = $this->commentCoordinates($co[$i]['Comment']['message'], $counter1, true);
			$co[$i]['Comment']['message'] = $array[0];
			array_push($commentCoordinates, $array[1]);
			$counter1++;
			/*
			// Does not make sense on status integer!
			$array = $this->commentCoordinates($co[$i]['Comment']['status'], $counter1, true);
			$co[$i]['Comment']['status'] = $array[0];
			array_push($commentCoordinates, $array[1]);
			$counter1++;
			*/
		}
		if ($mode == 1) {
			if ($this->loggedInUser && !$this->Session->check('noLogin')) {
				$utsMap = TsumegoStatusHelper::getMapForUser($this->loggedInUser['User']['id']);
				$utsMapx = array_count_values($utsMap);
				$correctCounter = $utsMapx['C'] + $utsMapx['S'] + $utsMapx['W'];
				$this->loggedInUser['User']['solved'] = $correctCounter;
				$ut = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $this->loggedInUserID(), 'tsumego_id' => $t['Tsumego']['id']]]);
			} else {
				$allUts = null;
				$ut = null;
			}
		} elseif ($mode == 2) {
			$allUts1 = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $this->loggedInUserID(), 'tsumego_id' => $t['Tsumego']['id']]]);
			$this->loggedInUser['User']['mode'] = 2;
			$allUts = [];
			$allUts2 = [];
			$allUts2['TsumegoStatus']['id'] = 59;
			$allUts2['TsumegoStatus']['user_id'] = 72;
			$allUts2['TsumegoStatus']['tsumego_id'] = 572;
			$allUts2['TsumegoStatus']['status'] = 'V';
			$allUts2['TsumegoStatus']['created'] = '2018-02-07 16:35:10';
			array_push($allUts, $allUts1);
			array_push($allUts, $allUts2);
			$ut = $allUts[0];

			$old_u = [];
			$old_u['old_r'] = $this->loggedInUser['User']['elo_rating_mode'];
			$old_u['old_rd'] = $this->loggedInUser['User']['rd'];
			$old_t = [];
			$old_t['old_r'] = $t['Tsumego']['elo_rating_mode'];
			$old_t['old_rd'] = $t['Tsumego']['rd'];

			$diff = $t['Tsumego']['elo_rating_mode'] - $this->loggedInUser['User']['elo_rating_mode'];
			if ($diff <= -600) {
				$diff = -595;
			}
			$newV = (($diff - $oldmin) / ($oldmax - $oldmin)) * ($newmax - $newmin);

		} elseif ($mode == 3) {
			$allUts1 = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $this->loggedInUser['User']['id'], 'tsumego_id' => $t['Tsumego']['id']]]);
			$allUts = [];
			$allUts2 = [];
			$allUts2['TsumegoStatus']['id'] = 59;
			$allUts2['TsumegoStatus']['user_id'] = 72;
			$allUts2['TsumegoStatus']['tsumego_id'] = 572;
			$allUts2['TsumegoStatus']['status'] = 'V';
			$allUts2['TsumegoStatus']['created'] = '2018-02-07 16:35:10';
			array_push($allUts, $allUts1);
			array_push($allUts, $allUts2);
			$ut = $allUts[0];
		}

		if (isset($ut['TsumegoStatus']['status']) && $ut['TsumegoStatus']['status'] == 'G') {
				$goldenTsumego = true;
		}

		if (isset($_COOKIE['previousTsumegoID'])) {
			$preTsumego = $this->Tsumego->findById((int)$_COOKIE['previousTsumegoID']);
			$preSc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $preTsumego['Tsumego']['id']]]);
			$preTsumego['Tsumego']['set_id'] = $preSc['SetConnection']['set_id'];
			$utPre = $this->findUt((int)$_COOKIE['previousTsumegoID'], $utsMap);
		}

		if ($mode == 1 || $mode == 3) {
			if (isset($_COOKIE['previousTsumegoID']) && (int)$_COOKIE['previousTsumegoID'] == $t['Tsumego']['id']) {
				if ($_COOKIE['score'] != 0) {
					$_COOKIE['score'] = $this->decrypt($_COOKIE['score']);
					$scoreArr = explode('-', $_COOKIE['score']);
					$isNum = $preTsumego['Tsumego']['num'] == $scoreArr[0];
					$isSet = $preTsumego['Tsumego']['set_id'] == $scoreArr[2];
					if ($isNum && $isSet) {
						$ut['TsumegoStatus']['status'] = 'S';
					}
				}
			}
		}
		if ($mode == 3) {
			$mode3Score1 = $this->encrypt($t['Tsumego']['num'] . '-solved-' . $t['Tsumego']['set_id']);
			$mode3Score2 = $this->encrypt($t['Tsumego']['num'] . '-failed-' . $t['Tsumego']['set_id']);
			$mode3Score3 = $this->encrypt($t['Tsumego']['num'] . '-timeout-' . $t['Tsumego']['set_id']);
			$mode3Score4 = $this->encrypt($t['Tsumego']['num'] . '-skipped-' . $t['Tsumego']['set_id']);
			array_push($mode3ScoreArray, $mode3Score1);
			array_push($mode3ScoreArray, $mode3Score2);
			array_push($mode3ScoreArray, $mode3Score3);
			array_push($mode3ScoreArray, $mode3Score4);
		}

		$favorite = $this->Favorite->find('first', ['conditions' => ['user_id' => $this->loggedInUserID(), 'tsumego_id' => $id]]);
		if (isset($_COOKIE['favorite']) && $_COOKIE['favorite'] != '0') {
			if ($this->isLoggedIn()) {
				if ($_COOKIE['favorite'] > 0) {
					$fav = $this->Favorite->find('first', ['conditions' => ['user_id' => $this->loggedInUserID(), 'tsumego_id' => $_COOKIE['favorite']]]);
					if ($fav == null) {
						$fav = [];
						$fav['Favorite']['user_id'] = $this->loggedInUserID();
						$fav['Favorite']['tsumego_id'] = $_COOKIE['favorite'];
						$fav['Favorite']['created'] = date('Y-m-d H:i:s');
						$this->Favorite->create();
						$this->Favorite->save($fav);
					}
				} else {
					$favId = $_COOKIE['favorite'] * -1;
					$favDel = $this->Favorite->find('first', ['conditions' => ['user_id' => $this->loggedInUserID(), 'tsumego_id' => $favId]]);
					$this->Favorite->delete($favDel['Favorite']['id']);
				}
				unset($_COOKIE['favorite']);
			}
		}
		if (isset($_COOKIE['sound']) && $_COOKIE['sound'] != '0') {
			$this->user['User']['sound'] = 'test';
			$this->user['User']['sound'] = $_COOKIE['sound'];
			unset($_COOKIE['sound']);
		}
		if (isset($_COOKIE['rank']) && $_COOKIE['rank'] != '0') {
			$drCookie = $this->decrypt($_COOKIE['rank']);
			$drCookie2 = explode('-', $drCookie);
			$_COOKIE['rank'] = $drCookie2[1];
		}

		if ($this->isLoggedIn() && $this->loggedInUser['User']['potion'] >= 15) {
			$this->setPotionCondition();
		}

		if (isset($_COOKIE['rejuvenationx']) && $_COOKIE['rejuvenationx'] != 0) {
			if ($this->user['User']['usedRejuvenation'] == 0 && $_COOKIE['rejuvenationx'] == 1) {
				$this->user['User']['damage'] = 0;
				$this->user['User']['intuition'] = 1;
				$this->user['User']['damage'] = 0;
				$rejuvenation = true;
			} elseif ($_COOKIE['rejuvenationx'] == 2) {
				$this->user['User']['damage'] = 0;
			}
			$_COOKIE['misplay'] = 0;
			unset($_COOKIE['rejuvenationx']);
		}
		//Incorrect
		if (isset($_COOKIE['misplay']) && $_COOKIE['misplay'] != 0) {
			if ($mode == 1 && $this->user['User']['id'] != 33) {
				if ($this->isLoggedIn()) {
					if (isset($_COOKIE['previousTsumegoID']) && (int)$_COOKIE['previousTsumegoID'] > 0) {
						$this->TsumegoAttempt->create();
						$ur1 = [];
						$ur1['TsumegoAttempt']['user_id'] = $this->loggedInUserID();
						$ur1['TsumegoAttempt']['elo'] = $this->loggedInUser['User']['elo_rating_mode'];
						$ur1['TsumegoAttempt']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
						$ur1['TsumegoAttempt']['gain'] = 0;
						$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
						$ur1['TsumegoAttempt']['solved'] = '0';
						$ur1['TsumegoAttempt']['misplays'] = $_COOKIE['misplay'];
						$this->TsumegoAttempt->save($ur1);
					}
				}
			}
			if ($mode == 1 || $mode == 3) {
				if ($mode == 1 && $_COOKIE['transition'] != 2) {
					$this->user['User']['damage'] += $_COOKIE['misplay'];
				}
				if (isset($_COOKIE['rank']) && $_COOKIE['rank'] != '0') {
					$ranks = $this->Rank->find('all', ['conditions' => ['session' => $this->loggedInUser['User']['activeRank']]]);
					if (!$ranks) {
						$ranks = [];
					}
					$currentNum = $ranks[0]['Rank']['currentNum'];
					$ranksCount = count($ranks);

					for ($i = 0; $i < $ranksCount; $i++) {
						if ($ranks[$i]['Rank']['num'] == $currentNum - 1) {
							$ranks[$i]['Rank']['result'] = $_COOKIE['rank'];
							if (isset($_COOKIE['previousTsumegoID'])) {
								$ranks[$i]['Rank']['seconds'] = $_COOKIE['seconds'] / 10;
							} else {
								$ranks[$i]['Rank']['seconds'] = 0;
							}
							$this->Rank->save($ranks[$i]);
						}
					}

					$eloDifference = abs($this->loggedInUser['User']['elo_rating_mode'] - $preTsumego['Tsumego']['elo_rating_mode']);
					if ($this->loggedInUser['User']['elo_rating_mode'] > $preTsumego['Tsumego']['elo_rating_mode']) {
						$eloBigger = 'u';
					} else {
						$eloBigger = 't';
					}
					$activityValueTime = 1;
					if (isset($_COOKIE['av'])) {
						$activityValueTime = $_COOKIE['av'];
					}
					$activityValueTime = $this->getNewElo($eloDifference, $eloBigger, $activityValueTime, $preTsumego['Tsumego']['id'], 'l');

					$preTsumego['Tsumego']['elo_rating_mode'] += $activityValueTime['tsumego'];
					$preTsumego['Tsumego']['activity_value']++;
					if ($preTsumego['Tsumego']['elo_rating_mode'] > 100) {
						$this->Tsumego->save($preTsumego);
					}

					$userId = $this->loggedInUserID();
					if ($userId > 0) {
						$this->TsumegoAttempt->create();
						$ur1 = [];
						$ur1['TsumegoAttempt']['user_id'] = $userId;
						$ur1['TsumegoAttempt']['elo'] = $this->loggedInUser['User']['elo_rating_mode'];
						$ur1['TsumegoAttempt']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
						$ur1['TsumegoAttempt']['gain'] = 0;
						$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'] / 10;
						$ur1['TsumegoAttempt']['solved'] = '0';
						$ur1['TsumegoAttempt']['misplays'] = 1;
						$ur1['TsumegoAttempt']['mode'] = 3;
						$ur1['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['elo_rating_mode'];
						$this->TsumegoAttempt->save($ur1);
					}
				}
				if ($_COOKIE['type'] == 'g') {
					$this->updateGoldenCondition();
				}
			} elseif ($mode == 2) {
				if (isset($_COOKIE['previousTsumegoID'])) {
					$eloDifference = abs($this->loggedInUser['User']['elo_rating_mode'] - $preTsumego['Tsumego']['elo_rating_mode']);
					if ($this->loggedInUser['User']['elo_rating_mode'] > $preTsumego['Tsumego']['elo_rating_mode']) {
						$eloBigger = 'u';
					} else {
						$eloBigger = 't';
					}
					$activityValueRating = 1;
					if (isset($_COOKIE['av'])) {
						$activityValueRating = $_COOKIE['av'];
					}
					$newUserEloWRating = $this->getNewElo($eloDifference, $eloBigger, $activityValueRating, $preTsumego['Tsumego']['id'], 'l');
					$preTsumego['Tsumego']['elo_rating_mode'] += $newUserEloWRating['tsumego'];
					$preTsumego['Tsumego']['activity_value']++;
					if ($_COOKIE['type'] == 'g') {
						$this->updateGoldenCondition();
					}

					if ($preTsumego['Tsumego']['elo_rating_mode'] > 100) {
						$this->Tsumego->save($preTsumego);
					}

					$this->TsumegoAttempt->create();
					$ur1 = [];
					$ur1['TsumegoAttempt']['user_id'] = $this->loggedInUserID();
					$ur1['TsumegoAttempt']['elo'] = $this->loggedInUser['User']['elo_rating_mode'];
					$ur1['TsumegoAttempt']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
					$ur1['TsumegoAttempt']['gain'] = $this->loggedInUser['User']['elo_rating_mode'];
					$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
					$ur1['TsumegoAttempt']['solved'] = '0';
					$ur1['TsumegoAttempt']['misplays'] = 1;
					$ur1['TsumegoAttempt']['mode'] = 2;
					$ur1['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['elo_rating_mode'];
					if ($ur1['TsumegoAttempt']['user_id'] > 0) {
						$this->TsumegoAttempt->save($ur1);
					}
				}
			}
			$aCondition = $this->AchievementCondition->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => $this->loggedInUserID(),
					'category' => 'err',
				],
			]);
			if ($aCondition == null) {
				$aCondition = [];
			}
			$aCondition['AchievementCondition']['category'] = 'err';
			$aCondition['AchievementCondition']['user_id'] = $this->loggedInUserID();
			$aCondition['AchievementCondition']['value'] = 0;
			$this->AchievementCondition->save($aCondition);
			if ($this->loggedInUser['User']['damage'] > $this->loggedInUser['User']['health']) {
				if (empty($utPre)) {
					$utPre['TsumegoStatus'] = [];
					$utPre['TsumegoStatus']['user_id'] = $this->loggedInUser['User']['id'];
					$utPre['TsumegoStatus']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
				}
				if (!$hasPremium) {
					if ($utPre['TsumegoStatus']['status'] == 'W') {
						$utPre['TsumegoStatus']['status'] = 'X';//W => X
					} elseif ($utPre['TsumegoStatus']['status'] == 'V') {
						$utPre['TsumegoStatus']['status'] = 'F';// V => F
					} elseif ($utPre['TsumegoStatus']['status'] == 'G') {
						$utPre['TsumegoStatus']['status'] = 'F';// G => F
					} elseif ($utPre['TsumegoStatus']['status'] == 'S') {
						$utPre['TsumegoStatus']['status'] = 'S';//S => S
					}
				}
				$utPre['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
				if ($this->isLoggedIn()) {
					if (!isset($utPre['TsumegoStatus']['status'])) {
						$utPre['TsumegoStatus']['status'] = 'V';
					}
					if ($this->hasPremium() || $this->loggedInUser['User']['level'] >= 50) {
						if ($this->loggedInUser['User']['potion'] != -69) {
							$potion = $this->Session->read('loggedInUser.User.potion');
							$potion++;
							$this->Session->write('loggedInUser.User.potion', $potion);
							$this->loggedInUser['User']['potion']++;
							$potionSuccess = false;
							if ($potion >= 5) {
								$potionPercent = $potion * 1.3;
								$potionPercent2 = rand(0, 100);
								$potionSuccess = $potionPercent > $potionPercent2;
							}
							if ($potionSuccess) {
								$this->loggedInUser['User']['potion'] = -69;
							}
						}
					}
				}
				if ($mode == 1) {
					$this->user['User']['damage'] = $this->user['User']['health'];
				}
			}
			$noUser['damage'] = $this->user['User']['damage'];
			unset($_COOKIE['misplay']);
			unset($_COOKIE['sequence']);
			unset($_COOKIE['type']);
			unset($_COOKIE['transition']);
		}
		$correctSolveAttempt = false;

		//Correct!
		if (isset($_COOKIE['previousTsumegoID']) && isset($_COOKIE['score']) && $_COOKIE['score'] != '0') {
			$_COOKIE['score'] = $this->decrypt($_COOKIE['score']);
			$scoreArr = explode('-', $_COOKIE['score']);
			$isNum = $preTsumego['Tsumego']['num'] == $scoreArr[0];
			$isSet = $preTsumego['Tsumego']['set_id'] == $scoreArr[2];
			$_COOKIE['score'] = $scoreArr[1];

			$solvedTsumegoRank = Rating::getReadableRankFromRating($preTsumego['Tsumego']['elo_rating_mode']);

			if ($isNum && $isSet || $mode == 2) {
				if ($mode == 1 || $mode == 3) {
					if ($this->loggedInUser && !$this->Session->check('noLogin')) {
						$ub = [];
						$ub['UserBoard']['user_id'] = $this->loggedInUserID();
						$ub['UserBoard']['b1'] = (int)$_COOKIE['previousTsumegoID'];
						$this->UserBoard->create();
						$this->UserBoard->save($ub);

						if ($_COOKIE['score'] >= 3000) {
							$_COOKIE['score'] = 0;
							$suspiciousBehavior = true;
						}
						if ($this->loggedInUser['User']['reuse3'] > 12000) {
							$this->loggedInUser['User']['reuse4'] = 1;
						}
					}
					if ($mode == 3) {
						$exploit = null;
						$suspiciousBehavior = false;
					}
					if ($exploit == null && $suspiciousBehavior == false) {
						if ($mode == 1) {
							$xpOld = $this->user['User']['xp'] + ((int)($_COOKIE['score']));
							$this->user['User']['reuse2']++;
							$this->user['User']['reuse3'] += (int)($_COOKIE['score']);
							if ($xpOld >= $this->user['User']['nextlvl']) {
								$xpOnNewLvl = -1 * ($this->user['User']['nextlvl'] - $xpOld);
								$this->user['User']['xp'] = $xpOnNewLvl;
								$this->user['User']['level'] += 1;
								$this->user['User']['nextlvl'] += $this->getXPJump($this->user['User']['level']);
								$this->user['User']['health'] = $this->getHealth($this->user['User']['level']);
								$noUser['xp'] = $this->user['User']['xp'];
								$noUser['level'] = $this->user['User']['level'];
								$noUser['nextlvl'] = $this->user['User']['nextlvl'];
								$noUser['health'] = $this->user['User']['health'];
							} else {
								$this->user['User']['xp'] = $xpOld;
								$this->user['User']['ip'] = $_SERVER['REMOTE_ADDR'];
								$noUser['xp'] = $this->user['User']['xp'];
							}
						}
						if ($mode == 1 && $this->user['User']['id'] != 33) {
							if ($this->isLoggedIn()) {
								if (isset($_COOKIE['previousTsumegoID'])) {
									$this->TsumegoAttempt->create();
									$ur = [];
									$ur['TsumegoAttempt']['user_id'] = $this->loggedInUserID();
									$ur['TsumegoAttempt']['elo'] = $this->user['User']['elo_rating_mode'];
									$ur['TsumegoAttempt']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
									$ur['TsumegoAttempt']['gain'] = $_COOKIE['score'];
									$ur['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
									$ur['TsumegoAttempt']['solved'] = '1';
									$ur['TsumegoAttempt']['mode'] = 1;
									$this->TsumegoAttempt->save($ur);
									$correctSolveAttempt = true;
									$this->saveDanSolveCondition($solvedTsumegoRank, $preTsumego['Tsumego']['id']);
									$this->updateGems($solvedTsumegoRank);
									if ($_COOKIE['sprint'] == 1) {
										$this->updateSprintCondition(true);
									} else {
										$this->updateSprintCondition();
									}
									if ($_COOKIE['type'] == 'g') {
										$this->updateGoldenCondition(true);
									}
								}
							}
						}
						if (isset($_COOKIE['rank']) && $_COOKIE['rank'] != '0') {
							$this->saveDanSolveCondition($solvedTsumegoRank, $preTsumego['Tsumego']['id']);
							$this->updateGems($solvedTsumegoRank);
							$ranks = $this->Rank->find('all', ['conditions' => ['session' => $this->loggedInUser['User']['activeRank']]]);
							if (!$ranks) {
								$ranks = [];
							}
							$currentNum = $ranks[0]['Rank']['currentNum'];
							$ranksCount = count($ranks);

							for ($i = 0; $i < $ranksCount; $i++) {
								if ($ranks[$i]['Rank']['num'] == $currentNum - 1) {
									if ($_COOKIE['rank'] != 'solved' && $_COOKIE['rank'] != 'failed' && $_COOKIE['rank'] != 'skipped' && $_COOKIE['rank'] != 'timeout') {
										$_COOKIE['rank'] = 'failed';
									}
									$ranks[$i]['Rank']['result'] = $_COOKIE['rank'];
		 							$ranks[$i]['Rank']['seconds'] = $_COOKIE['seconds'] / 10;
									$this->Rank->save($ranks[$i]);
								}
							}

							$ratingModeUt['TsumegoStatus']['status'] = $_COOKIE['preTsumegoBuffer'];

							if ($ratingModeUt['TsumegoStatus']['status'] == 'W') {
								$ratingModeXp = $preTsumego['Tsumego']['difficulty'] / 2;
							} elseif ($ratingModeUt['TsumegoStatus']['status'] == 'S' || $ratingModeUt['TsumegoStatus']['status'] == 'C') {
								$ratingModeXp = 0;
							} else {
								$ratingModeXp = $preTsumego['Tsumego']['difficulty'];
							}
							$xpOld = $this->user['User']['xp'] + $ratingModeXp;
							if ($xpOld >= $this->user['User']['nextlvl']) {
								$xpOnNewLvl = -1 * ($this->user['User']['nextlvl'] - $xpOld);
								$this->user['User']['xp'] = $xpOnNewLvl;
								$this->user['User']['level'] += 1;
								$this->user['User']['nextlvl'] += $this->getXPJump($this->user['User']['level']);
								$this->user['User']['health'] = $this->getHealth($this->user['User']['level']);
								$this->Session->write('loggedInUser.User.level', $this->user['User']['level']);
							} else {
								$this->user['User']['xp'] = $xpOld;
							}

							$eloDifference = abs($this->Session->read('loggedInUser.User.elo_rating_mode') - $preTsumego['Tsumego']['elo_rating_mode']);
							if ($this->Session->read('loggedInUser.User.elo_rating_mode') > $preTsumego['Tsumego']['elo_rating_mode']) {
								$eloBigger = 'u';
							} else {
								$eloBigger = 't';
							}
							$activityValueTime = 1;
							if (isset($_COOKIE['av'])) {
								$activityValueTime = $_COOKIE['av'];
							}
							$activityValueTime = $this->getNewElo($eloDifference, $eloBigger, $activityValueTime, $preTsumego['Tsumego']['id'], 'w');
							$preTsumego['Tsumego']['elo_rating_mode'] += $activityValueTime['tsumego'];
							$preTsumego['Tsumego']['activity_value']++;
							if ($preTsumego['Tsumego']['elo_rating_mode'] > 100) {
								$this->Tsumego->save($preTsumego);
							}

							$this->TsumegoAttempt->create();
							$ur1 = [];
							$ur1['TsumegoAttempt']['user_id'] = $this->loggedInUserID();
							$ur1['TsumegoAttempt']['elo'] = $this->Session->read('loggedInUser.User.elo_rating_mode');
							$ur1['TsumegoAttempt']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
							$ur1['TsumegoAttempt']['gain'] = 1;
							$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'] / 10;
							$ur1['TsumegoAttempt']['solved'] = '1';
							$ur1['TsumegoAttempt']['misplays'] = 0;
							$ur1['TsumegoAttempt']['mode'] = 3;
							$ur1['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['elo_rating_mode'];
							if ($ur1['TsumegoAttempt']['user_id'] > 0) {
								$this->TsumegoAttempt->save($ur1);
							}
						}
					}
					if (empty($utPre)) {
						$utPre['TsumegoStatus'] = [];
						$utPre['TsumegoStatus']['user_id'] = $this->user['User']['id'];
						$utPre['TsumegoStatus']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
						$utPre['TsumegoStatus']['status'] = 'V';
					}
					if ($utPre['TsumegoStatus']['status'] == 'W') {
						$utPre['TsumegoStatus']['status'] = 'C';
					} else {
						$utPre['TsumegoStatus']['status'] = 'S';
					}

					$utPre['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
					if ($this->Session->check('loggedInUser') && !$this->Session->check('noLogin')) {
						if (!isset($utPre['TsumegoStatus']['status'])) {
							$utPre['TsumegoStatus']['status'] = 'V';
						}
						if ($mode == 1) {
							//$this->TsumegoStatus->save($utPre); status is saved elsewhere
							//$this->Session->read('loggedInUser.uts')[$utPre['TsumegoStatus']['tsumego_id']] = $utPre['TsumegoStatus']['status'];
							//$utsMap[$utPre['TsumegoStatus']['tsumego_id']] = $utPre['TsumegoStatus']['status'];
						}
					}
				} elseif ($mode == 2 && $_COOKIE['transition'] != 1) {
					$userEloBefore = $this->user['User']['elo_rating_mode'];
					$tsumegoEloBefore = $preTsumego['Tsumego']['elo_rating_mode'];
					$diff = $preTsumego['Tsumego']['elo_rating_mode'] - $this->user['User']['elo_rating_mode'];

					$ratingModeUt['TsumegoStatus']['status'] = $_COOKIE['preTsumegoBuffer'];

					if ($ratingModeUt['TsumegoStatus']['status'] == 'W') {
						$ratingModeXp = $preTsumego['Tsumego']['difficulty'] / 2;
					} elseif ($ratingModeUt['TsumegoStatus']['status'] == 'S' || $ratingModeUt['TsumegoStatus']['status'] == 'C') {
						$ratingModeXp = 0;
					} else {
						$ratingModeXp = $preTsumego['Tsumego']['difficulty'];
					}
					$xpOld = $this->user['User']['xp'] + $ratingModeXp;
					if ($xpOld >= $this->user['User']['nextlvl']) {
						$xpOnNewLvl = -1 * ($this->user['User']['nextlvl'] - $xpOld);
						$this->user['User']['xp'] = $xpOnNewLvl;
						$this->user['User']['level'] += 1;
						$this->user['User']['nextlvl'] += $this->getXPJump($this->user['User']['level']);
						$this->user['User']['health'] = $this->getHealth($this->user['User']['level']);
						$this->Session->write('loggedInUser.User.level', $this->user['User']['level']);
					} else {
						$this->user['User']['xp'] = $xpOld;
						$this->Session->write('loggedInUser.User.xp', $xpOld);
					}
					if ((int)($_COOKIE['score'] > 100)) {
						$_COOKIE['score'] = 100;
					}

					if ($_COOKIE['type'] == 'g') {
						$this->updateGoldenCondition(true);
					}
					$this->saveDanSolveCondition($solvedTsumegoRank, $preTsumego['Tsumego']['id']);
					$this->updateGems($solvedTsumegoRank);
					$this->user['User']['solved2']++;

					$eloDifference = abs($this->Session->read('loggedInUser.User.elo_rating_mode') - $preTsumego['Tsumego']['elo_rating_mode']);
					if ($this->Session->read('loggedInUser.User.elo_rating_mode') > $preTsumego['Tsumego']['elo_rating_mode']) {
						$eloBigger = 'u';
					} else {
						$eloBigger = 't';
					}
					$activityValueRating = 1;
					if (isset($_COOKIE['av'])) {
						$activityValueRating = $_COOKIE['av'];
					}
					$newUserEloWRating = $this->getNewElo($eloDifference, $eloBigger, $activityValueRating, $preTsumego['Tsumego']['id'], 'w');
					$preTsumego['Tsumego']['elo_rating_mode'] += $newUserEloWRating['tsumego'];
					$preTsumego['Tsumego']['activity_value']++;
					if ($preTsumego['Tsumego']['elo_rating_mode'] > 100) {
						$this->Tsumego->save($preTsumego);
					}

					$this->TsumegoAttempt->create();
					$ur = [];
					$ur['TsumegoAttempt']['user_id'] = $this->loggedInUserID();
					$ur['TsumegoAttempt']['elo'] = $this->user['User']['elo_rating_mode'];
					$ur['TsumegoAttempt']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
					$ur['TsumegoAttempt']['gain'] = $this->user['User']['elo_rating_mode'];
					$ur['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
					$ur['TsumegoAttempt']['solved'] = '1';
					$ur['TsumegoAttempt']['mode'] = 2;
					$ur['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['elo_rating_mode'];
					if ($ur['TsumegoAttempt']['user_id'] > 0) {
						$this->TsumegoAttempt->save($ur);
					}
				}
				$aCondition = $this->AchievementCondition->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => $this->loggedInUserID(),
						'category' => 'err',
					],
				]);
				if ($aCondition == null) {
					$aCondition = [];
				}
				$aCondition['AchievementCondition']['category'] = 'err';
				$aCondition['AchievementCondition']['user_id'] = $this->loggedInUserID();
				$aCondition['AchievementCondition']['value']++;
				$this->AchievementCondition->save($aCondition);
			} else {
				$this->user['User']['penalty'] += 1;
			}
			unset($_COOKIE['preTsumegoBuffer']);
			unset($_COOKIE['score']);
			unset($_COOKIE['transition']);
			unset($_COOKIE['sequence']);
			unset($_COOKIE['type']);
		}

		if (isset($_COOKIE['correctNoPoints']) && $_COOKIE['correctNoPoints'] != '0') {
			if ($this->user['User']['id'] != 33 && !$correctSolveAttempt) {
				if (isset($_COOKIE['previousTsumegoID'])) {
					$this->TsumegoAttempt->create();
					$ur = [];
					$ur['TsumegoAttempt']['user_id'] = $this->loggedInUserID();
					$ur['TsumegoAttempt']['elo'] = $this->Session->read('loggedInUser.User.elo_rating_mode');
					$ur['TsumegoAttempt']['tsumego_id'] = (int)$_COOKIE['previousTsumegoID'];
					$ur['TsumegoAttempt']['gain'] = 0;
					$ur['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
					$ur['TsumegoAttempt']['solved'] = '1';
					$ur['TsumegoAttempt']['mode'] = 1;
					if ($ur['TsumegoAttempt']['user_id'] > 0) {
						$this->TsumegoAttempt->save($ur);
					}
				}
			}
		}
		unset($_COOKIE['previousTsumegoID']);
		setcookie('previousTsumegoID', $id);
		if (isset($_COOKIE['doublexp']) && $_COOKIE['doublexp'] != '0') {
			if ($this->user['User']['usedSprint'] == 0) {
				$doublexp = $_COOKIE['doublexp'];
			} else {
				unset($_COOKIE['doublexp']);
			}
		}
		if (isset($_COOKIE['sprint']) && $_COOKIE['sprint'] != '0') {
			$this->user['User']['sprint'] = 0;
			if ($_COOKIE['sprint'] == 1) {
				$this->set('sprintActivated', true);
			}
			if ($_COOKIE['sprint'] == 2) {
				$this->user['User']['usedSprint'] = 1;
			}
			unset($_COOKIE['sprint']);
		}
		if (isset($_COOKIE['intuition']) && $_COOKIE['intuition'] != '0') {
			if ($_COOKIE['intuition'] == '1') {
				$this->user['User']['intuition'] = 0;
			}
			if ($_COOKIE['intuition'] == '2') {
				$this->user['User']['intuition'] = 1;
			}
			unset($_COOKIE['intuition']);
		}
		if (isset($_COOKIE['rejuvenation']) && $_COOKIE['rejuvenation'] != '0') {
			$this->user['User']['rejuvenation'] = 0;
			$this->user['User']['usedRejuvenation'] = 1;
			unset($_COOKIE['rejuvenation']);
		}
		if (isset($_COOKIE['extendedSprint']) && $_COOKIE['extendedSprint'] != '0') {
			$this->user['User']['penalty'] += 1;
			unset($_COOKIE['extendedSprint']);
		}
		if (isset($_COOKIE['refinement']) && $_COOKIE['refinement'] != '0') {
			if ($_COOKIE['refinement'] > 0) {
				if ($this->user['User']['usedRefinement'] == 0) {
					$refinementUT = $this->findUt($id, $utsMap);
					if ($refinementUT == null) {
						$this->TsumegoStatus->create();
						$refinementUT['TsumegoStatus']['user_id'] = $this->user['User']['id'];
						$refinementUT['TsumegoStatus']['tsumego_id'] = $id;
					}
					$refinementUT['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
					$refinementUT['TsumegoStatus']['status'] = 'G';
					$testUt = $this->TsumegoStatus->find('first', [
						'conditions' => [
							'tsumego_id' => $refinementUT['TsumegoStatus']['tsumego_id'],
							'user_id' => $refinementUT['TsumegoStatus']['user_id'],
						],
					]);
					if ($testUt != null) {
						$refinementUT['TsumegoStatus']['id'] = $testUt['TsumegoStatus']['id'];
					}
					//$this->TsumegoStatus->save($refinementUT); status should be saved elsewhere
					//$this->Session->read('loggedInUser.uts')[$refinementUT['TsumegoStatus']['tsumego_id']] = $refinementUT['TsumegoStatus']['status'];
					//$utsMap[$refinementUT['TsumegoStatus']['tsumego_id']] = $refinementUT['TsumegoStatus']['status'];

					if (!$ut) {
						$ut = $refinementUT;
					} else {
						$ut['TsumegoStatus']['status'] = 'G';
					}
					$goldenTsumego = true;
					$this->user['User']['usedRefinement'] = 1;
				}
			} else {
				$resetRefinement = $this->findUt($id, $utsMap);
				if ($resetRefinement != null) {
					$resetRefinement['TsumegoStatus']['status'] = 'V';
					$testUt = $this->TsumegoStatus->find('first', [
						'conditions' => [
							'tsumego_id' => $resetRefinement['TsumegoStatus']['tsumego_id'],
							'user_id' => $resetRefinement['TsumegoStatus']['user_id'],
						],
					]);
					$resetRefinement['TsumegoStatus']['id'] = $testUt['TsumegoStatus']['id'];
					//$this->TsumegoStatus->save($resetRefinement);
					//$this->Session->read('loggedInUser.uts')[$resetRefinement['TsumegoStatus']['tsumego_id']] = $resetRefinement['TsumegoStatus']['status'];
					//$utsMap[$refinementUT['TsumegoStatus']['tsumego_id']] = $resetRefinement['TsumegoStatus']['status'];
				}
				if (!$ut) {
					$ut = $resetRefinement;
				} else {
					$ut['TsumegoStatus']['status'] = 'V';
				}
				$goldenTsumego = false;
			}
			$this->user['User']['refinement'] = 0;
			unset($_COOKIE['refinement']);
		}

		if ($rejuvenation) {
			$utr = $this->TsumegoStatus->find('all', ['conditions' => ['status' => 'F', 'user_id' => $this->user['User']['id']]]);
			if (!$utr) {
				$utr = [];
			}
			$utrCount = count($utr);

			for ($i = 0; $i < $utrCount; $i++) {
				$utr[$i]['TsumegoStatus']['status'] = 'V';
				$this->TsumegoStatus->create();
				//$this->TsumegoStatus->save($utr[$i]);
				//$this->Session->read('loggedInUser.uts')[$utr[$i]['TsumegoStatus']['tsumego_id']] = $utr[$i]['TsumegoStatus']['status'];
				//$utsMap[$utr[$i]['TsumegoStatus']['tsumego_id']] = $utr[$i]['TsumegoStatus']['status'];
			}
			$utrx = $this->TsumegoStatus->find('all', ['conditions' => ['status' => 'X', 'user_id' => $this->user['User']['id']]]);
			if (!$utrx) {
				$utrx = [];
			}
			$utrxCount = count($utrx);

			for ($j = 0; $j < $utrxCount; $j++) {
				$utrx[$j]['TsumegoStatus']['status'] = 'W';
				$this->TsumegoStatus->create();
				//$this->TsumegoStatus->save($utrx[$j]);
				//$this->Session->read('loggedInUser.uts')[$utrx[$i]['TsumegoStatus']['tsumego_id']] = $utrx[$i]['TsumegoStatus']['status'];
				//$utsMap[$utrx[$i]['TsumegoStatus']['tsumego_id']] = $utrx[$i]['TsumegoStatus']['status'];
			}
		}

		if (isset($_COOKIE['reputation']) && $_COOKIE['reputation'] != '0') {
			$reputation = $_COOKIE['reputation'];
			$reputation = [];
			$reputation['Reputation']['user_id'] = $this->loggedInUserID();
			$reputation['Reputation']['tsumego_id'] = abs($_COOKIE['reputation']);
			if ($_COOKIE['reputation'] > 0) {
				$reputation['Reputation']['value'] = 1;
			} else {
				$reputation['Reputation']['value'] = -1;
			}
			$this->Reputation->create();
			$this->Reputation->save($reputation);
			unset($_COOKIE['reputation']);
		}

		$this->Session->write('loggedInUser.User.activeRank', $this->user['User']['activeRank']);
		$this->Session->write('loggedInUser.User.premium', $this->user['User']['premium']);
		$this->Session->write('loggedInUser.User.completed', $this->user['User']['completed']);
		$this->Session->write('loggedInUser.User.level', $this->user['User']['level']);
		$this->Session->write('loggedInUser.User.reuse5', $this->user['User']['reuse5']);

		if (isset($noUser)) {
			$this->Session->write('noUser', $noUser);
		}
		if ($this->loggedInUser && $this->user['User']['id'] != 33) {
			$this->user['User']['mode'] = $this->Session->read('loggedInUser.User.mode');
			$userDate = new DateTime($this->user['User']['created']);
			$userDate = $userDate->format('Y-m-d');
			if ($userDate != date('Y-m-d')) {
				$this->user['User']['created'] = date('Y-m-d H:i:s');
				$this->deleteUnusedStatuses($this->loggedInUserID());
			}
			$this->User->save($this->loggedInUser);
		}
		if ($mode == 1 || $mode == 3) {
			if ($ut == null && $this->isLoggedIn()) {
				$this->TsumegoStatus->create();
				$ut['TsumegoStatus'] = [];
				$ut['TsumegoStatus']['user_id'] = $this->user['User']['id'];
				$ut['TsumegoStatus']['tsumego_id'] = $id;
				$ut['TsumegoStatus']['status'] = 'V';
				if ($mode != 3) {
					//$this->TsumegoStatus->save($ut);
					//$this->Session->read('loggedInUser.uts')[$ut['TsumegoStatus']['tsumego_id']] = $ut['TsumegoStatus']['status'];
					$utsMap[$ut['TsumegoStatus']['tsumego_id']] = $ut['TsumegoStatus']['status'];
				}
			}
		} elseif ($mode == 2) {
			$ut['TsumegoStatus'] = [];
			$ut['TsumegoStatus']['user_id'] = $this->user['User']['id'];
			$ut['TsumegoStatus']['tsumego_id'] = $id;
			$ut['TsumegoStatus']['status'] = 'V';
		}
		$set = $this->Set->findById($t['Tsumego']['set_id']);
		$amountOfOtherCollection = count($this->findTsumegoSet($t['Tsumego']['set_id']));
		$search3ids = [];
		$search3Count = count($search3);

		foreach ($search3 as $item) {
			$search3ids[] = $this->TagName->findByName($item)['TagName']['id'];
		}

		$sgf = [];
		if ($t['Tsumego']['duplicate'] <= 9) {
			$sgfdb = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $id]]);
		} else {
			$sgfdb = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $t['Tsumego']['duplicate']]]);
		}
		if ($sgfdb == null) {
			$sgf['Sgf']['sgf'] = file_get_contents('placeholder2.sgf');
			$sgf['Sgf']['user_id'] = 33;
			$sgf['Sgf']['tsumego_id'] = $id;
			$sgf['Sgf']['version'] = 1;
			$this->Sgf->save($sgf);
		} else {
			$sgf = $sgfdb;
		}
		if ($t['Tsumego']['set_id'] == 208 || $t['Tsumego']['set_id'] == 210) {
			$aw = strpos($sgf['Sgf']['sgf'], 'AW');
			$ab = strpos($sgf['Sgf']['sgf'], 'AB');
			$tr = strpos($sgf['Sgf']['sgf'], 'TR');
			$sq = strpos($sgf['Sgf']['sgf'], 'SQ');
			$seq1 = strpos($sgf['Sgf']['sgf'], ';B');
			$seq2 = strpos($sgf['Sgf']['sgf'], ';W');
			$sequencesSign = strpos($sgf['Sgf']['sgf'], ';B');
			$p4 = substr($sgf['Sgf']['sgf'], $tr, $sq - $tr);
			$trX = str_split(substr($p4, 2), 4);
			$p5 = substr($sgf['Sgf']['sgf'], $sq, $sequencesSign - $sq);
			$sqX = str_split(substr($p5, 2), 4);
			$sqXCount = count($sqX);

			for ($i = 0; $i < $sqXCount; $i++) {
				if (strlen($sqX[$i]) < 4) {
					unset($sqX[$i]);
				}
			}
			$this->set('multipleChoiceTriangles', count($trX));
			$this->set('multipleChoiceSquares', count($sqX));
		}
		$sgf2 = str_replace("\n", ' ', $sgf['Sgf']['sgf']);
		$sgf['Sgf']['sgf'] = str_replace("\r", '', $sgf['Sgf']['sgf']);
		$sgf['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $sgf['Sgf']['sgf']);
		if (isset($this->params['url']['requestProblem'])) {
			if (($this->params['url']['requestProblem'] / 1337) == $id) {
				$requestProblem = $_POST['sgfForBesogo'];
				$requestProblem = str_replace('@', ';', $requestProblem);
				$requestProblem = str_replace('€', "\n", $requestProblem);
				$requestProblem = str_replace('%2B', '+', $requestProblem);
				if ($t['Tsumego']['duplicate'] <= 9) {
					$lastV = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $id]]);
				} else {
					$lastV = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $t['Tsumego']['duplicate']]]);
				}
				if ($requestProblem !== $lastV['Sgf']['sgf']) {
					$sgf = [];
					$sgf['Sgf']['sgf'] = $requestProblem;
					$sgf['Sgf']['user_id'] = $this->loggedInUserID();
					$sgf['Sgf']['tsumego_id'] = $id;
					if ($this->Session->read('loggedInUser.User.isAdmin') > 0) {
						$sgf['Sgf']['version'] = $this->createNewVersionNumber($lastV, $this->loggedInUserID());
					} else {
						$sgf['Sgf']['version'] = 0;
					}
					$this->Sgf->save($sgf);
					$sgf['Sgf']['sgf'] = str_replace("\r", '', $sgf['Sgf']['sgf']);
					$sgf['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $sgf['Sgf']['sgf']);
					if ($this->Session->read('loggedInUser.User.isAdmin') > 0) {
						$this->AdminActivity->create();
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
						$adminActivity['AdminActivity']['answer'] = $t['Tsumego']['num'] . '.sgf' . ' <font color="grey">(direct save)</font>';
						$this->AdminActivity->save($adminActivity);
						$this->handleContribution($this->loggedInUserID(), 'made_proposal');
					}
				}
			}
		}

		if ($query == 'difficulty') {
			$t['Tsumego']['actualNum'] = $t['Tsumego']['num'];
			$setConditions = [];
			if (count($search1) > 0) {
				$search1ids = [];
				$search1Count = count($search1);

				for ($i = 0; $i < $search1Count; $i++) {
					$search1id = $this->Set->find('first', ['conditions' => ['title' => $search1[$i]]]);
					if ($search1id) {
						$search1ids[$i] = $search1id['Set']['id'];
					}
				}
				$setConditions['set_id'] = $search1ids;
			}
			$lastSet = $this->getTsumegoElo($this->Session->read('lastSet'));
			$ftFrom = [];
			$ftTo = [];
			$notPremiumArray = [];
			$ftFrom['elo_rating_mode >='] = $lastSet;
			$ftTo['elo_rating_mode <'] = $lastSet + 100;
			if ($this->Session->read('lastSet') == '15k') {
				$ftFrom['elo_rating_mode >='] = 50;
			}
			if (!$hasPremium) {
				$notPremiumArray['NOT'] = ['set_id' => $setsWithPremium];
			}
			$ts = $this->Tsumego->find('all', [
				'order' => 'id ASC',
				'conditions' => [
					'public' => 1,
					$notPremiumArray,
					$ftFrom,
					$ftTo,
					$setConditions,
				],
			]);
			if (!$ts) {
				$ts = [];
			}
			$ts1 = [];
			$i2 = 1;
			$tsCount = count($ts);

			for ($i = 0; $i < $tsCount; $i++) {
				$tagValid = false;
				if (count($search3) > 0) {
					$tagForTsumego = $this->Tag->find('first', [
						'conditions' => [
							'tsumego_id' => $ts[$i]['Tsumego']['id'],
							'tag_name_id' => $search3ids,
						],
					]);
					if ($tagForTsumego != null) {
						$tagValid = true;
					}
				} else {
					$tagValid = true;
				}
				if ($tagValid) {
					$ts[$i]['Tsumego']['num'] = $i2;
					if ($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']) {
						$t['Tsumego']['num'] = $ts[$i]['Tsumego']['num'];
					}
					array_push($ts1, $ts[$i]);
					$i2++;
				}
			}
			$ts = $ts1;

			if (count($ts) > $collectionSize) {
				$tsCount = count($ts);

				for ($i = 0; $i < $tsCount; $i++) {
					if ($i % $collectionSize == 0) {
						$partition++;
					}
					if ($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']) {
						break;
					}
				}
				$fromTo = $this->getPartitionRange(count($ts), $collectionSize, $partition);
				$ts1 = [];
				for ($i = $fromTo[0]; $i <= $fromTo[1]; $i++) {
					array_push($ts1, $ts[$i]);
				}
				$ts = $ts1;
			}
			if ($partition == -1) {
				$partitionText = '';
			} else {
				$partitionText = '#' . ($partition + 1);
			}
			$anzahl2 = 1;
			$tsCount = count($ts);

			for ($i = 0; $i < $tsCount; $i++) {
				if ($ts[$i]['Tsumego']['num'] > $anzahl2) {
					$anzahl2 = $ts[$i]['Tsumego']['num'];
				}
			}
			$queryTitle = $this->Session->read('lastSet') . ' ' . $partitionText . ' ' . $t['Tsumego']['num'] . '/' . $anzahl2;
		} elseif ($query == 'tags') {
			$t['Tsumego']['actualNum'] = $t['Tsumego']['num'];
			$setConditions = [];
			$rankConditions = [];
			$tagIds = [];
			$tagName = $this->TagName->findByName($this->Session->read('lastSet'));

			if (count($search1) > 0) {
				$search1idxx = [];
				$search1Count = count($search1);

				for ($i = 0; $i < $search1Count; $i++) {
					$search1id = $this->Set->find('first', ['conditions' => ['title' => $search1[$i]]]);
					if (!$search1id) {
						continue;
					}
					$search1idx = $this->findTsumegoSet($search1id['Set']['id']);
					$search1idxCount = count($search1idx);

					for ($j = 0; $j < $search1idxCount; $j++) {
						array_push($search1idxx, $search1idx[$j]['Tsumego']['id']);
					}
				}
				$setConditions['tsumego_id'] = $search1idxx;
			}
			$tagsx = $this->Tag->find('all', [
				'order' => 'id ASC',
				'conditions' => [
					'tag_name_id' => $tagName['TagName']['id'],
					'approved' => 1,
					$setConditions,
				],
			]);
			if (!$tagsx) {
				$tagsx = [];
			}

			$tagsxCount = count($tagsx);

			for ($i = 0; $i < $tagsxCount; $i++) {
				array_push($tagIds, $tagsx[$i]['Tag']['tsumego_id']);
			}
			if (!$hasPremium) {
				$currentIdsNew = [];
				$pTest = $this->Tsumego->find('all', ['conditions' => ['id' => $tagIds]]);
				if (!$pTest) {
					$pTest = [];
				}
				$pTestCount = count($pTest);

				for ($j = 0; $j < $pTestCount; $j++) {
					if (!in_array($pTest[$j]['Tsumego']['set_id'], $setsWithPremium)) {
						array_push($currentIdsNew, $pTest[$j]['Tsumego']['id']);
					}
				}
				$tagIds = $currentIdsNew;
			}

			if (count($search2) > 0) {
				$fromTo = [];
				$idsTemp = [];
				$search2Count = count($search2);

				for ($j = 0; $j < $search2Count; $j++) {
					$ft = [];
					$ft['elo_rating_mode >='] = $this->getTsumegoElo($search2[$j]);
					$ft['elo_rating_mode <'] = $ft['elo_rating_mode >='] + 100;
					if ($search2[$j] == '15k') {
						$ft['elo_rating_mode >='] = 50;
					}
					array_push($fromTo, $ft);
				}
				$rankConditions['OR'] = $fromTo;
			}
			$ts = $this->Tsumego->find('all', [
				'order' => 'id ASC',
				'conditions' => [
					'id' => $tagIds,
					'public' => 1,
					$rankConditions,
				],
			]);
			if (!$ts) {
				$ts = [];
			}
			$tsCount = count($ts);

			for ($i = 0; $i < $tsCount; $i++) {
				$ts[$i]['Tsumego']['num'] = $i + 1;
				if ($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']) {
					$t['Tsumego']['num'] = $ts[$i]['Tsumego']['num'];
				}
			}

			if (count($ts) > $collectionSize) {
				$tsCount = count($ts);

				for ($i = 0; $i < $tsCount; $i++) {
					if ($i % $collectionSize == 0) {
						$partition++;
					}
					if ($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']) {
						break;
					}
				}
				$fromTo = $this->getPartitionRange(count($ts), $collectionSize, $partition);
				$ts1 = [];
				for ($i = $fromTo[0]; $i <= $fromTo[1]; $i++) {
					array_push($ts1, $ts[$i]);
				}
				$ts = $ts1;
			}
			if ($partition == -1) {
				$partitionText = '';
			} else {
				$partitionText = '#' . ($partition + 1);
			}
			$anzahl2 = 1;
			$tsCount = count($ts);

			for ($i = 0; $i < $tsCount; $i++) {
				if ($ts[$i]['Tsumego']['num'] > $anzahl2) {
					$anzahl2 = $ts[$i]['Tsumego']['num'];
				}
			}
			$queryTitle = $this->Session->read('lastSet') . ' ' . $partitionText . ' ' . $t['Tsumego']['num'] . '/' . $anzahl2;
		} elseif ($query == 'topics') {
			$setConnectionIds = [];
			$tsTsumegosMap = [];
			$rankConditions = [];
			$ts = $this->SetConnection->find('all', ['order' => 'num ASC', 'conditions' => ['set_id' => $set['Set']['id']]]);
			if (!$ts) {
				$ts = [];
			}

			if (count($search2) > 0) {
				$fromTo = [];
				$search2Count = count($search2);

				for ($i = 0; $i < $search2Count; $i++) {
					$ft = [];
					$ft['elo_rating_mode >='] = $this->getTsumegoElo($search2[$i]);
					$ft['elo_rating_mode <'] = $ft['elo_rating_mode >='] + 100;
					if ($search2[$i] == '15k') {
						$ft['elo_rating_mode >='] = 50;
					}
					array_push($fromTo, $ft);
				}
				$rankConditions['OR'] = $fromTo;
			}
			$tsCount = count($ts);

			for ($i = 0; $i < $tsCount; $i++) {
				array_push($setConnectionIds, $ts[$i]['SetConnection']['tsumego_id']);
			}

			$tsTsumegos = $this->Tsumego->find('all', [
				'order' => 'num ASC',
				'conditions' => [
					'id' => $setConnectionIds,
					$rankConditions,
				],
			]);
			if (!$tsTsumegos) {
				$tsTsumegos = [];
			}
			$tsTsumegosCount = count($tsTsumegos);

			for ($i = 0; $i < $tsTsumegosCount; $i++) {
				$tsTsumegosMap[$tsTsumegos[$i]['Tsumego']['id']] = $tsTsumegos[$i];
			}

			$tsBuffer = [];
			$tsCount = count($ts);

			for ($i = 0; $i < $tsCount; $i++) {
				if (isset($tsTsumegosMap[$ts[$i]['SetConnection']['tsumego_id']])) {
					$tagValid = true;
				} else {
					$tagValid = false;
				}
				if ($tagValid == true) {
					if (count($search3) > 0) {
						$tagForTsumego = $this->Tag->find('first', [
							'conditions' => [
								'tsumego_id' => $ts[$i]['SetConnection']['tsumego_id'],
								'tag_name_id' => $search3ids,
							],
						]);
						if ($tagForTsumego != null) {
							$tagValid = true;
						} else {
							$tagValid = false;
						}
					}
				}
				if ($tagValid) {
					array_push($tsBuffer, $ts[$i]);
				}
			}
			$ts = $tsBuffer;
			if (count($ts) > $collectionSize) {
				$tsCount = count($ts);

				for ($i = 0; $i < $tsCount; $i++) {
					if ($i % $collectionSize == 0) {
						$partition++;
					}
					if ($ts[$i]['SetConnection']['tsumego_id'] == $t['Tsumego']['id']) {
						break;
					}
				}
				$fromTo = $this->getPartitionRange(count($ts), $collectionSize, $partition);
				$ts1 = [];
				for ($i = $fromTo[0]; $i <= $fromTo[1]; $i++) {
					array_push($ts1, $ts[$i]);
				}
				$ts = $ts1;
				$queryTitleSets = '#' . ($partition + 1);
			}
			$anzahl2 = 1;
			$tsCount = count($ts);

			for ($i = 0; $i < $tsCount; $i++) {
				if ($ts[$i]['SetConnection']['num'] > $anzahl2) {
					$anzahl2 = $ts[$i]['SetConnection']['num'];
				}
			}
		}
		$anzahl = count($ts);

		if ($query == 'topics') {
			$this->Session->write('title', $set['Set']['title'] . ' ' . $t['Tsumego']['num'] . '/' . $anzahl2 . ' on Tsumego Hero');
		} else {
			$this->Session->write('title', $this->Session->read('lastSet') . ' ' . $t['Tsumego']['num'] . '/' . $anzahl2 . ' on Tsumego Hero');
		}
		$prev = 0;
		$next = 0;
		$tsBack = [];
		$tsNext = [];
		if (!$inFavorite) {
			if ($query == 'difficulty' || $query == 'tags') {
				$tsCount = count($ts);

				for ($i = 0; $i < $tsCount; $i++) {
					if ($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']) {
						$a = 5;
						while ($a > 0) {
							if ($i - $a >= 0) {
								$tsBackA = $ts[$i - $a];
								array_push($tsBack, $tsBackA);
								if ($a == 1) {
									$prev = $ts[$i - $a]['Tsumego']['id'];
								}
								$newUT = $this->findUt($ts[$i - $a]['Tsumego']['id'], $utsMap);
								if (!isset($newUT['TsumegoStatus']['status'])) {
									$newUT['TsumegoStatus']['status'] = 'N';
								}
								$tsBack[count($tsBack) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
							}
							$a--;
						}
						$bMax = 10 - count($tsBack);
						$b = 1;
						if ($ts[0]['Tsumego']['id'] == $t['Tsumego']['id']) {
							$bMax++;
						}
						while ($b <= $bMax) {
							if ($i + $b < count($ts)) {
								$tsNextA = $ts[$i + $b];
								array_push($tsNext, $tsNextA);
								if ($b == 1) {
									$next = $ts[$i + $b]['Tsumego']['id'];
								}
								$newUT = $this->findUt($ts[$i + $b]['Tsumego']['id'], $utsMap);
								if (!isset($newUT['TsumegoStatus']['status'])) {
									$newUT['TsumegoStatus']['status'] = 'N';
								}
								$tsNext[count($tsNext) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
							}
							$b++;
						}
						if (count($tsNext) < 5 || $t['Tsumego']['id'] == $ts[count($ts) - 6]['Tsumego']['id']) {
							$tsBack = [];
							$a = 5 + (5 - count($tsNext));
							$a++;
							while ($a > 0) {
								if ($i - $a >= 0) {
									$tsBackA = $ts[$i - $a];
									array_push($tsBack, $tsBackA);
									if ($a == 1) {
										$prev = $ts[$i - $a]['Tsumego']['id'];
									}
									$newUT = $this->findUt($ts[$i - $a]['Tsumego']['id'], $utsMap);
									if (!isset($newUT['TsumegoStatus']['status'])) {
										$newUT['TsumegoStatus']['status'] = 'N';
									}
									$tsBack[count($tsBack) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
								}
								$a--;
							}
						}
						if ((count($tsBack) < 5 || $t['Tsumego']['id'] == $ts[5]['Tsumego']['id']) && $ts[0]['Tsumego']['id'] != $t['Tsumego']['id']) {
							$tsNextAdjust = count($tsNext) + 1;
							$tsNext = [];
							$b = 1;
							while ($b <= $tsNextAdjust) {
								if ($i + $b < count($ts)) {
									$tsNextA = $ts[$i + $b];
									array_push($tsNext, $tsNextA);
									if ($b == 1) {
										$next = $ts[$i + $b]['Tsumego']['id'];
									}
									$newUT = $this->findUt($ts[$i + $b]['Tsumego']['id'], $utsMap);
									if (!isset($newUT['TsumegoStatus']['status'])) {
										$newUT['TsumegoStatus']['status'] = 'N';
									}
									$tsNext[count($tsNext) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
								}
								$b++;
							}
						}
					}
				}
			} elseif ($query == 'topics') {
				//topics
				$tsCount = count($ts);

				for ($i = 0; $i < $tsCount; $i++) {
					if ($ts[$i]['SetConnection']['tsumego_id'] == $t['Tsumego']['id']) {
						$a = 5;
						while ($a > 0) {
							if ($i - $a >= 0) {
								$tsBackA = $tsTsumegosMap[$ts[$i - $a]['SetConnection']['tsumego_id']];
								$scTsBack = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $ts[$i - $a]['SetConnection']['tsumego_id']]]);
								if (!$scTsBack) {
									$scTsBack = [];
								}
								if (count($scTsBack) <= 1) {
									$tsBackA['Tsumego']['duplicateLink'] = '';
								} else {
									$tsBackA['Tsumego']['duplicateLink'] = '?sid=' . $ts[$i - $a]['SetConnection']['set_id'];
								}
								$tsBackA['Tsumego']['num'] = $ts[$i - $a]['SetConnection']['num'];
								array_push($tsBack, $tsBackA);
								if ($a == 1) {
									$prev = $ts[$i - $a]['SetConnection']['tsumego_id'];
								}
								$newUT = $this->findUt($ts[$i - $a]['SetConnection']['tsumego_id'], $utsMap);
								if (!isset($newUT['TsumegoStatus']['status'])) {
									$newUT['TsumegoStatus']['status'] = 'N';
								}
								$tsBack[count($tsBack) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
							}
							$a--;
						}
						$bMax = 10 - count($tsBack);
						$b = 1;
						if ($ts[0]['SetConnection']['tsumego_id'] == $t['Tsumego']['id']) {
							$bMax++;
						}
						while ($b <= $bMax) {
							if ($i + $b < count($ts)) {
								$tsNextA = $tsTsumegosMap[$ts[$i + $b]['SetConnection']['tsumego_id']];
								$scTsNext = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $ts[$i + $b]['SetConnection']['tsumego_id']]]);
								if (!$scTsNext) {
									$scTsNext = [];
								}
								if (count($scTsNext) <= 1) {
									$tsNextA['Tsumego']['duplicateLink'] = '';
								} else {
									$tsNextA['Tsumego']['duplicateLink'] = '?sid=' . $ts[$i + $b]['SetConnection']['set_id'];
								}
								$tsNextA['Tsumego']['num'] = $ts[$i + $b]['SetConnection']['num'];
								array_push($tsNext, $tsNextA);
								if ($b == 1) {
									$next = $ts[$i + $b]['SetConnection']['tsumego_id'];
								}
								$newUT = $this->findUt($ts[$i + $b]['SetConnection']['tsumego_id'], $utsMap);
								if (!isset($newUT['TsumegoStatus']['status'])) {
									$newUT['TsumegoStatus']['status'] = 'N';
								}
								$tsNext[count($tsNext) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
							}
							$b++;
						}
						if (count($tsNext) < 5 || $t['Tsumego']['id'] == $ts[count($ts) - 6]['SetConnection']['tsumego_id']) {
							$tsBack = [];
							$a = 5 + (5 - count($tsNext));
							$a++;
							while ($a > 0) {
								if ($i - $a >= 0) {
									$tsBackA = $tsTsumegosMap[$ts[$i - $a]['SetConnection']['tsumego_id']];
									$scTsBack = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $ts[$i - $a]['SetConnection']['tsumego_id']]]);
									if (!$scTsBack) {
										$scTsBack = [];
									}
									if (count($scTsBack) <= 1) {
										$tsBackA['Tsumego']['duplicateLink'] = '';
									} else {
										$tsBackA['Tsumego']['duplicateLink'] = '?sid=' . $ts[$i - $a]['SetConnection']['set_id'];
									}
									$tsBackA['Tsumego']['num'] = $ts[$i - $a]['SetConnection']['num'];
									array_push($tsBack, $tsBackA);
									if ($a == 1) {
										$prev = $ts[$i - $a]['SetConnection']['tsumego_id'];
									}
									$newUT = $this->findUt($ts[$i - $a]['SetConnection']['tsumego_id'], $utsMap);
									if (!isset($newUT['TsumegoStatus']['status'])) {
										$newUT['TsumegoStatus']['status'] = 'N';
									}
									$tsBack[count($tsBack) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
								}
								$a--;
							}
						}
						if ((count($tsBack) < 5 || $t['Tsumego']['id'] == $ts[5]['SetConnection']['tsumego_id']) && $ts[0]['SetConnection']['tsumego_id'] != $t['Tsumego']['id']) {
							$tsNextAdjust = count($tsNext) + 1;
							$tsNext = [];
							$b = 1;
							while ($b <= $tsNextAdjust) {
								if ($i + $b < count($ts)) {
									$tsNextA = $tsTsumegosMap[$ts[$i + $b]['SetConnection']['tsumego_id']];
									$scTsNext = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $ts[$i + $b]['SetConnection']['tsumego_id']]]);
									if (!$scTsNext) {
										$scTsNext = [];
									}
									if (count($scTsNext) <= 1) {
										$tsNextA['Tsumego']['duplicateLink'] = '';
									} else {
										$tsNextA['Tsumego']['duplicateLink'] = '?sid=' . $ts[$i + $b]['SetConnection']['set_id'];
									}
									$tsNextA['Tsumego']['num'] = $ts[$i + $b]['SetConnection']['num'];
									array_push($tsNext, $tsNextA);
									if ($b == 1) {
										$next = $ts[$i + $b]['SetConnection']['tsumego_id'];
									}
									$newUT = $this->findUt($ts[$i + $b]['SetConnection']['tsumego_id'], $utsMap);
									if (!isset($newUT['TsumegoStatus']['status'])) {
										$newUT['TsumegoStatus']['status'] = 'N';
									}
									$tsNext[count($tsNext) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
								}
								$b++;
							}
						}
					}
				}
			}
			$inFavorite = '';
		} else {
			//fav
			$fav = $this->Favorite->find('all', ['order' => 'created', 'direction' => 'DESC', 'conditions' => ['user_id' => $this->loggedInUserID()]]);
			if (!$fav) {
				$fav = [];
			}
			$ts = [];
			$favCount = count($fav);

			for ($i = 0; $i < $favCount; $i++) {
				$tx = $this->Tsumego->findById($fav[$i]['Favorite']['tsumego_id']);
				array_push($ts, $tx);
			}
			$tsCount = count($ts);

			for ($i = 0; $i < $tsCount; $i++) {
				if ($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']) {
					$a = 5;
					while ($a > 0) {
						if ($i - $a >= 0) {
							array_push($tsBack, $ts[$i - $a]);
							if ($a == 1) {
								$prev = $ts[$i - $a]['Tsumego']['id'];
							}
							$newUT = $this->findUt($ts[$i - $a]['Tsumego']['id'], $utsMap);
							if (!isset($newUT['TsumegoStatus']['status'])) {
								$newUT['TsumegoStatus']['status'] = 'N';
							}
							$tsBack[count($tsBack) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
						}
						$a--;
					}
					$bMax = 10 - count($tsBack);
					$b = 1;
					if ($ts[0]['Tsumego']['id'] == $t['Tsumego']['id']) {
						$bMax++;
					}
					while ($b <= $bMax) {
						if ($i + $b < count($ts)) {
							array_push($tsNext, $ts[$i + $b]);
							if ($b == 1) {
								$next = $ts[$i + $b]['Tsumego']['id'];
							}
							$newUT = $this->findUt($ts[$i + $b]['Tsumego']['id'], $utsMap);
							if (!isset($newUT['TsumegoStatus']['status'])) {
								$newUT['TsumegoStatus']['status'] = 'N';
							}
							$tsNext[count($tsNext) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
						}
						$b++;
					}
					if (count($tsNext) < 5 || $t['Tsumego']['id'] == $ts[count($ts) - 6]['Tsumego']['id']) {
						$tsBack = [];
						$a = 5 + (5 - count($tsNext));
						$a++;
						while ($a > 0) {
							if ($i - $a >= 0) {
								array_push($tsBack, $ts[$i - $a]);
								if ($a == 1) {
									$prev = $ts[$i - $a]['Tsumego']['id'];
								}
								$newUT = $this->findUt($ts[$i - $a]['Tsumego']['id'], $utsMap);
								if (!isset($newUT['TsumegoStatus']['status'])) {
									$newUT['TsumegoStatus']['status'] = 'N';
								}
								$tsBack[count($tsBack) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
							}
							$a--;
						}
					}
					if ((count($tsBack) < 5 || $t['Tsumego']['id'] == $ts[5]['Tsumego']['id']) && $ts[0]['Tsumego']['id'] != $t['Tsumego']['id']) {
						$tsNextAdjust = count($tsNext) + 1;
						$tsNext = [];
						$b = 1;
						while ($b <= $tsNextAdjust) {
							if ($i + $b < count($ts)) {
								array_push($tsNext, $ts[$i + $b]);
								if ($b == 1) {
									$next = $ts[$i + $b]['Tsumego']['id'];
								}
								$newUT = $this->findUt($ts[$i + $b]['Tsumego']['id'], $utsMap);
								if (!isset($newUT['TsumegoStatus']['status'])) {
									$newUT['TsumegoStatus']['status'] = 'N';
								}
								$tsNext[count($tsNext) - 1]['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
							}
							$b++;
						}
					}
				}
			}
			$inFavorite = '?favorite=1';
		}
		if ($query == 'difficulty' || $query == 'tags') {
			//tsFirst
			$tsFirst = $ts[0];
			$isInArray = -1;
			$tsBackCount = count($tsBack);

			for ($i = 0; $i < $tsBackCount; $i++) {
				if ($tsBack[$i]['Tsumego']['id'] == $tsFirst['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($tsBack[$isInArray]);
				$tsBack = array_values($tsBack);
			}
			$newUT = $this->findUt($ts[0]['Tsumego']['id'], $utsMap);
			if (!isset($newUT['TsumegoStatus']['status'])) {
				$newUT['TsumegoStatus']['status'] = 'N';
			}
			$tsFirst['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
			if ($t['Tsumego']['id'] == $tsFirst['Tsumego']['id']) {
				$tsFirst = null;
			}
			//tsLast
			$tsLast = $ts[count($ts) - 1];
			$isInArray = -1;
			$tsNextCount = count($tsNext);

			for ($i = 0; $i < $tsNextCount; $i++) {
				if ($tsNext[$i]['Tsumego']['id'] == $tsLast['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($tsNext[$isInArray]);
				$tsNext = array_values($tsNext);
			}
			$newUT = $this->findUt($ts[count($ts) - 1]['Tsumego']['id'], $utsMap);
		} elseif ($query == 'topics' && !$inFavorite) {
			//tsFirst
			$tsFirst = $this->Tsumego->findById($ts[0]['SetConnection']['tsumego_id']);
			$scTsFirst = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $ts[0]['SetConnection']['tsumego_id']]]);
			if (!$scTsFirst) {
				$scTsFirst = [];
			}
			if (count($scTsFirst) <= 1) {
				$tsFirst['Tsumego']['duplicateLink'] = '';
			} else {
				$tsFirst['Tsumego']['duplicateLink'] = '?sid=' . $ts[0]['SetConnection']['set_id'];
			}
			$tsFirst['Tsumego']['num'] = $ts[0]['SetConnection']['num'];
			$isInArray = -1;
			$tsBackCount = count($tsBack);

			for ($i = 0; $i < $tsBackCount; $i++) {
				if ($tsBack[$i]['Tsumego']['id'] == $tsFirst['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($tsBack[$isInArray]);
				$tsBack = array_values($tsBack);
			}
			$newUT = $this->findUt($ts[0]['SetConnection']['tsumego_id'], $utsMap);
			if (!isset($newUT['TsumegoStatus']['status'])) {
				$newUT['TsumegoStatus']['status'] = 'N';
			}
			$tsFirst['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
			if ($t['Tsumego']['id'] == $tsFirst['Tsumego']['id']) {
				$tsFirst = null;
			}
			//tsLast
			$tsLast = $this->Tsumego->findById($ts[count($ts) - 1]['SetConnection']['tsumego_id']);
			$scTsLast = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $ts[count($ts) - 1]['SetConnection']['tsumego_id']]]);
			if (!$scTsLast) {
				$scTsLast = [];
			}
			if (count($scTsLast) <= 1) {
				$tsLast['Tsumego']['duplicateLink'] = '';
			} else {
				$tsLast['Tsumego']['duplicateLink'] = '?sid=' . $ts[count($ts) - 1]['SetConnection']['set_id'];
			}
			$tsLast['Tsumego']['num'] = $ts[count($ts) - 1]['SetConnection']['num'];
			$isInArray = -1;
			$tsNextCount = count($tsNext);

			for ($i = 0; $i < $tsNextCount; $i++) {
				if ($tsNext[$i]['Tsumego']['id'] == $tsLast['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($tsNext[$isInArray]);
				$tsNext = array_values($tsNext);
			}
			$newUT = $this->findUt($ts[count($ts) - 1]['SetConnection']['tsumego_id'], $utsMap);
		} elseif ($inFavorite) {
			//tsFirst
			$tsFirst = $this->Tsumego->findById($fav[0]['Favorite']['tsumego_id']);
			$tsFirst['Tsumego']['duplicateLink'] = '';
			$isInArray = -1;
			$tsBackCount = count($tsBack);

			for ($i = 0; $i < $tsBackCount; $i++) {
				if ($tsBack[$i]['Tsumego']['id'] == $tsFirst['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($tsBack[$isInArray]);
				$tsBack = array_values($tsBack);
			}
			if ($t['Tsumego']['id'] == $tsFirst['Tsumego']['id']) {
				$lastInFav = -1;
			}
			$newUT = $this->findUt($fav[0]['Favorite']['tsumego_id'], $utsMap);
			if (!isset($newUT['TsumegoStatus']['status'])) {
				$newUT['TsumegoStatus']['status'] = 'N';
			}
			$tsFirst['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
			if ($t['Tsumego']['id'] == $tsFirst['Tsumego']['id']) {
				$tsFirst = null;
			}

			//tsLast
			$tsLast = $this->Tsumego->findById($fav[count($fav) - 1]['Favorite']['tsumego_id']);
			$tsLast['Tsumego']['duplicateLink'] = '';
			$isInArray = -1;
			$tsNextCount = count($tsNext);

			for ($i = 0; $i < $tsNextCount; $i++) {
				if ($tsNext[$i]['Tsumego']['id'] == $tsLast['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($tsNext[$isInArray]);
				$tsNext = array_values($tsNext);
			}
			if ($t['Tsumego']['id'] == $tsLast['Tsumego']['id']) {
				$lastInFav = 1;
			}
			$newUT = $this->findUt($fav[count($fav) - 1]['Favorite']['tsumego_id'], $utsMap);
		}

		if (!isset($newUT['TsumegoStatus']['status'])) {
			$newUT['TsumegoStatus']['status'] = 'N';
		}
		$tsLast['Tsumego']['status'] = 'set' . $newUT['TsumegoStatus']['status'] . '1';
		if ($t['Tsumego']['id'] == $tsLast['Tsumego']['id']) {
			$tsLast = null;
		}
		if ($this->isLoggedIn()) {
			if (!isset($ut['TsumegoStatus']['status'])) {
				$t['Tsumego']['status'] = 'V';
			}
			$t['Tsumego']['status'] = 'set' . $ut['TsumegoStatus']['status'] . '2';
			$half = '';
			if ($ut['TsumegoStatus']['status'] == 'W' || $ut['TsumegoStatus']['status'] == 'X') {
				$t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty'] / 2);
				$half = '(1/2)';
			}
		} else {
			$t['Tsumego']['status'] = 'setV2';
		}

		if (!isset($t['Tsumego']['file']) || $t['Tsumego']['file'] == '') {
			$t['Tsumego']['file'] = $t['Tsumego']['num'];
		}
		$file = 'placeholder2.sgf';
		if ($t['Tsumego']['variance'] == 100) {
			$file = 'placeholder2.sgf';
		}
		$orientation = null;
		$colorOrientation = null;
		if (isset($this->params['url']['orientation'])) {
			$orientation = $this->params['url']['orientation'];
		}
		if (isset($this->params['url']['playercolor'])) {
			$colorOrientation = $this->params['url']['playercolor'];
		}

		$checkBSize = 19;
		for ($i = 2; $i <= 19; $i++) {
			if (strpos(';' . $set['Set']['title'], $i . 'x' . $i)) {
				$checkBSize = $i;
			}
		}
		if (!$this->Session->check('loggedInUser')) {
			$this->user['User'] = $noUser;
		}

		$navi = [];
		array_push($navi, $tsFirst);
		$tsBackCount = count($tsBack);

		for ($i = 0; $i < $tsBackCount; $i++) {
			array_push($navi, $tsBack[$i]);
		}
		array_push($navi, $t);
		$tsNextCount = count($tsNext);

		for ($i = 0; $i < $tsNextCount; $i++) {
			array_push($navi, $tsNext[$i]);
		}
		array_push($navi, $tsLast);

		$tooltipSgfs = [];
		$tooltipInfo = [];
		$tooltipBoardSize = [];
		$naviCount = count($navi);

		for ($i = 0; $i < $naviCount; $i++) {
			$tts = $this->Sgf->find('all', ['limit' => 1, 'order' => 'version DESC', 'conditions' => ['tsumego_id' => $navi[$i]['Tsumego']['id']]]);
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			array_push($tooltipSgfs, $tArr[0]);
			array_push($tooltipInfo, $tArr[2]);
			array_push($tooltipBoardSize, $tArr[3]);
		}

		if ($t['Tsumego']['set_id'] == 161) {
			$joseki = $this->Joseki->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			if ($joseki) {
				$josekiLevel = $joseki['Joseki']['hints'];
			} else {
				$josekiLevel = 0;
			}
			$naviCount = count($navi);

			for ($i = 0; $i < $naviCount; $i++) {
				$j = $this->Joseki->find('first', ['conditions' => ['tsumego_id' => $navi[$i]['Tsumego']['id']]]);
				if ($j) {
					$navi[$i]['Tsumego']['type'] = $j['Joseki']['type'];
					$navi[$i]['Tsumego']['thumbnail'] = $j['Joseki']['thumbnail'];
				}

			}
		}
		if ($this->Session->check('noLogin')) {
			$naviCount = count($navi);

			for ($i = 0; $i < $naviCount; $i++) {
				$noLoginCount = count($noLogin);

				for ($j = 0; $j < $noLoginCount; $j++) {
					if ($navi[$i]['Tsumego']['id'] == $noLogin[$j]) {
						$navi[$i]['Tsumego']['status'] = 'set' . ' ' . substr($navi[$i]['Tsumego']['status'], -1);
					}
				}
			}
			$noLoginCount = count($noLogin);
		}
		if ($this->user['User']['health'] >= 8) {
			$fullHeart = 'heart1small';
			$emptyHeart = 'heart2small';
		} else {
			$fullHeart = 'heart1';
			$emptyHeart = 'heart2';
		}
		if ($this->isLoggedIn()) {
			$this->set('sprintEnabled', $this->user['User']['sprint']);
			$this->set('intuitionEnabled', $this->user['User']['intuition']);
			$this->set('rejuvenationEnabled', $this->user['User']['rejuvenation']);
			$this->set('refinementEnabled', $this->user['User']['refinement']);
			$this->set('maxNoUserLevel', false);
			if ($this->user['User']['reuse4'] == 0) {
				$this->Session->write('loggedInUser.User.reuse4', 0);
			}
			if ($this->hasPremium()) {
				$this->Session->write('loggedInUser.User.reuse4', 0);
			}
			if ($this->Session->read('loggedInUser.User.reuse4') == 1) {
				$dailyMaximum = true;
			}
			if ($this->Session->read('loggedInUser.User.reuse5') == 1) {
				$suspiciousBehavior = true;
			}
		} else {
			if ($noUser['level'] >= 10) {
				$this->set('maxNoUserLevel', true);
			} else {
				$this->set('maxNoUserLevel', false);
			}
		}
		if ($isSandbox || $t['Tsumego']['set_id'] == 51) {
			$this->set('sandboxXP', $t['Tsumego']['difficulty']);
			$t['Tsumego']['difficulty2'] = $t['Tsumego']['difficulty'];
			$t['Tsumego']['difficulty'] = 10;
		}
		if ($goldenTsumego) {
			$t['Tsumego']['difficulty'] *= 8;
		}
		$refinementT = $this->Tsumego->find('all', [
			'limit' => 5000,
			'conditions' => [
				'difficulty >' => 35,
			],
		]);

		$hasAnyFavorite = $this->Favorite->find('first', ['conditions' => ['user_id' => $this->user['User']['id']]]);
		$hash = $this->encrypt($t['Tsumego']['num'] . 'number' . $set['Set']['id']);

		if ($pdCounter == 1) {
			$t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty'] * .5);
		} elseif ($pdCounter == 2) {
			$t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty'] * .2);
		} elseif ($pdCounter == 3) {
			$t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty'] * .1);
		} elseif ($pdCounter > 3) {
			$t['Tsumego']['difficulty'] = 1;
		}

		if ($pdCounter > 0) {
			$sandboxComment2 = true;
		} else {
			$sandboxComment2 = false;
		}

		$score1 = $t['Tsumego']['num'] . '-' . $t['Tsumego']['difficulty'] . '-' . $t['Tsumego']['set_id'];
		$score1 = $this->encrypt($score1);
		$t2 = $t['Tsumego']['difficulty'] * 2;
		$score2 = $t['Tsumego']['num'] . '-' . $t2 . '-' . $t['Tsumego']['set_id'];
		$score2 = $this->encrypt($score2);

		$score3 = $t['Tsumego']['num'] . '-' . $eloScore . '-' . $t['Tsumego']['set_id'];
		$score3 = $this->encrypt($score3);

		shuffle($refinementT);

		$refinementPublic = false;
		$refinementPublicCounter = 0;

		while (!$refinementPublic) {
			$scRefinement = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $refinementT[$refinementPublicCounter]['Tsumego']['id']]]);
			$setScRefinement = $this->Set->findById($scRefinement['SetConnection']['set_id']);
			if ($setScRefinement['Set']['public'] == 1 && $setScRefinement['Set']['premium'] != 1) {
				$refinementPublic = true;
			} else {
				$refinementPublicCounter++;
			}
		}
		$activate = true;
		if ($this->isLoggedIn()) {
			if ($this->hasPremium() || $this->Session->read('loggedInUser.User.level') >= 50) {
				if ($this->user['User']['potion'] != -69) {
					if ($this->user['User']['health'] - $this->user['User']['damage'] <= 0) {
						$potionActive = true;
					}
				}
			}
			$achievementUpdate1 = $this->checkLevelAchievements();
			$achievementUpdate2 = $this->checkProblemNumberAchievements();
			$achievementUpdate3 = $this->checkNoErrorAchievements();
			$achievementUpdate4 = $this->checkRatingAchievements();
			$achievementUpdate5 = $this->checkDanSolveAchievements();
			$achievementUpdate = array_merge(
				$achievementUpdate1 ?: [],
				$achievementUpdate2 ?: [],
				$achievementUpdate3 ?: [],
				$achievementUpdate4 ?: [],
				$achievementUpdate5 ?: [],
			);
			if (count($achievementUpdate) > 0) {
				$this->updateXP($this->loggedInUserID(), $achievementUpdate);
			}
		}

		$admins = $this->User->find('all', ['conditions' => ['isAdmin' => 1]]);
		if ($mode == 2 || $mode == 3) {
			$this->Session->write('title', 'Tsumego Hero');
		}
		if ($isSandbox) {
			$t['Tsumego']['userWin'] = 0;
		}

		$crs = 0;
		if ($mode == 3) {
			$t['Tsumego']['status'] = 'setV2';
			$ranksCount = count($ranks);

			for ($i = 0; $i < $ranksCount; $i++) {
				if ($ranks[$i]['Rank']['result'] == 'solved') {
					$crs++;
				}
			}
		}
		if (isset($this->params['url']['rank'])) {
			$raName = $this->params['url']['rank'];
		} else {
			if (!isset($ranks[0]['Rank']['rank'])) {
				$ranks[0]['Rank']['rank'] = '';
			}
			$raName = $ranks[0]['Rank']['rank'];
		}

		if ($mode == 1) {
			$this->Session->write('page', 'level mode');
		} elseif ($mode == 2) {
			$this->Session->write('page', 'rating mode');
		} elseif ($mode == 3) {
			$this->Session->write('page', 'time mode');
		}

		if ($requestProblem != '') {
			$requestProblem = '?v=' . strlen($requestProblem);
		} else {
			$sgfx = file_get_contents($file);
			$requestProblem = '?v=' . strlen($sgfx);
		}

		$ui = 2;
		$file = 'placeholder2.sgf';

		if ($mode == 1) {
			$scPrev = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $prev]]);
			if (!$scPrev) {
				$scPrev = [];
			}
			$scPrevCount = count($scPrev);

			for ($i = 0; $i < $scPrevCount; $i++) {
				if (count($scPrev) > 1 && $scPrev[$i]['SetConnection']['set_id'] == $t['Tsumego']['set_id']) {
					$prev .= '?sid=' . $t['Tsumego']['set_id'];
				}
			}
			$scNext = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $next]]);
			if (!$scNext) {
				$scNext = [];
			}
			$scNextCount = count($scNext);

			for ($i = 0; $i < $scNextCount; $i++) {
				if (count($scNext) > 1 && $scNext[$i]['SetConnection']['set_id'] == $t['Tsumego']['set_id']) {
					$next .= '?sid=' . $t['Tsumego']['set_id'];
				}
			}
		} elseif ($mode == 2) {
			$next = $nextMode['Tsumego']['id'] ?? 0;
		} elseif ($mode == 3) {
			$next = $currentRank2['Tsumego']['id'] ?? 0;
		}
		$this->startPageUpdate();
		$startingPlayer = $this->getStartingPlayer($sgf2);

		if ($avActive == true) {
			$avActiveText = '';
		} else {
			$avActiveText = '<font style="color:gray;"> (recently played)</font>';
		}
		if ($avActive2 == false) {
			$avActiveText = '<font style="color:gray;"> (out of range)</font>';
		}

		$eloScoreRounded = round($eloScore);
		$eloScore2Rounded = round($eloScore2);

		$existingSignatures = $this->Signature->find('all', ['conditions' => ['tsumego_id' => $id]]);
		if ($existingSignatures == null || $existingSignatures[0]['Signature']['created'] < date('Y-m-d', strtotime('-1 week'))) {
			$requestSignature = 'true';
		} else {
			$requestSignature = 'false';
		}
		if (isset($_COOKIE['signatures']) && $set['Set']['public'] == 1) {
			$signature = explode('/', $_COOKIE['signatures']);
			$oldSignatures = $this->Signature->find('all', ['conditions' => ['tsumego_id' => $signature[count($signature) - 1]]]);
			if (!$oldSignatures) {
				$oldSignatures = [];
			}

			$oldSignaturesCount = count($oldSignatures);

			for ($i = 0; $i < $oldSignaturesCount; $i++) {
				$this->Signature->delete($oldSignatures[$i]['Signature']['id']);
			}

			$signatureCountMinus1 = count($signature) - 1;
			for ($i = 0; $i < $signatureCountMinus1; $i++) {
				$this->Signature->create();
				$newSignature = [];
				$newSignature['Signature']['tsumego_id'] = $signature[count($signature) - 1];
				$newSignature['Signature']['signature'] = $signature[$i];
				$this->Signature->save($newSignature);
			}
			unset($_COOKIE['signatures']);
		}
		$idForSignature = -1;
		$idForSignature2 = -1;
		if (isset($this->params['url']['idForTheThing'])) {
			$idForSignature2 = $this->params['url']['idForTheThing'] + 1;
			$idForSignature = $this->getTheIdForTheThing($idForSignature2);
		}
		if (!isset($difficulty)) {
			$difficulty = 4;
		}

		$this->user['User']['name'] = $this->checkPicture($this->user);
		$tags = $this->getTags($id);
		$tags = $this->checkTagDuplicates($tags);

		$allTags = $this->getAllTags($tags);
		$popularTags = $this->getPopularTags($tags);
		$uc = $this->UserContribution->find('first', ['conditions' => ['user_id' => $this->loggedInUserID()]]);
		$hasRevelation = false;
		if ($uc) {
			$hasRevelation = $uc['UserContribution']['reward3'];
		}
		if ($this->hasPremium() && $this->Session->read('loggedInUser.User.level') >= 100) {
			$hasRevelation = true;
		}

		$sgfProposal = $this->Sgf->find('first', ['conditions' => ['tsumego_id' => $id, 'version' => 0, 'user_id' => $this->loggedInUserID()]]);
		$isAllowedToContribute = false;
		$isAllowedToContribute2 = false;
		if ($this->isLoggedIn()) {
			if ($this->Session->read('loggedInUser.User.level') >= 40) {
				$isAllowedToContribute = true;
			} elseif ($this->Session->read('loggedInUser.User.elo_rating_mode') >= 1500) {
				$isAllowedToContribute = true;
			}

			if ($this->Session->read('loggedInUser.User.isAdmin') > 0) {
				$isAllowedToContribute2 = true;
			} else {
				$tagsToCheck = $this->Tag->find('all', ['limit' => 20, 'order' => 'created DESC', 'conditions' => ['user_id' => $this->loggedInUserID()]]);
				if (!$tagsToCheck) {
					$tagsToCheck = [];
				}
				$datex = date('Y-m-d', strtotime('today'));
				$tagsToCheckCount = count($tagsToCheck);

				for ($i = 0; $i < $tagsToCheckCount; $i++) {
					$datexx = new DateTime($tagsToCheck[$i]['Tag']['created']);
					$datexx = $datexx->format('Y-m-d');
					if ($datex !== $datexx) {
						$isAllowedToContribute2 = true;
					}
				}
				if (count($tagsToCheck) < 20) {
					$isAllowedToContribute2 = true;
				}
			}
		}
		if (in_array($t['Tsumego']['set_id'], $setsWithPremium)) {
			$t['Tsumego']['premium'] = 1;
		} else {
			$t['Tsumego']['premium'] = 0;
		}

		$checkFav = $inFavorite;
		if ($inFavorite) {
			$query = 'topics';
		}

		if (count($navi) == 3 && !isset($navi[0]['Tsumego']['id']) && !isset($navi[count($navi) - 1]['Tsumego']['id'])) {
			$checkNotInSearch = true;
		} else {
			$checkNotInSearch = false;
		}

		$isTSUMEGOinFAVORITE = $this->Favorite->find('first', ['conditions' => ['user_id' => $this->user['User']['id'], 'tsumego_id' => $id]]);

		$this->set('isAllowedToContribute', $isAllowedToContribute);
		$this->set('isAllowedToContribute2', $isAllowedToContribute2);
		$this->set('hasSgfProposal', $sgfProposal != null);
		$this->set('hasRevelation', $hasRevelation);
		$this->set('allTags', $allTags);
		$this->set('tags', $tags);
		$this->set('popularTags', $popularTags);
		$this->set('requestSignature', $requestSignature);
		$this->set('idForSignature', $idForSignature);
		$this->set('idForSignature2', $idForSignature2);
		$this->set('score3', $score3);
		$this->set('activityValue', $activityValue);
		$this->set('avActiveText', $avActiveText);
		$this->set('nothingInRange', $nothingInRange);
		$this->set('tRank', $tRank);
		$this->set('sgf', $sgf);
		$this->set('sgf2', $sgf2);
		$this->set('sandboxComment2', $sandboxComment2);
		$this->set('raName', $raName);
		$this->set('crs', $crs);
		$this->set('admins', $admins);
		$this->set('refresh', $refresh);
		$this->set('anz', $anzahl2);
		$this->set('showComment', $co);
		$this->set('orientation', $orientation);
		$this->set('colorOrientation', $colorOrientation);
		$this->set('g', $refinementT[$refinementPublicCounter]);
		$this->set('favorite', $checkFav);
		$this->set('isTSUMEGOinFAVORITE', $isTSUMEGOinFAVORITE != null);
		$this->set('hasAnyFavorite', $hasAnyFavorite != null);
		$this->set('inFavorite', $inFavorite);
		$this->set('lastInFav', $lastInFav);
		$this->set('dailyMaximum', $dailyMaximum);
		$this->set('suspiciousBehavior', $suspiciousBehavior);
		$this->set('isSandbox', $isSandbox);
		$this->set('goldenTsumego', $goldenTsumego);
		$this->set('fullHeart', $fullHeart);
		$this->set('emptyHeart', $emptyHeart);
		$this->set('libertyCount', $t['Tsumego']['libertyCount']);
		$this->set('semeaiType', $t['Tsumego']['semeaiType']);
		$this->set('insideLiberties', $t['Tsumego']['insideLiberties']);
		$this->set('doublexp', $doublexp);
		$this->set('half', $half);
		$this->set('set', $set);
		if (isset($this->user['User']['nextlvl']) && $this->user['User']['nextlvl'] > 0) {
			$this->set('barPercent', $this->user['User']['xp'] / $this->user['User']['nextlvl'] * 100);
		} else {
			$this->set('barPercent', 0);
		}
		$this->set('user', $this->user);
		$this->set('t', $t);
		$this->set('score1', $score1);
		$this->set('score2', $score2);
		$this->set('navi', $navi);
		$this->set('prev', $prev);
		$this->set('next', $next);
		$this->set('hash', $hash);
		$this->set('nextMode', $nextMode);
		$this->set('mode', $mode);
		$this->set('rating', $this->user['User']['elo_rating_mode']);
		$this->set('eloScore', $eloScore);
		$this->set('eloScore2', $eloScore2);
		$this->set('eloScoreRounded', $eloScoreRounded);
		$this->set('eloScore2Rounded', $eloScore2Rounded);
		$this->set('activate', $activate);
		$this->set('tsumegoElo', $t['Tsumego']['elo_rating_mode']);
		$this->set('trs', $trs);
		$this->set('difficulty', $difficulty);
		$this->set('potion', $potion);
		$this->set('potionSuccess', $potionSuccess);
		$this->set('potionActive', $potionActive);
		$this->set('reviewCheat', $reviewCheat);
		$this->set('commentCoordinates', $commentCoordinates);
		$this->set('part', $t['Tsumego']['part']);
		$this->set('josekiLevel', $josekiLevel);
		$this->set('checkBSize', $checkBSize);
		$this->set('rankTs', $rankTs);
		$this->set('ranks', $ranks);
		$this->set('currentRank', $currentRank);
		$this->set('currentRankNum', $currentRankNum);
		$this->set('firstRanks', $firstRanks);
		$this->set('r10', $r10);
		$this->set('stopParameter', $stopParameter);
		$this->set('stopParameter2', $stopParameter2);
		$this->set('mode3ScoreArray', $mode3ScoreArray);
		$this->set('potionAlert', $potionAlert);
		$this->set('file', $file);
		$this->set('ui', $ui);
		$this->set('requestProblem', $requestProblem);
		$this->set('alternative_response', $t['Tsumego']['alternative_response']);
		$this->set('passEnabled', $t['Tsumego']['pass']);
		$this->set('virtual_children', $t['Tsumego']['virtual_children']);
		$this->set('set_duplicate', $t['Tsumego']['duplicate']);
		$this->set('achievementUpdate', $achievementUpdate);
		$this->set('duplicates', $duplicates);
		$this->set('tooltipSgfs', $tooltipSgfs);
		$this->set('tooltipInfo', $tooltipInfo);
		$this->set('tooltipBoardSize', $tooltipBoardSize);
		$this->set('requestSolution', $requestSolution);
		$this->set('startingPlayer', $startingPlayer);
		$this->set('tv', $tv);
		$this->set('query', $query);
		$this->set('queryTitle', $queryTitle);
		$this->set('queryTitleSets', $queryTitleSets);
		$this->set('search1', $search1);
		$this->set('search2', $search2);
		$this->set('search3', $search3);
		$this->set('amountOfOtherCollection', $amountOfOtherCollection);
		$this->set('partition', $partition);
		$this->set('checkNotInSearch', $checkNotInSearch);
		$this->set('hasPremium', $hasPremium);
	}

	private function getPopularTags($tags) {
		$json = json_decode(file_get_contents('json/popular_tags.json'));
		$a = [];
		$tn = $this->TagName->find('all');
		if (!$tn) {
			$tn = [];
		}
		$tnKeys = [];
		$tnCount = count($tn);

		for ($i = 0; $i < $tnCount; $i++) {
			$tnKeys[$tn[$i]['TagName']['id']] = $tn[$i]['TagName']['name'];
		}
		$jsonCount = count($json);

		for ($i = 0; $i < $jsonCount; $i++) {
			array_push($a, $tnKeys[$json[$i]->id]);
		}
		$aNew = [];
		$added = 0;
		$x = 0;
		while ($added < 10 && $x < count($a)) {
			$found = false;
			$tagsCount = count($tags);

			for ($i = 0; $i < $tagsCount; $i++) {
				if ($a[$x] == $tags[$i]['Tag']['name']) {
					$found = true;
				}
			}
			if (!$found) {
				array_push($aNew, $a[$x]);
				$added++;
			}
			$x++;
		}

		return $aNew;
	}

	private function getTags($tsumego_id) {
		$tags = $this->Tag->find('all', ['conditions' => ['tsumego_id' => $tsumego_id]]);
		if (!$tags) {
			$tags = [];
		}
		$tagsCount = count($tags);

		for ($i = 0; $i < $tagsCount; $i++) {
			$tn = $this->TagName->findById($tags[$i]['Tag']['tag_name_id']);
			$tags[$i]['Tag']['name'] = $tn['TagName']['name'];
			$tags[$i]['Tag']['hint'] = $tn['TagName']['hint'];
		}

		return $tags;
	}

	private function checkTagDuplicates($array) {
		$tagIds = [];
		$foundDuplicate = 0;
		$newArray = [];
		$arrayCount = count($array);

		for ($i = 0; $i < $arrayCount; $i++) {
			if (!$this->inArrayX($array[$i], $newArray)) {
				array_push($newArray, $array[$i]);
			}
		}

		return $newArray;
	}

	private function inArrayX($x, $newArray) {
		$newArrayCount = count($newArray);

		for ($i = 0; $i < $newArrayCount; $i++) {
			if ($x['Tag']['tag_name_id'] == $newArray[$i]['Tag']['tag_name_id'] && $x['Tag']['approved'] == 1) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string|int|null $id Tsumego ID
	 * @param string|int|null $sgf1 First SGF ID
	 * @param string|int|null $sgf2 Second SGF ID
	 * @return void
	 */
	public function open($id = null, $sgf1 = null, $sgf2 = null) {
		$this->loadModel('Sgf');
		$s2 = null;
		$t = $this->Tsumego->findById($id);
		$s1 = $this->Sgf->findById($sgf1);
		$s1['Sgf']['sgf'] = str_replace('ß', 'ss', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace(';', '@', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace("\r", '', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace("\n", '€', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace('+', '%2B', $s1['Sgf']['sgf']);
		if ($sgf2 != null) {
			$s2 = $this->Sgf->findById($sgf2);
			$s2['Sgf']['sgf'] = str_replace('ß', 'ss', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace(';', '@', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace("\r", '', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace("\n", '€', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace('+', '%2B', $s2['Sgf']['sgf']);
		}
		$this->set('t', $t);
		$this->set('s1', $s1);
		$this->set('s2', $s2);
	}

	/**
	 * @param string|int|null $id Tsumego ID
	 * @return void
	 */
	public function duplicatesearchx($id = null) {
		$this->loadModel('Sgf');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');

		$maxDifference = 1;
		$includeSandbox = 'false';
		$includeColorSwitch = 'false';
		$hideSandbox = false;

		if (isset($this->params['url']['diff'])) {
			$maxDifference = $this->params['url']['diff'];
			$includeSandbox = $this->params['url']['sandbox'];
			$includeColorSwitch = $this->params['url']['colorSwitch'];
			$loop = false;
		} else {
			$loop = true;
		}
		$similarId = [];
		$similarArr = [];
		$similarArrInfo = [];
		$similarArrBoardSize = [];
		$similarTitle = [];
		$similarDiff = [];
		$similarDiffType = [];
		$similarOrder = [];
		$t = $this->Tsumego->findById($id);
		$sc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
		if (!$sc) {
			throw new NotFoundException('Set connection not found');
		}
		$s = $this->Set->findById($sc['SetConnection']['set_id']);
		$title = $s['Set']['title'] . ' - ' . $t['Tsumego']['num'];
		$sgf = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $id]]);
		if (!$sgf) {
			throw new NotFoundException('SGF not found');
		}
		$tSgfArr = $this->processSGF($sgf['Sgf']['sgf']);
		$tNumStones = isset($tSgfArr[1]) ? count($tSgfArr[1]) : 0;

		$sets2 = [];
		$sets3 = [];
		$sets3content = [];
		$sets1 = $this->Set->find('all', [
			'conditions' => [
				'public' => '1',
				'NOT' => [
					'id' => [6473, 11969, 29156, 31813, 33007, 71790, 74761, 81578],
				],
			],
		]);

		if ($this->isLoggedIn()) {
			if (!$this->hasPremium()) {
				$includeSandbox = 'false';
				$hideSandbox = true;
			} else {
				array_push($sets3content, 6473);
				array_push($sets3content, 11969);
				array_push($sets3content, 29156);
				array_push($sets3content, 31813);
				array_push($sets3content, 33007);
				array_push($sets3content, 71790);
				array_push($sets3content, 74761);
				array_push($sets3content, 81578);
			}
			$sets3 = $this->Set->find('all', ['conditions' => ['id' => $sets3content]]);
		} else {
			$includeSandbox = 'false';
			$hideSandbox = true;
		}
		if ($includeSandbox == 'true') {
			$sets2 = $this->Set->find('all', ['conditions' => ['public' => '0']]);
		}
		$sets = array_merge($sets1, $sets2, $sets3);

		$setsCount = count($sets);

		for ($h = 0; $h < $setsCount; $h++) {
				$ts = $this->findTsumegoSet($sets[$h]['Set']['id']);
				$tsCount = count($ts);

			for ($i = 0; $i < $tsCount; $i++) {
				if ($ts[$i]['Tsumego']['id'] != $id) {
					$sgf = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
					$sgfArr = $this->processSGF($sgf['Sgf']['sgf']);
					$numStones = count($sgfArr[1]);
					$stoneNumberDiff = abs($numStones - $tNumStones);
					if ($stoneNumberDiff <= $maxDifference) {
						if ($includeColorSwitch == 'true') {
							$compare = $this->compare($tSgfArr[0], $sgfArr[0], true);
						} else {
							$compare = $this->compare($tSgfArr[0], $sgfArr[0], false);
						}
						if ($compare[0] <= $maxDifference) {
							array_push($similarId, $ts[$i]['Tsumego']['id']);
							array_push($similarArr, $sgfArr[0]);
							array_push($similarArrInfo, $sgfArr[2]);
							array_push($similarArrBoardSize, $sgfArr[3]);
							array_push($similarDiff, $compare[0]);
							if ($compare[1] == 0) {
								array_push($similarDiffType, '');
							} elseif ($compare[1] == 1) {
								array_push($similarDiffType, 'Shifted position.');
							} elseif ($compare[1] == 2) {
								array_push($similarDiffType, 'Shifted and rotated.');
							} elseif ($compare[1] == 3) {
								array_push($similarDiffType, 'Switched colors.');
							} elseif ($compare[1] == 4) {
								array_push($similarDiffType, 'Switched colors and shifted position.');
							} elseif ($compare[1] == 5) {
								array_push($similarDiffType, 'Switched colors, shifted and rotated.');
							}
							array_push($similarOrder, $compare[2]);
							$set = $this->Set->findById($ts[$i]['Tsumego']['set_id']);
							$title2 = $set['Set']['title'] . ' - ' . $ts[$i]['Tsumego']['num'];
							array_push($similarTitle, $title2);
						}
					}
				}
			}
		}

		array_multisort($similarOrder, $similarArr, $similarArrInfo, $similarTitle, $similarDiff, $similarDiffType, $similarId);

		$this->set('tSgfArr', $tSgfArr[0]);
		$this->set('tSgfArrInfo', $tSgfArr[2]);
		$this->set('tSgfArrBoardSize', $tSgfArr[3]);
		$this->set('similarId', $similarId);
		$this->set('similarArr', $similarArr);
		$this->set('similarArrInfo', $similarArrInfo);
		$this->set('similarArrBoardSize', $similarArrBoardSize);
		$this->set('similarTitle', $similarTitle);
		$this->set('similarDiff', $similarDiff);
		$this->set('similarDiffType', $similarDiffType);
		$this->set('title', $title);
		$this->set('t', $t);
		$this->set('maxDifference', $maxDifference);
		$this->set('includeSandbox', $includeSandbox);
		$this->set('includeColorSwitch', $includeColorSwitch);
		$this->set('hideSandbox', $hideSandbox);
	}

	/**
	 * @param string|int|null $id Tsumego ID
	 * @return void
	 */
	public function duplicatesearch($id = null) {
		$this->loadModel('Sgf');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		$this->loadModel('Signature');
		$this->Session->write('page', 'play');

		$similarId = [];
		$similarArr = [];
		$similarArrInfo = [];
		$similarArrBoardSize = [];
		$similarTitle = [];
		$similarDiff = [];
		$similarDiffType = [];
		$similarOrder = [];

		$t = $this->Tsumego->findById($id);
		$sc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
		if (!$sc) {
			throw new NotFoundException('Set connection not found');
		}
		$s = $this->Set->findById($sc['SetConnection']['set_id']);
		$title = $s['Set']['title'] . ' - ' . $t['Tsumego']['num'];
		$sgf = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $id]]);
		if (!$sgf) {
			throw new NotFoundException('SGF not found');
		}
		$tSgfArr = $this->processSGF($sgf['Sgf']['sgf']);
		$tNumStones = isset($tSgfArr[1]) ? count($tSgfArr[1]) : 0;

		$this->Session->write('title', $s['Set']['title'] . ' ' . $t['Tsumego']['num'] . ' on Tsumego Hero');

		$t = $this->Tsumego->findById($id);
		$sig = $this->Signature->find('all', ['conditions' => ['tsumego_id' => $id]]);
		if (!$sig) {
			$sig = [];
		}
		$ts = [];
		$sigCount = count($sig);

		for ($i = 0; $i < $sigCount; $i++) {
			$sig2 = $this->Signature->find('all', ['conditions' => ['signature' => $sig[$i]['Signature']['signature']]]);
			if (!$sig2) {
				$sig2 = [];
			}
			$sig2Count = count($sig2);

			for ($j = 0; $j < $sig2Count; $j++) {
				array_push($ts, $this->Tsumego->findById($sig2[$j]['Signature']['tsumego_id']));
			}
		}

		$tsCount = count($ts);

		for ($i = 0; $i < $tsCount; $i++) {
			if ($ts[$i]['Tsumego']['id'] != $id) {
				$sgf = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
				if (!$sgf) {
					continue;
				}
				$sgfArr = $this->processSGF($sgf['Sgf']['sgf']);
				$numStones = isset($sgfArr[1]) ? count($sgfArr[1]) : 0;
				$stoneNumberDiff = abs($numStones - $tNumStones);
				$compare = $this->compare($tSgfArr[0], $sgfArr[0], false);
				array_push($similarId, $ts[$i]['Tsumego']['id']);
				array_push($similarArr, $sgfArr[0]);
				array_push($similarArrInfo, $sgfArr[2]);
				array_push($similarArrBoardSize, $sgfArr[3]);
				array_push($similarDiff, $compare[0]);
				if ($compare[1] == 0) {
					array_push($similarDiffType, '');
				} elseif ($compare[1] == 1) {
					array_push($similarDiffType, 'Shifted position.');
				} elseif ($compare[1] == 2) {
					array_push($similarDiffType, 'Shifted and rotated.');
				} elseif ($compare[1] == 3) {
					array_push($similarDiffType, 'Switched colors.');
				} elseif ($compare[1] == 4) {
					array_push($similarDiffType, 'Switched colors and shifted position.');
				} elseif ($compare[1] == 5) {
					array_push($similarDiffType, 'Switched colors, shifted and rotated.');
				}
				array_push($similarOrder, $compare[2]);
				$scx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
				$set = $this->Set->findById($scx['SetConnection']['set_id']);
				$title2 = $set['Set']['title'] . ' - ' . $ts[$i]['Tsumego']['num'];
				array_push($similarTitle, $title2);
			}
		}

		if (count($similarOrder) > 30) {
			$orderCounter = 0;
			$orderThreshold = 5;
			while ($orderCounter < 30) {
				$orderThreshold++;
				$orderCounter = 0;
				$similarOrderCount = count($similarOrder);

				for ($i = 0; $i < $similarOrderCount; $i++) {
					if (substr($similarOrder[$i], 0, 2) < $orderThreshold) {
						$orderCounter++;
					}
				}
			}
			$similarOrder2 = [];
			$similarArr2 = [];
			$similarArrInfo2 = [];
			$similarTitle2 = [];
			$similarDiff2 = [];
			$similarDiffType2 = [];
			$similarId2 = [];
			$similarArrBoardSize2 = [];

			$similarOrderCount = count($similarOrder);

			for ($i = 0; $i < $similarOrderCount; $i++) {
				if (substr($similarOrder[$i], 0, 2) < $orderThreshold) {
					array_push($similarOrder2, $similarOrder[$i]);
					array_push($similarArr2, $similarArr[$i]);
					array_push($similarArrInfo2, $similarArrInfo[$i]);
					array_push($similarTitle2, $similarTitle[$i]);
					array_push($similarDiff2, $similarDiff[$i]);
					array_push($similarDiffType2, $similarDiffType[$i]);
					array_push($similarId2, $similarId[$i]);
					array_push($similarArrBoardSize2, $similarArrBoardSize[$i]);
				}
			}
			$similarOrder = $similarOrder2;
			$similarArr = $similarArr2;
			$similarArrInfo = $similarArrInfo2;
			$similarTitle = $similarTitle2;
			$similarDiff = $similarDiff2;
			$similarDiffType = $similarDiffType2;
			$similarId = $similarId2;
			$similarArrBoardSize = $similarArrBoardSize2;
		}
		array_multisort($similarOrder, $similarArr, $similarArrInfo, $similarTitle, $similarDiff, $similarDiffType, $similarId, $similarArrBoardSize);

		$this->set('tSgfArr', $tSgfArr[0]);
		$this->set('tSgfArrInfo', $tSgfArr[2]);
		$this->set('tSgfArrBoardSize', $tSgfArr[3]);
		$this->set('similarId', $similarId);
		$this->set('similarArr', $similarArr);
		$this->set('similarArrInfo', $similarArrInfo);
		$this->set('similarArrBoardSize', $similarArrBoardSize);
		$this->set('similarTitle', $similarTitle);
		$this->set('similarDiff', $similarDiff);
		$this->set('similarDiffType', $similarDiffType);
		$this->set('title', $title);
		$this->set('t', $t);
	}

	private function getTheIdForTheThing($num) {
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		$t = [];
		$s = $this->Set->find('all', ['order' => 'id ASC', 'conditions' => ['public' => 1]]);
		if (!$s) {
			$s = [];
		}
		$sCount = count($s);

		for ($i = 0; $i < $sCount; $i++) {
			$sc = $this->SetConnection->find('all', ['order' => 'tsumego_id ASC', 'conditions' => ['set_id' => $s[$i]['Set']['id']]]);
			if (!$sc) {
				$sc = [];
			}
			$scCount = count($sc);

			for ($j = 0; $j < $scCount; $j++) {
				array_push($t, $sc[$j]['SetConnection']['tsumego_id']);
			}
		}
		if ($num >= count($t)) {
			return -1;
		}

		return $t[$num];
	}

	private function checkEloAdjust($t) {
		$ta = $this->TsumegoAttempt->find('all', [
			'limit' => 10,
			'order' => 'created DESC',
			'conditions' => [
				'tsumego_id' => $t['Tsumego']['id'],
				'NOT' => [
					'tsumego_elo' => 0,
				],
			],
		]);
		if (!$ta) {
			$ta = [];
		}
		$taPre = 3000;
		$jumpBack = $ta[0]['TsumegoAttempt']['tsumego_elo'];
		$jumpBackAmount = 0;
		$jumpI = 99;
		$taCount = count($ta);

		for ($i = 0; $i < $taCount; $i++) {
			$taPreSum = $ta[$i]['TsumegoAttempt']['tsumego_elo'] - $taPre;
			$taPre = $ta[$i]['TsumegoAttempt']['tsumego_elo'];
			if ($taPreSum > 500) {
				$jumpBack = $taPre;
				$jumpBackAmount = $taPreSum;
				$jumpI = $i;
			}
		}

		if ($jumpBackAmount != 0) {
			$taCount = count($ta);

			for ($i = 0; $i < $taCount; $i++) {
				if ($i < $jumpI) {
					$this->TsumegoAttempt->delete($ta[$i]['TsumegoAttempt']['id']);
				}
				if ($i == $jumpI) {
					$t['Tsumego']['elo_rating_mode'] = $ta[$i]['TsumegoAttempt']['tsumego_elo'];
				}
			}
		}

		return $t;
	}

	private function checkCommentValid($uid) {
		$comments = $this->Comment->find('all', ['limit' => 5, 'order' => 'created DESC', 'conditions' => ['user_id' => $uid]]);
		if (!$comments) {
			$comments = [];
		}
		$limitReachedCounter = 0;
		$commentsCount = count($comments);

		for ($i = 0; $i < $commentsCount; $i++) {
			$d = new DateTime($comments[$i]['Comment']['created']);
			$d = $d->format('Y-m-d');
			if ($d == date('Y-m-d')) {
				$limitReachedCounter++;
			}
		}
		if ($limitReachedCounter >= 50) {
			return false;
		}

		return true;
	}

	private function getStartingPlayer($sgf) {
		$bStart = strpos($sgf, ';B');
		$wStart = strpos($sgf, ';W');
		if ($wStart == 0) {
			return 0;
		}
		if ($bStart == 0) {
			return 1;
		}
		if ($bStart <= $wStart) {
			return 0;
		}

		return 1;
	}

	private function getLowest($a) {
		$lowestX = 19;
		$lowestY = 19;
		$aCount = count($a);

		for ($y = 0; $y < $aCount; $y++) {
			$aYCount = count($a[$y]);
			for ($x = 0; $x < $aYCount; $x++) {
				if ($a[$x][$y] == 'x' || $a[$x][$y] == 'o') {
					if ($x < $lowestX) {
						$lowestX = $x;
					}
					if ($y < $lowestY) {
						$lowestY = $y;
					}
				}
			}
		}
		$arr = [];
		array_push($arr, $lowestX);
		array_push($arr, $lowestY);

		return $arr;
	}
	private function shiftToCorner($a, $lowestX, $lowestY) {
		if ($lowestX != 0) {
			$aCount = count($a);

			for ($y = 0; $y < $aCount; $y++) {
				$aYCount = count($a[$y]);

				for ($x = 0; $x < $aYCount; $x++) {
					if ($a[$x][$y] == 'x' || $a[$x][$y] == 'o') {
						$c = $a[$x][$y];
						$a[$x - $lowestX][$y] = $c;
						$a[$x][$y] = '-';
					}
				}
			}
		}
		if ($lowestY != 0) {
			$aCount = count($a);

			for ($y = 0; $y < $aCount; $y++) {
				$aYCount = count($a[$y]);

				for ($x = 0; $x < $aYCount; $x++) {
					if ($a[$x][$y] == 'x' || $a[$x][$y] == 'o') {
						$c = $a[$x][$y];
						$a[$x][$y - $lowestY] = $c;
						$a[$x][$y] = '-';
					}
				}
			}
		}

		return $a;
	}

	/**
	 * @param array $b Board array
	 * @param bool $trigger Trigger flag
	 * @return void
	 */
	private function displayArray($b, $trigger = false) {
		$bCount = 0;
		if ($trigger) {
			$bCount = count($b);
		}

		for ($y = 0; $y < $bCount; $y++) {
			$bYCount = count($b[$y]);

			for ($x = 0; $x < $bYCount; $x++) {
				echo '&nbsp;&nbsp;' . $b[$x][$y] . ' ';
			}
			if ($y != 18) {
				echo '<br>';
			}
		}
	}
	private function compare($a, $b, $switch = false) {
		$compare = [];
		$this->displayArray($a);
		$diff1 = $this->compareSingle($a, $b);
		array_push($compare, $diff1);
		$this->displayArray($b);
		if ($switch) {
			$d = $this->colorSwitch($b);
		}
		$arr = $this->getLowest($a);
		$a = $this->shiftToCorner($a, $arr[0], $arr[1]);
		$arr = $this->getLowest($b);
		$b = $this->shiftToCorner($b, $arr[0], $arr[1]);
		if ($switch) {
			$c = $this->colorSwitch($b);
		}
		$diff2 = $this->compareSingle($a, $b);
		array_push($compare, $diff2);
		$this->displayArray($b);

		$b = $this->mirror($b);
		$diff3 = $this->compareSingle($a, $b);
		array_push($compare, $diff3);
		$this->displayArray($b);

		if ($switch) {
			$diff4 = $this->compareSingle($a, $d);
			array_push($compare, $diff4);
			$this->displayArray($d);

			$this->displayArray($c);
			$diff5 = $this->compareSingle($a, $c);
			array_push($compare, $diff5);

			$c = $this->mirror($c);
			$diff6 = $this->compareSingle($a, $c);
			array_push($compare, $diff6);
			$this->displayArray($c);
		}
		$lowestCompare = 6;
		$lowestCompareNum = 100;
		$compareCount = count($compare);

		for ($i = 0; $i < $compareCount; $i++) {
			if ($compare[$i] < $lowestCompareNum) {
				$lowestCompareNum = $compare[$i];
				$lowestCompare = $i;
			}
		}
		if ($lowestCompareNum < 10) {
			$lowestCompareNum = '0' . $lowestCompareNum;
		} elseif ($lowestCompareNum > 99) {
			$lowestCompareNum = 99;
		}
		$order = $lowestCompareNum . '-' . $lowestCompare;

		return [$lowestCompareNum, $lowestCompare, $order];
	}

	private function colorSwitch($b) {
		$bCount = count($b);

		for ($y = 0; $y < $bCount; $y++) {
			$bYCount = count($b[$y]);

			for ($x = 0; $x < $bYCount; $x++) {
				if ($b[$x][$y] == 'x') {
					$b[$x][$y] = 'o';
				} elseif ($b[$x][$y] == 'o') {
					$b[$x][$y] = 'x';
				}
			}
		}

		return $b;
	}

	private function compareSingle($a, $b) {
		$diff = 0;
		$bCount = count($b);

		for ($y = 0; $y < $bCount; $y++) {
			$bYCount = count($b[$y]);

			for ($x = 0; $x < $bYCount; $x++) {
				if ($a[$x][$y] != $b[$x][$y]) {
					$diff++;
				}
			}
		}

		return $diff;
	}

	private function mirror($a) {
		$a1 = [];
		$black = [];
		$white = [];
		$aCount = count($a);

		for ($y = 0; $y < $aCount; $y++) {
			$a1[$y] = [];
			$aYCount = count($a[$y]);

			for ($x = 0; $x < $aYCount; $x++) {
				$a1[$y][$x] = $a[$x][$y];
			}
		}

		return $a1;
	}

	private function findUt($id = null, $utsMap = null) {
		if (!isset($utsMap[$id])) {
			return null;
		}
		$ut = [];
		$ut['TsumegoStatus']['tsumego_id'] = $id;
		$ut['TsumegoStatus']['user_id'] = $this->loggedInUserID();
		$ut['TsumegoStatus']['status'] = $utsMap[$id];
		$ut['TsumegoStatus']['created'] = date('Y-m-d H:i:s');

		return $ut;
	}

	private function commentCoordinates($c = null, $counter = null, $noSyntax = null) {
		if (!is_string($c)) {
			return [$c, ''];
		}
		$m = str_split($c);
		$n = '';
		$n2 = '';
		$hasLink = false;
		$mCount = count($m);

		for ($i = 0; $i < $mCount; $i++) {
			if (isset($m[$i + 1])) {
				if (preg_match('/[a-tA-T]/', $m[$i]) && is_numeric($m[$i + 1])) {
					if (!preg_match('/[a-tA-T]/', $m[$i - 1]) && !is_numeric($m[$i - 1])) {
						if (is_numeric($m[$i + 2])) {
							if (!preg_match('/[a-tA-T]/', $m[$i + 3]) && !is_numeric($m[$i + 3])) {
								$n .= $m[$i] . $m[$i + 1] . $m[$i + 2] . ' ';
								$n2 .= $i . '/' . ($i + 2) . ' ';
							}
						} else {
							if (!preg_match('/[a-tA-T]/', $m[$i + 2])) {
								$n .= $m[$i] . $m[$i + 1] . ' ';
								$n2 .= $i . '/' . ($i + 1) . ' ';
							}
						}
					}
				}
			}

			if ($m[$i] == '<' && $m[$i + 1] == '/' && $m[$i + 2] == 'a' && $m[$i + 3] == '>') {
				$hasLink = true;
			}
		}
		if (substr($n, -1) == ' ') {
			$n = substr($n, 0, -1);
		}
		if (substr($n2, -1) == ' ') {
			$n2 = substr($n2, 0, -1);
		}

		if ($hasLink) {
			$n2 = [];
		}
		$coordForBesogo = [];

		if (is_string($n2) && strlen($n2) > 1) {
			$n2x = explode(' ', $n2);
			$fn = 1;
			$n2xCount = count($n2x);
			for ($i = $n2xCount - 1; $i >= 0; $i--) {
				$n2xx = explode('/', $n2x[$i]);
				$a = substr($c, 0, (int)$n2xx[0]);
				$cx = substr($c, (int)$n2xx[0], (int)$n2xx[1] - (int)$n2xx[0] + 1);
				if ($noSyntax) {
					$b = '<a href="#" title="original: ' . $cx . '" id="ccIn' . $counter . $fn . '" onmouseover="ccIn' . $counter . $fn . '()" onmouseout="ccOut' . $counter . $fn . '()" return false;>';
				} else {
					$b = '<a href=\"#\" title="original: ' . $cx . '" id="ccIn' . $counter . $fn . '" onmouseover=\"ccIn' . $counter . $fn . '()\" onmouseout=\"ccOut' . $counter . $fn . '()\" return false;>';
				}

				$d = '</a>';
				$e = substr($c, $n2xx[1] + 1, strlen($c) - 1);
				$coordForBesogo[$i] = $cx;
				$c = $a . $b . $cx . $d . $e;
				$fn++;
			}
		}

		$xx = explode(' ', $n);
		$coord1 = 0;
		$finalCoord = '';
		$xxCount = count($xx);

		for ($i = 0; $i < $xxCount; $i++) {
			if (strlen($xx[$i]) > 1) {
				$xxxx = [];
				$xxx = str_split($xx[$i]);
				$xxxCount = count($xxx);

				for ($j = 0; $j < $xxxCount; $j++) {
					if (preg_match('/[0-9]/', $xxx[$j])) {
						array_push($xxxx, $xxx[$j]);
					}
					if (preg_match('/[a-tA-T]/', $xxx[$j])) {
						$coord1 = $this->convertCoord($xxx[$j]);
					}
				}
				$coord2 = $this->convertCoord2(implode('', $xxxx));
				if ($coord1 != -1 && $coord2 != -1) {
					$finalCoord .= $coord1 . '-' . $coord2 . '-' . $coordForBesogo[$i] . ' ';
				}
			}
		}
		if (substr($finalCoord, -1) == ' ') {
			$finalCoord = substr($finalCoord, 0, -1);
		}

		$array = [];
		array_push($array, $c);
		array_push($array, $finalCoord);

		return $array;
	}

	private function convertCoord($l = null) {
		switch (strtolower($l)) {
			case 'a':
				return 0;
			case 'b':
				return 1;
			case 'c':
				return 2;
			case 'd':
				return 3;
			case 'e':
				return 4;
			case 'f':
				return 5;
			case 'g':
				return 6;
			case 'h':
				return 7;
			case 'j':
				return 8;
			case 'k':
				return 9;
			case 'l':
				return 10;
			case 'm':
				return 11;
			case 'n':
				return 12;
			case 'o':
				return 13;
			case 'p':
				return 14;
			case 'q':
				return 15;
			case 'r':
				return 16;
			case 's':
				return 17;
			case 't':
				return 18;
		}

		return 0;
	}
	private function convertCoord2($n = null) {
		switch ($n) {
			case '0':
				return 19;
			case '1':
				return 18;
			case '2':
				return 17;
			case '3':
				return 16;
			case '4':
				return 15;
			case '5':
				return 14;
			case '6':
				return 13;
			case '7':
				return 12;
			case '8':
				return 11;
			case '9':
				return 10;
			case '10':
				return 9;
			case '11':
				return 8;
			case '12':
				return 7;
			case '13':
				return 6;
			case '14':
				return 5;
			case '15':
				return 4;
			case '16':
				return 3;
			case '17':
				return 2;
			case '18':
				return 1;
		}

		return 0;
	}

}

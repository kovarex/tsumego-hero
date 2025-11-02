<?php

App::uses('TsumegoUtil', 'Utility');
App::uses('AdminActivityUtil', 'Utility');
App::uses('TsumegoButton', 'Utility');
App::uses('AppException', 'Utility');

class TsumegosController extends AppController {
	public $helpers = ['Html', 'Form'];
	public $components = ['TimeMode', 'TsumegoNavigationButtons'];

	private function deduceRelevantSetConnection(array $setConnections): array {
		if (!isset($this->params->query['sid'])) {
			return $setConnections[0];
		}
		foreach ($setConnections as $setConnection) {
			if ($setConnection['SetConnection']['set_id'] == $this->params->query['sid']) {
				return $setConnection;
			}
		}
		die("Problem doesn't exist in the specified set");
	}

	private static function getMatchingSetConnectionOfOtherTsumego(int $tsumegoID, int $currentSetID): ?int {
		if ($setConnections = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => $tsumegoID]])) {
			if ($result = array_find($setConnections, function (array $setConnection) use (&$currentSetID): bool {
				return $setConnection['SetConnection']['set_id'] == $currentSetID;
			})) {
				return $result['SetConnection']['id'];
			}
		}
		return null;
	}

	private static function tsumegoOrSetLink(?int $setConnectionID, ?int $tsumegoID, int $setID): string {
		if ($setConnectionID) {
			return '/' . $setConnectionID;
		}
		if ($tsumegoID) {
			return '/tsumegos/play/' . $tsumegoID;
		} // temporary, until we retrieve setConnectionID for everything
		return '/sets/view/' . $setID; // edge of the set (last or first), so we return to the set
	}

	private static function checkModeChange(): void {
		if (!Auth::isLoggedIn()) {
			return;
		}
		if ($modeChange = Util::clearCookie('change-mode')) {
			if ($modeChange != Constants::$TIME_MODE) {
				TimeModeComponent::cancelTimeMode();
			}
			Auth::getUser()['mode'] = $modeChange;
			Auth::saveUser();
		}
	}

	public function play($id = null, $setConnectionID = null): mixed {
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
		$this->loadModel('TimeModeAttempt');
		$this->loadModel('TimeModeSetting');
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

		$preTsumego = null;
		$ut = null;
		$ts = [];
		$anzahl2 = 0;
		$nextMode = null;
		$rejuvenation = false;
		$doublexp = null;
		$exploit = null;
		$dailyMaximum = false;
		$suspiciousBehavior = false;
		$half = '';
		$inFavorite = false;
		$lastInFav = 0;
		$isSandbox = false;
		$goldenTsumego = false;
		$refresh = null;
		$difficulty = 4;
		$potion = 0;
		$potionSuccess = false;
		$potionActive = false;
		$reviewCheat = false;
		$commentCoordinates = [];
		$josekiLevel = 1;
		$mode3ScoreArray = [];
		$trs = [];
		$potionAlert = false;
		$eloScore = 0;
		$eloScore2 = 0;
		$requestProblem = '';
		$achievementUpdate = [];
		$pdCounter = 0;
		$tRank = '15k';
		$nothingInRange = false;
		$tsumegoStatusMap = [];
		$setsWithPremium = [];
		$queryTitle = '';
		$queryTitleSets = '';
		$partition = -1;

		if ($setConnectionID) {
			$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
			if (!$setConnection) {
				throw new AppException("Set connection " . $setConnectionID . " wasn't found in the database.");
			}
			$id = $setConnection['SetConnection']['tsumego_id'];
		}

		$hasPremium = Auth::hasPremium();
		$swp = $this->Set->find('all', ['conditions' => ['premium' => 1]]) ?: [];
		foreach ($swp as $item) {
			$setsWithPremium[] = $item['Set']['id'];
		}

		if ($newID = $this->TimeMode->update($setsWithPremium, $this->params)) {
			$id = $newID;
		}

		if (!$id) {
			throw new AppException("Tsumego id nor set connection was provided");
		}

		$setConnections = TsumegoUtil::getSetConnectionsWithTitles($id);
		if (!$setConnections) {
			throw new AppException("Problem without any set connection");
		} // some redirect/nicer message ?
		if (!isset($setConnection)) {
			$setConnection = $this->deduceRelevantSetConnection($setConnections);
		}
		$set = $this->Set->findById($setConnection['SetConnection']['set_id']);

		$tsumegoVariant = $this->TsumegoVariant->find('first', ['conditions' => ['tsumego_id' => $id]]);

		if (isset($this->params['url']['potionAlert'])) {
			$potionAlert = true;
		}

		$searchPatameters = $this->processSearchParameters(Auth::getUserID());
		$query = $searchPatameters[0];
		$collectionSize = $searchPatameters[1];
		$search1 = $searchPatameters[2];
		$search2 = $searchPatameters[3];
		$search3 = $searchPatameters[4];

		if (isset($this->params['url']['search'])) {
			if ($this->params['url']['search'] == 'topics') {
				$query = $this->params['url']['search'];
				$_COOKIE['query'] = $this->params['url']['search'];
				$this->processSearchParameters(Auth::getUserID());
			}
		}

		self::checkModeChange();
		if ($this->TimeMode->checkFinishSession()) {
			return $this->redirect(['action' => '/timeMode/result']);
		}

		if (isset($this->params['url']['refresh'])) {
			$refresh = $this->params['url']['refresh'];
		}

		if (Auth::isLoggedIn()) {
			if (isset($this->params['url']['mode'])) {
				Auth::getUser()['mode'] = $this->params['url']['mode'];
			}
			$difficulty = Auth::getUser()['t_glicko'];
			if (isset($_COOKIE['difficulty']) && $_COOKIE['difficulty'] != '0') {
				$difficulty = $_COOKIE['difficulty'];
				Auth::getUser()['t_glicko'] = $_COOKIE['difficulty'];
				unset($_COOKIE['difficulty']);
			}
			if (Auth::isInRatingMode()) {
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

				$eloRange = Auth::getUser()['rating'] + $adjustDifficulty;
				$eloRangeMin = $eloRange - 240;
				$eloRangeMax = $eloRange + 240;

				$range = $this->Tsumego->find('all', [
					'conditions' => [
						'rating >=' => $eloRangeMin,
						'rating <=' => $eloRangeMax,
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
							if ($raS['Set']['id'] != 210 && $raS['Set']['id'] != 191 && $raS['Set']['id'] != 181 && $raS['Set']['id'] != 207 && $raS['Set']['id'] != 172
							&& $raS['Set']['id'] != 202 && $raS['Set']['id'] != 237 && $raS['Set']['id'] != 81578 && $raS['Set']['id'] != 74761 && $raS['Set']['id'] != 71790 && $raS['Set']['id'] != 33007
							&& $raS['Set']['id'] != 31813 && $raS['Set']['id'] != 29156 && $raS['Set']['id'] != 11969 && $raS['Set']['id'] != 6473) {
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
			}
		}

		$t = $this->Tsumego->findById($id);//the tsumego

		if ($t['Tsumego']['rating'] < 1000) {
			$t = $this->checkEloAdjust($t);
		}

		if (Auth::isLoggedIn()) {
			$activityValue = $this->getActivityValue(Auth::getUserID(), $t['Tsumego']['id']);
		}

		if ($t['Tsumego']['rating']) {
			$tRank = Rating::getReadableRankFromRating($t['Tsumego']['rating']);
		}

		$fSet = $this->Set->find('first', ['conditions' => ['id' => $t['Tsumego']['set_id']]]);
		if (!$fSet) {
			$fSet = $this->Set->findById(1);
		}
		if ($t == null) {
			$t = $this->Tsumego->findById($this->Session->read('lastVisit'));
		}

		$this->Session->write('lastVisit', $id);

		if (Auth::isLoggedIn()) {
			if (!empty($this->data)) {
				if (isset($this->data['Comment']['status']) && !isset($this->data['Study2'])) {
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
					$adminActivity['AdminActivity']['answer'] = $this->data['Comment']['status'];
					$this->AdminActivity->save($adminActivity);
					$this->Comment->save($this->data, true);
				} elseif (isset($this->data['Comment']['modifyDescription'])) {
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = 'description';
					$adminActivity['AdminActivity']['answer'] = 'Description: ' . $this->data['Comment']['modifyDescription'] . ' ' . $this->data['Comment']['modifyHint'];
					$t['Tsumego']['description'] = $this->data['Comment']['modifyDescription'];
					$t['Tsumego']['hint'] = $this->data['Comment']['modifyHint'];
					$t['Tsumego']['author'] = $this->data['Comment']['modifyAuthor'];
					if ($this->data['Comment']['modifyElo'] < 2900) {
						$t['Tsumego']['rating'] = $this->data['Comment']['modifyElo'];
					}
					if ($t['Tsumego']['rating'] > 100) {
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
					$tsumegoVariant['TsumegoVariant']['answer1'] = $this->data['Study']['study1'];
					$tsumegoVariant['TsumegoVariant']['answer2'] = $this->data['Study']['study2'];
					$tsumegoVariant['TsumegoVariant']['answer3'] = $this->data['Study']['study3'];
					$tsumegoVariant['TsumegoVariant']['answer4'] = $this->data['Study']['study4'];
					$tsumegoVariant['TsumegoVariant']['explanation'] = $this->data['Study']['explanation'];
					$tsumegoVariant['TsumegoVariant']['numAnswer'] = $this->data['Study']['studyCorrect'];
					$this->TsumegoVariant->save($tsumegoVariant);
				} elseif (isset($this->data['Study2'])) {
					$tsumegoVariant['TsumegoVariant']['winner'] = $this->data['Study2']['winner'];
					$tsumegoVariant['TsumegoVariant']['answer1'] = $this->data['Study2']['answer1'];
					$tsumegoVariant['TsumegoVariant']['answer2'] = $this->data['Study2']['answer2'];
					$tsumegoVariant['TsumegoVariant']['answer3'] = $this->data['Study2']['answer3'];
					$this->TsumegoVariant->save($tsumegoVariant);
				} elseif (isset($this->data['Settings'])) {
					if ($this->data['Settings']['r39'] == 'on' && $t['Tsumego']['alternative_response'] != 1) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity2['AdminActivity']['file'] = 'settings';
						$adminActivity2['AdminActivity']['answer'] = 'Turned on alternative response mode';
						$this->AdminActivity->create();
						$this->AdminActivity->save($adminActivity2);
					}
					if ($this->data['Settings']['r39'] == 'off' && $t['Tsumego']['alternative_response'] != 0) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity2['AdminActivity']['file'] = 'settings';
						$adminActivity2['AdminActivity']['answer'] = 'Turned off alternative response mode';
						$this->AdminActivity->create();
						$this->AdminActivity->save($adminActivity2);
					}
					if ($this->data['Settings']['r43'] == 'no' && $t['Tsumego']['pass'] != 0) {
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Disabled passing';
						$this->AdminActivity->create();
						$this->AdminActivity->save($adminActivity);
					}
					if ($this->data['Settings']['r43'] == 'yes' && $t['Tsumego']['pass'] != 1) {
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Enabled passing';
						$this->AdminActivity->create();
						$this->AdminActivity->save($adminActivity);
					}
					if ($this->data['Settings']['r41'] == 'yes' && $tsumegoVariant == null) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
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
					if ($this->data['Settings']['r41'] == 'no' && $tsumegoVariant != null) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity2['AdminActivity']['file'] = 'settings';
						$adminActivity2['AdminActivity']['answer'] = 'Deleted multiple choice problem type';
						$this->AdminActivity->create();
						$this->AdminActivity->save($adminActivity2);
						$this->TsumegoVariant->delete($tsumegoVariant['TsumegoVariant']['id']);
						$tsumegoVariant = null;
					}
					if ($this->data['Settings']['r42'] == 'yes' && $tsumegoVariant == null) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
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
					if ($this->data['Settings']['r42'] == 'no' && $tsumegoVariant != null) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity2['AdminActivity']['file'] = 'settings';
						$adminActivity2['AdminActivity']['answer'] = 'Deleted score estimating problem type';
						$this->AdminActivity->create();
						$this->AdminActivity->save($adminActivity2);
						$this->TsumegoVariant->delete($tsumegoVariant['TsumegoVariant']['id']);
						$tsumegoVariant = null;
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
					if ($t['Tsumego']['rating'] > 100) {
						$this->Tsumego->save($t, true);
					}
				} else {
					if ($this->data['Comment']['user_id'] != 33) {
						$this->Comment->create();
						if ($this->checkCommentValid(Auth::getUserID())) {
							$this->Comment->save($this->data, true);
						}
					}
				}
				$this->set('formRedirect', true);
			}
		}
		if (Auth::isAdmin()) {
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
			$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
			$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
			$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
			$adminActivity['AdminActivity']['answer'] = $deleteComment['Comment']['status'];
			$this->AdminActivity->save($adminActivity);
			$this->Comment->save($deleteComment);
		}
		if (isset($_FILES['game'])) {
			$errors = [];
			$file_size = $_FILES['game']['size'];
			$file_tmp = $_FILES['game']['tmp_name'];
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
			$sgfComment['user_id'] = Auth::getUserID();
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
			$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
			$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
			$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
			$adminActivity['AdminActivity']['answer'] = $file_name;
			$this->AdminActivity->save($adminActivity);
			$t['Tsumego']['variance'] = 0;
			if ($t['Tsumego']['rating'] > 100) {
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
				$sgf['Sgf']['user_id'] = Auth::getUserID();

				if ($t['Tsumego']['duplicate'] <= 9) {
					$sgf['Sgf']['tsumego_id'] = $id;
				} else {
					$sgf['Sgf']['tsumego_id'] = $t['Tsumego']['duplicate'];
				}

				$sgf['Sgf']['version'] = $this->createNewVersionNumber($lastV, Auth::getUserID());
				$this->handleContribution(Auth::getUserID(), 'made_proposal');
				$this->Sgf->save($sgf);
			}
		}
		$t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty'] * $fSet['Set']['multiplier']);

		if (Auth::isLoggedIn()) {
			$pd = $this->ProgressDeletion->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
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

		if (isset($_COOKIE['skip']) && $_COOKIE['skip'] != '0' && Auth::getUser()) {
			Auth::getUser()['readingTrial']--;
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
		}
		if (Auth::isInLevelMode()) {
			if (Auth::isLoggedIn()) {
				$tsumegoStatusMap = TsumegoUtil::getMapForCurrentUser();
				$utsMapx = array_count_values($tsumegoStatusMap);
				$correctCounter = $utsMapx['C'] + $utsMapx['S'] + $utsMapx['W'];
				Auth::getUser()['solved'] = $correctCounter;
				$ut = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $t['Tsumego']['id']]]);
			} else {
				$ut = null;
			}
		} elseif (Auth::isInRatingMode()) {
			$allUts1 = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $t['Tsumego']['id']]]);
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
		} elseif (Auth::isInTimeMode()) {
			$allUts1 = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $t['Tsumego']['id']]]);
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
			$preTsumego = $this->Tsumego->findById((int) $_COOKIE['previousTsumegoID']);
			$preSc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $preTsumego['Tsumego']['id']]]);
			$preTsumego['Tsumego']['set_id'] = $preSc['SetConnection']['set_id'];
			$utPre = $this->findUt((int) $_COOKIE['previousTsumegoID'], $tsumegoStatusMap);
		}

		if (Auth::isInLevelMode() || Auth::isInTimeMode()) {
			if (isset($_COOKIE['previousTsumegoID']) && (int) $_COOKIE['previousTsumegoID'] == $t['Tsumego']['id']) {
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
		if (Auth::isInTimeMode()) {
			$mode3Score1 = $this->encrypt($t['Tsumego']['num'] . '-solved-' . $t['Tsumego']['set_id']);
			$mode3Score2 = $this->encrypt($t['Tsumego']['num'] . '-failed-' . $t['Tsumego']['set_id']);
			$mode3Score3 = $this->encrypt($t['Tsumego']['num'] . '-timeout-' . $t['Tsumego']['set_id']);
			$mode3Score4 = $this->encrypt($t['Tsumego']['num'] . '-skipped-' . $t['Tsumego']['set_id']);
			array_push($mode3ScoreArray, $mode3Score1);
			array_push($mode3ScoreArray, $mode3Score2);
			array_push($mode3ScoreArray, $mode3Score3);
			array_push($mode3ScoreArray, $mode3Score4);
		}

		if (isset($_COOKIE['favorite']) && $_COOKIE['favorite'] != '0') {
			if (Auth::isLoggedIn()) {
				if ($_COOKIE['favorite'] > 0) {
					$fav = $this->Favorite->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $_COOKIE['favorite']]]);
					if ($fav == null) {
						$fav = [];
						$fav['Favorite']['user_id'] = Auth::getUserID();
						$fav['Favorite']['tsumego_id'] = $_COOKIE['favorite'];
						$fav['Favorite']['created'] = date('Y-m-d H:i:s');
						$this->Favorite->create();
						$this->Favorite->save($fav);
					}
				} else {
					$favId = $_COOKIE['favorite'] * -1;
					$favDel = $this->Favorite->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $favId]]);
					$this->Favorite->delete($favDel['Favorite']['id']);
				}
				unset($_COOKIE['favorite']);
			}
		}
		if (isset($_COOKIE['TimeModeAttempt']) && $_COOKIE['TimeModeAttempt'] != '0') {
			$drCookie = $this->decrypt($_COOKIE['TimeModeAttempt']);
			$drCookie2 = explode('-', $drCookie);
			$_COOKIE['TimeModeAttempt'] = $drCookie2[1];
		}

		if (Auth::isLoggedIn() && Auth::getUser()['potion'] >= 15) {
			$this->setPotionCondition();
		}

		if (Auth::isLoggedIn() && isset($_COOKIE['rejuvenationx']) && $_COOKIE['rejuvenationx'] != 0) {
			if (Auth::getUser()['usedRejuvenation'] == 0 && $_COOKIE['rejuvenationx'] == 1) {
				Auth::getUser()['damage'] = 0;
				Auth::getUser()['intuition'] = 1;
				Auth::getUser()['damage'] = 0;
				$rejuvenation = true;
			} elseif ($_COOKIE['rejuvenationx'] == 2) {
				Auth::getUser()['damage'] = 0;
			}
			Auth::saveUser();
			$_COOKIE['misplay'] = 0;
			unset($_COOKIE['rejuvenationx']);
		}
		//Incorrect
		if (Auth::isLoggedIn() && isset($_COOKIE['misplay']) && $_COOKIE['misplay'] != 0) {
			if (Auth::isInLevelMode()) {
				if (isset($_COOKIE['previousTsumegoID']) && (int) $_COOKIE['previousTsumegoID'] > 0) {
					$this->TsumegoAttempt->create();
					$ur1 = [];
					$ur1['TsumegoAttempt']['user_id'] = Auth::getUserID();
					$ur1['TsumegoAttempt']['elo'] = Auth::getUser()['rating'];
					$ur1['TsumegoAttempt']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
					$ur1['TsumegoAttempt']['gain'] = 0;
					$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
					$ur1['TsumegoAttempt']['solved'] = '0';
					$ur1['TsumegoAttempt']['misplays'] = $_COOKIE['misplay'];
					$this->TsumegoAttempt->save($ur1);
				}
			}
			if (Auth::isInLevelMode() || Auth::isInTimeMode()) {
				if (Auth::isInLevelMode() == 1 && $_COOKIE['transition'] != 2) {
					Auth::getUser()['damage'] += $_COOKIE['misplay'];
				}
				if (isset($_COOKIE['TimeModeAttempt']) && $_COOKIE['TimeModeAttempt'] != '0') {
					/* TODO: time mode details to fix and move once I want the next step working
					$this->TimeMode->timeModeAttempts = $this->TimeModeAttempt->find('all', ['conditions' => ['session' => Auth::getUser()['activeRank']]]) ?: [];
					$currentNum = $this->TimeMode->timeModeAttempts[0]['TimeModeAttempt']['currentNum'];
					$ranksCount = count($this->TimeMode->timeModeAttempts);

					for ($i = 0; $i < $ranksCount; $i++) {
						if ($this->TimeMode->timeModeAttempts[$i]['TimeModeAttempt']['num'] == $currentNum - 1) {
							$this->TimeMode->timeModeAttempts[$i]['TimeModeAttempt']['result'] = $_COOKIE['TimeModeAttempt'];
							if (isset($_COOKIE['previousTsumegoID'])) {
								$this->TimeMode->timeModeAttempts[$i]['TimeModeAttempt']['seconds'] = $_COOKIE['seconds'] / 10;
							} else {
								$this->TimeMode->timeModeAttempts[$i]['TimeModeAttempt']['seconds'] = 0;
							}
							$this->TimeModeAttempt->save($this->TimeMode->timeModeAttempts[$i]);
						}
					}

					$eloDifference = abs(Auth::getUser()['rating'] - $preTsumego['Tsumego']['rating']);
					if (Auth::getUser()['rating'] > $preTsumego['Tsumego']['rating']) {
						$eloBigger = 'u';
					} else {
						$eloBigger = 't';
					}
					$activityValueTime = 1;
					if (isset($_COOKIE['av'])) {
						$activityValueTime = $_COOKIE['av'];
					}
					$activityValueTime = $this->getNewElo($eloDifference, $eloBigger, $activityValueTime, $preTsumego['Tsumego']['id'], 'l');

					$preTsumego['Tsumego']['rating'] += $activityValueTime['tsumego'];
					$preTsumego['Tsumego']['activity_value']++;
					if ($preTsumego['Tsumego']['rating'] > 100) {
						$this->Tsumego->save($preTsumego);
					}

					$this->TsumegoAttempt->create();
					$ur1 = [];
					$ur1['TsumegoAttempt']['user_id'] = Auth::getUserID();
					$ur1['TsumegoAttempt']['elo'] = Auth::getUser()['rating'];
					$ur1['TsumegoAttempt']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
					$ur1['TsumegoAttempt']['gain'] = 0;
					$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'] / 10;
					$ur1['TsumegoAttempt']['solved'] = '0';
					$ur1['TsumegoAttempt']['misplays'] = 1;
					$ur1['TsumegoAttempt']['mode'] = 3;
					$ur1['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['rating'];
					$this->TsumegoAttempt->save($ur1);*/
				}
				if ($_COOKIE['type'] == 'g') {
					$this->updateGoldenCondition();
				}
			} elseif (Auth::isInRatingMode()) {
				if (isset($_COOKIE['previousTsumegoID'])) {
					$eloDifference = abs(Auth::getUser()['rating'] - $preTsumego['Tsumego']['rating']);
					if (Auth::getUser()['rating'] > $preTsumego['Tsumego']['rating']) {
						$eloBigger = 'u';
					} else {
						$eloBigger = 't';
					}
					$activityValueRating = 1;
					if (isset($_COOKIE['av'])) {
						$activityValueRating = $_COOKIE['av'];
					}
					$newUserEloWRating = $this->getNewElo($eloDifference, $eloBigger, $activityValueRating, $preTsumego['Tsumego']['id'], 'l');
					$preTsumego['Tsumego']['rating'] += $newUserEloWRating['tsumego'];
					$preTsumego['Tsumego']['activity_value']++;
					if ($_COOKIE['type'] == 'g') {
						$this->updateGoldenCondition();
					}

					if ($preTsumego['Tsumego']['rating'] > 100) {
						$this->Tsumego->save($preTsumego);
					}

					$this->TsumegoAttempt->create();
					$ur1 = [];
					$ur1['TsumegoAttempt']['user_id'] = Auth::getUserID();
					$ur1['TsumegoAttempt']['elo'] = Auth::getUser()['rating'];
					$ur1['TsumegoAttempt']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
					$ur1['TsumegoAttempt']['gain'] = Auth::getUser()['rating'];
					$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
					$ur1['TsumegoAttempt']['solved'] = '0';
					$ur1['TsumegoAttempt']['misplays'] = 1;
					$ur1['TsumegoAttempt']['mode'] = 2;
					$ur1['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['rating'];
					if ($ur1['TsumegoAttempt']['user_id'] > 0) {
						$this->TsumegoAttempt->save($ur1);
					}
				}
			}
			$aCondition = $this->AchievementCondition->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => 'err',
				],
			]);
			if ($aCondition == null) {
				$aCondition = [];
			}
			$aCondition['AchievementCondition']['category'] = 'err';
			$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
			$aCondition['AchievementCondition']['value'] = 0;
			$this->AchievementCondition->save($aCondition);
			if (Auth::getUser()['damage'] > Auth::getUser()['health']) {
				if (empty($utPre)) {
					$utPre['TsumegoStatus'] = [];
					$utPre['TsumegoStatus']['user_id'] = Auth::getUserID();
					$utPre['TsumegoStatus']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
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
				if (!isset($utPre['TsumegoStatus']['status'])) {
					$utPre['TsumegoStatus']['status'] = 'V';
				}
				if (Auth::hasPremium() || Auth::getUser()['level'] >= 50) {
					if (Auth::getUser()['potion'] != -69) {
						$potion = Auth::getUser()['potion'];
						$potion++;
						$this->Session->write('loggedInUser.User.potion', $potion);
						Auth::getUser()['potion']++;
						$potionSuccess = false;
						if ($potion >= 5) {
							$potionPercent = $potion * 1.3;
							$potionPercent2 = rand(0, 100);
							$potionSuccess = $potionPercent > $potionPercent2;
						}
						if ($potionSuccess) {
							Auth::getUser()['potion'] = -69;
						}
					}
				}
				if (Auth::isInLevelMode()) {
					Auth::getUser()['damage'] = Auth::getUser()['health'];
				}
			}
			unset($_COOKIE['misplay']);
			unset($_COOKIE['sequence']);
			unset($_COOKIE['type']);
			unset($_COOKIE['transition']);
		}
		$correctSolveAttempt = false;

		//Correct!
		if (Auth::isLoggedIn() && isset($_COOKIE['previousTsumegoID']) && isset($_COOKIE['score']) && $_COOKIE['score'] != '0') {
			$_COOKIE['score'] = $this->decrypt($_COOKIE['score']);
			$scoreArr = explode('-', $_COOKIE['score']);
			$isNum = $preTsumego['Tsumego']['num'] == $scoreArr[0];
			$isSet = $preTsumego['Tsumego']['set_id'] == $scoreArr[2];
			$_COOKIE['score'] = $scoreArr[1];

			$solvedTsumegoRank = Rating::getReadableRankFromRating($preTsumego['Tsumego']['rating']);

			if ($isNum && $isSet || Auth::isInRatingMode()) {
				if (Auth::isInLevelMode() || Auth::isInTimeMode()) {
					$ub = [];
					$ub['UserBoard']['user_id'] = Auth::getUserID();
					$ub['UserBoard']['b1'] = (int) $_COOKIE['previousTsumegoID'];
					$this->UserBoard->create();
					$this->UserBoard->save($ub);

					if ($_COOKIE['score'] >= 3000) {
						$_COOKIE['score'] = 0;
						$suspiciousBehavior = true;
					}
					if (Auth::getUser()['reuse3'] > 12000) {
						Auth::getUser()['reuse4'] = 1;
					}
					if (Auth::isInTimeMode()) {
						$exploit = null;
						$suspiciousBehavior = false;
					}
					if ($exploit == null && $suspiciousBehavior == false) {
						if (Auth::isInLevelMode()) {
							$xpOld = Auth::getUser()['xp'] + ((int) ($_COOKIE['score']));
							Auth::getUser()['reuse2']++;
							Auth::getUser()['reuse3'] += (int) ($_COOKIE['score']);
							if ($xpOld >= Auth::getUser()['nextlvl']) {
								$xpOnNewLvl = -1 * (Auth::getUser()['nextlvl'] - $xpOld);
								Auth::getUser()['xp'] = $xpOnNewLvl;
								Auth::getUser()['level'] += 1;
								Auth::getUser()['nextlvl'] += $this->getXPJump(Auth::getUser()['level']);
								Auth::getUser()['health'] = $this->getHealth(Auth::getUser()['level']);
							} else {
								Auth::getUser()['xp'] = $xpOld;
								Auth::getUser()['ip'] = $_SERVER['REMOTE_ADDR'];
							}
						}
						if (Auth::isInLevelMode()) {
							if (isset($_COOKIE['previousTsumegoID'])) {
								$this->TsumegoAttempt->create();
								$ur = [];
								$ur['TsumegoAttempt']['user_id'] = Auth::getUserID();
								$ur['TsumegoAttempt']['elo'] = Auth::getUser()['rating'];
								$ur['TsumegoAttempt']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
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
						if (isset($_COOKIE['TimeModeAttempt']) && $_COOKIE['TimeModeAttempt'] != '0') {
							/* TODO: time mode stuff to rewrite soon
							$this->saveDanSolveCondition($solvedTsumegoRank, $preTsumego['Tsumego']['id']);
							$this->updateGems($solvedTsumegoRank);
							$this->TimeMode->timeModeAttempts = $this->TimeModeAttempt->find('all', ['conditions' => ['session' => Auth::getUser()['activeRank']]]) ?: [];
							$currentNum = $this->TimeMode->timeModeAttempts[0]['TimeModeAttempt']['currentNum'];
							$ranksCount = count($this->TimeMode->timeModeAttempts);

							for ($i = 0; $i < $ranksCount; $i++) {
								if ($this->TimeMode->timeModeAttempts[$i]['TimeModeAttempt']['num'] == $currentNum - 1) {
									if ($_COOKIE['TimeModeAttempt'] != 'solved' && $_COOKIE['TimeModeAttempt'] != 'failed' && $_COOKIE['TimeModeAttempt'] != 'skipped' && $_COOKIE['TimeModeAttempt'] != 'timeout') {
										$_COOKIE['TimeModeAttempt'] = 'failed';
									}
									$this->TimeMode->timeModeAttempts[$i]['TimeModeAttempt']['result'] = $_COOKIE['TimeModeAttempt'];
									$this->TimeMode->timeModeAttempts[$i]['TimeModeAttempt']['seconds'] = $_COOKIE['seconds'] / 10;
									$this->TimeModeAttempt->save($this->TimeMode->timeModeAttempts[$i]);
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
							$xpOld = Auth::getUser()['xp'] + $ratingModeXp;
							if ($xpOld >= Auth::getUser()['nextlvl']) {
								$xpOnNewLvl = -1 * (Auth::getUser()['nextlvl'] - $xpOld);
								Auth::getUser()['xp'] = $xpOnNewLvl;
								Auth::getUser()['level'] += 1;
								Auth::getUser()['nextlvl'] += $this->getXPJump(Auth::getUser()['level']);
								Auth::getUser()['health'] = $this->getHealth(Auth::getUser()['level']);
							} else {
								Auth::getUser()['xp'] = $xpOld;
							}
							Auth::saveUser();

							$eloDifference = abs(Auth::getUser()['rating']) - $preTsumego['Tsumego']['rating'];
							if (Auth::getUser()['rating'] > $preTsumego['Tsumego']['rating']) {
								$eloBigger = 'u';
							} else {
								$eloBigger = 't';
							}
							$activityValueTime = 1;
							if (isset($_COOKIE['av'])) {
								$activityValueTime = $_COOKIE['av'];
							}
							$activityValueTime = $this->getNewElo($eloDifference, $eloBigger, $activityValueTime, $preTsumego['Tsumego']['id'], 'w');
							$preTsumego['Tsumego']['rating'] += $activityValueTime['tsumego'];
							$preTsumego['Tsumego']['activity_value']++;
							if ($preTsumego['Tsumego']['rating'] > 100) {
								$this->Tsumego->save($preTsumego);
							}

							$this->TsumegoAttempt->create();
							$ur1 = [];
							$ur1['TsumegoAttempt']['user_id'] = Auth::getUserID();
							$ur1['TsumegoAttempt']['elo'] = Auth::getUser()['rating'];
							$ur1['TsumegoAttempt']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
							$ur1['TsumegoAttempt']['gain'] = 1;
							$ur1['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'] / 10;
							$ur1['TsumegoAttempt']['solved'] = '1';
							$ur1['TsumegoAttempt']['misplays'] = 0;
							$ur1['TsumegoAttempt']['mode'] = 3;
							$ur1['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['rating'];
							if ($ur1['TsumegoAttempt']['user_id'] > 0) {
								$this->TsumegoAttempt->save($ur1);
							} */
						}
					}
					if (empty($utPre)) {
						$utPre['TsumegoStatus'] = [];
						$utPre['TsumegoStatus']['user_id'] = Auth::getUserID();
						$utPre['TsumegoStatus']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
						$utPre['TsumegoStatus']['status'] = 'V';
					}
					if ($utPre['TsumegoStatus']['status'] == 'W') {
						$utPre['TsumegoStatus']['status'] = 'C';
					} else {
						$utPre['TsumegoStatus']['status'] = 'S';
					}

					$utPre['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
					if (!isset($utPre['TsumegoStatus']['status'])) {
						$utPre['TsumegoStatus']['status'] = 'V';
					}
				} elseif (Auth::isInRatingMode() && $_COOKIE['transition'] != 1) {
					$userEloBefore = Auth::getUser()['rating'];
					$tsumegoEloBefore = $preTsumego['Tsumego']['rating'];
					$diff = $preTsumego['Tsumego']['rating'] - Auth::getUser()['rating'];

					$ratingModeUt['TsumegoStatus']['status'] = $_COOKIE['preTsumegoBuffer'];

					if ($ratingModeUt['TsumegoStatus']['status'] == 'W') {
						$ratingModeXp = $preTsumego['Tsumego']['difficulty'] / 2;
					} elseif ($ratingModeUt['TsumegoStatus']['status'] == 'S' || $ratingModeUt['TsumegoStatus']['status'] == 'C') {
						$ratingModeXp = 0;
					} else {
						$ratingModeXp = $preTsumego['Tsumego']['difficulty'];
					}
					$xpOld = Auth::getUser()['xp'] + $ratingModeXp;
					if ($xpOld >= Auth::getUser()['nextlvl']) {
						$xpOnNewLvl = -1 * (Auth::getUser()['nextlvl'] - $xpOld);
						Auth::getUser()['xp'] = $xpOnNewLvl;
						Auth::getUser()['level'] += 1;
						Auth::getUser()['nextlvl'] += $this->getXPJump(Auth::getUser()['level']);
						Auth::getUser()['health'] = $this->getHealth(Auth::getUser()['level']);
					} else {
						Auth::getUser()['xp'] = $xpOld;
					}
					Auth::saveUser();
					if ((int) ($_COOKIE['score'] > 100)) {
						$_COOKIE['score'] = 100;
					}

					if ($_COOKIE['type'] == 'g') {
						$this->updateGoldenCondition(true);
					}
					$this->saveDanSolveCondition($solvedTsumegoRank, $preTsumego['Tsumego']['id']);
					$this->updateGems($solvedTsumegoRank);
					Auth::getUser()['solved2']++;

					$eloDifference = abs(Auth::getUser()['rating']) - $preTsumego['Tsumego']['rating'];
					if (Auth::getUser()['rating'] > $preTsumego['Tsumego']['rating']) {
						$eloBigger = 'u';
					} else {
						$eloBigger = 't';
					}
					$activityValueRating = 1;
					if (isset($_COOKIE['av'])) {
						$activityValueRating = $_COOKIE['av'];
					}
					$newUserEloWRating = $this->getNewElo($eloDifference, $eloBigger, $activityValueRating, $preTsumego['Tsumego']['id'], 'w');
					$preTsumego['Tsumego']['rating'] += $newUserEloWRating['tsumego'];
					$preTsumego['Tsumego']['activity_value']++;
					if ($preTsumego['Tsumego']['rating'] > 100) {
						$this->Tsumego->save($preTsumego);
					}

					$this->TsumegoAttempt->create();
					$ur = [];
					$ur['TsumegoAttempt']['user_id'] = Auth::getUserID();
					$ur['TsumegoAttempt']['elo'] = Auth::getUser()['rating'];
					$ur['TsumegoAttempt']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
					$ur['TsumegoAttempt']['gain'] = Auth::getUser()['rating'];
					$ur['TsumegoAttempt']['seconds'] = $_COOKIE['seconds'];
					$ur['TsumegoAttempt']['solved'] = '1';
					$ur['TsumegoAttempt']['mode'] = 2;
					$ur['TsumegoAttempt']['tsumego_elo'] = $preTsumego['Tsumego']['rating'];
					if ($ur['TsumegoAttempt']['user_id'] > 0) {
						$this->TsumegoAttempt->save($ur);
					}
				}
				$aCondition = $this->AchievementCondition->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'err',
					],
				]);
				if ($aCondition == null) {
					$aCondition = [];
				}
				$aCondition['AchievementCondition']['category'] = 'err';
				$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
				$aCondition['AchievementCondition']['value']++;
				$this->AchievementCondition->save($aCondition);
			} else {
				Auth::getUser()['penalty'] += 1;
			}
			unset($_COOKIE['preTsumegoBuffer']);
			unset($_COOKIE['score']);
			unset($_COOKIE['transition']);
			unset($_COOKIE['sequence']);
			unset($_COOKIE['type']);
		}

		if (Auth::isLoggedIn() && isset($_COOKIE['correctNoPoints']) && $_COOKIE['correctNoPoints'] != '0') {
			if (!$correctSolveAttempt) {
				if (isset($_COOKIE['previousTsumegoID'])) {
					$this->TsumegoAttempt->create();
					$ur = [];
					$ur['TsumegoAttempt']['user_id'] = Auth::getUserID();
					$ur['TsumegoAttempt']['elo'] = Auth::getUser()['rating'];
					$ur['TsumegoAttempt']['tsumego_id'] = (int) $_COOKIE['previousTsumegoID'];
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
		if (Auth::isLoggedIn()) {
			if (isset($_COOKIE['doublexp']) && $_COOKIE['doublexp'] != '0') {
				if (Auth::getUser()['usedSprint'] == 0) {
					$doublexp = $_COOKIE['doublexp'];
				} else {
					unset($_COOKIE['doublexp']);
				}
			}
			if (isset($_COOKIE['sprint']) && $_COOKIE['sprint'] != '0') {
				Auth::getUser()['sprint'] = 0;
				if ($_COOKIE['sprint'] == 1) {
					$this->set('sprintActivated', true);
				}
				if ($_COOKIE['sprint'] == 2) {
					Auth::getUser()['usedSprint'] = 1;
				}
				unset($_COOKIE['sprint']);
			}
			if (isset($_COOKIE['intuition']) && $_COOKIE['intuition'] != '0') {
				if ($_COOKIE['intuition'] == '1') {
					Auth::getUser()['intuition'] = 0;
				}
				if ($_COOKIE['intuition'] == '2') {
					Auth::getUser()['intuition'] = 1;
				}
				unset($_COOKIE['intuition']);
			}
			if (isset($_COOKIE['rejuvenation']) && $_COOKIE['rejuvenation'] != '0') {
				Auth::getUser()['rejuvenation'] = 0;
				Auth::getUser()['usedRejuvenation'] = 1;
				unset($_COOKIE['rejuvenation']);
			}
			if (isset($_COOKIE['extendedSprint']) && $_COOKIE['extendedSprint'] != '0') {
				Auth::getUser()['penalty'] += 1;
				unset($_COOKIE['extendedSprint']);
			}
			if (isset($_COOKIE['refinement']) && $_COOKIE['refinement'] != '0') {
				if ($_COOKIE['refinement'] > 0) {
					if (Auth::getUser()['usedRefinement'] == 0) {
						$refinementUT = $this->findUt($id, $tsumegoStatusMap);
						if ($refinementUT == null) {
							$this->TsumegoStatus->create();
							$refinementUT['TsumegoStatus']['user_id'] = Auth::getUserID();
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
						//$tsumegoStatusMap[$refinementUT['TsumegoStatus']['tsumego_id']] = $refinementUT['TsumegoStatus']['status'];

						if (!$ut) {
							$ut = $refinementUT;
						} else {
							$ut['TsumegoStatus']['status'] = 'G';
						}
						$goldenTsumego = true;
						Auth::getUser()['usedRefinement'] = 1;
					}
				} else {
					$resetRefinement = $this->findUt($id, $tsumegoStatusMap);
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
						//$tsumegoStatusMap[$refinementUT['TsumegoStatus']['tsumego_id']] = $resetRefinement['TsumegoStatus']['status'];
					}
					if (!$ut) {
						$ut = $resetRefinement;
					} else {
						$ut['TsumegoStatus']['status'] = 'V';
					}
					$goldenTsumego = false;
				}
				Auth::getUser()['refinement'] = 0;
				unset($_COOKIE['refinement']);
			}
		}

		if ($rejuvenation) {
			$utr = $this->TsumegoStatus->find('all', ['conditions' => ['status' => 'F', 'user_id' => Auth::getUserID()]]);
			if (!$utr) {
				$utr = [];
			}
			$utrCount = count($utr);

			for ($i = 0; $i < $utrCount; $i++) {
				$utr[$i]['TsumegoStatus']['status'] = 'V';
				$this->TsumegoStatus->create();
				//$this->TsumegoStatus->save($utr[$i]);
				//$this->Session->read('loggedInUser.uts')[$utr[$i]['TsumegoStatus']['tsumego_id']] = $utr[$i]['TsumegoStatus']['status'];
				//$tsumegoStatusMap[$utr[$i]['TsumegoStatus']['tsumego_id']] = $utr[$i]['TsumegoStatus']['status'];
			}
			$utrx = $this->TsumegoStatus->find('all', ['conditions' => ['status' => 'X', 'user_id' => Auth::getUserID()]]);
			if (!$utrx) {
				$utrx = [];
			}
			$utrxCount = count($utrx);

			for ($j = 0; $j < $utrxCount; $j++) {
				$utrx[$j]['TsumegoStatus']['status'] = 'W';
				$this->TsumegoStatus->create();
				//$this->TsumegoStatus->save($utrx[$j]);
				//$this->Session->read('loggedInUser.uts')[$utrx[$i]['TsumegoStatus']['tsumego_id']] = $utrx[$i]['TsumegoStatus']['status'];
				//$tsumegoStatusMap[$utrx[$i]['TsumegoStatus']['tsumego_id']] = $utrx[$i]['TsumegoStatus']['status'];
			}
		}

		if (isset($_COOKIE['reputation']) && $_COOKIE['reputation'] != '0') {
			$reputation = $_COOKIE['reputation'];
			$reputation = [];
			$reputation['Reputation']['user_id'] = Auth::getUserID();
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

		if (Auth::isLoggedIn()) {
			$userDate = new DateTime(Auth::getUser()['created']);
			$userDate = $userDate->format('Y-m-d');
			if ($userDate != date('Y-m-d')) {
				Auth::getUser()['created'] = date('Y-m-d H:i:s');
				Auth::saveUser();
				$this->deleteUnusedStatuses(Auth::getUserID());
			}
		}
		if (Auth::isInLevelMode() || Auth::isInTimeMode()) {
			if ($ut == null && Auth::isLoggedIn()) {
				$this->TsumegoStatus->create();
				$ut['TsumegoStatus'] = [];
				$ut['TsumegoStatus']['user_id'] = Auth::getUserID();
				$ut['TsumegoStatus']['tsumego_id'] = $id;
				$ut['TsumegoStatus']['status'] = 'V';
			}
		} elseif (Auth::isInRatingMode()) {
			$ut['TsumegoStatus'] = [];
			$ut['TsumegoStatus']['user_id'] = Auth::getUserID();
			$ut['TsumegoStatus']['tsumego_id'] = $id;
			$ut['TsumegoStatus']['status'] = 'V';
		}
		$amountOfOtherCollection = count(TsumegoUtil::collectTsumegosFromSet($set['Set']['id']));
		$search3ids = [];

		foreach ($search3 as $item) {
			$search3ids[] = $this->TagName->findByName($item)['TagName']['id'];
		}

		$sgf = [];
		if ($t['Tsumego']['duplicate'] <= 9) {
			$sgfdb = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $id]]);
		} else {
			$sgfdb = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $t['Tsumego']['duplicate']]]);
		}
		if (!$sgfdb) {
			$sgf['Sgf']['sgf'] = Constants::$SGF_PLACEHOLDER;
			$sgf['Sgf']['tsumego_id'] = $id;
			$sgf['Sgf']['version'] = 1;
			$this->Sgf->save($sgf);
		} else {
			$sgf = $sgfdb;
		}
		if ($t['Tsumego']['set_id'] == 208 || $t['Tsumego']['set_id'] == 210) {
			$tr = strpos($sgf['Sgf']['sgf'], 'TR');
			$sq = strpos($sgf['Sgf']['sgf'], 'SQ');
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
					$sgf['Sgf']['user_id'] = Auth::getUserID();
					$sgf['Sgf']['tsumego_id'] = $id;
					if (Auth::isAdmin()) {
						$sgf['Sgf']['version'] = $this->createNewVersionNumber($lastV, Auth::getUserID());
					} else {
						$sgf['Sgf']['version'] = 0;
					}
					$this->Sgf->save($sgf);
					$sgf['Sgf']['sgf'] = str_replace("\r", '', $sgf['Sgf']['sgf']);
					$sgf['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $sgf['Sgf']['sgf']);
					if (Auth::isAdmin()) {
						$this->AdminActivity->create();
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
						$adminActivity['AdminActivity']['answer'] = $t['Tsumego']['num'] . '.sgf' . ' <font color="grey">(direct save)</font>';
						$this->AdminActivity->save($adminActivity);
						$this->handleContribution(Auth::getUserID(), 'made_proposal');
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
			$ftFrom['rating >='] = $lastSet;
			$ftTo['rating <'] = $lastSet + 100;
			if ($this->Session->read('lastSet') == '15k') {
				$ftFrom['rating >='] = 50;
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
			]) ?: [];
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
					$search1idx = TsumegoUtil::collectTsumegosFromSet($search1id['Set']['id']);
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
					$ft['rating >='] = $this->getTsumegoElo($search2[$j]);
					$ft['rating <'] = $ft['rating >='] + 100;
					if ($search2[$j] == '15k') {
						$ft['rating >='] = 50;
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
			]) ?: [];
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
			$ts = $this->SetConnection->find('all', ['order' => 'num ASC', 'conditions' => ['set_id' => $set['Set']['id']]]) ?: [];

			if (count($search2) > 0) {
				$fromTo = [];
				$search2Count = count($search2);

				for ($i = 0; $i < $search2Count; $i++) {
					$ft = [];
					$ft['rating >='] = $this->getTsumegoElo($search2[$i]);
					$ft['rating <'] = $ft['rating >='] + 100;
					if ($search2[$i] == '15k') {
						$ft['rating >='] = 50;
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

		if ($query == 'topics') {
			$this->Session->write('title', $set['Set']['title'] . ' ' . $t['Tsumego']['num'] . '/' . $anzahl2 . ' on Tsumego Hero');
		} else {
			$this->Session->write('title', $this->Session->read('lastSet') . ' ' . $t['Tsumego']['num'] . '/' . $anzahl2 . ' on Tsumego Hero');
		}
		if (!$inFavorite) {
			if ($query == 'difficulty' || $query == 'tags') {
				$tsCount = count($ts);

				for ($i = 0; $i < $tsCount; $i++) {
					if ($ts[$i]['Tsumego']['id'] == $t['Tsumego']['id']) {
						for ($a = 5; $a > 0; $a--) {
							if ($i - $a >= 0) {
								$this->TsumegoNavigationButtons->previous [] = TsumegoButton::constructFromSetConnection($ts[$i - $a], $tsumegoStatusMap);
								if ($a == 1) {
									$previousSetConnectionID = $ts[$i - $a]['SetConnection']['id'];
								}
							}
						}
						$bMax = 10 - count($this->TsumegoNavigationButtons->previous);
						$b = 1;
						if ($ts[0]['Tsumego']['id'] == $t['Tsumego']['id']) {
							$bMax++;
						}
						while ($b <= $bMax) {
							if ($i + $b < count($ts)) {
								$this->TsumegoNavigationButtons->next [] = TsumegoButton::constructFromSetConnection($ts[$i + $b], $tsumegoStatusMap);
								if ($b == 1) {
									$nextTsumegoID = $ts[$i + $b]['Tsumego']['id'];
								}
							}
							$b++;
						}
						if (count($this->TsumegoNavigationButtons->next) < 5 || $t['Tsumego']['id'] == $ts[count($ts) - 6]['Tsumego']['id']) {
							$this->TsumegoNavigationButtons->previous = [];
							$a = 5 + (5 - count($this->TsumegoNavigationButtons->next));
							$a++;
							while ($a > 0) {
								if ($i - $a >= 0) {
									$this->TsumegoNavigationButtons->previous [] = TsumegoButton::constructFromSetConnection($ts[$i - $a], $tsumegoStatusMap);
									if ($a == 1) {
										$previousTsumegoID = $ts[$i - $a]['Tsumego']['id'];
									}
									$newUT = $this->findUt($ts[$i - $a]['Tsumego']['id'], $tsumegoStatusMap);
									if (!isset($newUT['TsumegoStatus']['status'])) {
										$newUT['TsumegoStatus']['status'] = 'N';
									}
								}
								$a--;
							}
						}
						if ((count($this->TsumegoNavigationButtons->previous) < 5 || $t['Tsumego']['id'] == $ts[5]['Tsumego']['id']) && $ts[0]['Tsumego']['id'] != $t['Tsumego']['id']) {
							$tsNextAdjust = count($this->TsumegoNavigationButtons->next) + 1;
							$this->TsumegoNavigationButtons->next = [];
							$b = 1;
							while ($b <= $tsNextAdjust) {
								if ($i + $b < count($ts)) {
									$this->TsumegoNavigationButtons->next [] = TsumegoButton::constructFromSetConnection($ts[$i + $b], $tsumegoStatusMap);
									if ($b == 1) {
										$nextTsumegoID = $ts[$i + $b]['Tsumego']['id'];
									}
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
							if ($i - $a > 0) {
								$this->TsumegoNavigationButtons->previous [] = TsumegoButton::constructFromSetConnection($ts[$i - $a], $tsumegoStatusMap);
								if ($a == 1) {
									$previousSetConnectionID = $ts[$i - $a]['SetConnection']['id'];
								}
								$newUT = $this->findUt($ts[$i - $a]['SetConnection']['tsumego_id'], $tsumegoStatusMap);
								if (!isset($newUT['TsumegoStatus']['status'])) {
									$newUT['TsumegoStatus']['status'] = 'N';
								}
							}
							$a--;
						}
						$bMax = 10 - count($this->TsumegoNavigationButtons->previous);
						$b = 1;
						if ($ts[0]['SetConnection']['tsumego_id'] == $t['Tsumego']['id']) {
							$bMax++;
						}
						while ($b <= $bMax) {
							if ($i + $b + 1 < count($ts)) {
								$this->TsumegoNavigationButtons->next [] = TsumegoButton::constructFromSetConnection($ts[$i + $b], $tsumegoStatusMap);
								if ($b == 1) {
									$nextSetConnectionID = $ts[$i + $b]['SetConnection']['id'];
								}
							}
							$b++;
						}
						if (count($this->TsumegoNavigationButtons->next) < 5 || $t['Tsumego']['id'] == $ts[count($ts) - 6]['SetConnection']['tsumego_id']) {
							$a = 6 - count($this->TsumegoNavigationButtons->next);
							$offsetLast = $setConnection['SetConnection']['id'] == $ts[count($ts) - 1]['SetConnection']['id'] ? 1 : 0;
							while ($a < 11 + $offsetLast) {
								if ($i - $a > 0) {
									array_unshift($this->TsumegoNavigationButtons->previous, TsumegoButton::constructFromSetConnection($ts[$i - $a], $tsumegoStatusMap));
								}
								$a++;
							}
						}
						if ((count($this->TsumegoNavigationButtons->previous) < 5 || $t['Tsumego']['id'] == $ts[5]['SetConnection']['tsumego_id']) && $ts[0]['SetConnection']['tsumego_id'] != $t['Tsumego']['id']) {
							$tsNextAdjust = count($this->TsumegoNavigationButtons->next);
							$this->TsumegoNavigationButtons->next = [];
							$b = 1;
							while ($b <= $tsNextAdjust) {
								if ($i + $b + 1 < count($ts)) {
									$this->TsumegoNavigationButtons->next[] = TsumegoButton::constructFromSetConnection($ts[$i + $b], $tsumegoStatusMap);
									if ($b == 1) {
										$nextTsumegoID = $ts[$i + $b]['SetConnection']['tsumego_id'];
									}
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
			$fav = $this->Favorite->find('all', ['order' => 'created', 'direction' => 'DESC', 'conditions' => ['user_id' => Auth::getUserID()]]);
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
							$this->TsumegoNavigationButtons->previous [] = TsumegoButton::constructFromSetConnection($ts[$i - $a], $tsumegoStatusMap);
							if ($a == 1) {
								$previousTsumegoID = $ts[$i - $a]['Tsumego']['id'];
							}
						}
						$a--;
					}
					$bMax = 10 - count($this->TsumegoNavigationButtons->previous);
					$b = 1;
					if ($ts[0]['Tsumego']['id'] == $t['Tsumego']['id']) {
						$bMax++;
					}
					while ($b <= $bMax) {
						if ($i + $b < count($ts)) {
							$this->TsumegoNavigationButtons->next [] = TsumegoButton::constructFromSetConnection($ts[$i + $b], $tsumegoStatusMap);
							if ($b == 1) {
								$nextTsumegoID = $ts[$i + $b]['Tsumego']['id'];
							}
						}
						$b++;
					}
					if (count($this->TsumegoNavigationButtons->next) < 5 || $t['Tsumego']['id'] == $ts[count($ts) - 6]['Tsumego']['id']) {
						$this->TsumegoNavigationButtons->previous = [];
						$a = 5 + (5 - count($this->TsumegoNavigationButtons->next));
						$a++;
						while ($a > 0) {
							if ($i - $a >= 0) {
								$this->TsumegoNavigationButtons->previous [] = TsumegoButton::constructFromSetConnection($ts[$i - $a], $tsumegoStatusMap);
								if ($a == 1) {
									$previousTsumegoID = $ts[$i - $a]['Tsumego']['id'];
								}
							}
							$a--;
						}
					}
					if ((count($this->TsumegoNavigationButtons->previous) < 5 || $t['Tsumego']['id'] == $ts[5]['Tsumego']['id']) && $ts[0]['Tsumego']['id'] != $t['Tsumego']['id']) {
						$tsNextAdjust = count($this->TsumegoNavigationButtons->next) + 1;
						$this->TsumegoNavigationButtons->next = [];
						$b = 1;
						while ($b <= $tsNextAdjust) {
							if ($i + $b < count($ts)) {
								$this->TsumegoNavigationButtons->next [] = TsumegoButton::constructFromSetConnection($ts[$i + $b], $tsumegoStatusMap);
								if ($b == 1) {
									$nextTsumegoID = $ts[$i + $b]['Tsumego']['id'];
								}
							}
							$b++;
						}
					}
				}
			}
			$inFavorite = '?favorite=1';
		}
		if ($query == 'difficulty' || $query == 'tags') {
			if ($ts[0]) {
				$this->TsumegoNavigationButtons->first = TsumegoButton::constructFromTsumego($ts[0], $tsumegoStatusMap);
			}
			$isInArray = -1;
			$tsBackCount = count($this->TsumegoNavigationButtons->previous);

			for ($i = 0; $i < $tsBackCount; $i++) {
				if ($this->TsumegoNavigationButtons->previous[$i]['Tsumego']['id'] == $this->TsumegoNavigationButtons->first['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($this->TsumegoNavigationButtons->previous[$isInArray]);
				$this->TsumegoNavigationButtons->previous = array_values($this->TsumegoNavigationButtons->previous);
			}
			$newUT = $this->findUt($ts[0]['Tsumego']['id'], $tsumegoStatusMap);
			if (!isset($newUT['TsumegoStatus']['status'])) {
				$newUT['TsumegoStatus']['status'] = 'N';
			}
			if ($t['Tsumego']['id'] == $this->TsumegoNavigationButtons->first['Tsumego']['id']) {
				$this->TsumegoNavigationButtons->first = null;
			}

			if (count($ts) > 1 && $ts[count($ts) - 1]['SetConnection']['id'] != $setConnection['SetConnection']['id']) {
				$this->TsumegoNavigationButtons->last = TsumegoButton::constructFromSetConnection($ts[count($ts) - 1], $tsumegoStatusMap);
			}
			$isInArray = -1;
			$tsNextCount = count($this->TsumegoNavigationButtons->next);

			for ($i = 0; $i < $tsNextCount; $i++) {
				if ($this->TsumegoNavigationButtons->next[$i]['Tsumego']['id'] == $this->TsumegoNavigationButtons->last['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($this->TsumegoNavigationButtons->next[$isInArray]);
				$this->TsumegoNavigationButtons->next = array_values($this->TsumegoNavigationButtons->next);
			}
			$newUT = $this->findUt($ts[count($ts) - 1]['Tsumego']['id'], $tsumegoStatusMap);
		} elseif ($query == 'topics' && !$inFavorite) {
			$this->TsumegoNavigationButtons->first = TsumegoButton::constructFromSetConnection($ts[0], $tsumegoStatusMap);
			$isInArray = -1;
			$tsBackCount = count($this->TsumegoNavigationButtons->previous);

			for ($i = 0; $i < $tsBackCount; $i++) {
				if ($this->TsumegoNavigationButtons->previous[$i]['Tsumego']['id'] == $this->TsumegoNavigationButtons->first['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($this->TsumegoNavigationButtons->previous[$isInArray]);
				$this->TsumegoNavigationButtons->previous = array_values($this->TsumegoNavigationButtons->previous);
			}
			if ($setConnection['SetConnection']['id'] == $this->TsumegoNavigationButtons->first['SetConnection']['id']) {
				$this->TsumegoNavigationButtons->first = null;
			}
			//tsLast
			$this->TsumegoNavigationButtons->last = TsumegoButton::constructFromSetConnection($ts[count($ts) - 1], $tsumegoStatusMap);
			$isInArray = -1;
			$tsNextCount = count($this->TsumegoNavigationButtons->next);

			for ($i = 0; $i < $tsNextCount; $i++) {
				if ($this->TsumegoNavigationButtons->next[$i]['Tsumego']['id'] == $this->TsumegoNavigationButtons->last['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($this->TsumegoNavigationButtons->next[$isInArray]);
				$this->TsumegoNavigationButtons->next = array_values($this->TsumegoNavigationButtons->next);
			}
		} elseif ($inFavorite) {
			//tsFirst
			$this->TsumegoNavigationButtons->first = TsumegoButton::constructFromTsumego($this->Tsumego->findById($fav[0]['Favorite']['tsumego_id']), $tsumegoStatusMap);
			$isInArray = -1;
			$tsBackCount = count($this->TsumegoNavigationButtons->previous);

			for ($i = 0; $i < $tsBackCount; $i++) {
				if ($this->TsumegoNavigationButtons->previous[$i]['Tsumego']['id'] == $this->TsumegoNavigationButtons->first['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($this->TsumegoNavigationButtons->previous[$isInArray]);
				$this->TsumegoNavigationButtons->previous = array_values($this->TsumegoNavigationButtons->previous);
			}
			if ($t['Tsumego']['id'] == $this->TsumegoNavigationButtons->first['Tsumego']['id']) {
				$lastInFav = -1;
			}

			$this->TsumegoNavigationButtons->last = TsumegoButton::constructFromTsumego($this->Tsumego->findById($fav[count($fav) - 1]['Favorite']['tsumego_id']), $tsumegoStatusMap);
			$isInArray = -1;
			$tsNextCount = count($this->TsumegoNavigationButtons->next);

			for ($i = 0; $i < $tsNextCount; $i++) {
				if ($this->TsumegoNavigationButtons->next[$i]['Tsumego']['id'] == $this->TsumegoNavigationButtons->last['Tsumego']['id']) {
					$isInArray = $i;
				}
			}
			if ($isInArray != -1) {
				unset($this->TsumegoNavigationButtons->next[$isInArray]);
				$this->TsumegoNavigationButtons->next = array_values($this->TsumegoNavigationButtons->next);
			}
			if ($t['Tsumego']['id'] == $this->TsumegoNavigationButtons->last['Tsumego']['id']) {
				$lastInFav = 1;
			}
		}

		if ($setConnection['SetConnection']['id'] == $this->TsumegoNavigationButtons->last['SetConnection']['id']) {
			$this->TsumegoNavigationButtons->last = null;
		}
		if (Auth::isLoggedIn()) {
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

		$this->TsumegoNavigationButtons->current = TsumegoButton::constructFromSetConnection($setConnection, $tsumegoStatusMap);
		$navi = $this->TsumegoNavigationButtons->combine();

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

		if (Auth::getWithDefault('health', 0) >= 8) {
			$fullHeart = 'heart1small';
			$emptyHeart = 'heart2small';
		} else {
			$fullHeart = 'heart1';
			$emptyHeart = 'heart2';
		}
		if (Auth::isLoggedIn()) {
			$this->set('sprintEnabled', Auth::getUser()['sprint']);
			$this->set('intuitionEnabled', Auth::getUser()['intuition']);
			$this->set('rejuvenationEnabled', Auth::getUser()['rejuvenation']);
			$this->set('refinementEnabled', Auth::getUser()['refinement']);
			if (Auth::getUser()['reuse4'] == 1) {
				$dailyMaximum = true;
			}
			if (Auth::getUser()['reuse5'] == 1) {
				$suspiciousBehavior = true;
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

		$hasAnyFavorite = $this->Favorite->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]);
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

		/* TODO: I don't understand what is this piece of code trying to do, but it is getting stuck in an infinite loop
		while (!$refinementPublic) {
			$scRefinement = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $refinementT[$refinementPublicCounter]['Tsumego']['id']]]);
			$setScRefinement = $this->Set->findById($scRefinement['SetConnection']['set_id']);
			if ($setScRefinement['Set']['public'] == 1 && $setScRefinement['Set']['premium'] != 1) {
				$refinementPublic = true;
			} else {
				$refinementPublicCounter++;
			}
		}*/
		$activate = true;
		if (Auth::isLoggedIn()) {
			if (Auth::hasPremium() || Auth::getUser()['level'] >= 50) {
				if (Auth::getUser()['potion'] != -69) {
					if (Auth::getUser()['health'] - Auth::getUser()['damage'] <= 0) {
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
				$this->updateXP(Auth::getUserID(), $achievementUpdate);
			}
		}

		$admins = $this->User->find('all', ['conditions' => ['isAdmin' => 1]]);
		if (Auth::isInRatingMode() || Auth::isInTimeMode()) {
			$this->Session->write('title', 'Tsumego Hero');
		}
		if ($isSandbox) {
			$t['Tsumego']['userWin'] = 0;
		}

		$crs = 0;

		if (Auth::isInLevelMode()) {
			$this->Session->write('page', 'level mode');
		} elseif (Auth::isInRatingMode()) {
			$this->Session->write('page', 'rating mode');
		} elseif (Auth::isInTimeMode()) {
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

		if (!Auth::isInTimeMode()) {
			if (!isset($previousSetConnectionID) && isset($previousTsumegoID)) {
				$previousSetConnectionID = self::getMatchingSetConnectionOfOtherTsumego($previousTsumegoID, $set['Set']['id']);
			}
			if (!isset($nextSetConnectionID) && isset($nextTsumegoID)) {
				$nextSetConnectionID = self::getMatchingSetConnectionOfOtherTsumego($nextTsumegoID, $set['Set']['id']);
			}
		} else {
			$nextTsumegoID = $this->TimeMode->currentRank2['Tsumego']['id'] ?? 0;
		}
		$this->startPageUpdate();
		$startingPlayer = $this->getStartingPlayer($sgf2);

		$avActiveText = '';
		$avActiveText = '<font style="color:gray;"> (out of range)</font>';

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

		if (Auth::isLoggedIn()) {
			Auth::getUser()['name'] = $this->checkPicture(Auth::getUser());
		}
		$tags = $this->getTags($id);
		$tags = $this->checkTagDuplicates($tags);

		$allTags = $this->getAllTags($tags);
		$popularTags = $this->getPopularTags($tags);
		$uc = $this->UserContribution->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]);
		$hasRevelation = false;
		if ($uc) {
			$hasRevelation = $uc['UserContribution']['reward3'];
		}
		if (Auth::hasPremium() && Auth::getUser()['level'] >= 100) {
			$hasRevelation = true;
		}

		$sgfProposal = $this->Sgf->find('first', ['conditions' => ['tsumego_id' => $id, 'version' => 0, 'user_id' => Auth::getUserID()]]);
		$isAllowedToContribute = false;
		$isAllowedToContribute2 = false;
		if (Auth::isLoggedIn()) {
			if (Auth::getUser()['level'] >= 40) {
				$isAllowedToContribute = true;
			} elseif (Auth::getUser()['rating'] >= 1500) {
				$isAllowedToContribute = true;
			}

			if (Auth::isAdmin()) {
				$isAllowedToContribute2 = true;
			} else {
				$tagsToCheck = $this->Tag->find('all', ['limit' => 20, 'order' => 'created DESC', 'conditions' => ['user_id' => Auth::getUserID()]]);
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

		$isTSUMEGOinFAVORITE = $this->Favorite->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $id]]);

		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			if ($ts[$i]['SetConnection']['id'] == $setConnectionID) {
				$indexOfCurrentTsumegoInts = $i;
				break;
			}
		}

		if (!isset($previousSetConnectionID) && isset($indexOfCurrentTsumegoInts) && $indexOfCurrentTsumegoInts > 0) {
			$previousSetConnectionID = $ts[$indexOfCurrentTsumegoInts - 1]['SetConnection']['id'];
		}
		$previousLink = self::tsumegoOrSetLink(isset($previousSetConnectionID) ? $previousSetConnectionID : null, isset($previousTsumegoID) ? $previousTsumegoID : null, $set['Set']['id']);
		if (!isset($nextSetConnectionID) && isset($indexOfCurrentTsumegoInts) && $tsCount > $indexOfCurrentTsumegoInts + 1) {
			$nextSetConnectionID = $ts[$indexOfCurrentTsumegoInts + 1]['SetConnection']['id'];
		}
		$nextLink = self::tsumegoOrSetLink(isset($nextSetConnectionID) ? $nextSetConnectionID : null, isset($nextTsumegoID) ? $nextTsumegoID : null, $set['Set']['id']);

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
		if (isset($activityValue)) {
			$this->set('activityValue', $activityValue);
		}
		$this->set('avActiveText', $avActiveText);
		$this->set('nothingInRange', $nothingInRange);
		$this->set('tRank', $tRank);
		$this->set('sgf', $sgf);
		$this->set('sgf2', $sgf2);
		$this->set('sandboxComment2', $sandboxComment2);
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
		if (Auth::isLoggedIn() && Auth::getUser()['nextlvl'] > 0) {
			$this->set('barPercent', Auth::getUser()['xp'] / Auth::getUser()['nextlvl'] * 100);
		} else {
			$this->set('barPercent', 0);
		}
		$this->set('t', $t);
		$this->set('score1', $score1);
		$this->set('score2', $score2);
		$this->set('navi', $navi);
		$this->set('previousLink', $previousLink);
		$this->set('nextLink', $nextLink);
		$this->set('hash', $hash);
		$this->set('nextMode', $nextMode);
		$this->set('rating', Auth::getWithDefault('rating', 0));
		$this->set('eloScore', $eloScore);
		$this->set('eloScore2', $eloScore2);
		$this->set('eloScoreRounded', $eloScoreRounded);
		$this->set('eloScore2Rounded', $eloScore2Rounded);
		$this->set('activate', $activate);
		$this->set('tsumegoElo', $t['Tsumego']['rating']);
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
		$this->set('timeMode', Auth::isInTimeMode() ? (array) $this->TimeMode : null);
		$this->set('mode3ScoreArray', $mode3ScoreArray);
		$this->set('potionAlert', $potionAlert);
		$this->set('file', $file);
		$this->set('ui', $ui);
		$this->set('requestProblem', $requestProblem);
		$this->set('alternative_response', $t['Tsumego']['alternative_response']);
		$this->set('passEnabled', $t['Tsumego']['pass']);
		$this->set('set_duplicate', $t['Tsumego']['duplicate']);
		$this->set('achievementUpdate', $achievementUpdate);
		$this->set('setConnection', $setConnection);
		$this->set('setConnections', $setConnections);
		$this->set('tooltipSgfs', $tooltipSgfs);
		$this->set('tooltipInfo', $tooltipInfo);
		$this->set('tooltipBoardSize', $tooltipBoardSize);
		if (isset($this->params['url']['requestSolution'])) {
			$this->set('requestSolution', AdminActivityUtil::requestSolution($id));
		}
		$this->set('startingPlayer', $startingPlayer);
		$this->set('tv', $tsumegoVariant);
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
		return null;
	}

	private function getPopularTags($tags) {
		// for some reason, this returns null in the test environment
		$json = json_decode(file_get_contents('json/popular_tags.json')) ?: [];
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

		if (Auth::isLoggedIn()) {
			if (!Auth::hasPremium()) {
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
			$ts = TsumegoUtil::collectTsumegosFromSet($sets[$h]['Set']['id']);
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
					$t['Tsumego']['rating'] = $ta[$i]['TsumegoAttempt']['tsumego_elo'];
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
		$ut['TsumegoStatus']['user_id'] = Auth::getUserID();
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
				$a = substr($c, 0, (int) $n2xx[0]);
				$cx = substr($c, (int) $n2xx[0], (int) $n2xx[1] - (int) $n2xx[0] + 1);
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

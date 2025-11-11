<?php

App::uses('SetNavigationButtonsInput', 'Utility');

class Play {
	public function __construct($setFunction) {
		$this->setFunction = $setFunction;
	}

	public function getTsumegoStatus(array $tsumego): string {
		if (Auth::isLoggedIn()) {
			$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
				'user_id' => Auth::getUserID(),
				'tsumego_id' => $tsumego['Tsumego']['id']]]);
			if (!$status) {
				return 'V';
			}
			return $status['TsumegoStatus']['status'];
		}
		return 'V';
	}

	public function play(int $setConnectionID, $params): mixed {
		CakeSession::write('page', 'play');

		$anzahl2 = 0;
		$nextMode = null;
		$rejuvenation = false;
		$doublexp = null;
		$dailyMaximum = false;
		$suspiciousBehavior = false;
		$half = '';
		$inFavorite = isset($params['url']['favorite']);
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

		$currentSetConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		if (!$currentSetConnection) {
			throw new AppException("Set connection " . $setConnectionID . " wasn't found in the database.");
		}
		$id = $currentSetConnection['SetConnection']['tsumego_id'];

		$hasPremium = Auth::hasPremium();
		$swp = ClassRegistry::init('Set')->find('all', ['conditions' => ['premium' => 1]]) ?: [];
		foreach ($swp as $item) {
			$setsWithPremium[] = $item['Set']['id'];
		}

		$setConnections = TsumegoUtil::getSetConnectionsWithTitles($id);
		$set = ClassRegistry::init('Set')->findById($currentSetConnection['SetConnection']['set_id']);

		$tsumegoVariant = ClassRegistry::init('TsumegoVariant')->find('first', ['conditions' => ['tsumego_id' => $id]]);

		if (isset($params['url']['potionAlert'])) {
			$potionAlert = true;
		}

		$searchPatameters = SearchParameters::process();
		$query = $searchPatameters[0];
		$collectionSize = $searchPatameters[1];
		$search1 = $searchPatameters[2];
		$search2 = $searchPatameters[3];
		$search3 = $searchPatameters[4];

		if (isset($params['url']['search'])) {
			if ($params['url']['search'] == 'topics') {
				$query = $params['url']['search'];
				$_COOKIE['query'] = $params['url']['search'];
				SearchParameters::process();
			}
		}

		if (isset($params['url']['refresh'])) {
			$refresh = $params['url']['refresh'];
		}

		if (Auth::isLoggedIn()) {
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

				$range = ClassRegistry::init('Tsumego')->find('all', [
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
						$rafSc = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $range[$ratingFoundCounter]['Tsumego']['id']]]);
						if (!$rafSc) {
							$ratingFoundCounter++;

							continue;
						}
						$raS = ClassRegistry::init('Set')->findById($rafSc['SetConnection']['set_id']);
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
				if (!empty($_COOKIE['misplays'])) {
					$ratingCookieMisplay = true;
				}
				if (!empty($_COOKIE['ratingModePreId']) && !$ratingCookieScore && !$ratingCookieMisplay) {
					$nextMode = ClassRegistry::init('Tsumego')->findById($_COOKIE['ratingModePreId']);
					unset($_COOKIE['ratingModePreId']);
				}
				$id = $nextMode['Tsumego']['id'];
			}
		}

		$t = ClassRegistry::init('Tsumego')->findById($id);//the tsumego

		if (Auth::isLoggedIn()) {
			$activityValue = $this->getActivityValue(Auth::getUserID(), $t['Tsumego']['id']);
		}

		if ($t['Tsumego']['rating']) {
			$tRank = Rating::getReadableRankFromRating($t['Tsumego']['rating']);
		}

		$fSet = ClassRegistry::init('Set')->find('first', ['conditions' => ['id' => $t['Tsumego']['set_id']]]);
		if (!$fSet) {
			$fSet = ClassRegistry::init('Set')->findById(1);
		}
		if ($t == null) {
			$t = ClassRegistry::init('Tsumego')->findById(CakeSession::read('lastVisit'));
		}

		CakeSession::write('lastVisit', $id);

		if (Auth::isLoggedIn()) {
			if (!empty($this->data)) {
				if (isset($this->data['Comment']['status']) && !isset($this->data['Study2'])) {
					$adminActivity = [];
					$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
					$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
					$adminActivity['AdminActivity']['file'] = $t['Tsumego']['num'];
					$adminActivity['AdminActivity']['answer'] = $this->data['Comment']['status'];
					ClassRegistry::init('AdminActivity')->save($adminActivity);
					ClassRegistry::init('Comment')->save($this->data, true);
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
						ClassRegistry::init('Tsumego')->save($t, true);
					}

					if ($this->data['Comment']['deleteProblem'] == 'delete') {
						$adminActivity['AdminActivity']['answer'] = 'Problem deleted. (' . $t['Tsumego']['set_id'] . '-' . $t['Tsumego']['id'] . ')';
						$adminActivity['AdminActivity']['file'] = '/delete';
					}
					if ($this->data['Comment']['deleteTag'] != null) {
						$tagsToDelete = ClassRegistry::init('Tag')->find('all', ['conditions' => ['tsumego_id' => $id]]);
						if (!$tagsToDelete) {
							$tagsToDelete = [];
						}
						$tagsToDeleteCount = count($tagsToDelete);
						for ($i = 0; $i < $tagsToDeleteCount; $i++) {
							$tagNameForDelete = ClassRegistry::init('TagName')->findById($tagsToDelete[$i]['Tag']['tag_name_id']);
							if ($tagNameForDelete['TagName']['name'] == $this->data['Comment']['deleteTag']) {
								ClassRegistry::init('Tag')->delete($tagsToDelete[$i]['Tag']['id']);
							}
						}
					}
					ClassRegistry::init('AdminActivity')->save($adminActivity);
				} elseif (isset($this->data['Study'])) {
					$tsumegoVariant['TsumegoVariant']['answer1'] = $this->data['Study']['study1'];
					$tsumegoVariant['TsumegoVariant']['answer2'] = $this->data['Study']['study2'];
					$tsumegoVariant['TsumegoVariant']['answer3'] = $this->data['Study']['study3'];
					$tsumegoVariant['TsumegoVariant']['answer4'] = $this->data['Study']['study4'];
					$tsumegoVariant['TsumegoVariant']['explanation'] = $this->data['Study']['explanation'];
					$tsumegoVariant['TsumegoVariant']['numAnswer'] = $this->data['Study']['studyCorrect'];
					ClassRegistry::init('TsumegoVariant')->save($tsumegoVariant);
				} elseif (isset($this->data['Study2'])) {
					$tsumegoVariant['TsumegoVariant']['winner'] = $this->data['Study2']['winner'];
					$tsumegoVariant['TsumegoVariant']['answer1'] = $this->data['Study2']['answer1'];
					$tsumegoVariant['TsumegoVariant']['answer2'] = $this->data['Study2']['answer2'];
					$tsumegoVariant['TsumegoVariant']['answer3'] = $this->data['Study2']['answer3'];
					ClassRegistry::init('TsumegoVariant')->save($tsumegoVariant);
				} elseif (isset($this->data['Settings'])) {
					if ($this->data['Settings']['r39'] == 'on' && $t['Tsumego']['alternative_response'] != 1) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity2['AdminActivity']['file'] = 'settings';
						$adminActivity2['AdminActivity']['answer'] = 'Turned on alternative response mode';
						ClassRegistry::init('AdminActivity')->create();
						ClassRegistry::init('AdminActivity')->save($adminActivity2);
					}
					if ($this->data['Settings']['r39'] == 'off' && $t['Tsumego']['alternative_response'] != 0) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity2['AdminActivity']['file'] = 'settings';
						$adminActivity2['AdminActivity']['answer'] = 'Turned off alternative response mode';
						ClassRegistry::init('AdminActivity')->create();
						ClassRegistry::init('AdminActivity')->save($adminActivity2);
					}
					if ($this->data['Settings']['r43'] == 'no' && $t['Tsumego']['pass'] != 0) {
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Disabled passing';
						ClassRegistry::init('AdminActivity')->create();
						ClassRegistry::init('AdminActivity')->save($adminActivity);
					}
					if ($this->data['Settings']['r43'] == 'yes' && $t['Tsumego']['pass'] != 1) {
						$adminActivity = [];
						$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity['AdminActivity']['file'] = 'settings';
						$adminActivity['AdminActivity']['answer'] = 'Enabled passing';
						ClassRegistry::init('AdminActivity')->create();
						ClassRegistry::init('AdminActivity')->save($adminActivity);
					}
					if ($this->data['Settings']['r41'] == 'yes' && $tsumegoVariant == null) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity2['AdminActivity']['file'] = 'settings';
						$adminActivity2['AdminActivity']['answer'] = 'Changed problem type to multiple choice';
						ClassRegistry::init('AdminActivity')->create();
						ClassRegistry::init('AdminActivity')->save($adminActivity2);
						$tv1 = [];
						$tv1['TsumegoVariant']['tsumego_id'] = $id;
						$tv1['TsumegoVariant']['type'] = 'multiple_choice';
						$tv1['TsumegoVariant']['answer1'] = 'Black is dead';
						$tv1['TsumegoVariant']['answer2'] = 'White is dead';
						$tv1['TsumegoVariant']['answer3'] = 'Ko';
						$tv1['TsumegoVariant']['answer4'] = 'Seki';
						$tv1['TsumegoVariant']['numAnswer'] = '1';
						ClassRegistry::init('TsumegoVariant')->create();
						ClassRegistry::init('TsumegoVariant')->save($tv1);
					}
					if ($this->data['Settings']['r41'] == 'no' && $tsumegoVariant != null) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity2['AdminActivity']['file'] = 'settings';
						$adminActivity2['AdminActivity']['answer'] = 'Deleted multiple choice problem type';
						ClassRegistry::init('AdminActivity')->create();
						ClassRegistry::init('AdminActivity')->save($adminActivity2);
						ClassRegistry::init('TsumegoVariant')->delete($tsumegoVariant['TsumegoVariant']['id']);
						$tsumegoVariant = null;
					}
					if ($this->data['Settings']['r42'] == 'yes' && $tsumegoVariant == null) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity2['AdminActivity']['file'] = 'settings';
						$adminActivity2['AdminActivity']['answer'] = 'Changed problem type to score estimating';
						ClassRegistry::init('AdminActivity')->create();
						ClassRegistry::init('AdminActivity')->save($adminActivity2);
						$tv1 = [];
						$tv1['TsumegoVariant']['tsumego_id'] = $id;
						$tv1['TsumegoVariant']['type'] = 'score_estimating';
						$tv1['TsumegoVariant']['numAnswer'] = '0';
						ClassRegistry::init('TsumegoVariant')->create();
						ClassRegistry::init('TsumegoVariant')->save($tv1);
					}
					if ($this->data['Settings']['r42'] == 'no' && $tsumegoVariant != null) {
						$adminActivity2 = [];
						$adminActivity2['AdminActivity']['user_id'] = Auth::getUserID();
						$adminActivity2['AdminActivity']['tsumego_id'] = $t['Tsumego']['id'];
						$adminActivity2['AdminActivity']['file'] = 'settings';
						$adminActivity2['AdminActivity']['answer'] = 'Deleted score estimating problem type';
						ClassRegistry::init('AdminActivity')->create();
						ClassRegistry::init('AdminActivity')->save($adminActivity2);
						ClassRegistry::init('TsumegoVariant')->delete($tsumegoVariant['TsumegoVariant']['id']);
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
						ClassRegistry::init('Tsumego')->save($t, true);
					}
				} else {
					if ($this->data['Comment']['user_id'] != 33) {
						ClassRegistry::init('Comment')->create();
						if ($this->checkCommentValid(Auth::getUserID())) {
							ClassRegistry::init('Comment')->save($this->data, true);
						}
					}
				}
				($this->setFunction)('formRedirect', true);
			}
		}
		if (Auth::isAdmin()) {
			$aad = ClassRegistry::init('AdminActivity')->find('first', ['order' => 'id DESC']);
			if ($aad && $aad['AdminActivity']['file'] == '/delete') {
				($this->setFunction)('deleteProblem2', true);
			}
		}

		if (isset($params['url']['deleteComment'])) {
			$deleteComment = ClassRegistry::init('Comment')->findById($params['url']['deleteComment']);
			if (isset($params['url']['changeComment'])) {
				if ($params['url']['changeComment'] == 1) {
					$deleteComment['Comment']['status'] = 97;
				} elseif ($params['url']['changeComment'] == 2) {
					$deleteComment['Comment']['status'] = 98;
				} elseif ($params['url']['changeComment'] == 3) {
					$deleteComment['Comment']['status'] = 96;
				} elseif ($params['url']['changeComment'] == 4) {
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
			ClassRegistry::init('AdminActivity')->save($adminActivity);
			ClassRegistry::init('Comment')->save($deleteComment);
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
			$cox = count(ClassRegistry::init('Comment')->find('all', ['conditions' => (['tsumego_id' => $id])]) ?: []);
			if (empty($fSet['Set']['title2'])) {
				$title2 = '';
			} else {
				$title2 = '-';
			}
			$file_name = $fSet['Set']['title'] . $title2 . $fSet['Set']['title2'] . '-' . $t['Tsumego']['num'] . '-' . $cox . '.sgf';
			$sgfComment = [];
			ClassRegistry::init('Comment')->create();
			$sgfComment['user_id'] = Auth::getUserID();
			$sgfComment['tsumego_id'] = $t['Tsumego']['id'];
			$file_name = str_replace('#', 'num', $file_name);
			$sgfComment['message'] = '<a href="/files/ul1/' . $file_name . '">SGF</a>';
			$sgfComment['created'] = date('Y-m-d H:i:s');
			ClassRegistry::init('Comment')->save($sgfComment);
			if (empty($errors) == true) {
				$uploadfile = $_SERVER['DOCUMENT_ROOT'] . '/app/webroot/files/ul1/' . $file_name;
				move_uploaded_file($file_tmp, $uploadfile);
			}
		}
		$t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty'] * $fSet['Set']['multiplier']);

		if (Auth::isLoggedIn()) {
			$pd = ClassRegistry::init('ProgressDeletion')->find('all', [
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
		$sandboxSets = ClassRegistry::init('Set')->find('all', ['conditions' => ['public' => 0]]);
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

		$co = ClassRegistry::init('Comment')->find('all', ['conditions' => (['tsumego_id' => $id])]);
		if (!$co) {
			$co = [];
		}
		$counter1 = 1;
		$coCount = count($co);
		for ($i = 0; $i < $coCount; $i++) {
			if (strpos($co[$i]['Comment']['message'], '<a href="/files/ul1/') === false) {
				$co[$i]['Comment']['message'] = htmlspecialchars($co[$i]['Comment']['message']);
			}
			$cou = ClassRegistry::init('User')->findById($co[$i]['Comment']['user_id']);
			if ($cou == null) {
				$cou['User']['name'] = '[deleted user]';
			}
			$co[$i]['Comment']['user'] = AppController::checkPicture($cou);
			$cad = ClassRegistry::init('User')->findById($co[$i]['Comment']['admin_id']);
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
			$array = TsumegosController::commentCoordinates($co[$i]['Comment']['message'], $counter1, true);
			$co[$i]['Comment']['message'] = $array[0];
			array_push($commentCoordinates, $array[1]);
			$counter1++;
		}

		$tsumegoStatus = Play::getTsumegoStatus($t);
		if (Auth::isInLevelMode()) {
			if (Auth::isLoggedIn()) {
				$tsumegoStatusMap = TsumegoUtil::getMapForCurrentUser();
				$utsMapx = array_count_values($tsumegoStatusMap);
				$correctCounter = $utsMapx['C'] + $utsMapx['S'] + $utsMapx['W'];
				Auth::getUser()['solved'] = $correctCounter;
			}
		}

		if ($tsumegoStatus == 'G') {
			$goldenTsumego = true;
		}

		if (isset($_COOKIE['favorite']) && $_COOKIE['favorite'] != '0') {
			if (Auth::isLoggedIn()) {
				if ($_COOKIE['favorite'] > 0) {
					$fav = ClassRegistry::init('Favorite')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $_COOKIE['favorite']]]);
					if ($fav == null) {
						$fav = [];
						$fav['Favorite']['user_id'] = Auth::getUserID();
						$fav['Favorite']['tsumego_id'] = $_COOKIE['favorite'];
						$fav['Favorite']['created'] = date('Y-m-d H:i:s');
						ClassRegistry::init('Favorite')->create();
						ClassRegistry::init('Favorite')->save($fav);
					}
				} else {
					$favId = $_COOKIE['favorite'] * -1;
					$favDel = ClassRegistry::init('Favorite')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $favId]]);
					ClassRegistry::init('Favorite')->delete($favDel['Favorite']['id']);
				}
				unset($_COOKIE['favorite']);
			}
		}
		if (isset($_COOKIE['TimeModeAttempt']) && $_COOKIE['TimeModeAttempt'] != '0') {
			$drCookie = AppController::decrypt($_COOKIE['TimeModeAttempt']);
			$drCookie2 = explode('-', $drCookie);
			$_COOKIE['TimeModeAttempt'] = $drCookie2[1];
		}

		if (Auth::isLoggedIn() && Auth::getUser()['potion'] >= 15) {
			AppController::setPotionCondition();
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
			$_COOKIE['misplays'] = 0;
			unset($_COOKIE['rejuvenationx']);
		}

		Util::setCookie('previousTsumegoID', $id);
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
					($this->setFunction)('sprintActivated', true);
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
						$refinementUT = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
						if ($tsumegoStatus == null) {
							ClassRegistry::init('TsumegoStatus')->create();
							$refinementUT['TsumegoStatus']['user_id'] = Auth::getUserID();
							$refinementUT['TsumegoStatus']['tsumego_id'] = $id;
						}
						$refinementUT['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
						$refinementUT['TsumegoStatus']['status'] = 'G';
						ClassRegistry::init('TsumegoStatus')->save($refinementUT);
						$tsumegoStatusMap[$id] = 'G';
						$tsumegoStatus = 'G';
						$goldenTsumego = true;
						Auth::getUser()['usedRefinement'] = 1;
					}
				} else {
					$resetRefinement = TsumegosController::findUt($id, $tsumegoStatusMap);
					if ($resetRefinement != null) {
						$resetRefinement['TsumegoStatus']['status'] = 'V';
						$testUt = ClassRegistry::init('TsumegoStatus')->find('first', [
							'conditions' => [
								'tsumego_id' => $resetRefinement['TsumegoStatus']['tsumego_id'],
								'user_id' => $resetRefinement['TsumegoStatus']['user_id'],
							],
						]);
						$resetRefinement['TsumegoStatus']['id'] = $testUt['TsumegoStatus']['id'];
						//ClassRegistry::init('TsumegoStatus')->save($resetRefinement);
						//CakeSession::read('loggedInUser.uts')[$resetRefinement['TsumegoStatus']['tsumego_id']] = $resetRefinement['TsumegoStatus']['status'];
						//$tsumegoStatusMap[$refinementUT['TsumegoStatus']['tsumego_id']] = $resetRefinement['TsumegoStatus']['status'];
					}
					if (!$tsumegoStatus) {
						$tsumegoStatus = $resetRefinement;
					}
					$goldenTsumego = false;
				}
				Auth::getUser()['refinement'] = 0;
				unset($_COOKIE['refinement']);
			}
		}

		if ($rejuvenation) {
			$utr = ClassRegistry::init('TsumegoStatus')->find('all', ['conditions' => ['status' => 'F', 'user_id' => Auth::getUserID()]]) ?: [];
			foreach ($utr as $failedStatus) {
				$failedStatus['TsumegoStatus']['status'] = 'V';
				ClassRegistry::init('TsumegoStatus')->save($failedStatus);
				$tsumegoStatusMap[$failedStatus['TsumegoStatus']['tsumego_id']] = 'V';
			}

			$utrx = ClassRegistry::init('TsumegoStatus')->find('all', ['conditions' => ['status' => 'X', 'user_id' => Auth::getUserID()]]) ?: [];
			foreach ($utrx as $failedStatus) {
				$failedStatus['TsumegoStatus']['status'] = 'W';
				ClassRegistry::init('TsumegoStatus')->save($failedStatus);
				$tsumegoStatusMap[$failedStatus['TsumegoStatus']['tsumego_id']] = 'W';
			}
		}

		if (isset($_COOKIE['reputation']) && $_COOKIE['reputation'] != '0') {
			$reputation = [];
			$reputation['Reputation']['user_id'] = Auth::getUserID();
			$reputation['Reputation']['tsumego_id'] = abs($_COOKIE['reputation']);
			if ($_COOKIE['reputation'] > 0) {
				$reputation['Reputation']['value'] = 1;
			} else {
				$reputation['Reputation']['value'] = -1;
			}
			ClassRegistry::init('Reputation')->create();
			ClassRegistry::init('Reputation')->save($reputation);
			unset($_COOKIE['reputation']);
		}

		if (Auth::isLoggedIn()) {
			$userDate = new DateTime(Auth::getUser()['created']);
			$userDate = $userDate->format('Y-m-d');
			if ($userDate != date('Y-m-d')) {
				Auth::getUser()['created'] = date('Y-m-d H:i:s');
				Auth::saveUser();
				AppController::deleteUnusedStatuses(Auth::getUserID());
			}
		}

		$amountOfOtherCollection = count(TsumegoUtil::collectTsumegosFromSet($set['Set']['id']));
		$search3ids = [];

		foreach ($search3 as $item) {
			$search3ids[] = ClassRegistry::init('TagName')->findByName($item)['TagName']['id'];
		}

		$sgf = [];
		$sgfdb = ClassRegistry::init('Sgf')->find('first', ['order' => 'id DESC', 'conditions' => ['tsumego_id' => $id]]);
		if (!$sgfdb) {
			$sgf['Sgf']['sgf'] = Constants::$SGF_PLACEHOLDER;
			$sgf['Sgf']['tsumego_id'] = $id;
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
			($this->setFunction)('multipleChoiceTriangles', count($trX));
			($this->setFunction)('multipleChoiceSquares', count($sqX));
		}
		$sgf2 = str_replace("\n", ' ', $sgf['Sgf']['sgf']);
		$sgf['Sgf']['sgf'] = str_replace("\r", '', $sgf['Sgf']['sgf']);
		$sgf['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $sgf['Sgf']['sgf']);

		$this->setConnectionsOfCurrentSet = $this->selectSetConnectionsOfCurrentSet($set, $query, $inFavorite);

		if ($query == 'difficulty') {
			$t['Tsumego']['actualNum'] = $t['Tsumego']['num'];
			$setConditions = [];
			if (count($search1) > 0) {
				$search1ids = [];
				$search1Count = count($search1);

				for ($i = 0; $i < $search1Count; $i++) {
					$search1id = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $search1[$i]]]);
					if ($search1id) {
						$search1ids[$i] = $search1id['Set']['id'];
					}
				}
				$setConditions['set_id'] = $search1ids;
			}
			$lastSet = AppController::getTsumegoElo(CakeSession::read('lastSet'));
			$ftFrom = [];
			$ftTo = [];
			$notPremiumArray = [];
			$ftFrom['rating >='] = $lastSet;
			$ftTo['rating <'] = $lastSet + 100;
			if (CakeSession::read('lastSet') == '15k') {
				$ftFrom['rating >='] = 50;
			}
			if (!$hasPremium) {
				$notPremiumArray['NOT'] = ['set_id' => $setsWithPremium];
			}
			$ts = ClassRegistry::init('Tsumego')->find('all', [
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
					$tagForTsumego = ClassRegistry::init('Tag')->find('first', [
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
				$fromTo = AppController::getPartitionRange(count($ts), $collectionSize, $partition);
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
			$queryTitle = CakeSession::read('lastSet') . ' ' . $partitionText . ' ' . $t['Tsumego']['num'] . '/' . $anzahl2;
		} elseif ($query == 'tags') {
			$t['Tsumego']['actualNum'] = $t['Tsumego']['num'];
			$setConditions = [];
			$rankConditions = [];
			$tagIds = [];
			$tagName = ClassRegistry::init('TagName')->findByName(CakeSession::read('lastSet'));

			if (count($search1) > 0) {
				$search1idxx = [];
				$search1Count = count($search1);

				for ($i = 0; $i < $search1Count; $i++) {
					$search1id = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $search1[$i]]]);
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
			$tagsx = ClassRegistry::init('Tag')->find('all', [
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
				$pTest = ClassRegistry::init('Tsumego')->find('all', ['conditions' => ['id' => $tagIds]]);
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
					$ft['rating >='] = AppController::getTsumegoElo($search2[$j]);
					$ft['rating <'] = $ft['rating >='] + 100;
					if ($search2[$j] == '15k') {
						$ft['rating >='] = 50;
					}
					array_push($fromTo, $ft);
				}
				$rankConditions['OR'] = $fromTo;
			}
			$ts = ClassRegistry::init('Tsumego')->find('all', [
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
				$fromTo = AppController::getPartitionRange(count($ts), $collectionSize, $partition);
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
			$queryTitle = CakeSession::read('lastSet') . ' ' . $partitionText . ' ' . $t['Tsumego']['num'] . '/' . $anzahl2;
		} elseif ($query == 'topics') {
			$setConnectionIds = [];
			$tsTsumegosMap = [];
			$rankConditions = [];

			if (count($search2) > 0) {
				$fromTo = [];
				$search2Count = count($search2);

				for ($i = 0; $i < $search2Count; $i++) {
					$ft = [];
					$ft['rating >='] = AppController::getTsumegoElo($search2[$i]);
					$ft['rating <'] = $ft['rating >='] + 100;
					if ($search2[$i] == '15k') {
						$ft['rating >='] = 50;
					}
					array_push($fromTo, $ft);
				}
				$rankConditions['OR'] = $fromTo;
			}

			foreach ($this->setConnectionsOfCurrentSet as $setConnection) {
				array_push($setConnectionIds, $setConnection['SetConnection']['tsumego_id']);
			}

			$tsTsumegos = ClassRegistry::init('Tsumego')->find('all', [
				'conditions' => [
					'id' => $setConnectionIds,
					$rankConditions,
				],
			]) ?: [];

			foreach ($tsTsumegos as $setTsumego) {
				$tsTsumegosMap[$setTsumego['Tsumego']['id']] = $setTsumego;
			}

			$tsBuffer = [];

			foreach ($this->setConnectionsOfCurrentSet as $setConnection) {
				$tagValid = isset($tsTsumegosMap[$setConnection['SetConnection']['tsumego_id']]);
				if ($tagValid == true) {
					if (count($search3) > 0) {
						$tagForTsumego = ClassRegistry::init('Tag')->find('first', [
							'conditions' => [
								'tsumego_id' => $setConnection['SetConnection']['tsumego_id'],
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
					$tsBuffer [] = $setConnection;
				}
			}
			$this->setConnectionsOfCurrentSet = $tsBuffer;
			if (count($this->setConnectionsOfCurrentSet) > $collectionSize) {
				$tsCount = count($this->setConnectionsOfCurrentSet);

				for ($i = 0; $i < $tsCount; $i++) {
					if ($i % $collectionSize == 0) {
						$partition++;
					}
					if ($this->setConnectionsOfCurrentSet[$i]['SetConnection']['tsumego_id'] == $t['Tsumego']['id']) {
						break;
					}
				}
				$fromTo = AppController::getPartitionRange(count($this->setConnectionsOfCurrentSet), $collectionSize, $partition);
				$ts1 = [];
				for ($i = $fromTo[0]; $i <= $fromTo[1]; $i++) {
					array_push($ts1, $this->setConnectionsOfCurrentSet[$i]);
				}
				$ts = $ts1;
				$queryTitleSets = '#' . ($partition + 1);
			}
			$anzahl2 = 1;

			foreach ($this->setConnectionsOfCurrentSet as $setConnection) {
				if ($setConnection['SetConnection']['num'] > $anzahl2) {
					$anzahl2 = $setConnection['SetConnection']['num'];
				}
			}
		}

		if ($query == 'topics') {
			CakeSession::write('title', $set['Set']['title'] . ' ' . $t['Tsumego']['num'] . '/' . $anzahl2 . ' on Tsumego Hero');
		} else {
			CakeSession::write('title', CakeSession::read('lastSet') . ' ' . $t['Tsumego']['num'] . '/' . $anzahl2 . ' on Tsumego Hero');
		}

		if ($inFavorite) {
			$inFavorite = '?favorite=1';
		}

		if ($query == 'difficulty' || $query == 'tags') {
			// TODO: this is very unoptimal transition from tsuemgos into set connections until the difficulty and tags parts gets rewritten
			if (isset($ts)) {
				foreach ($ts as $tsumego) {
					if ($setConnection = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $tsumego['Tsumego']['id']]])) {
						$this->setConnectionsOfCurrentSet[] = $setConnection;
					}
				}
			}
		}

		if (!Auth::isInTimeMode()) {
			new SetNavigationButtonsInput($this->setFunction)->execute($this->setConnectionsOfCurrentSet, $currentSetConnection, $tsumegoStatusMap);
		}

		$t['Tsumego']['status'] = $tsumegoStatus;
		if (Auth::isLoggedIn()) {
			$half = '';
			if ($tsumegoStatus == 'W' || $tsumegoStatus == 'X') {
				$t['Tsumego']['difficulty'] = ceil($t['Tsumego']['difficulty'] / 2);
				$half = '(1/2)';
			}
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
		if (isset($params['url']['orientation'])) {
			$orientation = $params['url']['orientation'];
		}
		if (isset($params['url']['playercolor'])) {
			$colorOrientation = $params['url']['playercolor'];
		}

		$checkBSize = 19;
		for ($i = 2; $i <= 19; $i++) {
			if (strpos(';' . $set['Set']['title'], $i . 'x' . $i)) {
				$checkBSize = $i;
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
			($this->setFunction)('sprintEnabled', Auth::getUser()['sprint']);
			($this->setFunction)('intuitionEnabled', Auth::getUser()['intuition']);
			($this->setFunction)('rejuvenationEnabled', Auth::getUser()['rejuvenation']);
			($this->setFunction)('refinementEnabled', Auth::getUser()['refinement']);
			if (Auth::getUser()['reuse4'] == 1) {
				$dailyMaximum = true;
			}
			if (Auth::getUser()['reuse5'] == 1) {
				$suspiciousBehavior = true;
			}
		}
		if ($isSandbox || $t['Tsumego']['set_id'] == 51) {
			($this->setFunction)('sandboxXP', $t['Tsumego']['difficulty']);
			$t['Tsumego']['difficulty2'] = $t['Tsumego']['difficulty'];
			$t['Tsumego']['difficulty'] = 10;
		}
		if ($goldenTsumego) {
			$t['Tsumego']['difficulty'] *= 8;
		}
		$refinementT = ClassRegistry::init('Tsumego')->find('all', [
			'limit' => 5000,
			'conditions' => [
				'difficulty >' => 35,
			],
		]);

		$hasAnyFavorite = ClassRegistry::init('Favorite')->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]);
		$hash = AppController::encrypt($t['Tsumego']['num'] . 'number' . $set['Set']['id']);

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

		shuffle($refinementT);

		$refinementPublicCounter = 0;

		$activate = true;
		if (Auth::isLoggedIn()) {
			if (Auth::hasPremium() || Auth::getUser()['level'] >= 50) {
				if (Auth::getUser()['potion'] != -69) {
					if (Auth::getUser()['health'] - Auth::getUser()['damage'] <= 0) {
						$potionActive = true;
					}
				}
			}
			$achievementUpdate1 = AppController::checkLevelAchievements();
			$achievementUpdate2 = AppController::checkProblemNumberAchievements();
			$achievementUpdate3 = AppController::checkNoErrorAchievements();
			$achievementUpdate4 = AppController::checkRatingAchievements();
			$achievementUpdate5 = AppController::checkDanSolveAchievements();
			$achievementUpdate = array_merge(
				$achievementUpdate1 ?: [],
				$achievementUpdate2 ?: [],
				$achievementUpdate3 ?: [],
				$achievementUpdate4 ?: [],
				$achievementUpdate5 ?: []);
			if (count($achievementUpdate) > 0) {
				AppController::updateXP(Auth::getUserID(), $achievementUpdate);
			}
		}

		$admins = ClassRegistry::init('User')->find('all', ['conditions' => ['isAdmin' => 1]]);
		if (Auth::isInRatingMode() || Auth::isInTimeMode()) {
			CakeSession::write('title', 'Tsumego Hero');
		}
		if ($isSandbox) {
			$t['Tsumego']['userWin'] = 0;
		}

		$crs = 0;

		if (Auth::isInLevelMode()) {
			CakeSession::write('page', 'level mode');
		} elseif (Auth::isInRatingMode()) {
			CakeSession::write('page', 'rating mode');
		} elseif (Auth::isInTimeMode()) {
			CakeSession::write('page', 'time mode');
		}

		$ui = 2;
		$file = 'placeholder2.sgf';

		AppController::startPageUpdate();
		$startingPlayer = TsumegosController::getStartingPlayer($sgf2);

		$avActiveText = '<font style="color:gray;"> (out of range)</font>';

		$eloScoreRounded = round($eloScore);
		$eloScore2Rounded = round($eloScore2);

		$existingSignatures = ClassRegistry::init('Signature')->find('all', ['conditions' => ['tsumego_id' => $id]]);
		if ($existingSignatures == null || $existingSignatures[0]['Signature']['created'] < date('Y-m-d', strtotime('-1 week'))) {
			$requestSignature = 'true';
		} else {
			$requestSignature = 'false';
		}
		if (isset($_COOKIE['signatures']) && $set['Set']['public'] == 1) {
			$signature = explode('/', $_COOKIE['signatures']);
			$oldSignatures = ClassRegistry::init('Signature')->find('all', ['conditions' => ['tsumego_id' => $signature[count($signature) - 1]]]);
			if (!$oldSignatures) {
				$oldSignatures = [];
			}

			$oldSignaturesCount = count($oldSignatures);

			for ($i = 0; $i < $oldSignaturesCount; $i++) {
				ClassRegistry::init('Signature')->delete($oldSignatures[$i]['Signature']['id']);
			}

			$signatureCountMinus1 = count($signature) - 1;
			for ($i = 0; $i < $signatureCountMinus1; $i++) {
				ClassRegistry::init('Signature')->create();
				$newSignature = [];
				$newSignature['Signature']['tsumego_id'] = $signature[count($signature) - 1];
				$newSignature['Signature']['signature'] = $signature[$i];
				ClassRegistry::init('Signature')->save($newSignature);
			}
			unset($_COOKIE['signatures']);
		}
		$idForSignature = -1;
		$idForSignature2 = -1;
		if (isset($params['url']['idForTheThing'])) {
			$idForSignature2 = $params['url']['idForTheThing'] + 1;
			$idForSignature = TsumegosController::getTheIdForTheThing($idForSignature2);
		}
		if (!isset($difficulty)) {
			$difficulty = 4;
		}

		if (Auth::isLoggedIn()) {
			Auth::getUser()['name'] = AppController::checkPicture(Auth::getUser());
		}
		$tags = TsumegosController::getTags($id);
		$tags = TsumegosController::checkTagDuplicates($tags);

		$allTags = AppController::getAllTags($tags);
		$popularTags = TsumegosController::getPopularTags($tags);
		$uc = ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]);
		$hasRevelation = false;
		if ($uc) {
			$hasRevelation = $uc['UserContribution']['reward3'];
		}
		if (Auth::hasPremium() && Auth::getUser()['level'] >= 100) {
			$hasRevelation = true;
		}

		$sgfProposal = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['tsumego_id' => $id, 'user_id' => Auth::getUserID()]]);
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
				$tagsToCheck = ClassRegistry::init('Tag')->find('all', ['limit' => 20, 'order' => 'created DESC', 'conditions' => ['user_id' => Auth::getUserID()]]);
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

		$checkNotInSearch = false;

		$isTSUMEGOinFAVORITE = ClassRegistry::init('Favorite')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $id]]);

		$indexOfCurrent = array_find_key($this->setConnectionsOfCurrentSet, function ($setConnection) use ($setConnectionID) { return $setConnection['SetConnection']['id'] == $setConnectionID; });

		if (isset($indexOfCurrent) && $indexOfCurrent > 0) {
			$previousSetConnectionID = $this->setConnectionsOfCurrentSet[$indexOfCurrent - 1]['SetConnection']['id'];
		}
		$previousLink = TsumegosController::tsumegoOrSetLink(isset($previousSetConnectionID) ? $previousSetConnectionID : null, $set['Set']['id']);

		if (isset($indexOfCurrent) && count($this->setConnectionsOfCurrentSet) > $indexOfCurrent + 1) {
			$nextSetConnectionID = $this->setConnectionsOfCurrentSet[$indexOfCurrent + 1]['SetConnection']['id'];
		}
		if (!Auth::isInTimeMode()) {
			($this->setFunction)('nextLink', TsumegosController::tsumegoOrSetLink(isset($nextSetConnectionID) ? $nextSetConnectionID : null, $set['Set']['id']));
		}

		($this->setFunction)('isAllowedToContribute', $isAllowedToContribute);
		($this->setFunction)('isAllowedToContribute2', $isAllowedToContribute2);
		($this->setFunction)('hasSgfProposal', $sgfProposal != null);
		($this->setFunction)('hasRevelation', $hasRevelation);
		($this->setFunction)('allTags', $allTags);
		($this->setFunction)('tags', $tags);
		($this->setFunction)('popularTags', $popularTags);
		($this->setFunction)('requestSignature', $requestSignature);
		($this->setFunction)('idForSignature', $idForSignature);
		($this->setFunction)('idForSignature2', $idForSignature2);
		if (isset($activityValue)) {
			($this->setFunction)('activityValue', $activityValue);
		}
		($this->setFunction)('avActiveText', $avActiveText);
		($this->setFunction)('nothingInRange', $nothingInRange);
		($this->setFunction)('tRank', $tRank);
		($this->setFunction)('sgf', $sgf);
		($this->setFunction)('sgf2', $sgf2);
		($this->setFunction)('sandboxComment2', $sandboxComment2);
		($this->setFunction)('crs', $crs);
		($this->setFunction)('admins', $admins);
		($this->setFunction)('refresh', $refresh);
		($this->setFunction)('anz', $anzahl2);
		($this->setFunction)('showComment', $co);
		($this->setFunction)('orientation', $orientation);
		($this->setFunction)('colorOrientation', $colorOrientation);
		($this->setFunction)('g', $refinementT[$refinementPublicCounter]);
		($this->setFunction)('favorite', $checkFav);
		($this->setFunction)('isTSUMEGOinFAVORITE', $isTSUMEGOinFAVORITE != null);
		($this->setFunction)('hasAnyFavorite', $hasAnyFavorite != null);
		($this->setFunction)('inFavorite', $inFavorite);
		($this->setFunction)('lastInFav', $lastInFav);
		($this->setFunction)('dailyMaximum', $dailyMaximum);
		($this->setFunction)('suspiciousBehavior', $suspiciousBehavior);
		($this->setFunction)('isSandbox', $isSandbox);
		($this->setFunction)('goldenTsumego', $goldenTsumego);
		($this->setFunction)('fullHeart', $fullHeart);
		($this->setFunction)('emptyHeart', $emptyHeart);
		($this->setFunction)('libertyCount', $t['Tsumego']['libertyCount']);
		($this->setFunction)('semeaiType', $t['Tsumego']['semeaiType']);
		($this->setFunction)('insideLiberties', $t['Tsumego']['insideLiberties']);
		($this->setFunction)('doublexp', $doublexp);
		($this->setFunction)('half', $half);
		($this->setFunction)('set', $set);
		if (Auth::isLoggedIn() && Auth::getUser()['nextlvl'] > 0) {
			($this->setFunction)('barPercent', Auth::getUser()['xp'] / Auth::getUser()['nextlvl'] * 100);
		} else {
			($this->setFunction)('barPercent', 0);
		}
		($this->setFunction)('t', $t);
		($this->setFunction)('solvedCheck', AppController::encrypt($t['Tsumego']['id'] . '-' . time()));
		($this->setFunction)('previousLink', $previousLink);
		($this->setFunction)('hash', $hash);
		($this->setFunction)('nextMode', $nextMode);
		($this->setFunction)('rating', Auth::getWithDefault('rating', 0));
		($this->setFunction)('eloScore', $eloScore);
		($this->setFunction)('eloScore2', $eloScore2);
		($this->setFunction)('eloScoreRounded', $eloScoreRounded);
		($this->setFunction)('eloScore2Rounded', $eloScore2Rounded);
		($this->setFunction)('activate', $activate);
		($this->setFunction)('tsumegoElo', $t['Tsumego']['rating']);
		($this->setFunction)('trs', $trs);
		($this->setFunction)('difficulty', $difficulty);
		($this->setFunction)('potion', $potion);
		($this->setFunction)('potionSuccess', $potionSuccess);
		($this->setFunction)('potionActive', $potionActive);
		($this->setFunction)('reviewCheat', $reviewCheat);
		($this->setFunction)('commentCoordinates', $commentCoordinates);
		($this->setFunction)('part', $t['Tsumego']['part']);
		($this->setFunction)('checkBSize', $checkBSize);
		($this->setFunction)('potionAlert', $potionAlert);
		($this->setFunction)('file', $file);
		($this->setFunction)('ui', $ui);
		($this->setFunction)('requestProblem', $requestProblem);
		($this->setFunction)('alternative_response', $t['Tsumego']['alternative_response']);
		($this->setFunction)('passEnabled', $t['Tsumego']['pass']);
		($this->setFunction)('set_duplicate', $t['Tsumego']['duplicate']);
		($this->setFunction)('achievementUpdate', $achievementUpdate);
		($this->setFunction)('setConnection', $currentSetConnection);
		($this->setFunction)('setConnections', $setConnections);
		if (isset($params['url']['requestSolution'])) {
			($this->setFunction)('requestSolution', AdminActivityUtil::requestSolution($id));
		}
		($this->setFunction)('startingPlayer', $startingPlayer);
		($this->setFunction)('tv', $tsumegoVariant);
		($this->setFunction)('query', $query);
		($this->setFunction)('queryTitle', $queryTitle);
		($this->setFunction)('queryTitleSets', $queryTitleSets);
		($this->setFunction)('search1', $search1);
		($this->setFunction)('search2', $search2);
		($this->setFunction)('search3', $search3);
		($this->setFunction)('amountOfOtherCollection', $amountOfOtherCollection);
		($this->setFunction)('partition', $partition);
		($this->setFunction)('checkNotInSearch', $checkNotInSearch);
		($this->setFunction)('hasPremium', $hasPremium);
		return null;
	}

	protected function getActivityValue($uid, $tid) {
		$return = [];
		$tsumegoNum = 90;
		$ra = ClassRegistry::init('TsumegoAttempt')->find('all', ['limit' => $tsumegoNum, 'order' => 'created DESC', 'conditions' => ['user_id' => $uid]]);
		if (!$ra) {
			$ra = [];
		}
		if (count($ra) < $tsumegoNum) {
			$missing = $tsumegoNum - count($ra);
			$raSize = count($ra);
			$datex = new DateTime('-1 month');
			while ($missing != 0) {
				$ra[$raSize]['TsumegoAttempt']['created'] = $datex->format('Y-m-d H:i:s');
				$ra[$raSize]['TsumegoAttempt']['tsumego_id'] = 1;
				$raSize++;
				$missing--;
			}
		}
		$date = date('Y-m-d H:i:s');
		$x = [];
		$avg = 0;
		$foundTsumego = 0;
		$raCount = count($ra);
		for ($i = 0; $i < $raCount; $i++) {
			if ($ra[$i]['TsumegoAttempt']['tsumego_id'] == $tid) {
				$foundTsumego = 1;
			}
			$d = $this->getActivityValueSingle($ra[$i]['TsumegoAttempt']['created']);
			$avg += $d;
			array_push($x, $d);
		}
		$avg /= count($x);
		$return[0] = round($avg);
		$return[1] = $foundTsumego;

		return $return;
	}

	private function getActivityValueSingle($date2) {
		$date1 = new DateTime('now');
		$date2 = new DateTime($date2);
		$interval = $date1->diff($date2);
		$m = $interval->m;
		$d = $interval->d;
		$h = $interval->h;
		$i = $interval->i;
		$months = 0;
		while ($m > 0) {
			$months += 672;
			$m--;
		}
		$hours = $h;
		while ($d > 0) {
			$hours += 24;
			$d--;
		}
		$hours += $months;

		return $hours;
	}

	private function checkCommentValid($uid) {
		$comments = ClassRegistry::init('Comment')->find('all', ['limit' => 5, 'order' => 'created DESC', 'conditions' => ['user_id' => $uid]]);
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

	private function selectSetConnectionsOfCurrentSet($set, $query, $inFavorite): array {
		if ($inFavorite) {
			return [];
		}

		if ($query == 'topics') {
			return $this->selectSetConnectionsOfCurrentSetBasedOnTopics($set);
		}
		if ($query == 'difficulty' || $query == 'tags') {
			return $this->selectSetConnectionsOfCurrentSetBasedOnDifficultyOrTags($set);
		}
		return [];
	}

	public function selectSetConnectionsOfCurrentSetBasedOnTopics($set): array {
		return ClassRegistry::init('SetConnection')->find('all', ['order' => 'num ASC', 'conditions' => ['set_id' => $set['Set']['id']]]) ?: [];
	}

	public function selectSetConnectionsOfCurrentSetBasedOnFavorites($set): array {
		$favorites = ClassRegistry::init('Favorite')->find('all', ['order' => 'created', 'direction' => 'DESC', 'conditions' => ['user_id' => Auth::getUserID()]]) ?: [];
		$result = [];
		foreach ($favorites as $favorite) {
			$tsumego = ClassRegistry::init('Tsumego')->findById($favorite['Favorite']['tsumego_id']);
			$setConnection = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $tsumego['Tsumego']['tsumego_id']]]);
			$result [] = $setConnection;
		}
		return $result;
	}

	public function selectSetConnectionsOfCurrentSetBasedOnDifficultyOrTags($set): array {
		return [];
	}

	private $setFunction;
	private $setConnectionsOfCurrentSet = [];
}

<?php

App::uses('SetNavigationButtonsInput', 'Utility');
App::uses('TsumegoButton', 'Utility');
App::uses('TsumegoButtons', 'Utility');
App::uses('TsumegoXPAndRating', 'Utility');
App::uses('ForbiddenException', 'Routing/Error');
App::uses('BasePolicy', 'Policy');
App::uses('SetPolicy', 'Policy');
App::uses('Level', 'Utility');
App::uses('AdminActivityLogger', 'Utility');
App::uses('AdminActivityType', 'Model');
App::uses('NotFoundException', 'Routing/Error');

class Play
{
	public function __construct($setFunction)
	{
		$this->setFunction = $setFunction;
	}

	public function getTsumegoStatus(array $tsumego): string
	{
		if (Auth::isLoggedIn())
		{
			$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
				'user_id' => Auth::getUserID(),
				'tsumego_id' => $tsumego['Tsumego']['id']]]);
			if (!$status)
				return 'V';
			return $status['TsumegoStatus']['status'];
		}
		return 'V';
	}

	public function play(int $setConnectionID, $params, $data): mixed
	{
		($this->setFunction)('page', 'play');

		$highestTsumegoOrder = 0;
		$doublexp = null;
		$suspiciousBehavior = false;
		$half = '';
		$isSandbox = false;
		$goldenTsumego = false;
		$reviewCheat = false;
		$commentCoordinates = [];
		$trs = [];
		$eloScore = 0;
		$eloScore2 = 0;
		$requestProblem = '';
		$achievementUpdate = [];
		$tRank = '15k';
		$nothingInRange = false;
		$queryTitle = '';

		$currentSetConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		if (!$currentSetConnection)
			throw new NotFoundException("Set connection " . $setConnectionID . " wasn't found in the database.");
		$id = $currentSetConnection['SetConnection']['tsumego_id'];

		$t = ClassRegistry::init('Tsumego')->findById($id); //the tsumego

		if ($t == null)
		{
			$t = ClassRegistry::init('Tsumego')->findById($_COOKIE['lastVisit'] ?? Constants::$DEFAULT_TSUMEGO_ID);
			$id = $t['Tsumego']['id'];
		}

		$setConnections = TsumegoUtil::getSetConnectionsWithTitles($id);
		$set = ClassRegistry::init('Set')->findById($currentSetConnection['SetConnection']['set_id']);

		$tsumegoVariant = ClassRegistry::init('TsumegoVariant')->find('first', ['conditions' => ['tsumego_id' => $id]]);

		if (isset($params['url']['search']))
			if ($params['url']['search'] == 'topics')
			{
				$query = $params['url']['search'];
				$_COOKIE['query'] = $params['url']['search'];
			}

		$tsumegoFilters = new TsumegoFilters();

		if ($t['Tsumego']['rating'])
			$tRank = Rating::getReadableRankFromRating($t['Tsumego']['rating']);

		Util::setCookie('lastVisit', $id);

		if (Auth::isLoggedIn())
			if (!empty($data))
			{
				if (isset($data['Study']))
				{
					$tsumegoVariant['TsumegoVariant']['answer1'] = $data['Study']['study1'];
					$tsumegoVariant['TsumegoVariant']['answer2'] = $data['Study']['study2'];
					$tsumegoVariant['TsumegoVariant']['answer3'] = $data['Study']['study3'];
					$tsumegoVariant['TsumegoVariant']['answer4'] = $data['Study']['study4'];
					$tsumegoVariant['TsumegoVariant']['explanation'] = $data['Study']['explanation'];
					$tsumegoVariant['TsumegoVariant']['numAnswer'] = $data['Study']['studyCorrect'];
					ClassRegistry::init('TsumegoVariant')->save($tsumegoVariant);
				}
				elseif (isset($data['Study2']))
				{
					$tsumegoVariant['TsumegoVariant']['winner'] = $data['Study2']['winner'];
					$tsumegoVariant['TsumegoVariant']['answer1'] = $data['Study2']['answer1'];
					$tsumegoVariant['TsumegoVariant']['answer2'] = $data['Study2']['answer2'];
					$tsumegoVariant['TsumegoVariant']['answer3'] = $data['Study2']['answer3'];
					ClassRegistry::init('TsumegoVariant')->save($tsumegoVariant);
				}
				elseif (isset($data['Settings']))
				{
					if ($data['Settings']['r39'] == 'on' && $t['Tsumego']['alternative_response'] != 1)
						AdminActivityLogger::log(AdminActivityType::ALTERNATIVE_RESPONSE, $t['Tsumego']['id'], null, null, '1');
					if ($data['Settings']['r39'] == 'off' && $t['Tsumego']['alternative_response'] != 0)
						AdminActivityLogger::log(AdminActivityType::ALTERNATIVE_RESPONSE, $t['Tsumego']['id'], null, null, '0');
					if ($data['Settings']['r43'] == 'no' && $t['Tsumego']['pass'] != 0)
						AdminActivityLogger::log(AdminActivityType::PASS_MODE, $t['Tsumego']['id'], null, null, '0');
					if ($data['Settings']['r43'] == 'yes' && $t['Tsumego']['pass'] != 1)
						AdminActivityLogger::log(AdminActivityType::PASS_MODE, $t['Tsumego']['id'], null, null, '1');
					if ($data['Settings']['r41'] == 'yes' && $tsumegoVariant == null)
					{
						AdminActivityLogger::log(AdminActivityType::MULTIPLE_CHOICE, $t['Tsumego']['id'], null, null, '1');
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
					if ($data['Settings']['r41'] == 'no' && $tsumegoVariant != null)
					{
						AdminActivityLogger::log(AdminActivityType::MULTIPLE_CHOICE, $t['Tsumego']['id'], null, null, '0');
						ClassRegistry::init('TsumegoVariant')->delete($tsumegoVariant['TsumegoVariant']['id']);
						$tsumegoVariant = null;
					}
					if ($data['Settings']['r42'] == 'yes' && $tsumegoVariant == null)
					{
						AdminActivityLogger::log(AdminActivityType::SCORE_ESTIMATING, $t['Tsumego']['id'], null, null, '1');
						$tv1 = [];
						$tv1['TsumegoVariant']['tsumego_id'] = $id;
						$tv1['TsumegoVariant']['type'] = 'score_estimating';
						$tv1['TsumegoVariant']['numAnswer'] = '0';
						ClassRegistry::init('TsumegoVariant')->create();
						ClassRegistry::init('TsumegoVariant')->save($tv1);
					}
					if ($data['Settings']['r42'] == 'no' && $tsumegoVariant != null)
					{
						AdminActivityLogger::log(AdminActivityType::SCORE_ESTIMATING, $t['Tsumego']['id'], null, null, '0');
						ClassRegistry::init('TsumegoVariant')->delete($tsumegoVariant['TsumegoVariant']['id']);
						$tsumegoVariant = null;
					}
					if ($data['Settings']['r39'] == 'on')
						$t['Tsumego']['alternative_response'] = 1;
					else
						$t['Tsumego']['alternative_response'] = 0;
					if ($data['Settings']['r43'] == 'yes')
						$t['Tsumego']['pass'] = 1;
					else
						$t['Tsumego']['pass'] = 0;
					if ($t['Tsumego']['rating'] > 100)
						ClassRegistry::init('Tsumego')->save($t, true);
				}
				($this->setFunction)('formRedirect', true);
			}
		if (Auth::isAdmin())
		{
			$aad = ClassRegistry::init('AdminActivity')->find('first', ['order' => 'id DESC']);
			if ($aad && $aad['AdminActivity']['type'] === AdminActivityType::PROBLEM_DELETE)($this->setFunction)('deleteProblem2', true);
		}

		if (isset($_COOKIE['skip']) && $_COOKIE['skip'] != '0' && Auth::isLoggedIn())
		{
			Auth::getUser()['readingTrial']--;
			unset($_COOKIE['skip']);
		}
		$isSandbox = ($set['Set']['public'] == 0 && $set['Set']['user_id'] === null);

		// TODO: Direct policy call because Play is a component, not a controller —
		// can't use AuthorizationComponent here. Revisit with CakePHP 5 where
		// authorization middleware can handle this at the controller level.
		if (!SetPolicy::canPlay(Auth::getIdentity(), $set['Set']))
			throw new ForbiddenException();

		$tsumegoStatus = Play::getTsumegoStatus($t);

		if ($tsumegoStatus == 'G')
			$goldenTsumego = true;

		Util::setCookie('previousTsumegoID', $id);

		$amountOfOtherCollection = count(TsumegoUtil::collectTsumegosFromSet($set['Set']['id']));

		$sgf = [];
		$sgfdb = ClassRegistry::init('Sgf')->find('first', [
			'order' => 'id DESC',
			'conditions' => [
				'tsumego_id' => $id,
				'accepted' => true]]);
		if (!$sgfdb)
		{
			$sgf['Sgf']['sgf'] = Constants::$SGF_PLACEHOLDER;
			$sgf['Sgf']['tsumego_id'] = $id;
		}
		else
			$sgf = $sgfdb;

		$ogTitle = $set['Set']['title'] ?? '';
		$ogTitle .= ' ' . $currentSetConnection['SetConnection']['num'] . '/' . $amountOfOtherCollection;

		$ogDescription = strip_tags($t['Tsumego']['description'] ?? '');
		$ogDescription = str_ireplace('[b]', 'Black', $ogDescription);
		// The OG image renders actual SGF colors (no board inversion), while
		// descriptions are normalized to "Black = solver". For White-first SGFs,
		// swap Black<->White so the description matches the OG image.
		if (TsumegosController::getStartingPlayer($sgf['Sgf']['sgf']) == 1)
		{
			$ogDescription = preg_replace_callback(
				'/\b(Black|black|White|white)\b/',
				fn($m) => ['Black' => 'White', 'black' => 'white', 'White' => 'Black', 'white' => 'black'][$m[1]],
				$ogDescription
			);
		}
		$author = $t['Tsumego']['author'] ?? '';
		if ($author !== '' && $author !== 'Unknown')
			$ogDescription .= ' - by ' . $author;
		if (trim($ogDescription) === '')
			$ogDescription = $ogTitle;

		$sgfCreated = $sgf['Sgf']['created'] ?? '';
		$imageUrl = Router::url('/tsumego-image/' . $currentSetConnection['SetConnection']['id']
			. '?v=' . Constants::$TSUMEGO_IMAGE_VERSION
			. '&t=' . ($sgfCreated !== '' ? strtotime($sgfCreated) : ''), true);

		($this->setFunction)('og', [
			'title' => $ogTitle,
			'description' => $ogDescription,
			'image' => $imageUrl,
			'image_type' => 'image/png',
			'image_width' => Constants::$OG_IMAGE_WIDTH,
			'image_height' => Constants::$OG_IMAGE_HEIGHT,
			'image_alt' => $ogTitle,
			'url' => Router::url('/' . $currentSetConnection['SetConnection']['id'], true),
			'type' => 'website',
			'site_name' => 'Tsumego',
			'locale' => 'en_US',
			'twitter_card' => 'summary_large_image',
		]);

		if (!is_null($t['Tsumego']['semeaiType']) && $t['Tsumego']['semeaiType'] != 0)
		{
			($this->setFunction)('multipleChoiceTriangles', count(Util::getFollowingSgfCoordinates($sgf['Sgf']['sgf'], strpos($sgf['Sgf']['sgf'], 'TR') + 2)));
			($this->setFunction)('multipleChoiceSquares', count(Util::getFollowingSgfCoordinates($sgf['Sgf']['sgf'], strpos($sgf['Sgf']['sgf'], 'SQ') + 2)));
		}
		if ($tsumegoFilters->query == 'topics')($this->setFunction)('_title', $set['Set']['title'] . ' ' . $currentSetConnection['SetConnection']['num'] . '/' . $highestTsumegoOrder . ' on Tsumego Hero');
		else
		($this->setFunction)('_title', ($_COOKIE['lastSet'] ?? 'Tsumego') . ' ' . $currentSetConnection['SetConnection']['num'] . '/' . $highestTsumegoOrder . ' on Tsumego Hero');

		if (Auth::isInLevelMode())
		{
			$tsumegoButtons = new TsumegoButtons($tsumegoFilters, $currentSetConnection['SetConnection']['id'], null, $set['Set']['id']);
			new SetNavigationButtonsInput($this->setFunction)->execute($tsumegoButtons, $currentSetConnection);
			$queryTitle = $tsumegoFilters->getSetTitle($set) . $tsumegoButtons->getPartitionTitleSuffix() . ' ' . $tsumegoButtons->currentOrder . '/' . $tsumegoButtons->highestTsumegoOrder;
		}

		$t['Tsumego']['status'] = $tsumegoStatus;

		if (!isset($t['Tsumego']['file']) || $t['Tsumego']['file'] == '')
			$t['Tsumego']['file'] = $currentSetConnection['SetConnection']['num'];
		$orientation = null;
		$colorOrientation = null;
		if (isset($params['url']['orientation']))
			$orientation = $params['url']['orientation'];
		if (isset($params['url']['playercolor']))
			$colorOrientation = $params['url']['playercolor'];

		$checkBSize = 19;
		for ($i = 2; $i <= 19; $i++)
			if (strpos(';' . $set['Set']['title'], $i . 'x' . $i))
				$checkBSize = $i;

		if (Util::getHealthBasedOnLevel(Auth::getWithDefault('level', 0)) >= 8)
		{
			$fullHeart = 'heart1small';
			$emptyHeart = 'heart2small';
		}
		else
		{
			$fullHeart = 'heart1';
			$emptyHeart = 'heart2';
		}
		if (Auth::isLoggedIn())
			if (Auth::getUser()['reuse5'] == 1)
				$suspiciousBehavior = true;
		$hash = AppController::encrypt($currentSetConnection['SetConnection']['num'] . 'number' . $set['Set']['id']);

		$activate = true;
		if (Auth::isInRatingMode() || Auth::isInTimeMode())($this->setFunction)('_title', 'Tsumego Hero');
		if ($isSandbox)
			$t['Tsumego']['userWin'] = 0;

		$crs = 0;

		if (Auth::isInLevelMode())($this->setFunction)('page', 'level mode');
		elseif (Auth::isInRatingMode())($this->setFunction)('page', 'rating mode');
		elseif (Auth::isInTimeMode())($this->setFunction)('page', 'time mode');

		$ui = 2;
		$file = 'placeholder2.sgf';
		$startingPlayer = TsumegosController::getStartingPlayer($sgf['Sgf']['sgf']);

		$eloScoreRounded = round($eloScore);
		$eloScore2Rounded = round($eloScore2);

		$existingSignatures = ClassRegistry::init('Signature')->find('all', ['conditions' => ['tsumego_id' => $id]]);
		if ($existingSignatures == null || $existingSignatures[0]['Signature']['created'] < date('Y-m-d', strtotime('-1 week')))
			$requestSignature = 'true';
		else
			$requestSignature = 'false';
		if (isset($_COOKIE['signatures']) && $set['Set']['public'] == 1)
		{
			$signature = explode('/', $_COOKIE['signatures']);
			$oldSignatures = ClassRegistry::init('Signature')->find('all', ['conditions' => ['tsumego_id' => $signature[count($signature) - 1]]]);
			if (!$oldSignatures)
				$oldSignatures = [];

			$oldSignaturesCount = count($oldSignatures);

			for ($i = 0; $i < $oldSignaturesCount; $i++)
				ClassRegistry::init('Signature')->delete($oldSignatures[$i]['Signature']['id']);

			$signatureCountMinus1 = count($signature) - 1;
			for ($i = 0; $i < $signatureCountMinus1; $i++)
			{
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
		if (isset($params['url']['idForTheThing']))
		{
			$idForSignature2 = $params['url']['idForTheThing'] + 1;
			$idForSignature = TsumegosController::getTheIdForTheThing($idForSignature2);
		}

		$tagData = ClassRegistry::init('Tag')::getForTsumego($id);

		$isAllowedToContribute = false;
		$canAddMoreTags = false;
		if (Auth::isLoggedIn())
		{
			$isAllowedToContribute = BasePolicy::canPropose(Auth::getIdentity());
			$canAddMoreTags = ClassRegistry::init('TagConnection')::canCurrentUserAddTag();
		}

		$checkNotInSearch = false;

		$userSetsJson = '[]';
		if (Auth::isLoggedIn())
		{
			// Build user sets list for the heart dropdown
			$userSets = Util::query("
SELECT s.id, s.title, sc.tsumego_id
FROM `set` s
LEFT JOIN set_connection sc ON sc.set_id = s.id AND sc.tsumego_id = ?
WHERE s.user_id = ?
ORDER BY s.title", [$id, Auth::getUserID()]);

			$setsData = [];
			foreach ($userSets as $s)
			{
				$setsData[] = [
					'id' => $s['id'],
					'title' => $s['title'],
					'contains' => ($s['tsumego_id'] != null),
				];
			}
			$userSetsJson = json_encode($setsData);
		}
		($this->setFunction)('userSetsJson', $userSetsJson);

		if (Auth::isInLevelMode())
			$tsumegoButtons->exportCurrentAndPreviousLink($this->setFunction, $tsumegoFilters, $setConnectionID, $set);

		($this->setFunction)('isAllowedToContribute', $isAllowedToContribute);
		($this->setFunction)('canAddMoreTags', $canAddMoreTags);

		$sgfProposal = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['tsumego_id' => $id, 'user_id' => Auth::getUserID(), 'accepted' => false]]);
		($this->setFunction)('hasSgfProposal', $sgfProposal != null);

		($this->setFunction)('tagData', $tagData);
		($this->setFunction)('requestSignature', $requestSignature);
		($this->setFunction)('idForSignature', $idForSignature);
		($this->setFunction)('idForSignature2', $idForSignature2);
		($this->setFunction)('nothingInRange', $nothingInRange);
		($this->setFunction)('tRank', $tRank);
		($this->setFunction)('sgf', $sgf);
		($this->setFunction)('crs', $crs);
		($this->setFunction)('orientation', $orientation);
		($this->setFunction)('colorOrientation', $colorOrientation);
		($this->setFunction)('suspiciousBehavior', $suspiciousBehavior);
		($this->setFunction)('isSandbox', $isSandbox);
		($this->setFunction)('goldenTsumego', $goldenTsumego);
		$boardsBitmask = BoardSelector::filterValidBits(Auth::isLoggedIn() ? Auth::getUser()['boards_bitmask'] : BoardSelector::$DEFAULT_BOARDS_BITMASK);
		($this->setFunction)('boardSelection', BoardSelector::selectBoard($boardsBitmask, $goldenTsumego, $set['Set']['board_theme_index']));
		($this->setFunction)('fullHeart', $fullHeart);
		($this->setFunction)('emptyHeart', $emptyHeart);
		($this->setFunction)('maxHealth', Util::getHealthBasedOnLevel(Auth::getWithDefault('level', 0)));
		($this->setFunction)('libertyCount', $t['Tsumego']['libertyCount']);
		($this->setFunction)('semeaiType', $t['Tsumego']['semeaiType']);
		($this->setFunction)('insideLiberties', $t['Tsumego']['insideLiberties']);
		($this->setFunction)('doublexp', $doublexp);
		($this->setFunction)('half', $half);
		($this->setFunction)('set', $set);
		if (Auth::isLoggedIn())($this->setFunction)('barPercent', Util::getPercent(Auth::getUser()['xp'], Level::getXPForNext(Auth::getUser()['level'])));
		else
		($this->setFunction)('barPercent', 0);
		($this->setFunction)('t', $t);
		($this->setFunction)('solvedCheck', AppController::encrypt($t['Tsumego']['id'] . '-' . time()));
		($this->setFunction)('hash', $hash);
		($this->setFunction)('rating', Auth::getWithDefault('rating', 0));
		($this->setFunction)('eloScore', $eloScore);
		($this->setFunction)('eloScore2', $eloScore2);
		($this->setFunction)('eloScoreRounded', $eloScoreRounded);
		($this->setFunction)('eloScore2Rounded', $eloScore2Rounded);
		($this->setFunction)('activate', $activate);
		($this->setFunction)('tsumegoElo', $t['Tsumego']['rating']);
		($this->setFunction)('trs', $trs);
		($this->setFunction)('reviewCheat', $reviewCheat);
		($this->setFunction)('part', $t['Tsumego']['part']);
		($this->setFunction)('checkBSize', $checkBSize);
		($this->setFunction)('file', $file);
		($this->setFunction)('ui', $ui);
		($this->setFunction)('requestProblem', $requestProblem);
		($this->setFunction)('alternative_response', $t['Tsumego']['alternative_response']);
		($this->setFunction)('passEnabled', $t['Tsumego']['pass']);
		($this->setFunction)('achievementUpdate', $achievementUpdate);
		($this->setFunction)('setConnection', $currentSetConnection);
		($this->setFunction)('setConnections', $setConnections);
		if (isset($params['url']['requestSolution']))($this->setFunction)('requestSolution', AdminActivityLogger::log(AdminActivityType::SOLUTION_REQUEST, $id));
		($this->setFunction)('startingPlayer', $startingPlayer);
		($this->setFunction)('tv', $tsumegoVariant);
		($this->setFunction)('tsumegoFilters', $tsumegoFilters);
		($this->setFunction)('queryTitle', $queryTitle);
		($this->setFunction)('amountOfOtherCollection', $amountOfOtherCollection);
		($this->setFunction)('checkNotInSearch', $checkNotInSearch);
		($this->setFunction)('tsumegoXPAndRating', new TsumegoXPAndRating($t['Tsumego'], $tsumegoStatus));

		return null;
	}

	public static function renderTitle($setConnection, $set, $tsumegoFilters, $tsumegoButtons, $amountOfOtherCollection, $difficulty, $timeMode, $queryTitle, $t)
	{
		if (Auth::isInTimeMode())
			return '<font size="5px">' . $timeMode->currentOrder . ' of ' . $timeMode->overallCount . '</font>';

		if (Auth::isInRatingMode())
			return '<div class="slidecontainer">
						<input type="range" min="1" max="7" value="' . $difficulty . '" class="slider" id="rangeInput" name="rangeInput">
						<div id="sliderText">regular</div>
						</div>
						<a id="playTitleA" href=""></a>';

		$order = $setConnection['SetConnection']['num'];
		if ($tsumegoFilters->query == 'difficulty' || $tsumegoFilters->query == 'tags')
			return '<a id="playTitleA" href="/sets/view/' . $tsumegoFilters->getSetID($set['Set']['id']) . $tsumegoButtons->getPartitionLinkSuffix() . '">' . $queryTitle . '</a><br>
							<font style="font-weight:400;" color="grey">
											<a style="color:grey;" id="playTitleA" href="/sets/view/' . $set['Set']['id'] . '">
												(' . $set['Set']['title'] . ' ' . $order . '/' . $amountOfOtherCollection . ')
											</a>
										</font>';
		return '<a id="playTitleA" href="/sets/view/' . $set['Set']['id'] . $tsumegoButtons->getPartitionLinkSuffix() . '">' . $set['Set']['title'] . ' ' . $tsumegoButtons->getPartitionTitleSuffix() . ' ' . $order . '/' . $tsumegoButtons->highestTsumegoOrder . '</a>';
	}

	private $setFunction;
}

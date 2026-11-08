<?php

namespace App\Utility;

use App\Model\Entity\Set;
use App\Model\Entity\SetConnection;
use App\Model\Entity\Sgf;
use App\Model\Entity\Tsumego;
use App\Model\Entity\TsumegoSemeai;
use App\Model\Table\SgfTable;
use App\Model\Table\TsumegoStatusTable;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\ClassroomAssignmentTable;
use App\Model\Table\TagConnectionTable;
use App\Model\Table\TagTable;
use App\Model\Table\TsumegoVariantTable;
use App\Model\Table\UserTable;
use App\Policy\AdminPolicy;
use App\Policy\BasePolicy;
use App\Policy\SetPolicy;
use App\Policy\TagConnectionPolicy;
use Cake\ORM\TableRegistry;
use App\Controller\Component\AuthorizationComponent;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;

class Play
{
	//The play page of the problem of a set connection. Its view variables come in two parts: what the
	//page has around any problem ($page: the player, the mode) and what changes with the problem
	//($problem), which is all a page will need to show the next problem without loading again.
	//$classroomAssignment: the class assignment the problem is played in, with its collections, set by
	//its route (ClassroomsController::play()): its problems are the row of buttons and Next walks them.
	//$markAsVisited: the normal modes mark a problem the player opens for the first time as visited.
	/** @param array<string, mixed>|null $classroomAssignment */
	public function __construct(int $setConnectionID, ServerRequest $request, private ?array $classroomAssignment = null, private bool $markAsVisited = false)
	{
		$this->buildPage();
		$this->buildProblem($setConnectionID, $request);
	}

	//Both parts, as the view gets them
	/** @return array<string, mixed> */
	public function viewVars(): array
	{
		return array_merge($this->page, $this->problem);
	}

	//What only the route has (its canonical address, its menu item, its links), it wins over what the
	//problem would show
	/** @param array<string, mixed> $routeValues */
	public function addRouteValues(array $routeValues): void
	{
		$this->problem = array_merge($this->problem, $routeValues);
	}

	//The data the play page's script reads, plain values only: what is around any problem ($page) and
	//the problem itself ($problem). It is what a request for the next problem will return.
	/** @return array{page: array<string, mixed>, problem: array<string, mixed>} */
	public function clientData(string $lightDark): array
	{
		$problem = $this->problem;
		$tsumego = $problem['tsumego'];
		$setConnection = $problem['setConnection'];
		$variant = $problem['tv'];
		$semeai = $problem['semeai'];
		$tsumegoFilters = $problem['tsumegoFilters'];
		return [
			'page' => [
				'mode' => Auth::getMode(),
				'modes' => [
					'level' => Constants::$LEVEL_MODE,
					'rating' => Constants::$RATING_MODE,
					'time' => Constants::$TIME_MODE,
					'mistakeTraining' => Constants::$MISTAKE_TRAINING_MODE],
				'userID' => Auth::getUserID(),
				'isAdmin' => Auth::isAdmin(),
				'canRequestSolution' => AdminPolicy::canRequestSolution(Auth::getIdentity()),
				'accountBlocked' => $this->page['accountBlocked'],
				'currentHealth' => Auth::getRemainingHealth(),
				'maxHealth' => $this->page['maxHealth'],
				'fullHeart' => $this->page['fullHeart'],
				'emptyHeart' => $this->page['emptyHeart'],
				'revelationUseCount' => HeroPowers::remainingRevelationUseCount(),
				'heroPowers' => Auth::isInMistakeTrainingMode() ? null : HeroPowers::clientData(),
				'sprintSeconds' => Constants::$SPRINT_SECONDS,
				'playerRatingCalculationModifier' => Constants::$PLAYER_RATING_CALCULATION_MODIFIER,
				'secondsAddedPerMovePlayed' => TimeModeUtil::SECONDS_ADDED_PER_MOVE_PLAYED,
				'timeModeSecondsToSolve' => $problem['timeMode']->secondsToSolve ?? null,
				//a run of a challenge, which keeps the lines of its fails (ChallengeFailedLine), unlike plain time mode
				'challengeRun' => ($problem['timeMode']->currentSession->challenge_id ?? null) !== null,
				'playOutcome' => [
					'solvedClean' => PlayOutcome::SolvedClean->value,
					'solvedAfterMistake' => PlayOutcome::SolvedAfterMistake->value,
					'failed' => PlayOutcome::Failed->value,
					'timedOut' => PlayOutcome::TimedOut->value],
				'mistakeTrainingQueueLink' => MistakeTraining::$QUEUE_LINK,
				'light' => $lightDark,
				'boardPanels' => Auth::isAdmin() ? 'tree+control+tool+comment+file' : 'tree+control',
				//The tiles of the filters the player chose, but those of the filter the collection is chosen by
				'activeTopicTiles' => $tsumegoFilters->query != 'topics' ? array_values($tsumegoFilters->sets) : [],
				'activeDifficultyTiles' => $tsumegoFilters->query != 'difficulty' ? array_values($tsumegoFilters->rankFilterLabels()) : [],
				'activeTagTiles' => $tsumegoFilters->query != 'tags' ? array_values($tsumegoFilters->tags) : []],
			'problem' => [
				'tsumegoID' => (int) $tsumego->id,
				'setConnectionID' => (int) $setConnection->id,
				'status' => $problem['tsumegoStatus'],
				'solved' => $problem['solved'],
				'goldenTsumego' => $problem['goldenTsumego'],
				'isSandbox' => $problem['isSandbox'],
				'assignmentReplay' => $problem['assignmentReplay'],
				//The author plays the problem as an author, also an admin who asked for its solution
				'authorProblem' => ($tsumego->author_user_id !== null && $tsumego->author_user_id == Auth::getUserID()) || $problem['requestSolution'],
				'requestSolution' => (bool) $problem['requestSolution'],
				'previousLink' => $problem['previousLink'] ?? '',
				'nextLink' => $problem['nextLink'] ?? '',
				'resetLink' => $problem['resetLink'],
				'noSkipNextLink' => $problem['noSkipNextLink'] ?? '',
				'author' => $tsumego->author,
				'userSets' => $problem['userSets'],
				'pass' => (bool) $tsumego->pass,
				'sgf' => $problem['sgf']->sgf,
				//the version played, which the line of a failed problem of a challenge is in (ChallengeFailedLine)
				'sgfID' => (int) $problem['sgf']->id,
				'corner' => $problem['corner'],
				'playerColor' => $problem['playerColor'],
				'swapColors' => $problem['swapColors'],
				'board' => $problem['boardSelection'],
				'xpStatus' => $problem['tsumegoXPAndRating']->clientData(),
				'semeai' => $semeai === null ? null : [
					'libertyCount' => $semeai->liberty_count,
					'semeaiTypeID' => $semeai->semeai_type_id,
					'insideLiberties' => $semeai->inside_liberties,
					'explanation' => $semeai->explanation,
					//The liberties the question is about, without a liberty count no question can be made
					'question' => $semeai->liberty_count === null ? null : self::semeaiQuestion($semeai, $problem)],
				'variant' => $variant === null ? null : [
					'type' => TsumegoVariantTable::BOARD_TYPE_NAMES[$variant->tsumego_variant_type_id],
					'answers' => array_values($problem['displayAnswers']),
					'correctAnswer' => (int) $variant->correct_answer,
					'explanation' => $variant->explanation ?? ''],
			],
		];
	}

	/**
	 * @param array<string, mixed> $problem
	 * @return array{libertiesB: int, libertiesW: int, variance: int}
	 */
	private static function semeaiQuestion(TsumegoSemeai $semeai, array $problem): array
	{
		[$libertiesB, $libertiesW] = self::calculateLibertyStatus(
			$problem['multipleChoiceSquares'],
			$problem['multipleChoiceTriangles'],
			$semeai->minimum_liberties,
			$semeai->maximum_liberties,
			$semeai->liberty_count,
			$semeai->variance);
		return ['libertiesB' => $libertiesB, 'libertiesW' => $libertiesW, 'variance' => $problem['multipleChoiceTriangles']];
	}

	//The liberties to take from Black and from White in a semeai question, at random within its limits
	/** @return array{int, int} */
	private static function calculateLibertyStatus(
		int $multipleChoiceSquares,
		int $multipleChoiceTriangles,
		?int $minLiberties,
		?int $maxLiberties,
		int $libertyCount,
		?int $variance): array
	{
		// no variance, like 0, is the distance between the minimum and the maximum
		$minLiberties = max(1, $minLiberties);
		$maxLiberties = $maxLiberties != 0 ? min($maxLiberties, $libertyCount) : $libertyCount;

		$minBlackLiberties = max($minLiberties, $libertyCount - $multipleChoiceSquares);
		// the maximum can be unobtainable when there is not enough places where we can remove black liberties
		$maxBlackLiberties = max($maxLiberties, $libertyCount - $multipleChoiceSquares);

		$minWhiteLiberties = max($minLiberties, $libertyCount - $multipleChoiceTriangles);
		// the maximum can be unobtainable when there is not enough places where we can remove white liberties
		$maxWhiteLiberties = max($maxLiberties, $libertyCount - $multipleChoiceTriangles);

		$variance = $variance ?: $maxLiberties - $minLiberties;

		// Restrict Black to values for which a valid White value exists
		$minBlackLiberties = max($minBlackLiberties, $minWhiteLiberties - $variance);
		$maxBlackLiberties = min($maxBlackLiberties, $maxWhiteLiberties + $variance);

		$remainingBlackLiberties = random_int($minBlackLiberties, $maxBlackLiberties);

		// Calculate valid White range
		$validMinWhiteLiberties = max($minWhiteLiberties, $remainingBlackLiberties - $variance);
		$validMaxWhiteLiberties = min($maxWhiteLiberties, $remainingBlackLiberties + $variance);

		// Pick remaining White liberties
		$remainingW = random_int($validMinWhiteLiberties, $validMaxWhiteLiberties);

		// Return number of liberties to remove
		return [$libertyCount - $remainingBlackLiberties, $libertyCount - $remainingW];
	}

	/** @var array<string, mixed> */
	public array $page = [];

	/** @var array<string, mixed> */
	public array $problem = [];

	private function buildPage(): void
	{
		//What only some modes have: the link of the time mode to its next problem without a skip and
		//the difficulty slider of the rating mode, which their routes set
		$this->page['noSkipNextLink'] = '';
		$this->page['difficulty'] = null;
		if (Auth::isInRatingMode() || Auth::isInTimeMode())
			$this->page['_title'] = 'Tsumego Hero';
		//The menu item of the mode played
		if (Auth::isInLevelMode())
			$this->page['_page'] = 'level mode';
		elseif (Auth::isInRatingMode())
			$this->page['_page'] = 'rating mode';
		elseif (Auth::isInTimeMode())
			$this->page['_page'] = 'time mode';

		$maxHealth = Util::getHealthBasedOnLevel(Auth::getWithDefault('level', 0));
		$this->page['maxHealth'] = $maxHealth;
		$this->page['fullHeart'] = $maxHealth >= 8 ? 'heart1small' : 'heart1';
		$this->page['emptyHeart'] = $maxHealth >= 8 ? 'heart2small' : 'heart2';
		$this->page['accountBlocked'] = Auth::isLoggedIn() && (bool) Auth::getUser()['blocked'];
		$this->page['isAllowedToContribute'] = Auth::isLoggedIn() && BasePolicy::canPropose(Auth::getIdentity());
		$this->page['canAddMoreTags'] = Auth::isLoggedIn() && TagConnectionTable::canCurrentUserAddTag();
		$this->page['classroomAssignment'] = $this->classroomAssignment;
	}

	private function buildProblem(int $setConnectionID, ServerRequest $request): void
	{
		$setConnection = TableRegistry::getTableLocator()->get('SetConnection')->find()->where(['id' => $setConnectionID])->first();
		if (!$setConnection)
			throw new NotFoundException("Set connection " . $setConnectionID . " wasn't found in the database.");
		$tsumegoID = (int) $setConnection->tsumego_id;
		//The set connection's foreign key deletes it with its problem
		//A semeai problem comes with its values, joined in the same query
		$tsumego = TableRegistry::getTableLocator()->get('Tsumego')->get($tsumegoID, contain: ['TsumegoSemeai']);
		$semeai = $tsumego->tsumego_semeai;
		//A problem in the trash is gone for everyone but the admins, who can take it back
		if ($tsumego->deleted !== null && !Auth::isAdmin())
			throw new NotFoundException('Problem not found');

		$set = TableRegistry::getTableLocator()->get('Set')->get($setConnection->set_id);
		AuthorizationComponent::require(SetPolicy::canPlay(Auth::getIdentity(), $set));
		Util::setCookie('lastVisit', $tsumegoID);

		$tsumegoVariant = TableRegistry::getTableLocator()->get('TsumegoVariant')->find()->where(['tsumego_id' => $tsumegoID])->first();
		$tsumegoFilters = new TsumegoFilters();
		$tsumegoStatus = $this->tsumegoStatus($tsumegoID);
		$goldenTsumego = $tsumegoStatus == TsumegoStatusTable::$GOLDEN;
		$amountOfOtherCollection = TsumegoUtil::countTsumegosFromSet((int) $set->id);
		$sgf = self::acceptedSgf($tsumegoID);
		//Solved for the current mode: in time mode every problem is presented fresh, so this stays
		//false until it is solved
		$solved = TsumegoUtil::isSolvedForCurrentMode($tsumegoStatus);

		$this->problem['og'] = self::openGraph($setConnection, $set, $tsumego, $sgf, $amountOfOtherCollection);
		if ($semeai !== null)
		{
			$marks = SgfParser::firstValues($sgf->sgf, ['TR', 'SQ']);
			$this->problem['multipleChoiceTriangles'] = count(SgfParser::points($marks['TR']));
			$this->problem['multipleChoiceSquares'] = count(SgfParser::points($marks['SQ']));
		}
		$this->buildNavigation($setConnection, $set, $tsumegoFilters);

		$this->buildPlayerColor($request, $sgf->sgf, $tsumegoVariant != null
			|| $semeai !== null
			|| SgfParser::boardSize($sgf->sgf) != 19
			|| $tsumego->played_as_black);
		//The corner is purely a visual mirror, it never affects solving. "Original" shows the
		//problem's canonical orientation, which besogo normalizes to top-left.
		$this->problem['corner'] = Auth::getBoardOrientationSetting() === UserTable::BOARD_ORIENTATION_SETTING_ORIGINAL ? 'top-left' : self::randomCorner();

		//The description, the hint and the answers are stored in the true colors of the stones; when the
		//board is inverted, they swap Black and White to match what the player sees
		$swapColors = $this->problem['swapColors'];
		$this->problem['displayDescription'] = $swapColors ? self::swapColorWords($tsumego->description) : $tsumego->description;
		$hint = (string) $tsumego->hint;
		$this->problem['displayHint'] = $swapColors ? self::swapColorWords($hint) : $hint;
		$displayAnswers = $tsumegoVariant?->extract(['answer1', 'answer2', 'answer3', 'answer4']);
		if ($displayAnswers !== null && $swapColors)
			$displayAnswers = array_map(fn($answer) => $answer === null ? null : self::swapColorWords($answer), $displayAnswers);
		$this->problem['displayAnswers'] = $displayAnswers;

		$this->problem['solved'] = $solved;
		//A problem solved before is played again for a class assignment, which counts the solve on its own
		$this->problem['assignmentReplay'] = $this->classroomAssignment !== null && $solved && empty($this->problem['solvedForAssignment']);
		//Reset opens the problem again on the route it is played from, so a retry in the training or
		//in a class assignment stays there
		if ($this->classroomAssignment !== null)
			$this->problem['resetLink'] = ClassroomAssignmentTable::playLink((int) $this->classroomAssignment['id'], (int) $setConnection->id);
		else
			$this->problem['resetLink'] = (Auth::isInMistakeTrainingMode() ? MistakeTraining::$PLAY_LINK_PREFIX : '/') . (int) $setConnection->id;

		$this->problem['isAllowedToTag'] = Auth::isLoggedIn() && TagConnectionPolicy::canAdd(Auth::getIdentity(), $tsumego);
		$this->problem['tagData'] = TagTable::getForTsumego($tsumegoID);
		$this->problem['userSets'] = self::playerSets($tsumegoID);
		$this->problem['hasSgfProposal'] = TableRegistry::getTableLocator()->get('Sgf')->exists([
			'tsumego_id' => $tsumegoID,
			'user_id' => Auth::getUserID(),
			'sgf_status_id' => SgfTable::STATUS_PROPOSED]);
		$this->problem['requestSolution'] = false;
		if ($request->getQuery('requestSolution') !== null)
		{
			AuthorizationComponent::require(AdminPolicy::canRequestSolution(Auth::getIdentity()));
			$this->problem['requestSolution'] = AdminActivityLogger::log(AdminActivityTypeTable::SOLUTION_REQUEST, $tsumegoID);
		}

		$this->problem['boardSelection'] = BoardSelector::selectBoard(BoardSelector::playerBitmask(), $goldenTsumego, $set->board_theme_index);
		$this->problem['isSandbox'] = $set->public == 0 && $set->user_id === null;
		$this->problem['goldenTsumego'] = $goldenTsumego;
		$this->problem['sgf'] = $sgf;
		$this->problem['set'] = $set;
		$this->problem['tsumego'] = $tsumego;
		$this->problem['semeai'] = $semeai;
		$this->problem['tsumegoStatus'] = $tsumegoStatus;
		$this->problem['tv'] = $tsumegoVariant;
		$this->problem['setConnection'] = $setConnection;
		$this->problem['setConnections'] = TsumegoUtil::getSetConnectionsWithTitles($tsumegoID);
		$this->problem['tsumegoFilters'] = $tsumegoFilters;
		$this->problem['amountOfOtherCollection'] = $amountOfOtherCollection;
		$this->problem['tsumegoXPAndRating'] = new TsumegoXPAndRating($tsumego, $tsumegoStatus, (int) $setConnection->id);
	}

	public static function swapColorWords(string $text): string
	{
		return preg_replace_callback(
			'/\b(Black|black|White|white)\b/',
			fn($match) => ['Black' => 'White', 'black' => 'white', 'White' => 'Black', 'white' => 'black'][$match[1]],
			$text);
	}

	//The player's status of the problem, visited when there is none. A first visit is stored when the
	//route marks it ($markAsVisited); a status written meanwhile by another request is kept, and read.
	private function tsumegoStatus(int $tsumegoID): string
	{
		if (!Auth::isLoggedIn())
			return TsumegoStatusTable::$VISITED;
		$status = TsumegoStatusTable::statusOf(Auth::getUserID(), $tsumegoID);
		if ($status !== null || !$this->markAsVisited)
			return $status ?? TsumegoStatusTable::$VISITED;
		$inserted = Util::execute(
			'INSERT INTO tsumego_status (user_id, tsumego_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = status',
			[Auth::getUserID(), $tsumegoID, TsumegoStatusTable::$VISITED]);
		return $inserted ? TsumegoStatusTable::$VISITED : TsumegoStatusTable::statusOf(Auth::getUserID(), $tsumegoID) ?? TsumegoStatusTable::$VISITED;
	}

	//The newest accepted SGF of the problem, a placeholder board when it has none
	private static function acceptedSgf(int $tsumegoID): Sgf
	{
		return TableRegistry::getTableLocator()->get('Sgf')->find()
			->select(['id', 'sgf', 'user_id', 'created'])
			->where(['tsumego_id' => $tsumegoID, 'sgf_status_id' => SgfTable::STATUS_ACCEPTED])
			->orderByDesc('id')
			->first()
			?? new Sgf(['id' => null, 'sgf' => Constants::$SGF_PLACEHOLDER, 'user_id' => null, 'created' => null]);
	}

	//The player's own sets for the heart dropdown, which of them have the problem already, and which
	//is the default one the heart itself adds to
	private static function playerSets(int $tsumegoID): array
	{
		if (!Auth::isLoggedIn())
			return [];
		$sets = Util::query("
SELECT s.id, s.title, sc.tsumego_id
FROM `set` s
LEFT JOIN set_connection sc ON sc.set_id = s.id AND sc.tsumego_id = ?
WHERE s.user_id = ?
ORDER BY s.title", [$tsumegoID, Auth::getUserID()]);
		$defaultSetID = (int) (Auth::getUser()['default_set_id'] ?? 0);
		return array_map(fn($set) => [
			'id' => $set['id'],
			'title' => $set['title'],
			'contains' => $set['tsumego_id'] != null,
			'isDefault' => (int) $set['id'] === $defaultSetID], $sets);
	}

	/** @return array<string, mixed> */
	private static function openGraph(SetConnection $setConnection, Set $set, Tsumego $tsumego, Sgf $sgf, int $amountOfOtherCollection): array
	{
		$title = ($set->title ?? '') . ' ' . $setConnection->num . '/' . $amountOfOtherCollection;
		//The descriptions and the image both use the true colors of the SGF
		$description = strip_tags($tsumego->description ?? '');
		$author = $tsumego->author;
		if ($author !== '' && $author !== 'Unknown')
			$description .= ' - by ' . $author;
		if (trim($description) === '')
			$description = $title;

		return [
			'title' => $title,
			'description' => $description,
			'image' => Router::url('/tsumego-image/' . $setConnection->id
				. '?v=' . Constants::$TSUMEGO_IMAGE_VERSION
				. '&t=' . ($sgf->created?->getTimestamp() ?? ''), true),
			'image_type' => 'image/png',
			'image_width' => Constants::$OG_IMAGE_WIDTH,
			'image_height' => Constants::$OG_IMAGE_HEIGHT,
			'image_alt' => $title,
			'url' => Router::url('/' . $setConnection->id, true),
			'type' => 'website',
			'site_name' => 'Tsumego',
			'locale' => 'en_US',
			'twitter_card' => 'summary_large_image',
		];
	}

	//The row of problem buttons, the previous and next links and the title of the modes that walk
	//a list: a class assignment, the level mode and the mistake training. The board always gets both
	//arrows, and an empty one is the arrow turned off.
	private function buildNavigation(SetConnection $setConnection, Set $set, TsumegoFilters $tsumegoFilters): void
	{
		$setConnectionID = (int) $setConnection->id;
		$this->problem['tsumegoButtons'] = null;
		$this->problem['previousLink'] = '';
		$this->problem['queryTitle'] = '';
		if ($this->classroomAssignment !== null)
		{
			$buttons = ClassroomAssignmentTable::playButtons($this->classroomAssignment, (int) Auth::getUserID(), $setConnectionID);
			$this->problem['tsumegoButtons'] = SetNavigationButtons::around($buttons, $setConnection);
			$this->problem['_title'] = ClassroomAssignmentTable::title($this->classroomAssignment) . ' ' . $buttons->currentOrder . '/' . $buttons->highestTsumegoOrder . ' on Tsumego Hero';
			$currentButton = array_find((array) $buttons, fn($button) => $button->setConnectionID == $setConnectionID);
			$this->problem['solvedForAssignment'] = $currentButton?->status === ClassroomAssignmentTable::BUTTON_SOLVED;
			//Past either end of a class assignment is the page of its class
			$this->problem = array_merge($this->problem, $buttons->previousAndNextLink(null, $setConnectionID, null, '/classes/' . (int) $this->classroomAssignment['classroom_id']));
		}
		elseif (Auth::isInLevelMode())
		{
			$buttons = new TsumegoButtons($tsumegoFilters, $setConnectionID, null, $set->id);
			$this->problem['tsumegoButtons'] = SetNavigationButtons::around($buttons, $setConnection);
			$collectionTitle = $tsumegoFilters->query == 'topics' ? $set->title : (Util::cookie('lastSet') ?? 'Tsumego');
			$this->problem['_title'] = $collectionTitle . ' ' . $setConnection->num . '/' . $buttons->highestTsumegoOrder . ' on Tsumego Hero';
			$this->problem = array_merge($this->problem, $buttons->previousAndNextLink($tsumegoFilters, $setConnectionID, $set));
			$this->problem['queryTitle'] = $tsumegoFilters->getSetTitle($set) . $buttons->getPartitionTitleSuffix() . ' ' . $buttons->currentOrder . '/' . $buttons->highestTsumegoOrder;
		}
		elseif (Auth::isInMistakeTrainingMode())
		{
			$buttons = MistakeTraining::buildQueueButtons($setConnectionID);
			$this->problem['tsumegoButtons'] = SetNavigationButtons::around($buttons, $setConnection);
			$this->problem['_title'] = 'Mistake Training ' . $buttons->currentOrder . '/' . $buttons->highestTsumegoOrder . ' on Tsumego Hero';
			//No link before the first problem: the queue page would serve the first due one again
			$this->problem['previousLink'] = $buttons->previousAndNextLink($tsumegoFilters, $setConnectionID, $set, '')['previousLink'];
			//The row keeps what today already solved, so "Next" skips it instead of serving the neighbour
			$this->problem['nextLink'] = MistakeTraining::nextLink($buttons, $setConnectionID);
		}
	}

	//Which color the player plays, and whether the board colors are swapped for it (when the SGF
	//starts with the other color). "Original" plays the SGF as designed, "Random" picks a side.
	//$playAsBlack: the problem is about the actual black and white groups (variants, semeai, small
	//boards, some sets), so the player is black. ?playercolor=black|white overrides all of it.
	private function buildPlayerColor(ServerRequest $request, string $sgf, bool $playAsBlack): void
	{
		$sgfColor = SgfParser::firstMoveColor($sgf);
		$requestedColor = strtolower((string) ($request->getQuery('playercolor') ?? ''));
		if (in_array($requestedColor, ['black', 'white'], true))
			$playerColor = $requestedColor;
		elseif ($playAsBlack)
			$playerColor = 'black';
		elseif (Auth::getPlayerColorSetting() === UserTable::PLAYER_COLOR_SETTING_ORIGINAL)
			$playerColor = $sgfColor === 'W' ? 'white' : 'black';
		else
			$playerColor = rand(0, 1) ? 'white' : 'black';
		$this->problem['playerColor'] = $playerColor;
		$this->problem['swapColors'] = ($sgfColor === 'W' && $playerColor === 'black') || ($sgfColor === 'B' && $playerColor === 'white');
	}

	private static function randomCorner(): string
	{
		$corners = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
		return $corners[array_rand($corners)];
	}
}

<?php

use App\Model\Entity\Sgf;
use App\Model\Table\SgfTable;
use App\Model\Table\TsumegoStatusTable;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\ClassroomAssignmentTable;
use App\Model\Table\TagConnectionTable;
use App\Model\Table\TagTable;
use App\Policy\AdminPolicy;
use App\Policy\BasePolicy;
use App\Policy\SetPolicy;
use App\Policy\TagConnectionPolicy;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\BoardSelector;
use App\Utility\Constants;
use App\Utility\MistakeTraining;
use App\Utility\SetNavigationButtonsInput;
use App\Utility\SgfParser;
use App\Utility\TimeMode;
use App\Utility\TsumegoButtons;
use App\Utility\TsumegoFilters;
use App\Utility\TsumegoUtil;
use App\Utility\TsumegoXPAndRating;
use App\Utility\Util;
use Cake\ORM\TableRegistry;

class Play
{
	public function __construct(callable $setFunction)
	{
		$this->setFunction = $setFunction;
	}

	private function set(string $name, mixed $value): void
	{
		($this->setFunction)($name, $value);
	}

	public function play(int $setConnectionID, CakeRequest $request): void
	{
		$this->set('page', 'play');
		//The board always receives both arrows, and an empty one is the arrow turned off.
		//Only the modes that walk a list fill this one in.
		$this->set('previousLink', '');

		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		if (!$setConnection)
			throw new NotFoundException("Set connection " . $setConnectionID . " wasn't found in the database.");
		$tsumegoID = (int) $setConnection['SetConnection']['tsumego_id'];
		//The set connection's foreign key deletes it with its problem
		$t = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		//A problem in the trash is gone for everyone but the admins, who can take it back
		if (!empty($t['Tsumego']['deleted']) && !Auth::isAdmin())
			throw new NotFoundException('Problem not found');

		$set = ClassRegistry::init('Set')->findById($setConnection['SetConnection']['set_id']);
		AuthorizationComponent::require(SetPolicy::canPlay(Auth::getIdentity(), $set['Set']));
		Util::setCookie('lastVisit', $tsumegoID);

		$tsumegoVariant = TableRegistry::getTableLocator()->get('TsumegoVariant')->find()->where(['tsumego_id' => $tsumegoID])->first();
		$tsumegoFilters = new TsumegoFilters();
		$tsumegoStatus = self::tsumegoStatus($tsumegoID);
		$goldenTsumego = $tsumegoStatus == TsumegoStatusTable::$GOLDEN;
		$amountOfOtherCollection = TsumegoUtil::countTsumegosFromSet((int) $set['Set']['id']);
		$sgf = self::acceptedSgf($tsumegoID);

		$this->exportOpenGraph($setConnection, $set, $t, $sgf, $amountOfOtherCollection);
		if (!empty($t['Tsumego']['semeai_type']))
		{
			$this->set('multipleChoiceTriangles', count(Util::getFollowingSgfCoordinates($sgf->sgf, strpos($sgf->sgf, 'TR') + 2)));
			$this->set('multipleChoiceSquares', count(Util::getFollowingSgfCoordinates($sgf->sgf, strpos($sgf->sgf, 'SQ') + 2)));
		}
		$queryTitle = $this->exportNavigation($setConnection, $set, $tsumegoFilters);

		$t['Tsumego']['status'] = $tsumegoStatus;
		if (empty($t['Tsumego']['file']))
			$t['Tsumego']['file'] = $setConnection['SetConnection']['num'];
		$boardSize = self::boardSizeFromSetTitle($set['Set']['title']);
		$this->exportPlayerColor($request, $sgf->sgf, $tsumegoVariant != null
			|| ($t['Tsumego']['semeai_type'] ?? 0) != 0
			|| $boardSize != 19
			|| in_array($set['Set']['id'], self::SETS_PLAYED_AS_BLACK));
		//The corner is purely a visual mirror, it never affects solving. "Original" shows the
		//problem's canonical orientation, which besogo normalizes to top-left.
		$this->set('corner', Auth::getBoardOrientationSetting() === User::BOARD_ORIENTATION_SETTING_ORIGINAL ? 'top-left' : self::randomCorner());

		if (Auth::isInRatingMode() || Auth::isInTimeMode())
			$this->set('_title', 'Tsumego Hero');
		if (Auth::isInLevelMode())
			$this->set('page', 'level mode');
		elseif (Auth::isInRatingMode())
			$this->set('page', 'rating mode');
		elseif (Auth::isInTimeMode())
			$this->set('page', 'time mode');

		$maxHealth = Util::getHealthBasedOnLevel(Auth::getWithDefault('level', 0));
		$this->set('maxHealth', $maxHealth);
		$this->set('fullHeart', $maxHealth >= 8 ? 'heart1small' : 'heart1');
		$this->set('emptyHeart', $maxHealth >= 8 ? 'heart2small' : 'heart2');

		$this->set('isAllowedToContribute', Auth::isLoggedIn() && BasePolicy::canPropose(Auth::getIdentity()));
		$this->set('isAllowedToTag', Auth::isLoggedIn() && TagConnectionPolicy::canAdd(Auth::getIdentity(), $t['Tsumego']));
		$this->set('canAddMoreTags', Auth::isLoggedIn() && TagConnectionTable::canCurrentUserAddTag());
		$this->set('tagData', TagTable::getForTsumego($tsumegoID));
		$this->set('userSetsJson', json_encode(self::playerSets($tsumegoID)));
		$this->set('hasSgfProposal', TableRegistry::getTableLocator()->get('Sgf')->exists([
			'tsumego_id' => $tsumegoID,
			'user_id' => Auth::getUserID(),
			'sgf_status_id' => SgfTable::STATUS_PROPOSED]));
		if (isset($request->query['requestSolution']))
		{
			AuthorizationComponent::require(AdminPolicy::canRequestSolution(Auth::getIdentity()));
			$this->set('requestSolution', AdminActivityLogger::log(AdminActivityTypeTable::SOLUTION_REQUEST, $tsumegoID));
		}

		$boardsBitmask = BoardSelector::filterValidBits(Auth::isLoggedIn() ? Auth::getUser()['boards_bitmask'] : BoardSelector::$DEFAULT_BOARDS_BITMASK);
		$this->set('boardSelection', BoardSelector::selectBoard($boardsBitmask, $goldenTsumego, $set['Set']['board_theme_index']));
		$this->set('accountBlocked', Auth::isLoggedIn() && (bool) Auth::getUser()['blocked']);
		$this->set('isSandbox', $set['Set']['public'] == 0 && $set['Set']['user_id'] === null);
		$this->set('goldenTsumego', $goldenTsumego);
		$this->set('sgf', $sgf);
		$this->set('set', $set);
		$this->set('t', $t);
		$this->set('tv', $tsumegoVariant);
		$this->set('checkBSize', $boardSize);
		$this->set('file', 'placeholder2.sgf');
		$this->set('alternative_response', $t['Tsumego']['alternative_response']);
		$this->set('passEnabled', $t['Tsumego']['pass']);
		$this->set('setConnection', $setConnection);
		$this->set('setConnections', TsumegoUtil::getSetConnectionsWithTitles($tsumegoID));
		$this->set('tsumegoFilters', $tsumegoFilters);
		$this->set('queryTitle', $queryTitle);
		$this->set('amountOfOtherCollection', $amountOfOtherCollection);
		$this->set('tsumegoXPAndRating', new TsumegoXPAndRating($t['Tsumego'], $tsumegoStatus, (int) $setConnection['SetConnection']['id']));
	}

	//The sets in which the player is always black: problems where the colors matter for the question
	private const SETS_PLAYED_AS_BLACK = [109, 233, 236, 239, 243, 244, 246, 251, 253];

	private static function tsumegoStatus(int $tsumegoID): string
	{
		if (!Auth::isLoggedIn())
			return TsumegoStatusTable::$VISITED;
		return TsumegoStatusTable::statusOf(Auth::getUserID(), $tsumegoID) ?? TsumegoStatusTable::$VISITED;
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

	//A set titled e.g. "Life and death 9x9" is played on that board size, everything else on 19x19
	private static function boardSizeFromSetTitle(string $title): int
	{
		$boardSize = 19;
		for ($size = 2; $size <= 19; $size++)
			if (strpos(';' . $title, $size . 'x' . $size))
				$boardSize = $size;
		return $boardSize;
	}

	//The player's own sets for the heart dropdown, and which of them have the problem already
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
		return array_map(fn($set) => ['id' => $set['id'], 'title' => $set['title'], 'contains' => $set['tsumego_id'] != null], $sets);
	}

	private function exportOpenGraph(array $setConnection, array $set, array $t, Sgf $sgf, int $amountOfOtherCollection): void
	{
		$title = ($set['Set']['title'] ?? '') . ' ' . $setConnection['SetConnection']['num'] . '/' . $amountOfOtherCollection;
		//The descriptions and the image both use the true colors of the SGF
		$description = strip_tags($t['Tsumego']['description'] ?? '');
		$author = $t['Tsumego']['author'] ?? '';
		if ($author !== '' && $author !== 'Unknown')
			$description .= ' - by ' . $author;
		if (trim($description) === '')
			$description = $title;

		$this->set('og', [
			'title' => $title,
			'description' => $description,
			'image' => Router::url('/tsumego-image/' . $setConnection['SetConnection']['id']
				. '?v=' . Constants::$TSUMEGO_IMAGE_VERSION
				. '&t=' . ($sgf->created?->getTimestamp() ?? ''), true),
			'image_type' => 'image/png',
			'image_width' => Constants::$OG_IMAGE_WIDTH,
			'image_height' => Constants::$OG_IMAGE_HEIGHT,
			'image_alt' => $title,
			'url' => Router::url('/' . $setConnection['SetConnection']['id'], true),
			'type' => 'website',
			'site_name' => 'Tsumego',
			'locale' => 'en_US',
			'twitter_card' => 'summary_large_image',
		]);
	}

	//The row of problem buttons, the previous and next links and the title of the modes that walk
	//a list: a class assignment, the level mode and the mistake training. Returns the title of
	//the collection the level mode walks.
	private function exportNavigation(array $setConnection, array $set, TsumegoFilters $tsumegoFilters): string
	{
		$setConnectionID = (int) $setConnection['SetConnection']['id'];
		if ($this->classroomAssignment !== null)
		{
			$buttons = ClassroomAssignmentTable::playButtons($this->classroomAssignment, (int) Auth::getUserID(), $setConnectionID);
			new SetNavigationButtonsInput($this->setFunction)->execute($buttons, $setConnection);
			$this->set('_title', ClassroomAssignmentTable::title($this->classroomAssignment) . ' ' . $buttons->currentOrder . '/' . $buttons->highestTsumegoOrder . ' on Tsumego Hero');
			$this->set('classroomAssignment', $this->classroomAssignment);
			$currentButton = array_find((array) $buttons, fn($button) => $button->setConnectionID == $setConnectionID);
			$this->set('solvedForAssignment', $currentButton?->status === ClassroomAssignmentTable::BUTTON_SOLVED);
			//Past either end of a class assignment is the page of its class
			$buttons->exportCurrentAndPreviousLink($this->setFunction, null, $setConnectionID, null, '/classes/' . (int) $this->classroomAssignment['classroom_id']);
			return '';
		}
		if (Auth::isInLevelMode())
		{
			$buttons = new TsumegoButtons($tsumegoFilters, $setConnectionID, null, $set['Set']['id']);
			new SetNavigationButtonsInput($this->setFunction)->execute($buttons, $setConnection);
			$collectionTitle = $tsumegoFilters->query == 'topics' ? $set['Set']['title'] : ($_COOKIE['lastSet'] ?? 'Tsumego');
			$this->set('_title', $collectionTitle . ' ' . $setConnection['SetConnection']['num'] . '/' . $buttons->highestTsumegoOrder . ' on Tsumego Hero');
			$buttons->exportCurrentAndPreviousLink($this->setFunction, $tsumegoFilters, $setConnectionID, $set);
			return $tsumegoFilters->getSetTitle($set) . $buttons->getPartitionTitleSuffix() . ' ' . $buttons->currentOrder . '/' . $buttons->highestTsumegoOrder;
		}
		if (Auth::isInMistakeTrainingMode())
		{
			$buttons = MistakeTraining::buildQueueButtons($setConnectionID);
			new SetNavigationButtonsInput($this->setFunction)->execute($buttons, $setConnection);
			$this->set('_title', 'Mistake Training ' . $buttons->currentOrder . '/' . $buttons->highestTsumegoOrder . ' on Tsumego Hero');
			//No link before the first problem: the queue page would serve the first due one again
			$buttons->exportCurrentAndPreviousLink($this->setFunction, $tsumegoFilters, $setConnectionID, $set, '');
			//The row keeps what today already solved, so "Next" skips it instead of serving the neighbour
			$this->set('nextLink', MistakeTraining::nextLink($buttons, $setConnectionID));
		}
		return '';
	}

	//Which color the player plays, and whether the board colors are swapped for it (when the SGF
	//starts with the other color). "Original" plays the SGF as designed, "Random" picks a side.
	//$playAsBlack: the problem is about the actual black and white groups (variants, semeai, small
	//boards, some sets), so the player is black. ?playercolor=black|white overrides all of it.
	private function exportPlayerColor(CakeRequest $request, string $sgf, bool $playAsBlack): void
	{
		$sgfColor = SgfParser::firstMoveColor($sgf);
		$requestedColor = strtolower((string) ($request->query['playercolor'] ?? ''));
		if (in_array($requestedColor, ['black', 'white'], true))
			$playerColor = $requestedColor;
		elseif ($playAsBlack)
			$playerColor = 'black';
		elseif (Auth::getPlayerColorSetting() === User::PLAYER_COLOR_SETTING_ORIGINAL)
			$playerColor = $sgfColor === 'W' ? 'white' : 'black';
		else
			$playerColor = rand(0, 1) ? 'white' : 'black';
		$this->set('playerColor', $playerColor);
		$this->set('swapColors', ($sgfColor === 'W' && $playerColor === 'black') || ($sgfColor === 'B' && $playerColor === 'white'));
	}

	public static function renderTitle(array $setConnection, array $set, TsumegoFilters $tsumegoFilters, ?TsumegoButtons $tsumegoButtons, int $amountOfOtherCollection, int|float|string|null $difficulty, ?TimeMode $timeMode, string $queryTitle, array $t): string
	{
		if (Auth::isInTimeMode())
			return '<font size="5px">' . $timeMode?->currentPosition() . ' of ' . $timeMode?->overallCount . '</font>';

		if (Auth::isInRatingMode())
			return '<div class="slidecontainer">
						<input type="range" min="1" max="7" value="' . $difficulty . '" class="slider" id="rangeInput" name="rangeInput">
						<div id="sliderText">regular</div>
						</div>
						<a id="playTitleA" href=""></a>';

		if (Auth::isInMistakeTrainingMode())
			return '<a id="playTitleA" href="' . MistakeTraining::$OVERVIEW_LINK . '">Mistake Training ' . ($tsumegoButtons->currentOrder ?? 0) . '/' . ($tsumegoButtons->highestTsumegoOrder ?? 0) . '</a>';

		$order = $setConnection['SetConnection']['num'];
		if ($tsumegoFilters->query == 'difficulty' || $tsumegoFilters->query == 'tags')
			return '<a id="playTitleA" href="' . Set::url($tsumegoFilters->getSetID($set), $tsumegoButtons?->getPartition() ?? 1) . '">' . $queryTitle . '</a><br>
							<font style="font-weight:400;" color="grey">
											<a style="color:grey;" id="playTitleA" href="' . Set::url($set['Set']['id']) . '">
												(' . $set['Set']['title'] . ' ' . $order . '/' . $amountOfOtherCollection . ')
											</a>
										</font>';
		return '<a id="playTitleA" href="' . Set::url($set['Set']['id'], $tsumegoButtons?->getPartition() ?? 1) . '">' . $set['Set']['title'] . ' ' . $tsumegoButtons?->getPartitionTitleSuffix() . ' ' . $order . '/' . $tsumegoButtons?->highestTsumegoOrder . '</a>';
	}

	//The title of a problem played as a part of a class assignment: the assignment and where in
	//it the player is, and under it which problem of which collection it really is.
	/** @param array<string, mixed> $assignment With its collections, see ClassroomAssignmentTable::withCollections() */
	public static function renderAssignmentTitle(array $assignment, TsumegoButtons $tsumegoButtons, array $setConnection, array $set, int $amountOfOtherCollection): string
	{
		return '<a id="playTitleA" href="/classes/' . (int) $assignment['classroom_id'] . '">'
			. h(ClassroomAssignmentTable::title($assignment)) . ' ' . $tsumegoButtons->currentOrder . '/' . $tsumegoButtons->highestTsumegoOrder . '</a><br>
							<font style="font-weight:400;" color="grey">
								<a style="color:grey;" class="play-title__origin" href="' . Set::url($set['Set']['id']) . '">
									(' . h($set['Set']['title']) . ' ' . $setConnection['SetConnection']['num'] . '/' . $amountOfOtherCollection . ')
								</a>
							</font>';
	}

	private static function randomCorner(): string
	{
		$corners = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
		return $corners[array_rand($corners)];
	}

	//The class assignment the problem is played in, with its collections, set by its route
	//(ClassroomsController::play()): its problems are the row of buttons and Next walks them.
	/** @var array<string, mixed>|null */
	public ?array $classroomAssignment = null;

	/** @var callable */
	private $setFunction;
}

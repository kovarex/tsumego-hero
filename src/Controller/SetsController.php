<?php

namespace App\Controller;

use Cake\Http\Response;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Routing\Router;
use Exception;
use App\Model\Entity\Set;
use App\Model\Table\SetTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\SgfTable;
use App\Model\Table\TsumegoTable;
use Cake\Http\Exception\ConflictException;
use App\Model\Table\AchievementConditionTable;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\ProgressDeletionTable;
use App\Model\Table\UserTable;
use App\Utility\AchievementChecker;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\HtmlSanitizer;
use App\Utility\Preferences;
use App\Utility\Rating;
use App\Utility\SetPartBestScores;
use App\Utility\SetImage;
use App\Utility\SetsSelector;
use App\Utility\Trash;
use App\Utility\TsumegoButtons;
use App\Utility\TsumegoFilters;
use App\Utility\TsumegoUtil;
use App\Utility\Util;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;

class SetsController extends AppController
{
	#[HttpGet]
	public function sandbox(): void
	{
		$this->Authorization->authorize('Set');

		$this->set('_page', 'sandbox');
		$this->set('_title', 'Tsumego Hero - Collections');
		// the sandbox: the builds of the admins, not published yet and owned by nobody
		$setsNew = SetTable::cards('s.public = 0 AND s.user_id IS NULL AND s.deleted IS NULL', [], Auth::getUserID());

		$admins = $this->fetchTable('User')->find()
			->select(['display_name'])
			->where(['user_role_id IN' => UserTable::ADMIN_ROLES])
			->orderBy(['id' => 'ASC'])
			->all()
			->extract('display_name')
			->toList();

		$this->set('admins', $admins);
		$this->set('setsNew', $setsNew);
		$this->set('overallCounter', array_sum(array_column($setsNew, 'amount')));
	}

	#[HttpGet]
	public function mine()
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/');

		$this->showUserSets(Auth::getUserID());
	}

	#[HttpGet]
	public function userSets(string $userId): void
	{
		$this->showUserSets((int) $userId);
	}

	private function showUserSets(int $userId): void
	{
		$isOwn = ($userId === Auth::getUserID());
		$profileUser = UserTable::row($userId);
		if (!$profileUser)
			throw new NotFoundException('User not found');
		$this->assertUserVisible($profileUser);
		$pageTitle = $isOwn ? 'My Sets' : h($profileUser['display_name']) . "'s Sets";
		$this->set('_title', 'Tsumego Hero - ' . $pageTitle);
		$this->set('profileUser', $profileUser);
		$this->set('isOwn', $isOwn);
		// /sets/mine is the shortcut to this page, so the page itself is known by the player's address.
		$this->set('canonicalPath', '/sets/user/' . $userId);

		// Own sets (or an admin browsing) show all sets; others only see public ones
		$publicFilter = ($isOwn || Auth::isAdmin()) ? '' : ' AND s.public = 1';
		$setsNew = SetTable::cards('s.user_id = ?' . $publicFilter, [$userId], Auth::getUserID());

		$this->set('setsNew', $setsNew);
		$this->render('user_sets');
	}

	#[HttpGet]
	#[HttpPost]
	public function create(): ?Response
	{
		$this->Authorization->authorize('Set', 'create');

		if ($this->inputs() !== [])
		{
			$isSandbox = $this->query('sandbox') !== null && $this->Authorization->can('Set', 'createSandbox');

			$set = [];
			$set['title'] = $this->input('title');
			$set['public'] = false;
			if ($this->input('description') !== null)
				$set['description'] = HtmlSanitizer::sanitize((string) $this->input('description'));
			if ($this->input('color') !== null && $this->input('color') !== '')
				$set['color'] = $this->input('color');
			else
				$set['color'] = '#5b9bd5';

			$set['order'] = Constants::$DEFAULT_SET_ORDER;

			if ($isSandbox)
				$set['image'] = 'b1.png';
			else
				$set['user_id'] = Auth::getUserID();

			$setTable = $this->fetchTable('Set');
			$set = $setTable->saveOrFail($setTable->newEntity($set));

			if ($isSandbox)
			{
				$tsumegoTable = $this->fetchTable('Tsumego');
				$tsumego = $tsumegoTable->saveOrFail($tsumegoTable->newEntity([
					'description' => 'b to kill',
					'author' => Auth::getUser()['display_name'],
					'author_user_id' => Auth::getUserID()]));

				$setConnectionTable = $this->fetchTable('SetConnection');
				$setConnectionTable->saveOrFail($setConnectionTable->newEntity([
					'set_id' => $set->id,
					'tsumego_id' => $tsumego->id,
					'num' => 1]));
			}

			return $this->redirect('/sets/edit/' . $set->id);
		}
		return null;
	}

	//Edit a set: details, problems and (for admins) re-rate and solve-mode settings. Owner or admin only.
	#[HttpGet]
	#[HttpPost]
	public function edit(?string $id = null)
	{
		$set = $this->findSet($id);

		$this->Authorization->authorize($set);
		$canEditSettings = $this->Authorization->can('Set', 'editSettings');
		$isSandbox = ($set->public == 0 && $set->user_id === null);

		// Problems in this set, in display order
		$problems = Util::query("
SELECT sc.id AS set_connection_id, sc.num, sc.tsumego_id, t.rating,
	t.pass
FROM set_connection sc
JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL
WHERE sc.set_id = ?
ORDER BY sc.num ASC", [(int) $id]);

		// Both forms of the page post here: the details, and for admins the re-rate and solve modes.
		$formSent = $this->inputs() !== [];
		if ($formSent)
		{
			$this->saveDetails($set);
			if ($canEditSettings)
				$this->saveProblemSettings((int) $set->id, $problems);
		}
		$this->saveCover($set);
		if ($formSent)
			return $this->redirect('/sets/edit/' . (int) $id);

		// Tsumego buttons (status colors, tooltips, board previews) for the problem list
		$tsumegoButtons = new TsumegoButtons(new TsumegoFilters('topics'), null, null, (int) $id);
		$tsumegoButtons->loadPreviews();

		// Solve-mode states for the admin settings panel
		$allPassActive = true;
		$allPassInactive = true;
		foreach ($problems as $problem)
		{
			if (!$problem['pass'])
				$allPassActive = false;
			if ($problem['pass'])
				$allPassInactive = false;
		}

		$setRating = 0;
		if (count($problems) > 0)
			$setRating = round(array_sum(array_column($problems, 'rating')) / count($problems));

		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Edit Set: ' . $set->title);
		$this->set('set', $set);
		$this->set('problems', $problems);
		$this->set('tsumegoButtons', $tsumegoButtons);
		$this->set('canEditSettings', $canEditSettings);
		$this->set('canDelete', $this->Authorization->can($set, 'delete'));
		$this->set('isSandbox', $isSandbox);
		$this->set('allPassActive', $allPassActive);
		$this->set('allPassInactive', $allPassInactive);
		$this->set('setRating', $setRating);
	}

	//The details the form sent: title, description, color and order. An admin's change of a collection that
	//isn't theirs is noted in the admin activity.
	private function saveDetails(Set $set): void
	{
		$old = ['title' => $set->title, 'description' => $set->description, 'color' => $set->color, 'order' => $set->order];
		if ($this->input('title') !== null)
			$set->title = $this->input('title');
		if ($this->input('title2') !== null)
			$set->title2 = $this->input('title2');
		if ($this->input('description') !== null)
			$set->description = HtmlSanitizer::sanitize((string) $this->input('description'));
		if ($this->input('color') !== null && $this->input('color') !== '')
			$set->color = $this->input('color');
		if ($this->input('order') !== null && $this->input('order') !== '')
			$set->order = (int) $this->input('order');
		$this->fetchTable('Set')->saveOrFail($set);

		if ($this->isElevatedSetEdit($set))
			foreach ([
				'title' => AdminActivityTypeTable::SET_TITLE_EDIT,
				'description' => AdminActivityTypeTable::SET_DESCRIPTION_EDIT,
				'color' => AdminActivityTypeTable::SET_COLOR_EDIT,
				'order' => AdminActivityTypeTable::SET_ORDER_EDIT] as $field => $activityType)
					if ($old[$field] != $set->get($field))
						AdminActivityLogger::log($activityType, null, (int) $set->id, Util::strOrNull($old[$field]), Util::strOrNull($set->get($field)));

		CookieFlash::set('Set saved', 'success');
	}

	//The settings of all the problems of the collection, for admins: a new rating for each, and the pass mode
	//when a choice was made for it.
	private function saveProblemSettings(int $setID, array $problems): void
	{
		$problemIDs = array_column($problems, 'tsumego_id');
		$difficulty = $this->input('setDifficulty');
		if ($difficulty !== null && $difficulty != 1200 && $difficulty >= 900 && $difficulty <= 2900)
		{
			//The new rating of each problem is raised to its minimum, then lowered to its maximum
			//(GREATEST and LEAST give NULL for a limit the problem doesn't have)
			$difficulty = (float) $difficulty;
			if ($problemIDs)
				Util::execute('UPDATE tsumego SET rating = COALESCE(LEAST(COALESCE(GREATEST(?, minimum_rating), ?), maximum_rating), COALESCE(GREATEST(?, minimum_rating), ?))
					WHERE id IN (' . implode(',', array_fill(0, count($problemIDs), '?')) . ')', [$difficulty, $difficulty, $difficulty, $difficulty, ...$problemIDs]);
			AdminActivityLogger::log(AdminActivityTypeTable::SET_RATING_EDIT, null, $setID);
		}

		// sent only when the admin picked a choice for it
		$passMode = $this->input('r43');
		if ($passMode != 'yes' && $passMode != 'no')
			return;
		if ($problemIDs)
			$this->fetchTable('Tsumego')->updateAll(['pass' => $passMode == 'yes'], ['id IN' => $problemIDs]);
		AdminActivityLogger::log(AdminActivityTypeTable::SET_PASS_MODE, null, $setID, null, $passMode == 'yes' ? '1' : '0');
	}

	//The cover the form removed or uploaded.
	private function saveCover(Set $set): void
	{
		$setTable = $this->fetchTable('Set');
		if (!empty($this->input('remove_image')))
		{
			SetImage::remove($set->image);
			$set->image = '';
			$setTable->saveOrFail($set);
			CookieFlash::set('Image removed', 'success');
		}

		$image = $this->uploadedFile('image');
		if ($image === null || $image['error'] !== UPLOAD_ERR_OK)
			return;
		$refusal = SetImage::refusalOf($image);
		if ($refusal !== null)
		{
			CookieFlash::set($refusal, 'error');
			return;
		}
		try
		{
			$set->image = SetImage::replace((int) $set->id, $set->image, $image['tmp_name'], SetImage::extensionOf($image['name']));
			$setTable->saveOrFail($set);
			CookieFlash::set('Image uploaded', 'success');
		}
		catch (Exception $e)
		{
			CookieFlash::set('Image upload failed: ' . $e->getMessage(), 'error');
		}
	}

	//Open or close the link sharing of a collection a player assembled.
	#[HttpPost]
	public function share(?string $id = null): mixed
	{
		$setTable = $this->fetchTable('Set');
		$set = $this->findSet($id);
		$this->Authorization->authorize($set, 'share');

		$shared = !empty($this->input('shared'));
		$set->public = $shared;
		if (!$setTable->save($set))
		{
			CookieFlash::set('Sharing could not be saved', 'error');
			return $this->redirect(SetTable::url((int) $id));
		}

		CookieFlash::set($shared
			? 'Anyone with the link can open this collection now'
			: 'Only you can open this collection now', 'success');
		return $this->redirect(SetTable::url((int) $id));
	}

	#[HttpPost]
	public function delete(?string $id = null): Response
	{
		$setID = $id ?? $this->input('id');
		if (!$setID)
			throw new BadRequestException();

		$setTable = $this->fetchTable('Set');
		$set = $this->findSet($setID);

		$this->Authorization->authorize($set);

		// A collection a player assembled is theirs to throw away, the problems in it stay on the site.
		if ($set->user_id !== null)
		{
			$setTable->deleteOrFail($set);
			SetImage::removeFolder((int) $setID);
			return $this->redirect('/sets/mine');
		}

		// A collection of the site goes to the trash, which takes a big part of the site with it,
		// so its title has to be typed to confirm.
		if (trim($this->input('confirmation') ?? '') !== trim((string) $set->title))
		{
			CookieFlash::set('The collection was not deleted: type its title to confirm.', 'error');
			return $this->redirect('/sets/edit/' . (int) $setID);
		}
		Trash::deleteSet((int) $setID);
		AdminActivityLogger::log(AdminActivityTypeTable::SET_DELETE, null, (int) $setID, $set->title);
		CookieFlash::set('The collection ' . $set->title . ' was moved to the trash, it can be restored for '
			. Trash::$RETENTION_DAYS . ' days.', 'success');
		return $this->redirect($set->public == 0 ? '/sets/sandbox' : '/sets');
	}

	#[HttpGet]
	public function index(): void
	{
		$this->set('_page', 'set');
		$this->set('_title', 'Tsumego Hero - Collections');

		$tsumegoFilters = new TsumegoFilters();
		$setTiles = array_column(Util::query('SELECT s.title FROM `set` s WHERE ' . SetTable::officialCondition('s')
			. ' ORDER BY ' . SetConnectionTable::displayOrderForSetSql('s')), 'title');
		$tagTiles = $this->fetchTable('Tag')->find()->select(['name'])->where(['approved' => 1])->all()->extract('name')->toList();

		[$rankFrom, $rankTo] = $tsumegoFilters->rankRange();
		// The weakest rank is the same as leaving the lower end open.
		if ($rankFrom === Rating::weakestRank())
			$rankFrom = '';

		$this->set('setsSelector', new SetsSelector($tsumegoFilters));
		$this->set('rankFrom', $rankFrom);
		$this->set('rankTo', $rankTo);
		$this->set('rankFilterLabels', $tsumegoFilters->rankFilterLabels());
		$this->set('setTiles', $setTiles);
		$this->set('difficultyTiles', Rating::ranks());
		$this->set('tagTiles', $tagTiles);
		$this->set('tsumegoFilters', $tsumegoFilters);
		// whether the cards follow filters of their own kind, which the page reloads for when they change
		$this->set('queryRefresh', match ($tsumegoFilters->query)
		{
			'topics' => !empty($tsumegoFilters->sets),
			'difficulty' => !empty($tsumegoFilters->ranks),
			'tags' => !empty($tsumegoFilters->tags),
			default => true,
		});
	}

	//Gets the first unsolved set connection ID from a collection of tsumego buttons.
	//Falls back to the first button if all are solved.
	//$tsumegoButtons: Iterator of TsumegoButton objects
	//Returns the setConnectionID of the first unsolved button, or first button if all solved, or null if empty
	private function getFirstUnsolvedSetConnectionId(TsumegoButtons $tsumegoButtons): ?int
	{
		if ($firstUnsolvedButton = array_find((array) $tsumegoButtons, function ($tsumegoButton) {
			return !TsumegoUtil::isSolvedStatus($tsumegoButton->status);
		}))
			return $firstUnsolvedButton->setConnectionID;
		if ($firstRecentlyUnsolved = array_find((array) $tsumegoButtons, function ($tsumegoButton) {
			return !TsumegoUtil::isRecentlySolved($tsumegoButton->status);
		}))
			return $firstRecentlyUnsolved->setConnectionID;
		return ($tsumegoButtons[0] ?? null)?->setConnectionID;
	}

	private static function decodeQueryType(string $input): string
	{
		if (is_numeric($input))
			return 'topics';
		try
		{
			if (Rating::parseReadableRankRange($input) === null)
				Rating::getRankFromReadableRank($input);
			return 'difficulty';
		}
		catch (Exception $e)
		{
			return 'tags';
		}
	}

	#[HttpPost]
	public function addTsumego(string $setID)
	{
		if ($setID === 'favorites')
		{
			$set = $this->getOrCreateDefaultFavoritesSet();
			$setID = $set->id;
		}
		else
			$set = $this->findSet($setID);

		$this->Authorization->authorize($set);

		$tsumegoId = (int) ($this->input('tsumego_id') ?? 0);
		if (!$tsumegoId)
			throw new BadRequestException();
		if (!$this->fetchTable('Tsumego')->exists(['id' => (int) $tsumegoId]))
			throw new NotFoundException('Tsumego not found');

		$setConnectionTable = $this->fetchTable('SetConnection');
		if ($setConnectionTable->exists(['set_id' => $setID, 'tsumego_id' => $tsumegoId]))
			throw new ConflictException('Already in set');

		$lastSetConnection = $setConnectionTable->find()->select(['num'])->where(['set_id' => $setID])->orderByDesc('num')->first();
		$setConnectionTable->saveOrFail($setConnectionTable->newEntity([
			'set_id' => $setID,
			'tsumego_id' => $tsumegoId,
			'num' => $lastSetConnection ? $lastSetConnection->num + 1 : 1]));
		if (SetTable::isOfficial($set))
			TsumegoTable::forgetPublicProblemCount();

		$achievementChecker = new AchievementChecker();
		$achievementChecker->checkFavoritesAchievement((int) $setID);
		$achievementChecker->finalize();

		if ($this->request->is('ajax'))
		{
			// Include the resolved set so the client can keep its set list in
			// sync when the Favorites set is created on the fly (heart toggle).
			return $this->respondJson([
				'contains' => true,
				'set_id' => $setID,
				'title' => $set->title,
			]);
		}

		CookieFlash::set('Added to set', 'success');
		return $this->redirect('/sets/edit/' . (int) $setID);
	}

	//Create a new tsumego and add it to a set. Admin only.
	#[HttpPost]
	public function createAndAddTsumego(string $setID)
	{
		$this->Authorization->authorize('Set');

		if (!$this->fetchTable('Set')->exists(['id' => (int) $setID]))
			throw new NotFoundException('Set not found');

		if ($this->input('order') === null)
			throw new BadRequestException();

		$database = Util::database();
		$database->begin();

		try
		{
			$tsumegoTable = $this->fetchTable('Tsumego');
			$tsumego = $tsumegoTable->saveOrFail($tsumegoTable->newEntity([
				'author' => Auth::getUser()['display_name'],
				'author_user_id' => Auth::getUserID()]));

			$setConnectionTable = $this->fetchTable('SetConnection');
			$setConnectionTable->saveOrFail($setConnectionTable->newEntity([
				'set_id' => $setID,
				'tsumego_id' => $tsumego->id,
				'num' => $this->input('order')]));

			$fileUpload = $this->uploadedFile('adminUpload');
			if ($fileUpload !== null && $fileUpload['error'] !== UPLOAD_ERR_OK)
				$fileUpload = null;
			$sgfDataOrFile = $this->input('sgf') ?? $fileUpload;

			if ($sgfDataOrFile)
				SgfTable::upload($sgfDataOrFile, $tsumego->id, Auth::getUserID(), Auth::isAdmin());
			$database->commit();
			TsumegoTable::forgetPublicProblemCount();
			AdminActivityLogger::log(AdminActivityTypeTable::PROBLEM_ADD, $tsumego->id, $setID);
		}
		catch (Exception $e)
		{
			$database->rollback();
			CookieFlash::set('Unexpected error:' . $e->getMessage(), 'error');
		}
		return $this->redirect('/sets/edit/' . (int) $setID);
	}

	//Get or create the user's default "Favorites" set.
	private function getOrCreateDefaultFavoritesSet(): Set
	{
		$userId = Auth::getUserID();
		$defaultSetId = Auth::getUser()['default_set_id'] ?? null;
		$setTable = $this->fetchTable('Set');

		if ($defaultSetId)
		{
			$set = $setTable->find()->where(['id' => (int) $defaultSetId])->first();
			if ($set)
				return $set;
		}

		// No default set or it was deleted, create one
		$set = $setTable->saveOrFail($setTable->newEntity([
			'user_id' => $userId,
			'title' => 'Favorites',
			'public' => false,
			'image' => null,
			'order' => Constants::$DEFAULT_SET_ORDER,
		]));

		// Update user.default_set_id
		Auth::saveUserField('default_set_id', $set->id);

		return $setTable->get($set->id);
	}

	#[HttpPost]
	public function removeTsumego(int $setID)
	{
		$set = $this->findSet($setID);

		$this->Authorization->authorize($set);

		$tsumegoId = $this->input('tsumego_id');
		if (!$tsumegoId)
			throw new BadRequestException();

		$this->fetchTable('SetConnection')->deleteAll([
			'set_id' => $setID,
			'tsumego_id' => (int) $tsumegoId,
		]);
		if (SetTable::isOfficial($set))
			TsumegoTable::forgetPublicProblemCount();

		if ($this->request->is('ajax'))
			return $this->respondJson(['contains' => false]);

		CookieFlash::set('Removed from set', 'success');
		return $this->redirect('/sets/edit/' . $setID);
	}

	//Swap the order of two adjacent set_connections.
	#[HttpPost]
	public function reorderTsumego(int $setID)
	{
		$set = $this->findSet($setID);
		$this->Authorization->authorize($set);

		$tsumegoId = $this->query('tsumego_id') ?? $this->input('tsumego_id');
		$dir = $this->query('dir') ?? $this->input('dir');

		if (!$tsumegoId || !in_array($dir, ['up', 'down']))
			throw new BadRequestException();

		$setConnectionTable = $this->fetchTable('SetConnection');
		$current = $setConnectionTable->find()->select(['id', 'num'])->where(['set_id' => $setID, 'tsumego_id' => (int) $tsumegoId])->first();
		if (!$current)
			throw new NotFoundException('Tsumego not in set');

		$currentNum = $current->num;
		$adjacentNum = $dir === 'up' ? $currentNum - 1 : $currentNum + 1;

		$adjacent = $setConnectionTable->find()->select(['id', 'num'])->where(['set_id' => $setID, 'num' => $adjacentNum])->first();
		if (!$adjacent)
			throw new ConflictException();

		// Swap num values
		$current->num = $adjacentNum;
		$setConnectionTable->saveOrFail($current);
		$adjacent->num = $currentNum;
		$setConnectionTable->saveOrFail($adjacent);

		CookieFlash::set('Reordered', 'success');
		return $this->redirect('/sets/edit/' . $setID);
	}

	#[HttpGet]
	public function view(string|int|null $id = null, int $partition = 1): ?Response
	{
		// transferring from 1 indexed for humans to 0 indexed for us programmers.
		$partition = $partition - 1;

		// Favorites is a shortcut to the player's default collection.
		if ($id === 'favorites')
		{
			if (!Auth::isLoggedIn())
				return $this->redirect('/');
			$defaultSet = $this->getOrCreateDefaultFavoritesSet();
			return $this->redirect(SetTable::url((int) $defaultSet->id));
		}

		if ($id === null)
			return $this->redirect('/sets/mine');

		// A collection, a rank and a tag have one address whatever path led here; the partition says
		// which page of it the link pointed at, and the first page is the collection itself.
		$this->set('canonicalPath', SetTable::url($id, $partition + 1));

		$this->set('_page', $id != '1' ? 'set' : 'favs');
		$this->set('canEdit', false);
		// the collection is found and authorized before the filters, which save the query to the preferences
		$queryType = self::decodeQueryType((string) $id);
		$set = $this->loadViewedCollection($queryType, $id);
		$tsumegoFilters = new TsumegoFilters($queryType);

		Util::setCookie('lastSet', $id);
		$tsumegoButtons = new TsumegoButtons($tsumegoFilters, null, $partition, $id);
		$tsumegoButtons->loadPreviews();
		$this->set('startingSetConnectionID', $this->getFirstUnsolvedSetConnectionId($tsumegoButtons));

		$setAuthor = empty($set->user_id) ? null : UserTable::row($set->user_id);
		$this->set('setAuthor', $setAuthor);

		if ($tsumegoButtons->description)
			$set->description = $tsumegoButtons->description;

		$displayTitle = $set->title . $tsumegoButtons->getPartitionTitleSuffix();
		$this->set('_title', $displayTitle . ' on Tsumego Hero');
		$this->set('setTitle', $displayTitle);
		$this->set('og', $this->openGraph($set, $displayTitle, $setAuthor, $tsumegoFilters->query === 'topics'));

		$problemSolvedPercent = $tsumegoButtons->getProblemsSolvedPercent();
		$setRating = $tsumegoButtons->getProblemsRating();
		$this->set('setRating', $setRating);
		$this->set('problemSolvedPercent', $problemSolvedPercent);

		// the player's own scores are kept on the collections, a rank or a tag has none
		$scoring = Auth::isLoggedIn() && $tsumegoFilters->query == 'topics';
		// the bests are per part of a collection, the part a reset clears: a page shows them, and offers the reset, when
		// it is exactly one part, of the size of the parts and without rank and tag filters
		$pageIsAPart = $tsumegoFilters->collectionSize == ProgressDeletionTable::$PARTITION_SIZE && !$tsumegoFilters->hasProblemFilters();
		$avgTime = 0;
		$accuracy = 0;
		if ($scoring)
		{
			$tsumegoButtons->loadAttempts(Auth::getUserID(), (int) $set->id);
			$avgTime = $tsumegoButtons->getAverageSolveTime();
			$accuracy = $tsumegoButtons->getAccuracy();
			if ($problemSolvedPercent >= 100)
			{
				$achievementChecker = new AchievementChecker();
				// Set achievements belong to the site's collections only (SetPartBestScores::record() keeps no best of a
				// collection a player assembled). A part finished before it was kept on its last solve gets its best here.
				if ($pageIsAPart && ($best = SetPartBestScores::record(Auth::getUserID(), (int) $set->id, $partition)) !== null)
					$achievementChecker->checkSetAchievements($set->id, $best->rating, $partition);
				$achievementChecker->checkCompletionAchievement($set);
				$achievementChecker->finalize();
				$this->set('achievementUpdates', $achievementChecker->updated);
			}
		}

		if ($tsumegoFilters->query == 'topics')
		{
			// the resets of the parts the page's problems are in, the count that reduces their XP
			$this->set('pdCounter', $scoring ? $this->recentResetsOf($tsumegoButtons, (int) $id) : 0);
			$this->set('bestTime', $scoring && $pageIsAPart ? AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_TIME, (int) $id, $partition) : null);
			$this->set('bestAccuracy', $scoring && $pageIsAPart ? AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_ACCURACY, (int) $id, $partition) : null);
		}

		$this->set('tsumegoFilters', $tsumegoFilters);
		$this->set('tsumegoButtons', $tsumegoButtons);
		$this->set('set', $set);
		$this->set('avgTime', $avgTime);
		$this->set('accuracy', $accuracy);
		$this->set('scoring', $scoring);
		$this->set('partition', $partition);
		return null;
	}

	//The collection the page shows: a set, or a rank or a tag shown as a collection of its problems.
	private function loadViewedCollection(string $queryType, string|int $id): Set
	{
		if ($queryType == 'difficulty')
			return new Set(['id' => $id, 'title' => $id, 'public' => true, 'user_id' => null, 'description' => null, 'image' => null, 'deleted' => null]);

		if ($queryType == 'tags')
		{
			$tag = $this->fetchTable('Tag')->find()->where(['name' => $id])->first();
			if (!$tag)
				throw new NotFoundException("Tag not found");
			return new Set(['id' => $id, 'title' => $id, 'public' => true, 'user_id' => null, 'description' => $tag->description, 'image' => '', 'deleted' => null]);
		}

		$set = $this->findSet($id);
		$this->Authorization->authorize($set, "view");
		if ($set->public == 0 && $set->user_id === null)
			$this->set('_page', 'sandbox');
		// Search engines are kept out of what a player assembled.
		$this->set('robotsNoIndex', !SetTable::isOfficial($set));
		$this->set('canEdit', $this->Authorization->can($set, 'edit'));
		$this->set('canShare', $this->Authorization->can($set, 'share'));
		return $set;
	}

	//The Open Graph tags of a collection page: how a shared link to it is previewed.
	private function openGraph(Set $set, string $displayTitle, ?array $setAuthor, bool $isSet): array
	{
		$description = trim(strip_tags($set->description ?? ''));
		if ($description === '')
			if ($isSet)
			{
				$description = SetTable::getProblemCount($set->id) . ' go problems';
				if ($setAuthor !== null)
					$description .= ' collected by ' . UserTable::displayName($setAuthor);
			}
			else
				$description = 'Interactive go problems';

		// A shared link names the player even when they are the one who opened it.
		$og = [
			'title' => $setAuthor === null
				? $displayTitle
				: $displayTitle . ' by ' . UserTable::displayName($setAuthor),
			'description' => $description,
			'type' => 'website',
			'site_name' => 'Tsumego',
		];

		$image = $set->image ?? '';
		if ($image === '')
			return $og;
		$og['image'] = Router::url('/img/' . $image, true);
		$og['image_alt'] = 'Cover image of ' . $displayTitle;
		$imagePath = WWW_ROOT . 'img' . DS . str_replace('/', DS, $image);
		$size = file_exists($imagePath) ? getimagesize($imagePath) : false;
		if ($size)
		{
			$og['image_width'] = $size[0];
			$og['image_height'] = $size[1];
			$og['image_type'] = $size['mime'];
		}
		return $og;
	}

	//Keeps the player's records on the set: the best average time and the best accuracy.
	//The resets of the last month of the parts of the set the page's problems are in (ProgressDeletionTable::$PARTITION_SIZE,
	//whatever the player's collection size), the most of them when the page has problems of two parts
	private function recentResetsOf(TsumegoButtons $tsumegoButtons, int $setID): int
	{
		$partOf = ProgressDeletionTable::partitionsOfSet($setID);
		$parts = [];
		foreach ($tsumegoButtons as $tsumegoButton)
			if (isset($partOf[$tsumegoButton->setConnectionID]))
				$parts[$partOf[$tsumegoButton->setConnectionID]] = true;
		$resets = 0;
		foreach (array_keys($parts) as $part)
			$resets = max($resets, ProgressDeletionTable::countRecent(Auth::getUserID(), $setID, $part));
		return $resets;
	}

	#[HttpPost]
	public function resetProgress(int $setID, int $partition): mixed
	{
		$redirectUrl = SetTable::url($setID, $partition);

		if (!Auth::isLoggedIn())
			return $this->redirect($redirectUrl);

		if ($this->input('reset-check') != 'reset')
		{
			CookieFlash::set('Reset check wasn\'t correctly typed', 'error');
			return $this->redirect($redirectUrl);
		}

		// a reset clears one part of the set, the same for everybody (see ProgressDeletionTable::$PARTITION_SIZE)
		$tsumegoFilters = new TsumegoFilters();
		if ($tsumegoFilters->collectionSize != ProgressDeletionTable::$PARTITION_SIZE)
		{
			CookieFlash::set('Reset is only possible for collection size ' . ProgressDeletionTable::$PARTITION_SIZE, 'error');
			return $this->redirect($redirectUrl);
		}
		if ($tsumegoFilters->hasProblemFilters())
		{
			CookieFlash::set('Reset is only possible without rank and tag filters', 'error');
			return $this->redirect($redirectUrl);
		}
		if ($partition < 1)
			return $this->redirect($redirectUrl);
		$tsumegoFilters->query = 'topics';
		$tsumegoButtons = new TsumegoButtons($tsumegoFilters, null, $partition - 1, $setID);
		$tsumegoIDToClear = [];
		foreach ($tsumegoButtons as $tsumegoButton)
			$tsumegoIDToClear[] = $tsumegoButton->tsumegoID;

		if ($tsumegoButtons->getProblemsSolvedPercent() < 50)
			return $this->redirect($redirectUrl);

		Util::execute('DELETE FROM tsumego_status WHERE user_id = ? AND tsumego_id IN (' . implode(',', array_fill(0, count($tsumegoIDToClear), '?')) . ')',
			[Auth::getUserID(), ...$tsumegoIDToClear]);
		$progressDeletionTable = $this->fetchTable('ProgressDeletion');
		$progressDeletionTable->saveOrFail($progressDeletionTable->newEntity([
			'user_id' => Auth::getUserID(),
			'set_id' => $setID,
			'set_partition' => $partition - 1]));
		return $this->redirect($redirectUrl);
	}

	#[HttpPost]
	public function changeCollectionSize(): mixed
	{
		$collectionSize = $this->input('collection_size');
		if ($collectionSize === null || $collectionSize === '')
		{
			CookieFlash::set('Collection size to change not provided', 'error');
			return $this->redirect('/sets');
		}
		$collectionSizeInt = filter_var($collectionSize, FILTER_VALIDATE_INT, [
			'options' => ['min_range' => Constants::$MIN_COLLECTION_SIZE, 'max_range' => Constants::$MAX_COLLECTION_SIZE],
		]);
		if ($collectionSizeInt === false || $collectionSizeInt % Constants::$COLLECTION_SIZE_STEP !== 0)
		{
			CookieFlash::set('Collection size must be a multiple of ' . Constants::$COLLECTION_SIZE_STEP . ' between ' . Constants::$MIN_COLLECTION_SIZE . ' and ' . Constants::$MAX_COLLECTION_SIZE, 'error');
			return $this->redirect('/sets');
		}
		Preferences::set('collection_size', $collectionSizeInt);
		return $this->redirect('/sets');
	}

	//The set of the address, a 404 when there is none.
	private function findSet(int|string|null $id): Set
	{
		$set = $this->fetchTable('Set')->find()->where(['id' => (int) $id])->first();
		if (!$set)
			throw new NotFoundException('Set not found');
		return $set;
	}

	//True when an admin edits a set they do not own (elevated privilege).
	private function isElevatedSetEdit(Set $set): bool
	{
		return Auth::isAdmin() && $set->user_id != Auth::getUserID();
	}
}

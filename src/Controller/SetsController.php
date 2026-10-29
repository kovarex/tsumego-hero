<?php

use App\Model\Entity\Set;
use App\Model\Table\SetTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\SgfTable;
use App\Lib\Error\ConflictException;
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
		$setsNew = [];

		$sets = Util::query('SELECT id, title, color FROM `set` WHERE public = 0 AND user_id IS NULL AND deleted IS NULL ORDER BY `order`');

		if (Auth::isLoggedIn())
		{
			$tsumegoStatusMap = [];
			foreach (Util::query('SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ?', [Auth::getUserID()]) as $userTsumegoStatus)
				$tsumegoStatusMap[$userTsumegoStatus['tsumego_id']] = $userTsumegoStatus['status'];
		}
		$overallCounter = 0;

		$setsCount = count($sets);
		for ($i = 0; $i < $setsCount; $i++)
		{
			$ts = TsumegoUtil::collectTsumegosFromSet($sets[$i]['id']);
			$counter = 0;
			$elo = 0;
			$tsCount3 = count($ts);
			for ($k = 0; $k < $tsCount3; $k++)
			{

				$elo += $ts[$k]['Tsumego']['rating'];
				if (Auth::isLoggedIn())
					if (isset($tsumegoStatusMap[$ts[$k]['Tsumego']['id']]))
						if ($tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'S' || $tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'W' || $tsumegoStatusMap[$ts[$k]['Tsumego']['id']] == 'C')
							$counter++;
			}
			if (count($ts) > 0)
				$elo = $elo / count($ts);
			else
				$elo = 0;
			$percent = 0;
			if (count($ts) > 0)
				$percent = Util::getPercentButAvoid100UntilComplete($counter, count($ts));
			$overallCounter += count($ts);
			$sn = [];
			$sn['id'] = $sets[$i]['id'];
			$sn['name'] = $sets[$i]['title'];
			$sn['amount'] = count($ts);
			$sn['color'] = $sets[$i]['color'];
			$sn['difficulty'] = Rating::getReadableRankFromRating($elo);
			$sn['solved'] = $percent;
			array_push($setsNew, $sn);
		}

		$admins = $this->fetchTable('User')->find()
			->select(['display_name'])
			->where(['user_role_id IN' => UserTable::ADMIN_ROLES])
			->orderBy(['id' => 'ASC'])
			->all()
			->extract('display_name')
			->toList();

		$this->set('admins', $admins);
		$this->set('setsNew', $setsNew);
		$this->set('overallCounter', $overallCounter);
	}

	#[HttpGet]
	public function mine()
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/');

		$this->_showUserSets(Auth::getUserID());
	}

	#[HttpGet]
	public function userSets(string $userId): void
	{
		$this->_showUserSets((int) $userId);
	}

	private function _showUserSets(int $userId): void
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
		$publicFilter = ($isOwn || Auth::isAdmin()) ? '' : 'AND s.public = 1';

		$rows = Util::query("
SELECT
	s.id,
	s.title,
	s.color,
	COUNT(sc.tsumego_id) AS amount,
	COALESCE(SUM(t.rating), 0) AS elo_sum,
	COALESCE(SUM(CASE WHEN ts.status IN ('S','W','C') THEN 1 ELSE 0 END), 0) AS solved
FROM `set` s
LEFT JOIN (set_connection sc
	JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL) ON sc.set_id = s.id
LEFT JOIN tsumego_status ts ON ts.tsumego_id = sc.tsumego_id AND ts.user_id = ?
WHERE s.user_id = ? $publicFilter
GROUP BY s.id, s.title, s.color
ORDER BY s.order", [Auth::getUserID(), $userId]);

		$setsNew = [];
		foreach ($rows as $row)
		{
			$amount = (int) $row['amount'];
			$elo = $amount > 0 ? (float) $row['elo_sum'] / $amount : 0.0;
			$percent = $amount > 0 ? Util::getPercentButAvoid100UntilComplete((int) $row['solved'], $amount) : 0;

			$setsNew[] = [
				'id' => $row['id'],
				'name' => $row['title'],
				'amount' => $amount,
				'color' => $row['color'],
				'difficulty' => Rating::getReadableRankFromRating($elo),
				'solved' => $percent,
			];
		}

		$this->set('setsNew', $setsNew);
		$this->render('user_sets');
	}

	#[HttpGet]
	#[HttpPost]
	public function create(): ?CakeResponse
	{
		$this->Authorization->authorize('Set', 'create');

		$redirect = false;

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
					'variance' => 100,
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
		$setTable = $this->fetchTable('Set');
		$set = $setTable->find()->where(['id' => (int) $id])->first();
		if (!$set)
			throw new NotFoundException('Set not found');

		$this->Authorization->authorize($set);
		$canEditSettings = $this->Authorization->can('Set', 'editSettings');
		$isSandbox = ($set->public == 0 && $set->user_id === null);

		// Problems in this set, in display order
		$problems = Util::query("
SELECT sc.id AS set_connection_id, sc.num, sc.tsumego_id, t.rating,
	t.alternative_response, t.pass
FROM set_connection sc
JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL
WHERE sc.set_id = ?
ORDER BY sc.num ASC", [(int) $id]);

		// Tsumego buttons (status colors, tooltips, board previews) for the problem list
		$tsumegoButtons = new TsumegoButtons(new TsumegoFilters('topics'), null, null, (int) $id);
		$tsumegoButtons->loadPreviews();

		// Both forms of the page post here: the details, and for admins the re-rate and solve modes.
		$formSent = $this->inputs() !== [];
		if ($formSent)
		{
			$oldTitle = $set->title;
			$oldDescription = $set->description;
			$oldColor = $set->color;
			$oldOrder = $set->order;
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
			$setTable->saveOrFail($set);

			if ($this->_isElevatedSetEdit($set))
			{
				if ($oldTitle != $set->title)
					AdminActivityLogger::log(AdminActivityTypeTable::SET_TITLE_EDIT, null, (int) $id, $oldTitle, $set->title);
				if ($oldDescription != $set->description)
					AdminActivityLogger::log(AdminActivityTypeTable::SET_DESCRIPTION_EDIT, null, (int) $id, $oldDescription, $set->description);
				if ($oldColor != $set->color)
					AdminActivityLogger::log(AdminActivityTypeTable::SET_COLOR_EDIT, null, (int) $id, $oldColor, $set->color);
				if ($oldOrder != $set->order)
					AdminActivityLogger::log(AdminActivityTypeTable::SET_ORDER_EDIT, null, (int) $id, Util::strOrNull($oldOrder), Util::strOrNull($set->order));
			}

			CookieFlash::set('Set saved', 'success');
		}

		// Re-rate every problem in this set (admin only)
		if ($canEditSettings && $this->input('setDifficulty') !== null)
			if ($this->input('setDifficulty') != 1200 && $this->input('setDifficulty') >= 900 && $this->input('setDifficulty') <= 2900)
			{
				//The new rating of each problem is raised to its minimum, then lowered to its maximum
				//(GREATEST and LEAST give NULL for a limit the problem doesn't have)
				$difficulty = (float) $this->input('setDifficulty');
				$problemIDs = array_column($problems, 'tsumego_id');
				if ($problemIDs)
					Util::execute('UPDATE tsumego SET rating = COALESCE(LEAST(COALESCE(GREATEST(?, minimum_rating), ?), maximum_rating), COALESCE(GREATEST(?, minimum_rating), ?))
						WHERE id IN (' . implode(',', array_fill(0, count($problemIDs), '?')) . ')', [$difficulty, $difficulty, $difficulty, $difficulty, ...$problemIDs]);
				AdminActivityLogger::log(AdminActivityTypeTable::SET_RATING_EDIT, null, (int) $id);
			}

		// Alternative response / pass mode for all problems (admin only)
		if ($canEditSettings && $formSent)
		{
			// each of the two settings is sent only when the admin picked a choice for it
			$alternativeResponse = $this->input('r39');
			$passMode = $this->input('r43');
			if ($alternativeResponse == 'on')
			{
				$this->setProblemsField($problems, 'alternative_response', true);
				AdminActivityLogger::log(AdminActivityTypeTable::SET_ALTERNATIVE_RESPONSE, null, (int) $id, null, '1');
			}
			if ($alternativeResponse == 'off')
			{
				$this->setProblemsField($problems, 'alternative_response', false);
				AdminActivityLogger::log(AdminActivityTypeTable::SET_ALTERNATIVE_RESPONSE, null, (int) $id, null, '0');
			}
			if ($passMode == 'yes')
			{
				$this->setProblemsField($problems, 'pass', true);
				AdminActivityLogger::log(AdminActivityTypeTable::SET_PASS_MODE, null, (int) $id, null, '1');
			}
			if ($passMode == 'no')
			{
				$this->setProblemsField($problems, 'pass', false);
				AdminActivityLogger::log(AdminActivityTypeTable::SET_PASS_MODE, null, (int) $id, null, '0');
			}
		}

		// Handle image removal
		if (!empty($this->input('remove_image')))
		{
			SetImage::remove($set->image);
			$set->image = '';
			$setTable->saveOrFail($set);
			CookieFlash::set('Image removed', 'success');
		}

		// Handle image upload from the details form
		$image = $this->uploadedFile('image');
		if ($image !== null && $image['error'] === UPLOAD_ERR_OK)
		{
			$file_ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));

			if (!in_array($file_ext, ['png', 'jpg', 'jpeg', 'webp']))
				CookieFlash::set('png/jpg/webp allowed.', 'error');
			elseif ($image['size'] > 2097152)
				CookieFlash::set('The file is too large (max 2MB).', 'error');
			else
			{
				$oldImage = $set->image;

				try
				{
					$processed = SetImage::process($image['tmp_name'], $file_ext);
					$filename = SetImage::store((int) $id, $processed);

					// The name of a cover carries the hash of its picture, so an upload is a
					// new file and the cover it replaces is removed here.
					if ($oldImage !== $filename)
						SetImage::remove($oldImage);

					$set->image = $filename;
					$setTable->saveOrFail($set);
					CookieFlash::set('Image uploaded', 'success');
				}
				catch (Exception $e)
				{
					CookieFlash::set('Image upload failed: ' . $e->getMessage(), 'error');
				}
			}
		}

		if ($formSent)
			return $this->redirect('/sets/edit/' . (int) $id);

		// Solve-mode states for the admin settings panel
		$allArActive = true;
		$allArInactive = true;
		$allPassActive = true;
		$allPassInactive = true;
		foreach ($problems as $problem)
		{
			if (!$problem['alternative_response'])
				$allArActive = false;
			if ($problem['alternative_response'])
				$allArInactive = false;
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
		$this->set('allArActive', $allArActive);
		$this->set('allArInactive', $allArInactive);
		$this->set('allPassActive', $allPassActive);
		$this->set('allPassInactive', $allPassInactive);
		$this->set('setRating', $setRating);
	}

	//Open or close the link sharing of a collection a player assembled.
	#[HttpPost]
	public function share(?string $id = null): mixed
	{
		$setTable = $this->fetchTable('Set');
		$set = $setTable->find()->where(['id' => (int) $id])->first();
		if (!$set)
			throw new NotFoundException('Set not found');
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
	public function delete(?string $id = null): CakeResponse
	{
		$setID = $id ?? $this->input('id');
		if (!$setID)
			throw new BadRequestException();

		$setTable = $this->fetchTable('Set');
		$set = $setTable->find()->where(['id' => (int) $setID])->first();
		if (!$set)
			throw new NotFoundException('Set not found');

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

		$setTiles = [];
		$difficultyTiles = [];
		$sets = [];
		$tagList = [];

		$problemsCount = 0;

		$tsumegoFilters = new TsumegoFilters();
		//setTiles
		$setTiles = array_column(Util::query('SELECT s.title FROM `set` s WHERE ' . SetTable::officialCondition('s')
			. ' ORDER BY ' . SetConnectionTable::displayOrderForSetSql('s')), 'title');

		//difficultyTiles
		$difficultyTiles = Rating::ranks();

		//tagTiles
		$tagTiles = $this->fetchTable('Tag')->find()->select(['name'])->where(['approved' => 1, 'name !=' => 'Tsumego'])->all()->extract('name')->toList();

		$setsSelector = new SetsSelector($tsumegoFilters);

		[$rankFrom, $rankTo] = $tsumegoFilters->rankRange();
		// The weakest rank is the same as leaving the lower end open.
		if ($rankFrom === Rating::weakestRank())
			$rankFrom = '';

		if ($tsumegoFilters->query == 'topics' && empty($tsumegoFilters->sets))
			$queryRefresh = false;
		elseif ($tsumegoFilters->query == 'difficulty' && empty($tsumegoFilters->ranks))
			$queryRefresh = false;
		elseif ($tsumegoFilters->query == 'tags' && empty($tsumegoFilters->tags))
			$queryRefresh = false;
		else
			$queryRefresh = true;



		$this->set('setsSelector', $setsSelector);
		$this->set('rankFrom', $rankFrom);
		$this->set('rankTo', $rankTo);
		$this->set('rankFilterLabels', $tsumegoFilters->rankFilterLabels());
		$this->set('tagList', $tagList);
		$this->set('setTiles', $setTiles);
		$this->set('difficultyTiles', $difficultyTiles);
		$this->set('tagTiles', $tagTiles);
		$this->set('tsumegoFilters', $tsumegoFilters);
		$this->set('queryRefresh', $queryRefresh);
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
		return isset($tsumegoButtons[0]) ? $tsumegoButtons[0]->setConnectionID : null;
	}

	private function decodeQueryType(string $input): string
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
			$set = $this->_getOrCreateDefaultFavoritesSet();
			$setID = $set->id;
		}
		else
		{
			$set = $this->fetchTable('Set')->find()->where(['id' => (int) $setID])->first();
			if (!$set)
				throw new NotFoundException('Set not found');
		}

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
	private function _getOrCreateDefaultFavoritesSet(): Set
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
	public function removeTsumego(string $setID)
	{
		$set = $this->fetchTable('Set')->find()->where(['id' => $setID])->first();
		if (!$set)
			throw new NotFoundException('Set not found');

		$this->Authorization->authorize($set);

		$tsumegoId = $this->input('tsumego_id');
		if (!$tsumegoId)
			throw new BadRequestException();

		$this->fetchTable('SetConnection')->deleteAll([
			'set_id' => $setID,
			'tsumego_id' => (int) $tsumegoId,
		]);

		if ($this->request->is('ajax'))
			return $this->respondJson(['contains' => false]);

		CookieFlash::set('Removed from set', 'success');
		return $this->redirect('/sets/edit/' . (int) $setID);
	}

	//Swap the order of two adjacent set_connections.
	#[HttpPost]
	public function reorderTsumego(string $setID)
	{
		$set = $this->fetchTable('Set')->find()->where(['id' => $setID])->first();
		if (!$set)
			throw new NotFoundException('Set not found');
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
		return $this->redirect('/sets/edit/' . (int) $setID);
	}

	#[HttpGet]
	public function view(string|int|null $id = null, int $partition = 1): ?CakeResponse
	{
		// transferring from 1 indexed for humans to 0 indexed for us programmers.
		$partition = $partition - 1;

		// Favorites is a shortcut to the player's default collection.
		if ($id === 'favorites')
		{
			if (!Auth::isLoggedIn())
				return $this->redirect('/');
			$defaultSet = $this->_getOrCreateDefaultFavoritesSet();
			return $this->redirect(SetTable::url((int) $defaultSet->id));
		}

		if ($id === null)
			return $this->redirect('/sets/mine');

		// A collection, a rank and a tag have one address whatever path led here; the partition says
		// which page of it the link pointed at, and the first page is the collection itself.
		$this->set('canonicalPath', SetTable::url($id, $partition + 1));

		if ($id != '1')
			$this->set('_page', 'set');
		else
			$this->set('_page', 'favs');
		$tsIds = [];
		$refreshView = false;
		$avgTime = 0;
		$accuracy = 0;
		$pdCounter = 0;
		$bestTime = null;
		$bestAccuracy = null;
		$this->set('canEdit', false);
		$this->set('isFav', false);
		$queryType = self::decodeQueryType($id);

		if ($queryType == 'topics' && is_numeric($id))
		{
			$set = $this->fetchTable('Set')->find()->where(['id' => $id])->first();
			if (!$set)
				throw new NotFoundException("Set not found");
			$this->Authorization->authorize($set, "view");
		}

		if ($queryType == 'tags')
			if (!$this->fetchTable('Tag')->exists(['name' => $id]))
				throw new NotFoundException("Tag not found");

		$tsumegoFilters = new TsumegoFilters($queryType);
		Util::setCookie('lastSet', $id);
		$tsumegoButtons = new TsumegoButtons($tsumegoFilters, null, $partition, $id);
		$tsumegoButtons->loadPreviews();
		$this->set('startingSetConnectionID', $this->getFirstUnsolvedSetConnectionId($tsumegoButtons));

		if ($tsumegoFilters->query == 'difficulty')
		{
			// a rank is shown as a collection of its problems
			$set = new Set(['id' => $id, 'title' => $id, 'public' => true, 'user_id' => null, 'description' => null, 'image' => null, 'deleted' => null]);
		}
		elseif ($tsumegoFilters->query == 'tags')
		{
			// a tag is shown as a collection of its problems
			$tag = $this->fetchTable('Tag')->find()->where(['name' => $id])->first();
			$set = new Set(['id' => $id, 'title' => $id, 'public' => true, 'user_id' => null, 'description' => $tag?->description, 'image' => '', 'deleted' => null]);
		}
		elseif ($tsumegoFilters->query == 'topics')
		{
			$set = $this->fetchTable('Set')->find()->where(['id' => $id])->first();
			if (!$set)
				throw new NotFoundException("Set not found");
			$this->Authorization->authorize($set, "view");

			foreach ($tsumegoButtons as $tsumegoButton)
				$tsIds [] = $tsumegoButton->tsumegoID;
			if ($set->public == 0 && $set->user_id === null)
				$this->set('_page', 'sandbox');
			// Search engines are kept out of what a player assembled.
			$this->set('robotsNoIndex', !SetTable::isOfficial($set));
			$this->set('canEdit', $this->Authorization->can($set, 'edit'));
			$this->set('canShare', $this->Authorization->can($set, 'share'));
		}
		else
			throw new BadRequestException('Unknown query type: ' . $tsumegoFilters->query);

		$setAuthor = null;
		if (!empty($set->user_id))
			$setAuthor = UserTable::row($set->user_id);
		$this->set('setAuthor', $setAuthor);

		if ($tsumegoButtons->description)
			$set->description = $tsumegoButtons->description;

		$displayTitle = $set->title . $tsumegoButtons->getPartitionTitleSuffix();
		$this->set('_title', $displayTitle . ' on Tsumego Hero');
		$this->set('setTitle', $displayTitle);

		$ogDescription = trim(strip_tags($set->description ?? ''));
		if ($ogDescription === '')
			if ($tsumegoFilters->query === 'topics')
			{
				$ogDescription = SetTable::getProblemCount($set->id) . ' go problems';
				if ($setAuthor !== null)
					$ogDescription .= ' collected by ' . UserTable::displayName($setAuthor);
			}
			else
				$ogDescription = 'Interactive go problems';

		// A shared link names the player even when they are the one who opened it.
		$og = [
			'title' => $setAuthor === null
				? $displayTitle
				: $displayTitle . ' by ' . UserTable::displayName($setAuthor),
			'description' => $ogDescription,
			'type' => 'website',
			'site_name' => 'Tsumego',
		];

		$image = $set->image ?? '';
		if ($image !== '')
		{
			$og['image'] = Router::url('/img/' . $image, true);
			$og['image_alt'] = 'Cover image of ' . $displayTitle;
			$imagePath = WWW_ROOT . 'img' . DS . str_replace('/', DS, $image);
			if (file_exists($imagePath))
			{
				$size = getimagesize($imagePath);
				if ($size)
				{
					$og['image_width'] = $size[0];
					$og['image_height'] = $size[1];
					$og['image_type'] = $size['mime'];
				}
			}
		}

		$this->set('og', $og);

		if (Auth::isLoggedIn() && $tsumegoFilters->query == 'topics')
		{
			// The attempts of the player on the problems of the page, newest first, read once and
			// sorted out by problem: a player who trains a set has dozens of attempts on each of them.
			$attempts = empty($tsIds) ? [] : Util::query('SELECT tsumego_id, solved, seconds, misplays FROM tsumego_attempt
				WHERE user_id = ? AND tsumego_id IN (' . implode(',', array_fill(0, count($tsIds), '?')) . ')
				ORDER BY created DESC, id DESC', [Auth::getUserID(), ...$tsIds]);
			$attemptsByTsumego = [];
			foreach ($attempts as $attempt)
				$attemptsByTsumego[(int) $attempt['tsumego_id']][] = $attempt;
			foreach ($tsumegoButtons as $tsumegoButton)
			{
				$urSum = '';
				$tsumegoButton->seconds = 0;
				$solvedSeconds = []; // Track all successful solve times to find minimum (best)
				foreach ($attemptsByTsumego[$tsumegoButton->tsumegoID] ?? [] as $attempt)
				{
					$isSolved = (bool) $attempt['solved'];
					$attemptSeconds = (int) $attempt['seconds'];
					if ($isSolved && $attemptSeconds > 0)
						$solvedSeconds[] = $attemptSeconds;

					$mis = (int) $attempt['misplays'];
					if (!$isSolved && $mis <= 0)
						$mis = 1;
					$urSum .= str_repeat('F', max(0, $mis));
					if ($isSolved)
						$urSum .= '1';
				}
				// Use minimum (best) solve time from all successful attempts
				if (!empty($solvedSeconds))
					$tsumegoButton->seconds = min($solvedSeconds);
				$tsumegoButton->performance = $urSum;
			}
		}

		$problemSolvedPercent = $tsumegoButtons->getProblemsSolvedPercent();
		$setRating = $tsumegoButtons->getProblemsRating();
		$this->set('setRating', $setRating);

		$this->set('problemSolvedPercent', $problemSolvedPercent);

		$scoring = true;
		if (Auth::isLoggedIn() && $tsumegoFilters->query == 'topics')
		{
			// the resets of this part of the set, the same count that reduces the XP of its problems
			$pdCounter = ProgressDeletionTable::countRecent(Auth::getUserID(), (int) $id, $partition);
			$urSecCounter = 0;
			$urSecAvg = 0;
			$pSsum = 0;
			$pFsum = 0;
			foreach ($tsumegoButtons as $tsumegoButton)
			{
				// The average is of the solved problems only, an unsolved one would pull it down
				// and each new solve up (forum#149). A solve without a recorded time counts as a minute.
				if (TsumegoUtil::isSolvedStatus($tsumegoButton->status))
				{
					$urSecAvg += $tsumegoButton->seconds ?: 60;
					$urSecCounter++;
				}

				if ($tsumegoButton->performance == '')
					if (TsumegoUtil::isSolvedStatus($tsumegoButton->status))
						$tss2 = 'F';
					else
						$tss2 = '';
				else
					$tss2 = $tsumegoButton->performance;
				$pS = substr_count($tss2, '1');
				$pF = substr_count($tss2, 'F');
				$pSsum += $pS;
				$pFsum += $pF;
			}
			if ($urSecCounter == 0)
				$avgTime = 0;
			else
				$avgTime = round($urSecAvg / $urSecCounter, 2);
			if ($pSsum + $pFsum == 0)
				$accuracy = 0;
			else
				$accuracy = round($pSsum / ($pSsum + $pFsum) * 100, 2);
			$avgTime2 = $avgTime;
			if ($problemSolvedPercent >= 100)
			{
				$achievementChecker = new AchievementChecker();
				// Set achievements belong to the site's collections only, so nothing is recorded for a
				// collection a player assembled, however complete it is.
				if ($set->id != 210 && SetTable::isOfficial($set))
				{
					$this->updateAchievementConditions($set->id, $avgTime2, $accuracy);
					$achievementChecker->checkSetAchievements($set->id, $setRating);
				}
				if ($id == 50 || $id == 52 || $id == 53 || $id == 54)
					$achievementChecker->setAchievementSpecial('cc1');
				elseif ($id == 41 || $id == 49 || $id == 65 || $id == 66)
					$achievementChecker->setAchievementSpecial('cc2');
				elseif ($id == 186 || $id == 187 || $id == 196 || $id == 203)
					$achievementChecker->setAchievementSpecial('cc3');
				elseif ($id == 190 || $id == 193 || $id == 198)
					$achievementChecker->setAchievementSpecial('1000w1');
				elseif ($id == 216)
					$achievementChecker->setAchievementSpecial('1000w2');
				$achievementChecker->finalize();
				$this->set('achievementUpdates', $achievementChecker->updated);
			}

			$bestTime = AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_TIME, (int) $id);
			$bestAccuracy = AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_ACCURACY, (int) $id);
		}
		else
			$scoring = false;

		if ($tsumegoFilters->query == 'topics')
		{
			$this->set('pdCounter', $pdCounter);
			$this->set('bestTime', $bestTime);
			$this->set('bestAccuracy', $bestAccuracy);
		}

		$this->set('tsumegoFilters', $tsumegoFilters);
		$this->set('tsumegoButtons', $tsumegoButtons);
		$this->set('set', $set);
		$this->set('refreshView', $refreshView);
		$this->set('avgTime', $avgTime);
		$this->set('accuracy', $accuracy);
		$this->set('scoring', $scoring);
		$this->set('partition', $partition);
		return null;
	}

	//Keeps the player's records on the set: the best average time and the best accuracy.
	private function updateAchievementConditions(int $sid, int|float $avgTime, int|float $accuracy): void
	{
		AchievementConditionTable::keepLowest(Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_TIME, $avgTime, $sid);
		AchievementConditionTable::keepHighest(Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_ACCURACY, $accuracy, $sid);
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

		Util::query("
DELETE tsumego_status FROM tsumego_status
WHERE tsumego_status.user_id = ? AND tsumego_status.tsumego_id IN(" . implode(',', $tsumegoIDToClear) . ")", [Auth::getUserID()]);
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

	//True when an admin edits a set they do not own (elevated privilege).
	private function _isElevatedSetEdit(Set $set): bool
	{
		return Auth::isAdmin() && $set->user_id != Auth::getUserID();
	}

	//Turns a setting of every problem of the set on or off
	private function setProblemsField(array $problems, string $field, bool $value): void
	{
		$problemIDs = array_column($problems, 'tsumego_id');
		if ($problemIDs)
			$this->fetchTable('Tsumego')->updateAll([$field => $value], ['id IN' => $problemIDs]);
	}
}

<?php

use App\Utility\AchievementId;
use App\Utility\Auth;
use App\Utility\SetCompletionCounter;
use App\Utility\Util;
use App\Attribute\HttpGet;
use SetConnection;

/**
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class AchievementsController extends AppController
{
	/**
	 * @return void
	 */
	#[HttpGet]
	public function index(): void
	{
		$this->renderAchievementsPage(Auth::isLoggedIn() ? Auth::getUser() : null);
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	#[HttpGet]
	public function user(string $userId): void
	{
		$user = $this->User->findById($userId);
		if (!$user)
			throw new NotFoundException('User not found');
		$this->assertUserVisible($user['User']);

		$this->renderAchievementsPage($user['User']);
		$this->render('index');
	}

	/**
	 * @param UserRow|null $viewedUser
	 * @return void
	 */
	private function renderAchievementsPage(?array $viewedUser): void
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Achievements');
		$this->loadModel('AchievementStatus');
		$existingAs = [];

		$a = $this->Achievement->find('all', ['order' => 'order ASC']);
		if (!$a)
			$a = [];

		if ($viewedUser)
		{
			$this->set('viewedUser', $viewedUser);
			$as = $this->AchievementStatus->find('all', ['conditions' => ['user_id' => $viewedUser['id']]]);
			if (!$as)
				$as = [];

			foreach ($as as $item)
				$existingAs[$item['AchievementStatus']['achievement_id']] = $item;
		}

		$aCount = count($a);
		for ($i = 0; $i < $aCount; $i++)
		{
			$a[$i]['Achievement']['unlocked'] = false;
			$a[$i]['Achievement']['unlocked_at'] = null;
			if (isset($existingAs[$a[$i]['Achievement']['id']]))
			{
				// Repeatable achievements are earned more than once: they show how often,
				// but still count as a single completed achievement.
				$a[$i]['Achievement']['earned_count'] = (int) $existingAs[$a[$i]['Achievement']['id']]['AchievementStatus']['value'];
				$a[$i]['Achievement']['unlocked'] = true;
				$a[$i]['Achievement']['unlocked_at'] = $existingAs[$a[$i]['Achievement']['id']]['AchievementStatus']['created'];
			}
		}
		if ($viewedUser)
			$this->fillProgress($a, (int) $viewedUser['id']);
		$this->set('a', $a);
	}

	/**
	 * Adds progress and progress_goal to the locked set completion achievements, so their cards
	 * can show how far the user got.
	 *
	 * @param array $achievements
	 * @param int $userID
	 * @return void
	 */
	private function fillProgress(array &$achievements, int $userID): void
	{
		$definitions = [];
		foreach ($achievements as $index => $achievement)
			if (!$achievement['Achievement']['unlocked'])
				if ($definition = Achievement::progressDefinition((int) $achievement['Achievement']['id']))
					if ($definition['category'] == SetCompletionCounter::$CATEGORY)
						$definitions[$index] = $definition;
		if (!$definitions)
			return;

		$this->loadModel('AchievementCondition');
		$conditions = $this->AchievementCondition->find('all', ['conditions' => [
			'user_id' => $userID,
			'category' => array_values(array_unique(array_column($definitions, 'category')))]]) ?: [];
		$values = [];
		foreach ($conditions as $condition)
		{
			$category = $condition['AchievementCondition']['category'];
			$values[$category] = max($values[$category] ?? 0, (int) $condition['AchievementCondition']['value']);
		}

		foreach ($definitions as $index => $definition)
		{
			$achievements[$index]['Achievement']['progress'] = min($values[$definition['category']] ?? 0, $definition['goal']);
			$achievements[$index]['Achievement']['progress_goal'] = $definition['goal'];
		}
	}

	/**
	 * @param string|null $id
	 * @param string|null $userId
	 * @return void
	 */
	#[HttpGet]
	public function view(?string $id = null, ?string $userId = null): void
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Achievements');
		$this->loadModel('AchievementCondition');
		$this->loadModel('AchievementStatus');
		$this->loadModel('User');
		$achievement = $this->Achievement->findById($id);
		if (!$achievement)
			throw new NotFoundException('Achievement not found');

		$viewedUser = null;
		if ($userId !== null)
		{
			$user = $this->User->findById($userId);
			if (!$user)
				throw new NotFoundException('User not found');
			$this->assertUserVisible($user['User']);
			$viewedUser = $user['User'];
		}
		$statusUserId = $viewedUser ? (int) $viewedUser['id'] : (Auth::isLoggedIn() ? Auth::getUserID() : null);

		$currentUserStatus = [];
		$completerConditions = [
			'achievement_id' => $id,
			'AchievementStatus.user_id NOT IN (SELECT id FROM user WHERE deleted IS NOT NULL)'];
		$completerCount = $this->AchievementStatus->find('count', ['conditions' => $completerConditions]);
		$completers = $this->AchievementStatus->find('all', [
			'order' => 'created DESC, AchievementStatus.id DESC',
			'conditions' => $completerConditions,
			'limit' => 10]);
		if (!$completers)
			$completers = [];
		if ($statusUserId)
			$currentUserStatus = $this->AchievementStatus->find('first', ['conditions' => ['achievement_id' => $id, 'user_id' => $statusUserId]]);
		$userIds = array_map(fn($item) => $item['AchievementStatus']['user_id'], $completers);
		$usersById = [];
		if ($userIds)
			foreach ($this->User->find('all', ['conditions' => ['id' => $userIds]]) as $u)
				$usersById[$u['User']['id']] = $u['User'];
		foreach ($completers as &$item)
			$item['AchievementStatus']['user'] = $usersById[$item['AchievementStatus']['user_id']] ?? null;
		unset($item);
		$userCount = $this->User->find('count', ['conditions' => ['deleted IS NULL']]);
		$completionPercent = $userCount > 0 ? round($completerCount / $userCount * 100, 1) : 0;
		$rarity = Achievement::getRarityLabel($completionPercent);

		$progress = null;
		$progressGoal = null;
		if ($statusUserId)
		{
			$progressDefinition = Achievement::progressDefinition((int) $achievement['Achievement']['id']);
			if ($progressDefinition)
			{
				$condition = $this->AchievementCondition->find('first', [
					'order' => 'value DESC',
					'conditions' => ['user_id' => $statusUserId, 'category' => $progressDefinition['category']],
				]);
				$progress = $condition ? (int) $condition['AchievementCondition']['value'] : 0;
				$progressGoal = $progressDefinition['goal'];
				if ($currentUserStatus)
					$progress = $progressGoal;
				$progress = min($progress, $progressGoal);
			}
		}

		$this->set('achievement', $achievement);
		$this->set('viewedUser', $viewedUser);
		$this->set('collections', $this->fillEarningCollections($achievement, $statusUserId));
		$this->set('currentUserStatus', $currentUserStatus);
		$this->set('completers', $completers);
		$this->set('completerCount', $completerCount);
		$this->set('userCount', $userCount);
		$this->set('completionPercent', $completionPercent);
		$this->set('rarity', $rarity);
		$this->set('progress', $progress);
		$this->set('progressGoal', $progressGoal);
	}

	/**
	 * The public collections that can earn a repeatable collection achievement, with the
	 * best accuracy the player reached on each of them. The achievement page lists them so
	 * a player can see which collections are still left to finish.
	 *
	 * @param array $achievement
	 * @param int|null $userID
	 * @return array
	 */
	private function fillEarningCollections(array $achievement, ?int $userID): array
	{
		if ((int) $achievement['Achievement']['id'] !== AchievementId::SUPERIOR_ACCURACY->value)
			return [];

		$rows = Util::query(
			'SELECT s.id, s.title FROM `set` s WHERE s.id IN ('
			. SetConnection::publicCollectionsWithMinimumProblemsSql() . ') '
			. 'ORDER BY ' . SetConnection::displayOrderForSetSql('s')
		);

		$bestAccuracy = [];
		if ($userID)
			foreach (Util::query(
				'SELECT ac.set_id, MAX(ac.value) AS accuracy FROM achievement_condition ac '
				. 'WHERE ac.user_id = ? AND ac.category = ? GROUP BY ac.set_id',
				[$userID, '%']) as $row)
					$bestAccuracy[(int) $row['set_id']] = (float) $row['accuracy'];

		$collections = [];
		foreach ($rows as $row)
		{
			$accuracy = $bestAccuracy[(int) $row['id']] ?? 0;
			$collections[] = [
				'id' => (int) $row['id'],
				'title' => $row['title'],
				'accuracy' => $accuracy,
				'earned' => $accuracy >= 100,
			];
		}
		return $collections;
	}

}

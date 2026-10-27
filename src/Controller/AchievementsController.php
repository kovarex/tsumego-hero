<?php

use App\Utility\AchievementId;
use App\Utility\Auth;
use App\Utility\Util;
use App\Attribute\HttpGet;
use SetConnection;

/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class AchievementsController extends AppController
{
	#[HttpGet]
	public function index(): void
	{
		$this->renderAchievementsPage(Auth::isLoggedIn() ? Auth::getUser() : null);
	}

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

	//One card per achievement. Without a viewed user (anonymous visitor) every card is locked.
	/** @param UserRow|null $viewedUser */
	private function renderAchievementsPage(?array $viewedUser): void
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Achievements');
		$this->loadModel('AchievementStatus');

		$statusByAchievementId = [];
		if ($viewedUser)
		{
			$this->set('viewedUser', $viewedUser);
			foreach ($this->AchievementStatus->find('all', ['conditions' => ['user_id' => $viewedUser['id']]]) as $status)
				$statusByAchievementId[$status['AchievementStatus']['achievement_id']] = $status['AchievementStatus'];
		}

		$achievements = [];
		foreach ($this->Achievement->find('all', ['order' => 'order ASC']) as $row)
		{
			$achievement = $row['Achievement'];
			$status = $statusByAchievementId[$achievement['id']] ?? null;
			$achievements[] = [
				'id' => (int) $achievement['id'],
				'url' => '/achievements/view/' . $achievement['id'] . ($viewedUser ? '/' . $viewedUser['id'] : ''),
				'name' => $achievement['name'],
				'long_name' => strlen($achievement['name']) > 30,
				'description' => $achievement['description'],
				'xp' => $achievement['xp'],
				'image' => $status ? $achievement['image'] : 'ac000i',
				'color' => $status ? $achievement['color'] : 'achievementColorGray',
				'unlocked' => $status !== null,
				'unlocked_at' => $status ? $status['created'] : null,
				//Repeatable achievements are earned more than once: they show how often,
				//but still count as a single completed achievement.
				'earned_count' => $status ? (int) $status['value'] : 0,
				'progress' => null,
				'progress_goal' => null,
				'progress_percent' => null,
			];
		}
		if ($viewedUser)
			$this->fillProgress($achievements, (int) $viewedUser['id']);

		$this->set('achievements', $achievements);
		$this->set('unlockedCount', count(array_filter(array_column($achievements, 'unlocked'))));
	}

	//Fills progress, progress_goal and progress_percent of the locked set completion
	//achievements, so their cards can show how far the user got.
	private function fillProgress(array &$achievements, int $userID): void
	{
		$definitions = [];
		foreach ($achievements as $index => $achievement)
			if (!$achievement['unlocked'])
				if ($definition = Achievement::progressDefinition($achievement['id']))
					if ($definition['category'] == AchievementCondition::CATEGORY_COMPLETED_SETS)
						$definitions[$index] = $definition;
		if (!$definitions)
			return;

		$values = AchievementCondition::values($userID, array_values(array_unique(array_column($definitions, 'category'))));
		foreach ($definitions as $index => $definition)
		{
			$progress = min((int) ($values[$definition['category']] ?? 0), $definition['goal']);
			$achievements[$index]['progress'] = $progress;
			$achievements[$index]['progress_goal'] = $definition['goal'];
			$achievements[$index]['progress_percent'] = (int) round($progress / $definition['goal'] * 100);
		}
	}

	#[HttpGet]
	public function view(?string $id = null, ?string $userId = null): void
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Achievements');
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
				$progress = (int) AchievementCondition::value($statusUserId, $progressDefinition['category']);
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

	//The public collections that can earn a repeatable collection achievement, with the
	//best accuracy the player reached on each of them. The achievement page lists them so
	//a player can see which collections are still left to finish.
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
				'SELECT set_id, value AS accuracy FROM achievement_condition WHERE user_id = ? AND achievement_condition_category_id = ?',
				[$userID, AchievementCondition::CATEGORY_SET_ACCURACY]) as $row)
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

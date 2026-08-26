<?php

App::uses('NotFoundException', 'Routing/Error');

class AchievementsController extends AppController
{
	/**
	 * @return void
	 */
	public function index()
	{
		$this->renderAchievementsPage(Auth::isLoggedIn() ? Auth::getUser() : null);
	}

	/**
	 * @param string|int $userId
	 * @return void
	 */
	public function user($userId)
	{
		$user = $this->User->findById($userId);
		if (!$user)
			throw new NotFoundException('User not found');

		$this->renderAchievementsPage($user['User']);
		$this->render('index');
	}

	/**
	 * @param array|null $viewedUser
	 * @return void
	 */
	private function renderAchievementsPage($viewedUser)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Achievements');
		$this->loadModel('AchievementStatus');
		$existingAs = [];
		$unlockedCounter2 = 0;

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
				if ($a[$i]['Achievement']['id'] == 46)
				{
					$a[$i]['Achievement']['a46value'] = $existingAs[$a[$i]['Achievement']['id']]['AchievementStatus']['value'];
					$unlockedCounter2 = $existingAs[$a[$i]['Achievement']['id']]['AchievementStatus']['value'] - 1;
				}
				$a[$i]['Achievement']['unlocked'] = true;
				$a[$i]['Achievement']['unlocked_at'] = $existingAs[$a[$i]['Achievement']['id']]['AchievementStatus']['created'];
			}
		}
		$this->set('a', $a);
		$this->set('unlockedCounter2', $unlockedCounter2);
	}

	/**
	 * @param string|int|null $id
	 * @return void
	 */
	public function view($id = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Achievements');
		$this->loadModel('AchievementCondition');
		$this->loadModel('AchievementStatus');
		$this->loadModel('User');
		$achievement = $this->Achievement->findById($id);
		if (!$achievement)
			throw new NotFoundException('Achievement not found');

		$currentUserStatus = [];
		$completerCount = $this->AchievementStatus->find('count', ['conditions' => ['achievement_id' => $id]]);
		$completers = $this->AchievementStatus->find('all', [
			'order' => 'created DESC',
			'conditions' => ['achievement_id' => $id],
			'limit' => 10]);
		if (!$completers)
			$completers = [];
		if (Auth::isLoggedIn())
			$currentUserStatus = $this->AchievementStatus->find('first', ['conditions' => ['achievement_id' => $id, 'user_id' => Auth::getUserID()]]);
		$userIds = array_map(fn($item) => $item['AchievementStatus']['user_id'], $completers);
		$usersById = [];
		if ($userIds)
			foreach ($this->User->find('all', ['conditions' => ['id' => $userIds]]) as $u)
				$usersById[$u['User']['id']] = $u['User'];
		foreach ($completers as &$item)
			$item['AchievementStatus']['user'] = $usersById[$item['AchievementStatus']['user_id']] ?? null;
		unset($item);
		$userCount = $this->User->find('count');
		$completionPercent = $userCount > 0 ? round($completerCount / $userCount * 100, 1) : 0;
		$rarity = Achievement::getRarityLabel($completionPercent);

		$progress = null;
		$progressGoal = null;
		if (Auth::isLoggedIn())
		{
			$progressDefinition = Achievement::progressDefinition((int) $achievement['Achievement']['id']);
			if ($progressDefinition)
			{
				$condition = $this->AchievementCondition->find('first', [
					'order' => 'value DESC',
					'conditions' => ['user_id' => Auth::getUserID(), 'category' => $progressDefinition['category']],
				]);
				$progress = $condition ? (int) $condition['AchievementCondition']['value'] : 0;
				$progressGoal = $progressDefinition['goal'];
				if ($currentUserStatus)
					$progress = $progressGoal;
				$progress = min($progress, $progressGoal);
			}
		}

		$this->set('achievement', $achievement);
		$this->set('currentUserStatus', $currentUserStatus);
		$this->set('completers', $completers);
		$this->set('completerCount', $completerCount);
		$this->set('userCount', $userCount);
		$this->set('completionPercent', $completionPercent);
		$this->set('rarity', $rarity);
		$this->set('progress', $progress);
		$this->set('progressGoal', $progressGoal);
	}

}

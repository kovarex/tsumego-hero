<?php

App::uses('AppController', 'Controller');

class AchievementStatus extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'achievement_status';
		parent::__construct($id, $table, $ds);
	}

	/**
	 * Returns the most recent achievements with user and achievement details.
	 *
	 * @param int $limit Maximum number of results (default: 7)
	 * @return array
	 */
	public function getRecent(int $limit = 7): array
	{
		$cacheKey = 'recent_achievements_' . $limit;
		$cached = Cache::read($cacheKey);
		if ($cached !== false)
		{
			return $cached;
		}

		$rows = $this->find('all', [
			'joins' => [
				[
					'table' => 'achievement',
					'alias' => 'Achievement',
					'type' => 'INNER',
					'conditions' => ['AchievementStatus.achievement_id = Achievement.id'],
				],
				[
					'table' => 'user',
					'alias' => 'User',
					'type' => 'INNER',
					'conditions' => ['AchievementStatus.user_id = User.id'],
				],
			],
			'fields' => [
				'AchievementStatus.id', 'AchievementStatus.created', 'Achievement.id', 'Achievement.name', 'Achievement.image',
				'User.id', 'User.name', 'User.external_id',
			],
			'limit' => $limit,
			'order' => 'AchievementStatus.created DESC',
		]) ?: [];

		$result = [];
		foreach ($rows as $row)
		{
			$result[] = [
				'status_id' => $row['AchievementStatus']['id'],
				'id' => $row['Achievement']['id'],
				'name' => $row['Achievement']['name'],
				'image' => $row['Achievement']['image'],
				'user_id' => (int) $row['User']['id'],
				'user_name' => AppController::checkPicture($row['User']),
				'created' => $row['AchievementStatus']['created'],
			];
		}
		Cache::write($cacheKey, $result, 'default');
		return $result;
	}
}

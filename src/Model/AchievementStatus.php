<?php

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
				'User.id', 'User.display_name', 'User.name', 'User.external_id', 'User.picture', 'User.email', 'User.rating',
			],
			'limit' => $limit,
			'order' => 'AchievementStatus.created DESC',
		]) ?: [];

		$result = [];
		foreach ($rows as $row)
		{
			$userData = [
				'external_id' => $row['User']['external_id'],
				'picture' => $row['User']['picture'],
				'email' => $row['User']['email'],
			];
			$rating = (float) $row['User']['rating'];
			$result[] = [
				'status_id' => $row['AchievementStatus']['id'],
				'id' => $row['Achievement']['id'],
				'name' => $row['Achievement']['name'],
				'image' => $row['Achievement']['image'],
				'user_id' => (int) $row['User']['id'],
				'user_name' => $row['User']['display_name'] ?: $row['User']['name'],
				'user_avatar_url' => User::getAvatarUrl($userData, 20),
				'user_rank' => $rating > 0 ? Rating::getReadableRankFromRating($rating) : '',
				'created' => $row['AchievementStatus']['created'],
			];
		}
		return $result;
	}
}

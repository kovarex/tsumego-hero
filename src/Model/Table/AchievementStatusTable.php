<?php

namespace App\Model\Table;

use App\Utility\Rating;
use Cake\ORM\TableRegistry;

//An achievement a player unlocked, with how often a repeatable one was earned (value).
class AchievementStatusTable extends AppTable
{
	//Returns the most recent achievements with user and achievement details.
	//$limit: Maximum number of results (default: 7)
	//Not cached: the index on created makes it read only the $limit newest rows.
	public static function getRecent(int $limit = 7): array
	{
		$rows = TableRegistry::getTableLocator()->get('AchievementStatus')->find()
			->select([
				'AchievementStatus.id', 'AchievementStatus.created', 'Achievement.id', 'Achievement.name', 'Achievement.image',
				'User.id', 'User.display_name', 'User.name', 'User.external_id', 'User.picture', 'User.avatar_setting_id', 'User.uploaded_avatar_hash', 'User.email', 'User.rating',
			])
			->innerJoin(['Achievement' => 'achievement'], ['AchievementStatus.achievement_id = Achievement.id'])
			->innerJoin(['User' => 'user'], ['AchievementStatus.user_id = User.id'])
			->where(['User.deleted IS NULL'])
			->orderByDesc('AchievementStatus.created')
			->limit($limit)
			->disableHydration()
			->toArray();

		$result = [];
		foreach ($rows as $row)
		{
			$userData = [
				'id' => (int) $row['User']['id'],
				'display_name' => $row['User']['display_name'],
				'external_id' => $row['User']['external_id'],
				'picture' => $row['User']['picture'],
				'avatar_setting_id' => $row['User']['avatar_setting_id'],
				'uploaded_avatar_hash' => $row['User']['uploaded_avatar_hash'],
				'email' => $row['User']['email'],
			];
			$rating = (float) $row['User']['rating'];
			$result[] = [
				'status_id' => $row['id'],
				'id' => $row['Achievement']['id'],
				'name' => $row['Achievement']['name'],
				'image' => $row['Achievement']['image'],
				'user_id' => (int) $row['User']['id'],
				'user_display_name' => $row['User']['display_name'],
				'user_avatar_url' => UserTable::getAvatarUrl($userData),
				'user_rank' => $rating > 0 ? Rating::getReadableRankFromRating($rating) : '',
				'created' => $row['created']->format('c'),
			];
		}
		return $result;
	}
}

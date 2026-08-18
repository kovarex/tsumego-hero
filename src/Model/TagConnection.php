<?php

class TagConnection extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'tag_connection';
		parent::__construct($id, $table, $ds);
	}

	/**
	 * How many tag connections the user has added today (UTC calendar day).
	 */
	public static function countTodaysTags(int $userId): int
	{
		$today = gmdate('Y-m-d');
		$result = ClassRegistry::init('TagConnection')->find('first', [
			'conditions' => [
				'user_id' => $userId,
				'created >=' => $today . ' 00:00:00',
			],
			'fields' => ['COUNT(*) AS cnt'],
			'recursive' => -1,
		]);
		return (int) ($result[0]['cnt'] ?? 0);
	}

	/**
	 * Whether the user may add more tags today. Admins are never rate-limited.
	 */
	public static function canUserAddTag(int $userId): bool
	{
		if (Auth::isAdmin())
			return true;
		return self::countTodaysTags($userId) < Constants::$DAILY_TAG_LIMIT;
	}
}

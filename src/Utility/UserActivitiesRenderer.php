<?php

namespace App\Utility;

use SetConnection;
use User;

/**
 * The latest attempts, for the admins: of all players, of one player, or on the problems of one set.
 * One query reads the page with its players and problems.
 */
class UserActivitiesRenderer extends DataTableRenderer
{
	/**
	 * Without a filter only the latest attempts are listed: counting the whole table would read
	 * every attempt there ever was on each page view.
	 */
	public static int $UNFILTERED_LIMIT = 1000;

	public function __construct(array $urlParams, ?int $userID = null, ?int $setID = null)
	{
		if ($userID !== null)
		{
			$condition = 'WHERE tsumego_attempt.user_id = ?';
			$parameters = [$userID];
			$order = 'tsumego_attempt.created DESC';
			$user = Util::query('SELECT display_name, deleted FROM user WHERE id = ?', [$userID])[0] ?? null;
			$caption = 'Attempts of ' . h($user && !$user['deleted'] ? $user['display_name'] : User::DELETED_NAME);
		}
		elseif ($setID !== null)
		{
			$condition = 'WHERE tsumego_attempt.tsumego_id IN (SELECT tsumego_id FROM set_connection WHERE set_id = ?)';
			$parameters = [$setID];
			$order = 'tsumego_attempt.created DESC';
			$set = Util::query("SELECT CONCAT_WS(' ', title, title2) AS title FROM `set` WHERE id = ?", [$setID])[0] ?? null;
			$caption = 'Attempts on ' . h($set['title'] ?? 'a set that is gone');
		}
		else
		{
			$condition = '';
			$parameters = [];
			$order = 'tsumego_attempt.id DESC';
			$caption = 'Latest attempts';
		}

		$this->count = $userID === null && $setID === null
			? (int) Util::query('SELECT COUNT(*) AS total FROM (SELECT 1 FROM tsumego_attempt ORDER BY id DESC LIMIT ' . self::$UNFILTERED_LIMIT . ') AS latest')[0]['total']
			: (int) Util::query('SELECT COUNT(*) AS total FROM tsumego_attempt ' . $condition, $parameters)[0]['total'];
		parent::__construct($urlParams, 'attempts_page', $caption);

		// a problem is shown in the set it was filtered by, otherwise in the set it is displayed in
		$setConnection = $setID !== null
			? 'set_connection.tsumego_id = tsumego_attempt.tsumego_id AND set_connection.set_id = ?'
			: 'set_connection.id = (
				SELECT sc2.id
				FROM set_connection sc2
				JOIN `set` s2 ON s2.id = sc2.set_id
				WHERE sc2.tsumego_id = tsumego_attempt.tsumego_id
				ORDER BY ' . SetConnection::displayOrderSql('s2', 'sc2') . '
				LIMIT 1
			)';
		$this->data = Util::query('
SELECT
	tsumego_attempt.solved AS solved,
	tsumego_attempt.misplays AS misplays,
	tsumego_attempt.seconds AS seconds,
	tsumego_attempt.gain AS gain,
	tsumego_attempt.tsumego_rating AS tsumego_rating,
	tsumego_attempt.created AS created,
	user.id AS user_id,
	user.display_name AS user_display_name,
	user.picture AS user_picture,
	user.pref_avatar AS user_pref_avatar,
	user.external_id AS user_external_id,
	user.email AS user_email,
	user.rating AS user_rating,
	user.deleted AS user_deleted,
	set_connection.id AS set_connection_id,
	set_connection.num AS num,
	CONCAT_WS(\' \', `set`.title, `set`.title2) AS set_title
FROM
	tsumego_attempt
	JOIN user ON user.id = tsumego_attempt.user_id
	LEFT JOIN set_connection ON ' . $setConnection . '
	LEFT JOIN `set` ON `set`.id = set_connection.set_id
' . $condition . '
ORDER BY ' . $order . '
LIMIT ' . self::$PAGE_SIZE . '
OFFSET ' . $this->offset, $setID !== null ? [$setID, ...$parameters] : $parameters);
	}

	protected function renderHeader(): void
	{
		echo '<tr><th>Player</th><th>Problem</th><th>Solved</th><th>Misplays</th><th>Seconds</th><th>XP</th><th>Problem rating</th><th>Time</th></tr>';
	}

	public function renderItem(int $index, array $item): void
	{
		echo '<td>' . User::renderLink($item) . ' <a class="link-plain" href="/users/userstats/' . $item['user_id'] . '">attempts</a></td>';
		echo '<td>';
		if ($item['set_connection_id'])
			echo '<a href="/' . $item['set_connection_id'] . '">' . h($item['set_title']) . ' - ' . h($item['num']) . '</a>';
		echo '</td>';
		echo '<td>' . ($item['solved'] ? 'yes' : 'no') . '</td>';
		echo '<td>' . $item['misplays'] . '</td>';
		echo '<td>' . $item['seconds'] . '</td>';
		echo '<td>' . $item['gain'] . '</td>';
		echo '<td>' . $item['tsumego_rating'] . '</td>';
		echo '<td><time datetime="' . Util::toIso8601($item['created']) . '" data-format="datetime">' . $item['created'] . '</time></td>';
	}
}

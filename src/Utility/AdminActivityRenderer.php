<?php

App::uses('DataTableRenderer', 'Utility');

class AdminActivityRenderer extends DataTableRenderer
{
	public function __construct($urlParams)
	{
		$this->count = Util::query("SELECT COUNT(*) as total FROM admin_activity")[0]['total'];
		parent::__construct($urlParams, 'activity_page', 'Admin Activity');
		$this->data = Util::query("
SELECT
	admin_activity.created AS created,
	admin_activity_type.id AS type,
	admin_activity_type.name AS readable_type,
	admin_activity.old_value AS old_value,
	admin_activity.new_value AS new_value,
	tsumego.id AS tsumego_id,
	user.id AS user_id,
	user.name AS user_name,
	user.picture AS user_picture,
	user.external_id AS user_external_id,
	user.rating AS user_rating,
	set_connection.id AS set_connection_id,
	set_connection.num AS num,
	tsumego_status.status AS status,
	CONCAT(`set`.title, ' ', `set`.title2) AS set_title
FROM
	admin_activity
	JOIN admin_activity_type
		ON admin_activity.type = admin_activity_type.id
	LEFT JOIN tsumego
		ON admin_activity.tsumego_id = tsumego.id

	/* pick exactly ONE set_connection per tsumego */
	LEFT JOIN (
		SELECT *
		FROM (
			SELECT
				set_connection.*,
				ROW_NUMBER() OVER (
					PARTITION BY set_connection.tsumego_id
					ORDER BY set_connection.id
				) AS rn
			FROM set_connection
		) ranked_set_connection
		WHERE ranked_set_connection.rn = 1
	) set_connection
		ON set_connection.tsumego_id = admin_activity.tsumego_id

	LEFT JOIN `set`
		ON `set`.id = set_connection.set_id
	LEFT JOIN user
		ON admin_activity.user_id = user.id
	LEFT JOIN tsumego_status
		ON tsumego_status.user_id = ?
	   AND tsumego_status.tsumego_id = tsumego.id
ORDER BY admin_activity.id DESC
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $this->offset, [Auth::getUserID()]);
	}

	public function renderItem($index, $item)
	{
		// Format date without seconds
		$timestamp = strtotime($item['created']);
		$dateFormatted = date('Y-m-d H:i', $timestamp);

		echo '<td>' . ($index + 1 + 100 * ($this->page - 1)) . '</td>';
		echo '<td>';
		if ($item['set_connection_id'])
			new TsumegoButton($item['tsumego_id'], $item['set_connection_id'], $item['num'], $item['status'])->render();
		echo '</td>';
		echo '<td>';
		if ($item['set_connection_id'])
			echo '<a href="/' . $item['set_connection_id'] . '">' . $item['set_title'] . ' - ' . $item['num'] . '</a>';
		else
			echo '(Set-wide)';
		echo '<div style="color:#666; margin-top:5px;">' . AdminActivity::renderChange($item) . '</div>
				</td>
				<td>
					<div>' . $dateFormatted . '</div>
					<div style="font-size:0.9em; color:#666; margin-top:2px;">' . User::renderLink($item) . '</div>
				</td>';
	}
}

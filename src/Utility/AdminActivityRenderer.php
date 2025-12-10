<?php

class AdminActivityRenderer
{
	public function __construct($urlParams)
	{
			// Get total count of proposals
		$this->count = Util::query("SELECT COUNT(*) as total FROM admin_activity")[0]['total'];
		$this->page = isset($urlParams['activity_page']) ? max(1, (int) $urlParams['activity_page']) : 1;
		$this->pageCount = ceil($this->count / self::$PAGE_SIZE);
		$offset = ($this->page - 1) * self::$PAGE_SIZE;

		$this->data = Util::query("
SELECT
	admin_activity.created as created,
	admin_activity_type.id as type,
	admin_activity_type.name as readable_type,
	admin_activity.old_value as old_value,
	admin_activity.new_value as new_value,
    tsumego.id as tsumego_id,
    user.id AS user_id,
    user.name AS user_name,
    set_connection.id AS set_connection_id,
    set_connection.num AS num,
    tsumego_status.status AS status,
    CONCAT(`set`.title, ' ', `set`.title2) AS set_title
FROM
	admin_activity
	JOIN admin_activity_type ON admin_activity.type = admin_activity_type.id
	LEFT JOIN tsumego ON admin_activity.tsumego_id = tsumego.id
	LEFT JOIN set_connection ON set_connection.tsumego_id = admin_activity.tsumego_id
	LEFT JOIN user ON admin_activity.user_id = user.id
	LEFT JOIN `set` ON `set`.id = set_connection.set_id
	LEFT JOIN tsumego_status ON tsumego_status.user_id = ? AND tsumego_status.tsumego_id = tsumego.id
ORDER BY admin_activity.id DESC
LIMIT " . self::$PAGE_SIZE . "
OFFSET $offset", [Auth::getUserID()]);
	}

	public function render()
	{
		echo '<h3>Admin Activity (' . $this->count . ')</h3>';
		echo PaginationHelper::render($this->page, $this->pageCount, 'activity_page');
		echo '<table border="0" class="statsTable" style="border-collapse:collapse;">';
		foreach ($this->data as $index => $adminActivity)
		{
			// Format date without seconds
			$timestamp = strtotime($adminActivity['created']);
			$dateFormatted = date('Y-m-d H:i', $timestamp);

			echo '<tr style="border-bottom:1px solid #e0e0e0;">';
			echo '<td>' . ($index + 1 + 100 * ($this->page - 1)) . '</td>';
			echo '<td>';
			if ($adminActivity['set_connection_id'])
				new TsumegoButton($adminActivity['tsumego_id'], $adminActivity['set_connection_id'], $adminActivity['num'], $adminActivity['status'])->render();
			echo '</td>';
			echo '<td>';
			if ($adminActivity['set_connection_id'])
				echo '<a href="/' . $adminActivity['set_connection_id'] . '">' . $adminActivity['set_title'] . ' - ' . $adminActivity['num'] . '</a>';
			else
				echo '(Set-wide)';
			echo '<div style="color:#666; margin-top:5px;">' . AdminActivity::renderChange($adminActivity) . '</div>
				</td>
				<td>
					<div>'.$dateFormatted.'</div>
					<div style="font-size:0.9em; color:#666; margin-top:2px;">' . $adminActivity['user_name'] . '</div>
				</td>';
			echo '</tr>';
		}
		echo '</table>';
		echo PaginationHelper::render($this->page, $this->pageCount, 'activity_page');
	}

	private static $PAGE_SIZE = 100;

	private $data;
	private $page;
	private $pageCount;
	private $count;
}

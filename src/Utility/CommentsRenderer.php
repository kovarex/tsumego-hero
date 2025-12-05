<?php

class CommentsRenderer
{
	public function __construct(string $name, ?int $userID, $urlParams)
	{
		$this->name = $name;
		$this->userID = $userID;
		$this->params = $urlParams;
	}

	private function renderComment($comment, $index)
	{
		echo '<div class="sandboxComment">';
		$commentColor = $comment['from_admin'] ? 'admin-text' : '';
		echo '<table class="sandboxTable2" width="100%" border="0">';
		echo '<tr><td width="73%"><div style="padding-bottom:7px;"><b>#' . ($index + 1) . '</b> | ';
		if ($comment['set_connection_id'])
		{
			echo '<a href="/' . $comment['set_connection_id'] . '?search=topics">';
			echo $comment['set_title'] . ' - ' . $comment['set_num'] . '</a><br>';
		}
		else
			echo '<i>Problem (id=' . $comment['tsumego_id'] . 'doesn\'t have set connection.</i>';

		echo '<br></div>';
		echo '<div class="' . $commentColor . '">';
		echo $comment['from_name'] . ':<br>';
		echo '</div>';
		if (TsumegoUtil::isSolvedStatus($comment['status']) || Auth::isAdmin())
			echo $comment['message'];
		else
			echo '<div class="grey-text">[You need to solve this problem to see the comment]</div>';
		$date = new DateTime($comment['created']);
		echo '</td><td class="sandboxTable2time" align="right">' . $date->format('Y-m-d') . '<br>' . $date->format('H:i') . '</td>';
		echo '</tr>';
		echo '<tr><td colspan="2"><div width="100%"><div align="center">';

		if ($comment['set_connection_id'])
			new TsumegoButton($comment['tsumego_id'], $comment['set_connection_id'], $comment['set_num'], $comment['status'], false, false)->render();
		echo '</div></td></tr>';
		echo '</table>';
		echo '</div>';
	}

	public function render()
	{
		$parameters = [];
		$parameters[] = Auth::getUserID();

		$queryCondition = "tsumego_comment.deleted = 0";
		if ($this->userID)
			Util::addSqlCondition($queryCondition, "tsumego_comment.user_id = " . $this->userID);

		$querySelects = "
SELECT
	tsumego_comment.message AS message,
	tsumego_comment.tsumego_id AS tsumego_id,
	tsumego_comment.created AS created,
	tsumego_status.status AS status,
	user.isAdmin AS from_admin,
	user.name as from_name,
	set_connection.id AS set_connection_id,
	CONCAT(`set`.title, ' ', `set`.title2) as set_title,
	set_connection.num as set_num";
		$queryFrom = "
FROM
	tsumego_comment
	JOIN tsumego ON tsumego_comment.tsumego_id = tsumego.id
	JOIN user ON tsumego_comment.user_id = user.id
	LEFT JOIN tsumego_status ON tsumego_status.tsumego_id = tsumego.id AND tsumego_status.user_id = ?
    LEFT JOIN set_connection ON set_connection.tsumego_id = tsumego.id
    LEFT JOIN `set` ON set_connection.set_id = `set`.id";

		if (!empty($queryCondition))
			$queryFrom .= " WHERE " . $queryCondition;
		$queryFrom .= ' ORDER BY tsumego_comment.created DESC';
		$queryFrom .= ' LIMIT ' . self::$PAGE_SIZE;

		$pageIndex = isset($this->params[$this->name]) ? max(1, (int) $this->params[$this->name]) : 1;
		$count = Util::query("SELECT COUNT(*) " . $queryFrom, $parameters)[0]['COUNT(*)'];

		$offset = ($pageIndex - 1) * self::$PAGE_SIZE;
		$queryFrom .= ' OFFSET ' . $offset;
		$comments = Util::query($querySelects . $queryFrom, $parameters);
		echo PaginationHelper::render($pageIndex, intval(ceil($count / self::$PAGE_SIZE)), $this->name);
		foreach ($comments as $index => $comment)
		{
			$this->renderComment($comment, $index + $offset);
			echo '<div class="space"><br></div>';
		}
	}
	public ?int $userID;
	public static int $PAGE_SIZE = 10;
	public string $name;
	public array $params;
}

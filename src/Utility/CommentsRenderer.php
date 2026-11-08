<?php

namespace App\Utility;

use App\View\Helper\PaginationHelper;
use App\Model\Table\SgfTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\UserTable;

class CommentsRenderer
{
	public function __construct(string $name, ?int $userID, array $urlParams)
	{
		$this->name = $name;
		$this->userID = $userID;
		$this->params = $urlParams;
	}

	private function renderComment(array $comment, int $index): void
	{
		echo '<div class="sandboxComment">';
		$commentColor = $comment['from_admin'] ? 'admin-text' : '';
		echo '<table class="sandboxTable2" width="100%" border="0">';
		echo '<tr><td width="73%"><div style="padding-bottom:7px;"><b>#' . ($index + 1) . '</b> | ';
		if ($comment['set_connection_id'])
		{
			echo '<a href="/' . (int) $comment['set_connection_id'] . '?search=topics">';
			echo h($comment['set_title']) . ' - ' . h($comment['set_num']) . '</a><br>';
		}
		else
			echo '<i>Problem (id=' . (int) $comment['tsumego_id'] . ' doesn\'t have set connection.</i>';

		echo '<br></div>';
		echo '<div class="' . $commentColor . '">';
		echo h(UserTable::displayName(['id' => $comment['from_id'], 'display_name' => $comment['from_display_name']])) . ':<br>';
		echo '</div>';
		// one class attribute: a browser keeps only the first of two, so a hidden admin comment wasn't muted
		$visible = TsumegoUtil::isSolvedStatus($comment['status']) || Auth::isAdmin();
		$classes = array_filter([$comment['from_admin'] ? 'commentBox2' : '', $visible ? '' : 'text-muted']);
		echo '<div' . ($classes ? ' class="' . implode(' ', $classes) . '"' : '') . ' id="comment_' . $comment['id'] . '">';
		echo $visible ? nl2br(h($comment['message'])) : '[You need to solve this problem to see the comment]';
		echo '</div>';
		echo '</td><td class="sandboxTable2time" align="right"><time datetime="' . Util::toIso8601($comment['created']) . '" data-format="date">' . h($comment['created']) . '</time><br><time datetime="' . Util::toIso8601($comment['created']) . '" data-format="time">' . h($comment['created']) . '</time></td>';
		echo '</tr>';
		echo '<tr><td colspan="2"><div width="100%"><div align="center">';

		if ($comment['set_connection_id'])
			new TsumegoButton($comment['tsumego_id'], $comment['set_connection_id'], $comment['set_num'], $comment['status'] ?: 'N', 0, $comment['sgf'])->render();
		echo '</div></td></tr>';
		echo '</table>';
		echo '</div>';
	}

	public function render(): void
	{
		// the comments of the list, of the player when given; the problem and its collection join only the page of them
		$listed = 'FROM tsumego_comment
			JOIN tsumego ON tsumego.id = tsumego_comment.tsumego_id
			JOIN user ON user.id = tsumego_comment.user_id
			WHERE tsumego_comment.deleted IS NULL AND tsumego.deleted IS NULL'
			. ($this->userID ? ' AND tsumego_comment.user_id = ' . $this->userID : '');
		$count = (int) Util::query('SELECT COUNT(*) AS count ' . $listed)[0]['count'];

		$pageIndex = isset($this->params[$this->name]) ? max(1, (int) $this->params[$this->name]) : 1;
		$offset = ($pageIndex - 1) * self::$PAGE_SIZE;
		$comments = Util::query("SELECT
				tsumego_comment.message,
				tsumego_comment.tsumego_id,
				tsumego_comment.created,
				tsumego_status.status,
				" . UserTable::isAdminSql('user.user_role_id') . " AS from_admin,
				user.display_name AS from_display_name,
				user.id AS from_id,
				tsumego_comment.id,
				set_connection.id AS set_connection_id,
				CONCAT_WS(' ', `set`.title, `set`.title2) AS set_title,
				set_connection.num AS set_num,
				COALESCE(sgf.sgf, '') AS sgf
			FROM (SELECT tsumego_comment.id " . $listed . "
				ORDER BY tsumego_comment.created DESC, tsumego_comment.id DESC
				LIMIT " . self::$PAGE_SIZE . " OFFSET " . $offset . ") page
			JOIN tsumego_comment ON tsumego_comment.id = page.id
			JOIN user ON user.id = tsumego_comment.user_id
			LEFT JOIN sgf ON sgf.id = " . SgfTable::currentVersionIDSql('tsumego_comment.tsumego_id') . "
			LEFT JOIN tsumego_status ON tsumego_status.tsumego_id = tsumego_comment.tsumego_id AND tsumego_status.user_id = ?
			LEFT JOIN set_connection ON set_connection.id = (
				SELECT sc.id
				FROM set_connection sc
				JOIN `set` s ON s.id = sc.set_id
				WHERE sc.tsumego_id = tsumego_comment.tsumego_id AND " . SetConnectionTable::visibilitySql('s') . "
				ORDER BY " . SetConnectionTable::displayOrderSql('s', 'sc') . "
				LIMIT 1)
			LEFT JOIN `set` ON `set`.id = set_connection.set_id
			ORDER BY tsumego_comment.created DESC, tsumego_comment.id DESC", [Auth::getUserID()]);
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

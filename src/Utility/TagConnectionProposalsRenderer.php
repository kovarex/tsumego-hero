<?php

class TagConnectionProposalsRenderer
{
	public function __construct($urlParams)
	{
		$this->count = ClassRegistry::init('TagConnection')->find('count', ['conditions' => ['approved' => 0]]);
		$this->page = isset($urlParams['tags_page']) ? max(1, (int) $urlParams['tags_page']) : 1;
		$this->pageCount = ceil($this->count / self::$PAGE_SIZE);
		$offset = ($this->page - 1) * self::$PAGE_SIZE;

		$this->toApprove = Util::query("
SELECT
	tag_connection.id as tag_connection_id,
	tag.id as tag_id,
	tag.name as tag_name,
    tsumego.id as tsumego_id,
    user.id AS user_id,
    user.name AS user_name,
    user.picture AS user_picture,
    user.external_id AS user_external_id,
    user.rating AS user_rating,
    set_connection.id AS set_connection_id,
    set_connection.num AS num,
    tsumego_status.status AS status,
    CONCAT(`set`.title, ' ', `set`.title2) AS set_title,
    tag_connection.created as created
FROM
	tag_connection
	JOIN tag ON tag_connection.tag_id = tag.id
	JOIN tsumego ON tag_connection.tsumego_id = tsumego.id
	JOIN set_connection ON set_connection.tsumego_id = tag_connection.tsumego_id
	JOIN user ON tag_connection.user_id = user.id
	JOIN `set` ON `set`.id = set_connection.set_id
	LEFT JOIN tsumego_status ON tsumego_status.user_id = ? AND tsumego_status.tsumego_id = tsumego.id
WHERE tag_connection.approved = FALSE
ORDER BY tag_connection.created, tag.id
LIMIT " . self::$PAGE_SIZE . "
OFFSET $offset", [Auth::getUserID()]);
	}

	public function render()
	{
		echo '<h3 style="margin:15px 0;" id="tagConnectionProposalsHeader">New Tags (' . $this->count . ')</h3>';
		echo PaginationHelper::render($this->page, $this->pageCount, 'proposals_page');
		echo '<table border="0">';
		foreach ($this->toApprove as $index => $toApprove)
		{
			echo '<tr>';
			echo '<td>' . ($index + 1) . '</td><td class="adminpanel-table-text">' . User::renderLink($toApprove) . ' added ';
			echo '<a class="adminpanel-link" href="/tag_names/view/' . $toApprove['tag_id'] . '">' . $toApprove['tag_name'];
			echo '</a> for <a class="adminpanel-link" href="/' . $toApprove['set_connection_id'] . '">' . $toApprove['set_title'] . ' - ' . $toApprove['num'] . '</a></td>';
			echo '<td>';
			new TsumegoButton($toApprove['tsumego_id'], $toApprove['set_connection_id'], $toApprove['num'], $toApprove['status'])->render();
			echo '</td>';
			echo '<td>';
			echo '<a class="new-button-default2" href="/users/acceptTagConnectionProposal/' . $toApprove['tag_connection_id'] . '" id="tag-accept-' . $toApprove['tag_connection_id'] . '">Accept</a>';
			echo '<a class="new-button-default2" href="/users/rejectTagConnectionProposal/' . $toApprove['tag_connection_id'] . '" id="tag-reject-' . $toApprove['tag_connection_id'] . '">Reject</a>';
			echo '</td>';
			echo '<td style="font-size:13px">' . $toApprove['created'] . '</td>';
			echo '</tr>';
		}
		echo '</table>';
		echo PaginationHelper::render($this->page, $this->pageCount, 'proposals_page');
		echo '<br><br><br><br><br>';
	}
	private static $PAGE_SIZE = 100;

	private $toApprove;
	private $page;
	private $pageCount;
	private $count;
}

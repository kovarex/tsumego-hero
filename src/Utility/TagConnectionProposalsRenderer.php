<?php

App::uses('DataTableRenderer', 'Utility');

class TagConnectionProposalsRenderer extends DataTableRenderer
{
	public function __construct($urlParams)
	{
		$this->count = ClassRegistry::init('TagConnection')->find('count', ['conditions' => ['approved' => 0]]);
		parent::__construct($urlParams, 'tag_connection_proposals_page', 'New Tags');
		$this->data = Util::query("
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
OFFSET " . $this->offset, [Auth::getUserID()]);
	}

	public function renderItem(int $index, array $item): void
	{
		echo '<td>' . ($index + 1) + ($this->page - 1) * self::$PAGE_SIZE . '</td><td class="adminpanel-table-text">' . User::renderLink($item) . ' added ';
		echo '<a class="adminpanel-link" href="/tags/view/' . $item['tag_id'] . '">' . $item['tag_name'];
		echo '</a> for <a class="adminpanel-link" href="/' . $item['set_connection_id'] . '">' . $item['set_title'] . ' - ' . $item['num'] . '</a></td>';
		echo '<td>';
		new TsumegoButton($item['tsumego_id'], $item['set_connection_id'], $item['num'], $item['status'])->render();
		echo '</td>';
		echo '<td>';
		echo '<a class="new-button-default2" href="/users/acceptTagConnectionProposal/' . $item['tag_connection_id'] . '" id="tag-connection-accept-' . $item['tag_connection_id'] . '">Accept</a>';
		echo '<a class="new-button-default2" href="/users/rejectTagConnectionProposal/' . $item['tag_connection_id'] . '" id="tag-connection-reject-' . $item['tag_connection_id'] . '">Reject</a>';
		echo '</td>';
		echo '<td style="font-size:13px">' . $item['created'] . '</td>';
	}
}

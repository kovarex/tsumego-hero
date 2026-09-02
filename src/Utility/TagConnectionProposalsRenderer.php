<?php

App::uses('DataTableRenderer', 'Utility');
App::uses('SetConnection', 'Model');

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
    COALESCE(sgf.sgf, '') AS sgf,
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
		AND set_connection.id = (
			SELECT sc2.id
			FROM set_connection sc2
			JOIN `set` s2 ON s2.id = sc2.set_id
			WHERE sc2.tsumego_id = tag_connection.tsumego_id
			ORDER BY " . SetConnection::displayOrderForSetSql('s2') . ", sc2.id ASC
			LIMIT 1
		)
	JOIN user ON tag_connection.user_id = user.id
	JOIN `set` ON `set`.id = set_connection.set_id
	LEFT JOIN sgf ON sgf.id = (SELECT MAX(s2.id) FROM sgf s2 WHERE s2.tsumego_id = tsumego.id)
	LEFT JOIN tsumego_status ON tsumego_status.user_id = ? AND tsumego_status.tsumego_id = tsumego.id
WHERE tag_connection.approved = FALSE
ORDER BY tag_connection.created, tag.id
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $this->offset, [Auth::getUserID()]);
	}

	protected function renderHeader(): void
	{
		echo '<tr><th>User</th><th>Tag</th><th style="min-width:200px">Problem</th><th></th><th></th><th>Date</th></tr>';
	}

	public function renderItem(int $index, array $item): void
	{
		echo '<td>' . User::renderLink($item) . '</td>';
		echo '<td><a class="adminpanel-link" href="/tags/view/' . $item['tag_id'] . '">' . h($item['tag_name']) . '</a></td>';
		echo '<td><a class="adminpanel-link" href="/' . $item['set_connection_id'] . '">' . h($item['set_title']) . ' - ' . h($item['num']) . '</a></td>';
		echo '<td>';
		new TsumegoButton($item['tsumego_id'], $item['set_connection_id'], $item['num'], $item['status'] ?: 'N', 0, $item['sgf'])->render();
		echo '</td>';
		echo '<td style="white-space:nowrap">';
		echo '<button class="btn btn--small" onclick="window.location=\'/users/acceptTagConnectionProposal/' . $item['tag_connection_id'] . '\'" id="tag-connection-accept-' . $item['tag_connection_id'] . '">Accept</button>';
		echo '<button class="btn btn--small" onclick="window.location=\'/users/rejectTagConnectionProposal/' . $item['tag_connection_id'] . '\'" id="tag-connection-reject-' . $item['tag_connection_id'] . '">Reject</button>';
		echo '</td>';
		echo '<td style="font-size:13px"><time datetime="' . Util::toIso8601($item['created']) . '" data-format="datetime">' . $item['created'] . '</time></td>';
	}
}

<?php

App::uses('DataTableRenderer', 'Utility');

class SGFProposalsRenderer extends DataTableRenderer
{
	public function __construct($urlParams)
	{
		$this->count = Util::query("SELECT COUNT(DISTINCT tsumego_id) as total FROM sgf WHERE accepted = false")[0]['total'];
		parent::__construct($urlParams, 'sgf_proposals_page', 'SGF proposals');
		$this->data = Util::query("
SELECT
    p.tsumego_id as tsumego_id,
    a.latest_accepted_id AS latest_accepted_id,
    p.id AS proposed_id,
    p.user_id AS proposed_user_id,
    user.name AS user_name,
    set_connection.id AS set_connection_id,
    set_connection.num AS num,
    tsumego_status.status AS status,
    CONCAT(`set`.title, ' ', `set`.title2) AS set_title
FROM sgf p
JOIN (
    SELECT tsumego_id, MAX(id) AS latest_accepted_id
    FROM sgf
    WHERE accepted = TRUE
    GROUP BY tsumego_id
) a ON a.tsumego_id = p.tsumego_id
JOIN set_connection ON set_connection.tsumego_id = p.tsumego_id
JOIN user ON p.user_id=user.id
JOIN `set` ON `set`.id = set_connection.set_id
LEFT JOIN tsumego_status ON tsumego_status.user_id = ? AND tsumego_status.tsumego_id = p.tsumego_id
WHERE p.accepted = FALSE
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $this->offset, [Auth::getUserID()]);
	}

	public function renderItem(int $index, array $item): void
	{
		echo '<td class="adminpanel-table-text">' . $item['user_name'] . ' made a proposal for <a class="adminpanel-link" href="/'
		. $item['set_connection_id'] . '">' . $item['set_title'] . ' - ' . $item['num'] . '</a>:</td>';
		echo '<td>';
		echo '<a href="/editor/?sgfID=' . $item['latest_accepted_id'] . '">current</a> |
			<a href="/editor/?sgfID=' . $item['proposed_id'] . '">proposal</a> |
			<a href="/editor/?sgfID=' . $item['proposed_id'] . '&diffID=' . $item['latest_accepted_id'] . '">diff</a>';
		echo '</td>';
		echo '<td>';
		new TsumegoButton($item['tsumego_id'], $item['set_connection_id'], $item['num'], $item['status'])->render();
		echo '<td><a class="new-button-default2" href="/users/acceptSGFProposal/'
			. $item['proposed_id'] . '" id="accept-' . $item['proposed_id'] . '">Accept</a>
			<a class="new-button-default2" href="/users/rejectSGFProposal/' . $item['proposed_id'] . '" id="reject-' . $item['proposed_id'] . '">Reject</a></td>';
	}
}

<?php

App::uses('DataTableRenderer', 'Utility');
App::uses('SetConnection', 'Model');

class SGFProposalsRenderer extends DataTableRenderer
{
	public function __construct($urlParams)
	{
		$this->count = Util::query("SELECT COUNT(*) AS total FROM sgf p WHERE p.accepted = false AND EXISTS (SELECT 1 FROM set_connection sc WHERE sc.tsumego_id = p.tsumego_id)")[0]['total'];
		parent::__construct($urlParams, 'sgf_proposals_page', 'SGF Proposals');
		$this->data = Util::query("
SELECT
    p.tsumego_id as tsumego_id,
    a.latest_accepted_id AS latest_accepted_id,
    COALESCE(sgf.sgf, '') AS sgf,
    p.id AS proposed_id,
    p.user_id AS proposed_user_id,
    user.name AS user_name,
    sc.id AS set_connection_id,
    sc.num AS num,
    tsumego_status.status AS status,
    CONCAT(s.title, ' ', s.title2) AS set_title
FROM sgf p
JOIN (
    SELECT tsumego_id, MAX(id) AS latest_accepted_id
    FROM sgf
    WHERE accepted = TRUE
    GROUP BY tsumego_id
) a ON a.tsumego_id = p.tsumego_id
JOIN set_connection sc ON sc.tsumego_id = p.tsumego_id
    AND sc.id = (
        SELECT sc2.id
        FROM set_connection sc2
        JOIN `set` s2 ON s2.id = sc2.set_id
        WHERE sc2.tsumego_id = p.tsumego_id
        ORDER BY " . SetConnection::displayOrderForSetSql('s2') . ", sc2.id ASC
        LIMIT 1
    )
JOIN `set` s ON s.id = sc.set_id
JOIN user ON p.user_id=user.id
LEFT JOIN sgf ON sgf.id = a.latest_accepted_id
LEFT JOIN tsumego_status ON tsumego_status.user_id = ? AND tsumego_status.tsumego_id = p.tsumego_id
WHERE p.accepted = FALSE
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $this->offset, [Auth::getUserID()]);
	}

	protected function renderHeader(): void
	{
		echo '<tr><th>User</th><th>Problem</th><th>Compare</th><th></th><th></th></tr>';
	}

	public function renderItem(int $index, array $item): void
	{
		echo '<td>' . h($item['user_name']) . '</td>';
		echo '<td><a class="adminpanel-link" href="/' . $item['set_connection_id'] . '">' . h($item['set_title']) . ' - ' . h($item['num']) . '</a></td>';
		echo '<td style="white-space:nowrap">';
		echo '<a href="/editor/?sgfID=' . $item['latest_accepted_id'] . '">current</a> | <a href="/editor/?sgfID=' . $item['proposed_id'] . '">proposal</a> | <a href="/editor/?sgfID=' . $item['proposed_id'] . '&diffID=' . $item['latest_accepted_id'] . '">diff</a>';
		echo '</td>';
		echo '<td>';
		new TsumegoButton($item['tsumego_id'], $item['set_connection_id'], $item['num'], $item['status'] ?: 'N', 0, $item['sgf'])->render();
		echo '</td>';
		echo '<td style="white-space:nowrap"><button class="btn btn--small" onclick="window.location=\'/users/acceptSGFProposal/' . $item['proposed_id'] . '\'" id="accept-' . $item['proposed_id'] . '">Accept</button> <button class="btn btn--small" onclick="window.location=\'/users/rejectSGFProposal/' . $item['proposed_id'] . '\'" id="reject-' . $item['proposed_id'] . '">Reject</button></td>';
	}
}

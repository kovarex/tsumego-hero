<?php

namespace App\Utility;

use App\Model\Table\SgfTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\UserTable;

class SGFProposalsRenderer extends DataTableRenderer
{
	//How many proposals wait for a decision, the admin page shows it on the link here.
	public static function total(): int
	{
		return (int) Util::query("SELECT COUNT(*) AS total FROM sgf p WHERE p.sgf_status_id = " . SgfTable::STATUS_PROPOSED . " AND EXISTS (SELECT 1 FROM set_connection sc WHERE sc.tsumego_id = p.tsumego_id)")[0]['total'];
	}

	public function __construct(array $urlParams)
	{
		$this->count = self::total();
		parent::__construct($urlParams, 'sgf_proposals_page', 'SGF Proposals');
		$this->data = Util::query("
SELECT
    p.tsumego_id as tsumego_id,
    a.latest_accepted_id AS latest_accepted_id,
    COALESCE(sgf.sgf, '') AS sgf,
    p.id AS proposed_id,
    p.description AS description,
    p.user_id AS user_id,
    p.user_id AS proposed_user_id,
    user.display_name AS user_display_name,
    user.external_id AS user_external_id,
    user.picture AS user_picture,
    user.avatar_setting_id AS user_avatar_setting_id,
    user.uploaded_avatar_hash AS user_uploaded_avatar_hash,
    user.rating AS user_rating,
    user.email AS user_email,
    sc.id AS set_connection_id,
    sc.num AS num,
    tsumego_status.status AS status,
    CONCAT_WS(' ', s.title, s.title2) AS set_title
FROM sgf p
JOIN (
    SELECT tsumego_id, MAX(id) AS latest_accepted_id
    FROM sgf
    WHERE sgf_status_id = " . SgfTable::STATUS_ACCEPTED . "
    GROUP BY tsumego_id
) a ON a.tsumego_id = p.tsumego_id
JOIN set_connection sc ON sc.tsumego_id = p.tsumego_id
    AND sc.id = (
        SELECT sc2.id
        FROM set_connection sc2
        JOIN `set` s2 ON s2.id = sc2.set_id
        WHERE sc2.tsumego_id = p.tsumego_id
        ORDER BY " . SetConnectionTable::displayOrderSql('s2', 'sc2') . "
        LIMIT 1
    )
JOIN `set` s ON s.id = sc.set_id
JOIN user ON p.user_id=user.id
LEFT JOIN sgf ON sgf.id = a.latest_accepted_id
LEFT JOIN tsumego_status ON tsumego_status.user_id = ? AND tsumego_status.tsumego_id = p.tsumego_id
WHERE p.sgf_status_id = " . SgfTable::STATUS_PROPOSED . "
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $this->offset, [Auth::getUserID()]);
	}

	protected function renderHeader(): void
	{
		echo '<tr><th>User</th><th>Problem</th><th>Description</th><th>Compare</th><th><label title="Toggle board previews"><input type="checkbox" class="preview-zoom-toggle">🔍</label></th><th></th></tr>';
	}

	public function renderItem(int $index, array $item): void
	{
		echo '<td>' . UserTable::renderLink($item) . '</td>';
		echo '<td><a class="adminpanel-link" href="/' . $item['set_connection_id'] . '">' . h($item['set_title']) . ' - ' . h($item['num']) . '</a></td>';
		echo '<td class="proposal-description" id="description-' . $item['proposed_id'] . '">' . SgfDescription::render($item['description']) . '</td>';
		echo '<td style="white-space:nowrap">';
		echo '<a href="/editor/?sgfID=' . $item['latest_accepted_id'] . '">current</a> | <a href="/editor/?sgfID=' . $item['proposed_id'] . '">proposal</a> | <a href="/editor/?sgfID=' . $item['proposed_id'] . '&diffID=' . $item['latest_accepted_id'] . '">diff</a>';
		echo '</td>';
		echo '<td>';
		new TsumegoButton($item['tsumego_id'], $item['set_connection_id'], $item['num'], $item['status'] ?: 'N', 0, $item['sgf'])->render();
		echo '</td>';
		echo '<td>';
		ProposalDecisionForm::render(
			'/users/acceptSGFProposal/' . $item['proposed_id'],
			'/users/rejectSGFProposal/' . $item['proposed_id'],
			'accept-' . $item['proposed_id'],
			'reject-' . $item['proposed_id'],
			'comment-' . $item['proposed_id']);
		echo '</td>';
	}
}

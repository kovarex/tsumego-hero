<?php

namespace App\Utility;

use App\Model\Table\SgfTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\UserTable;

//The rejected SGF proposals in the admin panel, folded under the ones waiting for a decision,
//with who rejected them and why, and a button to reopen one. Proposals rejected before the
//rejected versions were kept are gone, so they are not listed.
class RejectedSGFProposalsRenderer extends DataTableRenderer
{
	public function __construct(array $urlParams)
	{
		$this->count = Util::query("SELECT COUNT(*) AS total FROM sgf p WHERE p.sgf_status_id = " . SgfTable::STATUS_REJECTED . " AND EXISTS (SELECT 1 FROM set_connection sc WHERE sc.tsumego_id = p.tsumego_id)")[0]['total'];
		parent::__construct($urlParams, 'rejected_sgf_proposals_page', 'Rejected SGF Proposals');
		$this->opened = isset($urlParams[$this->name]);
		$this->data = Util::query("
SELECT
    p.id AS rejected_id,
    p.description AS description,
    " . SgfTable::currentVersionIDSql('p.tsumego_id') . " AS current_id,
    p.user_id AS user_id,
    user.display_name AS user_display_name,
    user.external_id AS user_external_id,
    user.picture AS user_picture,
    user.avatar_setting_id AS user_avatar_setting_id,
    user.uploaded_avatar_hash AS user_uploaded_avatar_hash,
    user.rating AS user_rating,
    user.email AS user_email,
    reject.created AS rejected,
    reject.comment AS comment,
    admin.id AS admin_id,
    admin.display_name AS admin_display_name,
    admin.external_id AS admin_external_id,
    admin.picture AS admin_picture,
    admin.avatar_setting_id AS admin_avatar_setting_id,
    admin.uploaded_avatar_hash AS admin_uploaded_avatar_hash,
    admin.rating AS admin_rating,
    admin.email AS admin_email,
    sc.id AS set_connection_id,
    sc.num AS num,
    CONCAT_WS(' ', s.title, s.title2) AS set_title
FROM sgf p
JOIN set_connection sc ON sc.id = (
    SELECT sc2.id
    FROM set_connection sc2
    JOIN `set` s2 ON s2.id = sc2.set_id
    WHERE sc2.tsumego_id = p.tsumego_id
    ORDER BY " . SetConnectionTable::displayOrderSql('s2', 'sc2') . "
    LIMIT 1
)
JOIN `set` s ON s.id = sc.set_id
LEFT JOIN user ON user.id = p.user_id
LEFT JOIN reject ON reject.id = (SELECT MAX(r2.id) FROM reject r2 WHERE r2.sgf_id = p.id)
LEFT JOIN user admin ON admin.id = reject.rejected_by_user_id
WHERE p.sgf_status_id = " . SgfTable::STATUS_REJECTED . "
ORDER BY p.id DESC
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $this->offset);
	}

	//Folded unless the admin is paging through it.
	public function render(): void
	{
		echo '<details class="rejected-proposals" id="rejected-sgf-proposals"' . ($this->opened ? ' open' : '') . '>';
		echo '<summary>Rejected SGF Proposals (' . $this->count . ')</summary>';
		parent::render();
		echo '</details>';
	}

	protected function renderHeader(): void
	{
		echo '<tr><th>User</th><th>Problem</th><th>Description</th><th>Compare</th><th>Rejected</th><th></th></tr>';
	}

	public function renderItem(int $index, array $item): void
	{
		echo '<td>' . UserTable::renderLink($item) . '</td>';
		echo '<td><a class="adminpanel-link" href="/' . $item['set_connection_id'] . '">' . h($item['set_title']) . ' - ' . h($item['num']) . '</a></td>';
		echo '<td class="proposal-description">' . SgfDescription::render($item['description']) . '</td>';
		echo '<td style="white-space:nowrap">';
		echo '<a href="/editor/?sgfID=' . $item['current_id'] . '">current</a> | <a href="/editor/?sgfID=' . $item['rejected_id'] . '">proposal</a> | <a href="/editor/?sgfID=' . $item['rejected_id'] . '&diffID=' . $item['current_id'] . '">diff</a>';
		echo '</td>';
		echo '<td class="proposal-description" id="rejection-' . $item['rejected_id'] . '">';
		if ($item['rejected'] !== null)
		{
			echo '<time datetime="' . Util::toIso8601($item['rejected']) . '" data-format="date">' . h($item['rejected']) . '</time> ';
			echo UserTable::renderLink($item['admin_id'] === null ? null : [
				'user_id' => $item['admin_id'],
				'user_display_name' => $item['admin_display_name'],
				'user_external_id' => $item['admin_external_id'],
				'user_picture' => $item['admin_picture'],
				'user_avatar_setting_id' => $item['admin_avatar_setting_id'],
				'user_uploaded_avatar_hash' => $item['admin_uploaded_avatar_hash'],
				'user_rating' => $item['admin_rating'],
				'user_email' => $item['admin_email']]);
			if ($item['comment'] !== null && $item['comment'] !== '')
				echo ': ' . SgfDescription::render($item['comment']);
		}
		echo '</td>';
		echo '<td>';
		echo '<form method="post" action="/users/reopenSGFProposal/' . $item['rejected_id'] . '" onsubmit="return confirm(\'Put this proposal back among the ones waiting for a decision?\');">';
		echo '<button type="submit" class="btn btn--small" id="reopen-' . $item['rejected_id'] . '">Reopen</button>';
		echo '</form>';
		echo '</td>';
	}

	private bool $opened;
}

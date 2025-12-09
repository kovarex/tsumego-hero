<?php

class SGFProposalsRenderer
{
	public function __construct($urlParams)
	{
		// Get total count of proposals
		$this->count = Util::query("SELECT COUNT(DISTINCT tsumego_id) as total FROM sgf WHERE accepted = false")[0]['total'];
		$this->page = isset($urlParams['proposals_page']) ? max(1, (int) $urlParams['proposals_page']) : 1;
		$this->pageCount = ceil($this->count / self::$PAGE_SIZE);
		$offset = ($this->page - 1) * self::$PAGE_SIZE;

		$this->toApprove = Util::query("
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
OFFSET $offset", [Auth::getUserID()]);
	}

	public function render()
	{
		echo '<h3 style="margin:15px 0;" id="sgfProposalsHeader">SGF Proposals (' . $this->count . ')</h3>';
		echo PaginationHelper::render($this->page, $this->pageCount, 'proposals_page');
		echo '<table border="0">';
		foreach ($this->toApprove as $toApprove)
		{
			echo '<tr>';
			echo '<td class="adminpanel-table-text">' . $toApprove['user_name'] . ' made a proposal for <a class="adminpanel-link" href="/'
			. $toApprove['set_connection_id'] . '">' . $toApprove['set_title'] . ' - ' . $toApprove['num'] . '</a>:</td>';
			echo '<td>';
			echo '<a href="/editor/?sgfID=' . $toApprove['latest_accepted_id'] . '">current</a> |
				<a href="/editor/?sgfID=' . $toApprove['proposed_id'] . '">proposal</a> |
				<a href="/editor/?sgfID=' . $toApprove['proposed_id'] . '&diffID=' . $toApprove['latest_accepted_id'] . '">diff</a>';
			echo '</td>';
			echo '<td>';
			new TsumegoButton($toApprove['tsumego_id'], $toApprove['set_connection_id'], $toApprove['num'], $toApprove['status'])->render();
			echo '<td><a class="new-button-default2" href="/users/acceptSGFProposal/'
				. $toApprove['proposed_id'] . '" id="accept-' . $toApprove['proposed_id'] . '">Accept</a>
				<a class="new-button-default2" href="/users/rejectSGFProposal/' . $toApprove['proposed_id'] . '" id="reject-' . $toApprove['proposed_id'] . '">Reject</a></td>';
			echo '</tr>';
		}
		echo '</table>';
		echo PaginationHelper::render($this->page, $this->pageCount, 'proposals_page');
		echo '<hr>';
	}

	private static $PAGE_SIZE = 100;

	private $toApprove;
	private $page;
	private $pageCount;
	private $count;
}

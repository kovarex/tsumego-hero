<?php

class TagProposalsRenderer
{
	public function __construct($urlParams)
	{
		// Get total count of proposals
		$this->count = Util::query("SELECT COUNT(DISTINCT tsumego_id) as total FROM sgf WHERE accepted = false")[0]['total'];
		$this->page = isset($urlParams['tag_proposals_page']) ? max(1, (int) $urlParams['tag_proposals_page']) : 1;
		$this->pageCount = ceil($this->count / self::$PAGE_SIZE);
		$offset = ($this->page - 1) * self::$PAGE_SIZE;
		$this->toApprove = Util::query("
SELECT
	tag.id as tag_id,
	tag.name as tag_name,
	user.name as user_name,
	user.id as user_id,
	user.rating as user_rating
FROM
	tag
	JOIN user ON user.id = tag.user_id
WHERE approved = 0
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $offset);
	}

	public function render()
	{
		echo '<h3 style="margin:15px 0;" id="sgfProposalsHeader">Tag names (' . $this->count . ')</h3>';
		echo PaginationHelper::render($this->page, $this->pageCount, 'tag_proposals_page');
		echo '<table border="0">';
		foreach ($this->toApprove as $toApprove)
		{
			echo '<tr>';
			echo '<td class="adminpanel-table-text">' . User::renderLink($toApprove) . ' made a proposal for <a href="/tags/view' . $toApprove['tag_id'] . '">' . $toApprove['tag_name'] . '</a>:</td>';
			echo '<a class="new-button-default2" href="/tags/acceptTagProposal/' . $toApprove['tag_id'] . '" id="tag-accept-' . $toApprove['tag_id'] . '">Accept</a>';
			echo '<a class="new-button-default2" href="/tags/rejectTagProposal/' . $toApprove['tag_id'] . '" id="tag-reject-' . $toApprove['tag_id'] . '">Reject</a>';
			echo '</td>';
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

<?php

App::uses('DataTableRenderer', 'Utility');

class TagProposalsRenderer extends DataTableRenderer
{
	public function __construct($urlParams)
	{
		$this->count = Util::query("SELECT COUNT(DISTINCT tsumego_id) as total FROM sgf WHERE accepted = false")[0]['total'];
		parent::__construct($urlParams, 'tag_proposals_page', 'Tag proposals');
		$this->data = Util::query("
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
OFFSET " . $this->offset);
	}

	public function renderItem(int $index, array $item): void
	{
		echo '<td class="adminpanel-table-text">' . User::renderLink($item) . ' made a proposal for <a href="/tags/view' . $item['tag_id'] . '">' . $item['tag_name'] . '</a>:</td>';
		echo '<td>';
		echo '<a class="new-button-default2" href="/tags/acceptTagProposal/' . $item['tag_id'] . '" id="tag-accept-' . $item['tag_id'] . '">Accept</a>';
		echo '<a class="new-button-default2" href="/tags/rejectTagProposal/' . $item['tag_id'] . '" id="tag-reject-' . $item['tag_id'] . '">Reject</a>';
		echo '</td>';
	}
}

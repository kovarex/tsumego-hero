<?php

namespace App\Utility;

use User;

class TagProposalsRenderer extends DataTableRenderer
{
	public function __construct(array $urlParams)
	{
		$this->count = Util::query("SELECT COUNT(DISTINCT tsumego_id) as total FROM sgf WHERE accepted = false")[0]['total'];
		parent::__construct($urlParams, 'tag_proposals_page', 'Tag proposals');
		$this->data = Util::query("
SELECT
	tag.id as tag_id,
	tag.name as tag_name,
	user.display_name AS user_display_name,
	user.id as user_id,
	user.picture AS user_picture,
	user.pref_avatar AS user_pref_avatar,
	user.external_id AS user_external_id,
	user.email AS user_email,
	user.rating as user_rating
FROM
	tag
	JOIN user ON user.id = tag.user_id
WHERE approved = 0
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $this->offset);
	}

	protected function renderHeader(): void
	{
		echo '<tr><th>User</th><th>Tag</th><th></th></tr>';
	}

	public function renderItem(int $index, array $item): void
	{
		echo '<td>' . User::renderLink($item) . '</td>';
		echo '<td><a href="/tags/view/' . (int) $item['tag_id'] . '">' . h($item['tag_name']) . '</a></td>';
		echo '<td>';
		echo '<form method="post" style="display:inline" action="/tags/acceptTagProposal/' . $item['tag_id'] . '">';
		echo '<button type="submit" class="btn btn--small" id="tag-accept-' . $item['tag_id'] . '">Accept</button>';
		echo '</form>';
		echo '<form method="post" style="display:inline" action="/tags/rejectTagProposal/' . $item['tag_id'] . '">';
		echo '<button type="submit" class="btn btn--small" id="tag-reject-' . $item['tag_id'] . '">Reject</button>';
		echo '</form>';
		echo '</td>';
	}
}

<?php

namespace App\Utility;

use User;

/**
 * The new tags players proposed, waiting for an admin to accept them. Proposals to put an existing
 * tag on a problem are in TagConnectionProposalsRenderer.
 */
class TagProposalsRenderer extends DataTableRenderer
{
	/**
	 * How many new tags wait for a decision, the admin page shows it on the link here.
	 */
	public static function total(): int
	{
		return (int) Util::query("SELECT COUNT(*) as total FROM tag WHERE approved = 0")[0]['total'];
	}

	public function __construct(array $urlParams)
	{
		$this->count = self::total();
		parent::__construct($urlParams, 'tag_proposals_page', 'New Tags');
		$this->data = Util::query("
SELECT
	tag.id as tag_id,
	tag.name as tag_name,
	user.display_name AS user_display_name,
	user.id as user_id,
	user.picture AS user_picture,
	user.avatar_setting_id AS user_avatar_setting_id,
	user.uploaded_avatar AS user_uploaded_avatar,
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
		ProposalDecisionForm::render(
			'/tags/acceptTagProposal/' . $item['tag_id'],
			'/tags/rejectTagProposal/' . $item['tag_id'],
			'tag-accept-' . $item['tag_id'],
			'tag-reject-' . $item['tag_id'],
			'tag-comment-' . $item['tag_id']);
		echo '</td>';
	}
}

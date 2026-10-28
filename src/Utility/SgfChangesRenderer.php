<?php

namespace App\Utility;

use App\Model\Table\SgfReviewTable;
use SetConnection;
use Sgf;
use User;

//The SGF changes of the admin area: every version that became part of a problem, an admin's own
//change or a proposal an admin accepted, the newest first. The proposals waiting for a decision
//and the rejected ones are on the SGF proposals page. Each admin ticks the changes of others they
//checked (SgfReviewTable) and sees who of the others checked them, so they can keep the changes under
//control without a decision.
class SgfChangesRenderer extends DataTableRenderer
{
	//The url parameter of the list on the page, one of the keys of LISTS
	public const LIST_PARAMETER = 'show';

	//The lists of the page by the labels of their tabs, the first one is shown when the url names none
	private const LISTS = [
		'not-reviewed' => 'Not reviewed by me',
		'mine' => 'My changes',
		'all' => 'All changes'];

	//The columns of a user the rows have twice, the admin who accepted the change and its author
	private const USER_FIELDS = ['id', 'display_name', 'external_id', 'picture', 'email', 'avatar_setting_id', 'uploaded_avatar_hash', 'rating', 'deleted'];

	//$newSince: When the admin looked the time before (see AdminPageTable::markViewed),
	//what the others accepted since then is marked as new
	/** @throws \BadRequestException When the url names a list the page doesn't have */
	public function __construct(array $urlParams, private ?string $newSince = null)
	{
		$this->userID = Auth::getUserID();
		$this->list = $urlParams[self::LIST_PARAMETER] ?? array_key_first(self::LISTS);
		if (!isset(self::LISTS[$this->list]))
			throw new \BadRequestException('There is no list ' . $this->list . ' of SGF changes');
		foreach (array_keys(self::LISTS) as $list)
		{
			[$condition, $parameters] = $this->conditionOf($list);
			$this->counts[$list] = (int) Util::query('SELECT COUNT(*) AS total FROM sgf WHERE sgf.sgf_status_id = ?' . $condition, [Sgf::STATUS_ACCEPTED, ...$parameters])[0]['total'];
		}
		$this->count = $this->counts[$this->list];
		// the tabs say which list it is and how many changes it has
		parent::__construct($urlParams, 'sgf_changes_page', null);

		[$condition, $parameters] = $this->conditionOf($this->list);
		$this->data = Util::query("
SELECT
	sgf.id AS id,
	sgf.tsumego_id AS tsumego_id,
	sgf.sgf AS sgf,
	sgf.created AS created,
	sgf.accepted AS accepted,
	sgf.description AS description,
	(SELECT MAX(previous.id) FROM sgf previous
		WHERE previous.tsumego_id = sgf.tsumego_id AND previous.sgf_status_id = " . Sgf::STATUS_ACCEPTED . " AND previous.id < sgf.id) AS previous_version_id,
	sgf.user_id AS user_id,
	sgf.accepted_by_user_id AS accepted_by_user_id,
	" . self::userColumnsSql('author') . ",
	" . self::userColumnsSql('accepted_by') . ",
	set_connection.id AS set_connection_id,
	set_connection.num AS num,
	`set`.id AS set_id,
	CONCAT_WS(' ', `set`.title, `set`.title2) AS set_title,
	tsumego_status.status AS status
FROM sgf
LEFT JOIN user author ON author.id = sgf.user_id
LEFT JOIN user accepted_by ON accepted_by.id = sgf.accepted_by_user_id
LEFT JOIN set_connection
	ON set_connection.tsumego_id = sgf.tsumego_id
	AND set_connection.id = (
		SELECT sc2.id
		FROM set_connection sc2
		JOIN `set` s2 ON s2.id = sc2.set_id
		WHERE sc2.tsumego_id = sgf.tsumego_id
		ORDER BY " . SetConnection::displayOrderSql('s2', 'sc2') . "
		LIMIT 1
	)
LEFT JOIN `set` ON `set`.id = set_connection.set_id
LEFT JOIN tsumego_status ON tsumego_status.user_id = ? AND tsumego_status.tsumego_id = sgf.tsumego_id
WHERE sgf.sgf_status_id = " . Sgf::STATUS_ACCEPTED . $condition . "
ORDER BY sgf.accepted DESC, sgf.id DESC
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $this->offset, [$this->userID, ...$parameters]);

		$this->reviewers = SgfReviewTable::reviewersOf(array_map(fn($item) => (int) $item['id'], $this->data));
	}

	//The condition the changes of the list add to the query, with its parameters:
	//- not-reviewed: accepted by others, the admin did not tick them yet
	//- mine: what the admin made part of a problem, their own changes and the proposals they accepted
	/** @return array{string, list<int>} */
	private function conditionOf(string $list): array
	{
		return match ($list)
		{
			'not-reviewed' => [' AND NOT (sgf.accepted_by_user_id <=> ?) AND NOT EXISTS (SELECT 1 FROM sgf_review WHERE sgf_review.sgf_id = sgf.id AND sgf_review.user_id = ?)', [$this->userID, $this->userID]],
			'mine' => [' AND sgf.accepted_by_user_id = ?', [$this->userID]],
			'all' => ['', []],
			default => throw new \InvalidArgumentException('There is no list ' . $list . ' of SGF changes'),
		};
	}

	//The columns of the user joined as $alias, each prefixed by the alias
	private static function userColumnsSql(string $alias): string
	{
		return implode(', ', array_map(fn($field) => $alias . '.' . $field . ' AS ' . $alias . '_' . $field, self::USER_FIELDS));
	}

	//The user of the columns of userColumnsSql, as User::renderLink takes it
	/** @return array<string, mixed> */
	private static function userOf(array $item, string $alias): array
	{
		$user = [];
		foreach (self::USER_FIELDS as $field)
			$user[$field] = $item[$alias . '_' . $field];
		return $user;
	}

	public function render(): void
	{
		echo '<div class="subnav sgf-changes__lists" id="sgf-changes-lists">';
		foreach (self::LISTS as $list => $label)
			echo '<a class="subnav__link' . ($list === $this->list ? ' subnav__link--active' : '') . '" href="?' . self::LIST_PARAMETER . '=' . $list . '">'
				. $label . ' (' . $this->counts[$list] . ')</a>';
		echo '</div>';
		parent::render();
	}

	protected function renderHeader(): void
	{
		echo '<tr><th title="Your tick, and the admins who reviewed the change">Reviewed</th>'
			. '<th>Diff</th><th>Open</th>'
			. '<th><label title="Toggle board previews"><input type="checkbox" class="preview-zoom-toggle">🔍</label></th>'
			. '<th>Collection</th>'
			. '<th title="The admin who made the change, or who accepted the proposal">Accepted by</th>'
			. '<th title="Who proposed the change, empty for an admin\'s own change">Original author</th>'
			. '<th>Description</th><th>Date</th></tr>';
	}

	protected function rowClass(array $item): string
	{
		return $this->newSince !== null && $item['accepted'] > $this->newSince && !$this->isAcceptedByMe($item)
			? 'data-table__row--new'
			: '';
	}

	private function isAcceptedByMe(array $item): bool
	{
		return (int) $item['accepted_by_user_id'] === $this->userID;
	}

	public function renderItem(int $index, array $item): void
	{
		$id = (int) $item['id'];
		$reviewers = $this->reviewers[$id] ?? [];
		$reviewedByMe = in_array($this->userID, array_map(fn($reviewer) => (int) $reviewer['id'], $reviewers), true);
		$isProposal = $item['accepted_by_user_id'] === null || (int) $item['user_id'] !== (int) $item['accepted_by_user_id'];

		echo '<td class="sgf-review" id="review-' . $id . '">';
		// what the admin made part of a problem is reviewed by the others
		if ($this->isAcceptedByMe($item))
			echo '<span class="sgf-review__own" title="Your change, the other admins review it"></span>';
		else
			echo '<button type="button" class="sgf-review__tick' . ($reviewedByMe ? ' sgf-review__tick--reviewed' : '') . '"'
				. ' data-sgf-id="' . $id . '" aria-pressed="' . ($reviewedByMe ? 'true' : 'false') . '"'
				. ' title="' . ($reviewedByMe ? 'You reviewed this change, click to take the tick back' : 'Mark as reviewed by you') . '">✓</button>';
		foreach ($reviewers as $reviewer)
			if ((int) $reviewer['id'] !== $this->userID)
				echo self::renderReviewer($reviewer);
		echo '</td>';

		echo '<td>';
		if ($item['previous_version_id'])
			echo '<a class="btn btn--small btn--navigation" id="compare-' . $id . '" href="/editor?sgfID=' . $id . '&diffID=' . (int) $item['previous_version_id'] . '">Diff</a>';
		else
			echo '<span class="btn btn--small btn--navigation btn--disabled" title="The first version of the problem, there is nothing to compare it with">Diff</span>';
		echo '</td>';
		echo '<td><a class="btn btn--small btn--navigation" id="open-' . $id . '" href="/editor?sgfID=' . $id . '">Open</a></td>';

		echo '<td>';
		if ($item['set_connection_id'])
			new TsumegoButton((int) $item['tsumego_id'], (int) $item['set_connection_id'], (int) $item['num'], $item['status'] ?: 'N', 0, (string) $item['sgf'])->render();
		echo '</td>';

		echo '<td>';
		if ($item['set_connection_id'])
			echo '<a class="adminpanel-link" href="/sets/' . (int) $item['set_id'] . '">' . h($item['set_title']) . '</a>';
		else
			echo 'Problem ' . (int) $item['tsumego_id'] . ' (in no collection)';
		echo '</td>';

		echo '<td class="sgf-changes__accepted-by">';
		echo $item['accepted_by_user_id'] === null
			? '<span class="sgf-changes__unknown" title="Accepted before the site recorded who accepted a proposal">unknown</span>'
			: User::renderLink(self::userOf($item, 'accepted_by'));
		echo '</td>';
		echo '<td class="sgf-changes__author">' . ($isProposal ? User::renderLink(self::userOf($item, 'author')) : '') . '</td>';
		echo '<td class="proposal-description" id="description-' . $id . '">' . SgfDescription::render($item['description']) . '</td>';
		echo '<td><time datetime="' . Util::toIso8601($item['accepted']) . '" data-format="datetime"'
			. ($isProposal ? ' title="Proposed ' . h($item['created']) . '"' : '') . '>' . h($item['accepted']) . '</time></td>';
	}

	//The avatar of another admin who reviewed the change, their name and when in the tooltip
	/** @param array<string, mixed> $reviewer Row of SgfReviewTable::reviewersOf */
	private static function renderReviewer(array $reviewer): string
	{
		$avatarUrl = User::avatarOrNull($reviewer);
		if ($avatarUrl === null)
			return '';
		$title = 'Reviewed by ' . $reviewer['display_name'] . ' on ' . $reviewer['reviewed']->format('Y-m-d');
		return '<a class="sgf-review__reviewer" href="/users/view/' . (int) $reviewer['id'] . '" title="' . h($title) . '">'
			. User::avatarImage($avatarUrl, '', true, User::boardUrl($reviewer)) . '</a>';
	}

	private int $userID;

	//The key of LISTS shown
	private string $list;

	/** @var array<string, int> How many changes each of the LISTS has */
	private array $counts = [];

	/** @var array<int, list<array<string, mixed>>> */
	private array $reviewers = [];
}

<?php

namespace App\Utility;

use SetConnection;
use Sgf;
use SgfReview;
use User;

/**
 * The SGF changes of the admin area: every version saved of a problem, the newest first, but the
 * rejected proposals. Each admin ticks the changes of others they checked (SgfReview) and sees who
 * of the others checked them, so they can keep the changes under control without a decision.
 */
class SgfChangesRenderer extends DataTableRenderer
{
	/** The url parameter of the list of the changes the admin did not check yet */
	public const NOT_REVIEWED_PARAMETER = 'not_reviewed';

	/**
	 * @param string|null $newSince When the admin looked the time before (see AdminPage::markViewed),
	 *   what the others saved since then is marked as new
	 */
	public function __construct(array $urlParams, private ?string $newSince = null)
	{
		$this->userID = Auth::getUserID();
		$this->onlyNotReviewed = !empty($urlParams[self::NOT_REVIEWED_PARAMETER]);
		$this->notReviewedCount = self::countNotReviewed($this->userID);
		$this->count = $this->onlyNotReviewed
			? $this->notReviewedCount
			: (int) Util::query('SELECT COUNT(*) AS total FROM sgf WHERE sgf_status_id <> ?', [Sgf::STATUS_REJECTED])[0]['total'];
		parent::__construct($urlParams, 'sgf_changes_page', $this->onlyNotReviewed ? 'SGF changes you did not review' : 'SGF changes');

		$this->data = Util::query("
SELECT
	sgf.id AS id,
	sgf.tsumego_id AS tsumego_id,
	sgf.sgf AS sgf,
	sgf.created AS created,
	sgf.description AS description,
	sgf.sgf_status_id AS sgf_status_id,
	(SELECT COUNT(*) FROM sgf version
		WHERE version.tsumego_id = sgf.tsumego_id AND version.sgf_status_id <> " . Sgf::STATUS_REJECTED . " AND version.id <= sgf.id) AS version,
	(SELECT MAX(previous.id) FROM sgf previous
		WHERE previous.tsumego_id = sgf.tsumego_id AND previous.sgf_status_id = " . Sgf::STATUS_ACCEPTED . " AND previous.id < sgf.id) AS previous_version_id,
	user.id AS user_id,
	user.display_name AS user_display_name,
	user.external_id AS user_external_id,
	user.picture AS user_picture,
	user.avatar_setting_id AS user_avatar_setting_id,
	user.email AS user_email,
	user.rating AS user_rating,
	user.deleted AS user_deleted,
	set_connection.id AS set_connection_id,
	set_connection.num AS num,
	tsumego_status.status AS status,
	CONCAT_WS(' ', `set`.title, `set`.title2) AS set_title
FROM sgf
LEFT JOIN user ON user.id = sgf.user_id
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
WHERE sgf.sgf_status_id <> " . Sgf::STATUS_REJECTED . ($this->onlyNotReviewed ? ' AND ' . self::notReviewedSql() : '') . "
ORDER BY sgf.id DESC
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $this->offset, $this->onlyNotReviewed ? [$this->userID, $this->userID, $this->userID] : [$this->userID]);

		$this->reviewers = SgfReview::reviewersOf(array_map(fn($item) => (int) $item['id'], $this->data));
	}

	/**
	 * The changes of others the admin did not review yet, the rejected proposals are not on the page.
	 */
	public static function countNotReviewed(int $userID): int
	{
		return (int) Util::query('SELECT COUNT(*) AS total FROM sgf WHERE sgf.sgf_status_id <> ? AND ' . self::notReviewedSql(), [Sgf::STATUS_REJECTED, $userID, $userID])[0]['total'];
	}

	/**
	 * The condition of a change of someone else the admin did not tick, the two parameters are the
	 * id of the admin.
	 */
	private static function notReviewedSql(): string
	{
		return 'NOT (sgf.user_id <=> ?) AND NOT EXISTS (SELECT 1 FROM sgf_review WHERE sgf_review.sgf_id = sgf.id AND sgf_review.user_id = ?)';
	}

	public function render(): void
	{
		echo '<div class="sgf-changes__filter" id="sgf-changes-filter">';
		echo $this->onlyNotReviewed
			? '<a href="?">All changes</a> | <strong>Not reviewed by me (' . $this->notReviewedCount . ')</strong>'
			: '<strong>All changes</strong> | <a href="?' . self::NOT_REVIEWED_PARAMETER . '=1">Not reviewed by me (' . $this->notReviewedCount . ')</a>';
		echo '</div>';
		parent::render();
	}

	protected function renderHeader(): void
	{
		echo '<tr><th title="Your tick, and the admins who reviewed the change">Reviewed</th>'
			. '<th><label title="Toggle board previews"><input type="checkbox" class="preview-zoom-toggle">🔍</label></th>'
			. '<th>Problem</th><th>Author</th><th>Description</th><th>Date</th><th></th></tr>';
	}

	protected function rowClass(array $item): string
	{
		return $this->newSince !== null && $item['created'] > $this->newSince && (int) $item['user_id'] !== $this->userID
			? 'data-table__row--new'
			: '';
	}

	public function renderItem(int $index, array $item): void
	{
		$id = (int) $item['id'];
		$reviewers = $this->reviewers[$id] ?? [];
		$reviewedByMe = in_array($this->userID, array_map(fn($reviewer) => (int) $reviewer['id'], $reviewers), true);

		echo '<td class="sgf-review" id="review-' . $id . '">';
		// the own changes are reviewed by the others
		if ((int) $item['user_id'] === $this->userID)
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
		if ($item['set_connection_id'])
			new TsumegoButton((int) $item['tsumego_id'], (int) $item['set_connection_id'], (int) $item['num'], $item['status'] ?: 'N', 0, (string) $item['sgf'])->render();
		echo '</td>';

		echo '<td>';
		if ($item['set_connection_id'])
			echo '<a class="adminpanel-link" href="/' . $item['set_connection_id'] . '">' . h($item['set_title']) . ' - ' . h($item['num']) . '</a>';
		else
			echo 'Problem ' . (int) $item['tsumego_id'] . ' (in no collection)';
		echo ' <a class="sgf-changes__version" href="/sgfs/view/' . (int) $item['tsumego_id'] . '" title="All versions of the problem">v' . (int) $item['version'] . '</a>';
		if ((int) $item['sgf_status_id'] === Sgf::STATUS_PROPOSED)
			echo ' <a class="sgf-changes__proposal" href="/admin/sgf-proposals" title="A proposal waiting for a decision">proposal</a>';
		echo '</td>';

		echo '<td>' . User::renderLink($item) . '</td>';
		echo '<td class="proposal-description" id="description-' . $id . '">' . SgfDescription::render($item['description']) . '</td>';
		echo '<td><time datetime="' . Util::toIso8601($item['created']) . '" data-format="datetime">' . h($item['created']) . '</time></td>';

		$fileName = ($item['set_connection_id'] ? $item['set_title'] . ' ' . $item['num'] : 'problem ' . $item['tsumego_id']) . ' v' . $item['version'] . '.sgf';
		echo '<td class="sgf-changes__actions">';
		echo '<a id="open-' . $id . '" href="/editor?sgfID=' . $id . '">open</a>';
		if ($item['previous_version_id'])
			echo ' | <a id="compare-' . $id . '" href="/editor?sgfID=' . $id . '&diffID=' . (int) $item['previous_version_id'] . '">diff</a>';
		echo ' | <a id="download-' . $id . '" href="/sgf/fetch/' . $id . '" download="' . h($fileName) . '">download</a>';
		echo '</td>';
	}

	/**
	 * The avatar of another admin who reviewed the change, their name and when in the tooltip
	 *
	 * @param array<string, mixed> $reviewer Row of SgfReview::reviewersOf
	 */
	private static function renderReviewer(array $reviewer): string
	{
		$avatarUrl = User::avatarOrNull($reviewer);
		if ($avatarUrl === null)
			return '';
		$title = 'Reviewed by ' . $reviewer['display_name'] . ' on ' . substr((string) $reviewer['reviewed'], 0, 10);
		return '<a class="sgf-review__reviewer" href="/users/view/' . (int) $reviewer['id'] . '" title="' . h($title) . '">'
			. User::avatarImage($avatarUrl, '', true, User::boardUrl($reviewer)) . '</a>';
	}

	private int $userID;
	private bool $onlyNotReviewed;
	private int $notReviewedCount;

	/** @var array<int, list<array<string, mixed>>> */
	private array $reviewers = [];
}

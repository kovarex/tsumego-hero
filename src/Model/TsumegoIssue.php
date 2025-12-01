<?php

App::uses('TsumegoComment', 'Model');
App::uses('User', 'Model');
App::uses('TsumegosController', 'Controller');

/**
 * TsumegoIssue Model
 *
 * Represents an issue/problem report for a tsumego, which can contain multiple comments.
 *
 * Table: tsumego_issue
 * Columns:
 *   - id (INT, PK, AUTO_INCREMENT)
 *   - tsumego_issue_status_id (INT, FK -> tsumego_issue_status)
 *   - tsumego_id (INT, FK -> tsumego)
 *   - user_id (INT, FK -> user) - author of the issue
 *   - created (DATETIME)
 *   - deleted (TINYINT) - soft delete flag
 *
 * Statuses (tsumego_issue_status table):
 *   - 1 = opened
 *   - 2 = closed
 *
 */
class TsumegoIssue extends AppModel
{
	public $useTable = 'tsumego_issue';
	public $actsAs = ['Containable'];

	public $belongsTo = [
		'Tsumego',
		'User',
	];

	public $hasMany = [
		'TsumegoComment' => [
			'foreignKey' => 'tsumego_issue_id',
			'dependent' => true,
		],
	];

	public static int $OPENED_STATUS = 1;
	public static int $CLOSED_STATUS = 2;

	/**
	 * Get the human-readable name for a status.
	 *
	 * @param int $status The status ID
	 * @return string The status name
	 * @throws \Exception If the status is invalid
	 */
	public static function statusName(int $status): string
	{
		if ($status === self::$OPENED_STATUS)
			return 'Opened';
		if ($status === self::$CLOSED_STATUS)
			return 'Closed';
		throw new \Exception("Invalid issue status: $status");
	}

	/**
	 * Delete an issue if it has no non-deleted comments.
	 *
	 * Should be called after a comment is deleted or removed from an issue
	 * to clean up empty issues automatically.
	 *
	 * @param int $issueId The issue ID to check and potentially delete
	 * @return bool True if issue was deleted, false if it still has comments or doesn't exist
	 */
	public function deleteIfEmpty(int $issueId): bool
	{
		/** @var TsumegoComment $commentModel */
		$commentModel = ClassRegistry::init('TsumegoComment');

		$count = $commentModel->find('count', [
			'conditions' => [
				'tsumego_issue_id' => $issueId,
				'deleted' => false,
			],
		]);

		if ($count === 0)
			return $this->delete($issueId);

		return false;
	}

	/**
	 * Find issues for the global issues index page.
	 *
	 * Uses a single optimized SQL query with:
	 * - JOINs to user, tsumego, set_connection, set tables
	 * - Derived table (tc_agg) to pre-aggregate comment counts and find first comment
	 * - Additional JOIN to get first comment message
	 *
	 * This avoids correlated subqueries which would run per-row.
	 *
	 * @param string $status 'opened', 'closed', or 'all' (default: 'opened')
	 * @param int $limit Number of results per page (default: 20)
	 * @param int $page Page number for pagination (default: 1)
	 * @return array Formatted issues ready for the view
	 */
	public function findForIndex(string $status = 'opened', int $limit = 20, int $page = 1): array
	{
		$offset = ($page - 1) * $limit;

		// Build status condition
		$statusCondition = '';
		if ($status === 'opened')
			$statusCondition = 'AND TsumegoIssue.tsumego_issue_status_id = ' . self::$OPENED_STATUS;
		elseif ($status === 'closed')
			$statusCondition = 'AND TsumegoIssue.tsumego_issue_status_id = ' . self::$CLOSED_STATUS;

		// Optimized query using derived table instead of correlated subqueries
		// The derived table (tc_agg) computes aggregates ONCE, then joins
		// We use another derived table (PrimarySet) to handle the 1:N set_connection relation
		$sql = "
			SELECT
				TsumegoIssue.id,
				TsumegoIssue.tsumego_issue_status_id,
				TsumegoIssue.tsumego_id,
				TsumegoIssue.user_id,
				TsumegoIssue.created,
				User.id AS author_id,
				User.name AS author_name,
				PrimarySet.num AS tsumego_num,
				`Set`.id AS set_id,
				`Set`.title AS set_title,
				tc_first.message AS first_message,
				COALESCE(tc_agg.comment_count, 0) AS comment_count
			FROM tsumego_issue TsumegoIssue
			LEFT JOIN user User ON User.id = TsumegoIssue.user_id
			LEFT JOIN tsumego Tsumego ON Tsumego.id = TsumegoIssue.tsumego_id
			LEFT JOIN (
				SELECT tsumego_id, MIN(id) AS min_id
				FROM set_connection
				GROUP BY tsumego_id
			) FirstSetConn ON FirstSetConn.tsumego_id = Tsumego.id
			LEFT JOIN set_connection PrimarySet ON PrimarySet.id = FirstSetConn.min_id
			LEFT JOIN `set` `Set` ON `Set`.id = PrimarySet.set_id
			LEFT JOIN (
				SELECT
					tsumego_issue_id,
					MIN(created) AS first_comment_created,
					COUNT(*) AS comment_count
				FROM tsumego_comment
				WHERE deleted = 0
				GROUP BY tsumego_issue_id
			) tc_agg ON tc_agg.tsumego_issue_id = TsumegoIssue.id
			LEFT JOIN tsumego_comment tc_first
				ON tc_first.tsumego_issue_id = TsumegoIssue.id
				AND tc_first.created = tc_agg.first_comment_created
				AND tc_first.deleted = 0
			WHERE TsumegoIssue.deleted = 0
			{$statusCondition}
			ORDER BY TsumegoIssue.created DESC
			LIMIT {$limit} OFFSET {$offset}
		";

		$rawResults = $this->query($sql);

		$processed = [];
		foreach ($rawResults as $row)
		{
			// CakePHP query() returns data grouped by table aliases
			// Aliased columns (AS x) go into their source table's array
			$issueData = $row['TsumegoIssue'] ?? [];
			$userData = $row['User'] ?? [];
			$setData = $row['Set'] ?? [];
			$primarySetData = $row['PrimarySet'] ?? [];
			$tcFirstData = $row['tc_first'] ?? [];

			// COALESCE results go in [0] array
			$virtualData = $row[0] ?? [];

			// tsumego_id from the issue is authoritative
			$tsumegoId = $issueData['tsumego_id'] ?? null;

			$processed[] = [
				'TsumegoIssue' => [
					'id' => $issueData['id'] ?? null,
					'tsumego_issue_status_id' => $issueData['tsumego_issue_status_id'] ?? null,
					'tsumego_id' => $tsumegoId,
					'user_id' => $issueData['user_id'] ?? null,
					'created' => $issueData['created'] ?? null,
				],
				'Author' => [
					'id' => $userData['author_id'] ?? null,
					'name' => $userData['author_name'] ?? '[deleted user]',
				],
				'Tsumego' => $tsumegoId ? ['id' => $tsumegoId] : null,
				'Set' => !empty($setData['set_id']) ? ['id' => $setData['set_id'], 'title' => $setData['set_title'] ?? null] : null,
				'TsumegoNum' => $primarySetData['tsumego_num'] ?? null,
				'FirstComment' => !empty($tcFirstData['first_message']) ? ['message' => $tcFirstData['first_message']] : null,
				'CommentCount' => (int) ($virtualData['comment_count'] ?? 0),
			];
		}

		return $processed;
	}

	/**
	 * Get counts for the issues index tabs.
	 *
	 * @return array{open: int, closed: int}
	 */
	public function getIndexCounts(): array
	{
		return [
			'open' => $this->find('count', [
				'conditions' => [
					'tsumego_issue_status_id' => self::$OPENED_STATUS,
					'deleted' => false,
				],
			]),
			'closed' => $this->find('count', [
				'conditions' => [
					'tsumego_issue_status_id' => self::$CLOSED_STATUS,
					'deleted' => false,
				],
			]),
		];
	}

	/**
	 * Get comment section counts for a specific tsumego.
	 *
	 * Used for updating the comment tabs (ALL/COMMENTS/ISSUES) via htmx OOB.
	 *
	 * @param int $tsumegoId The tsumego ID
	 * @return array{total: int, comments: int, issues: int, openIssues: int}
	 */
	public function getCommentSectionCounts(int $tsumegoId): array
	{
		$TsumegoComment = ClassRegistry::init('TsumegoComment');

		// Count standalone comments (not in any issue)
		$commentCount = $TsumegoComment->find('count', [
			'conditions' => [
				'TsumegoComment.tsumego_id' => $tsumegoId,
				'TsumegoComment.tsumego_issue_id IS NULL',
				'TsumegoComment.deleted' => false,
			],
		]);

		// Count issues for this tsumego
		$issueCount = $this->find('count', [
			'conditions' => [
				'TsumegoIssue.tsumego_id' => $tsumegoId,
			],
		]);

		// Count open issues for this tsumego
		$openIssueCount = $this->find('count', [
			'conditions' => [
				'TsumegoIssue.tsumego_id' => $tsumegoId,
				'TsumegoIssue.tsumego_issue_status_id' => self::$OPENED_STATUS,
			],
		]);

		return [
			'total' => $commentCount + $issueCount,
			'comments' => $commentCount,
			'issues' => $issueCount,
			'openIssues' => $openIssueCount,
		];
	}
}

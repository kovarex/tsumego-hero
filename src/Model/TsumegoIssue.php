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
	 * Returns issues with all their comments, formatted to be compatible with
	 * the TsumegoIssues/issue.ctp element (same format as Tsumego::loadCommentsData).
	 *
	 * @param string $status 'opened', 'closed', or 'all' (default: 'opened')
	 * @param int $limit Number of results per page (default: 20)
	 * @param int $page Page number for pagination (default: 1)
	 * @return array Formatted issues ready for the view (compatible with issue.ctp element)
	 */
	public function findForIndex(string $status = 'opened', int $limit = 20, int $page = 1): array
	{
		$offset = ($page - 1) * $limit;

		// Build status condition
		$statusCondition = '';
		if ($status === 'opened')
			$statusCondition = 'AND tsumego_issue_status_id = ' . self::$OPENED_STATUS;
		elseif ($status === 'closed')
			$statusCondition = 'AND tsumego_issue_status_id = ' . self::$CLOSED_STATUS;

		// Query 1: Get paginated issue IDs
		$idsSql = "
			SELECT id
			FROM tsumego_issue
			WHERE deleted = 0
			{$statusCondition}
			ORDER BY created DESC, id DESC
			LIMIT {$limit} OFFSET {$offset}
		";
		$idsResult = $this->query($idsSql) ?: [];
		$issueIds = array_column(array_column($idsResult, 'tsumego_issue'), 'id');

		if (empty($issueIds))
			return [];

		$issueIdsStr = implode(',', array_map('intval', $issueIds));

		// Query 2: Get full data for those issues only
		$sql = "
			SELECT
				ti.id AS issue_id,
				ti.tsumego_issue_status_id,
				ti.tsumego_id,
				ti.user_id AS issue_user_id,
				ti.created AS issue_created,
				u.name AS issue_author_name,
				tc.id AS comment_id,
				tc.message AS comment_text,
				tc.created AS comment_created,
				tc.user_id AS comment_user_id,
				cu.id AS comment_author_id,
				cu.name AS comment_author_name,
				cu.external_id AS comment_author_external_id,
				cu.picture AS comment_author_picture,
				cu.rating AS comment_author_rating,
				cu.isAdmin AS comment_author_isAdmin,
				s.id AS set_id,
				s.title AS set_title,
				sc.num AS tsumego_num
			FROM tsumego_issue ti
			LEFT JOIN user u ON u.id = ti.user_id
			LEFT JOIN tsumego_comment tc ON tc.tsumego_issue_id = ti.id AND tc.deleted = 0
			LEFT JOIN user cu ON cu.id = tc.user_id
			LEFT JOIN (
				SELECT sc1.tsumego_id, sc1.num, sc1.set_id
				FROM set_connection sc1
				INNER JOIN (
					SELECT tsumego_id, MIN(id) as min_id
					FROM set_connection
					GROUP BY tsumego_id
				) sc2 ON sc1.id = sc2.min_id
			) sc ON sc.tsumego_id = ti.tsumego_id
			LEFT JOIN `set` s ON s.id = sc.set_id
			WHERE ti.id IN ({$issueIdsStr})
			ORDER BY ti.created DESC, ti.id DESC, tc.created ASC
		";

		$rows = $this->query($sql) ?: [];

		// Group results by issue, maintaining order from first query
		$issuesMap = [];

		foreach ($rows as $row)
		{
			$issueId = $row['ti']['issue_id'];

			if (!isset($issuesMap[$issueId]))
			{
				$issuesMap[$issueId] = [
					'issue' => [
						'id' => $issueId,
						'tsumego_issue_status_id' => $row['ti']['tsumego_issue_status_id'],
						'tsumego_id' => $row['ti']['tsumego_id'],
						'user_id' => $row['ti']['issue_user_id'],
						'created' => $row['ti']['issue_created'],
					],
					'comments' => [],
					'author' => $row['u']['issue_author_name']
						? ['name' => $row['u']['issue_author_name']]
						: ['name' => '[deleted user]'],
					'tsumegoId' => $row['ti']['tsumego_id'],
					'Set' => $row['s']['set_id'] ? [
						'id' => $row['s']['set_id'],
						'title' => $row['s']['set_title'],
					] : null,
					'TsumegoNum' => $row['sc']['tsumego_num'] ?? null,
				];
			}

			// Add comment if exists
			if (!empty($row['tc']['comment_id']))
			{
				$issuesMap[$issueId]['comments'][] = [
					'id' => $row['tc']['comment_id'],
					'message' => $row['tc']['comment_text'],
					'created' => $row['tc']['comment_created'],
					'user_id' => $row['tc']['comment_user_id'],
					'user' => $row['cu']['comment_author_name']
						? [
							'id' => $row['cu']['comment_author_id'],
							'name' => $row['cu']['comment_author_name'],
							'external_id' => $row['cu']['comment_author_external_id'],
							'picture' => $row['cu']['comment_author_picture'],
							'rating' => $row['cu']['comment_author_rating'],
							'isAdmin' => $row['cu']['comment_author_isAdmin'],
						]
						: ['name' => '[deleted user]', 'isAdmin' => 0],
				];
			}
		}

		// Build result in original order (from first query)
		$result = [];
		foreach ($issueIds as $index => $issueId)
			if (isset($issuesMap[$issueId]))
			{
				$issueData = $issuesMap[$issueId];
				$issueData['issueNumber'] = $offset + $index + 1;
				$result[] = $issueData;
			}

		return $result;
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

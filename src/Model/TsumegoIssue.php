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
 *   - 3 = reviewed
 *
 * Custom find types:
 * - find('withComments', ['conditions' => ['tsumego_id' => $id]]) - Returns issues with loaded author and comments
 */
class TsumegoIssue extends AppModel
{
	public $useTable = 'tsumego_issue';

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
	public static int $REVIEW_STATUS = 3;

	/**
	 * Custom find types available on this model.
	 *
	 * @var array<string, bool>
	 */
	public $findMethods = ['withComments' => true];

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
		if ($status === self::$REVIEW_STATUS)
			return 'Reviewed';
		throw new \Exception("Invalid issue status: $status");
	}

	/**
	 * Custom find type: withComments
	 *
	 * Loads issues with their author and comments (each comment with its user).
	 * Usage: $this->TsumegoIssue->find('withComments', ['conditions' => ['tsumego_id' => $id]])
	 *
	 * @param string $state 'before' or 'after'
	 * @param array $query Query parameters
	 * @param array $results Results from database (only in 'after' state)
	 * @return array Modified query (before) or processed results with _coordinates key (after)
	 */
	protected function _findWithComments(string $state, array $query, array $results = []): array
	{
		if ($state === 'before') {
			// Set default ordering if not specified
			if (empty($query['order']))
				$query['order'] = 'TsumegoIssue.created DESC';

			return $query;
		}

		// 'after' state - process results
		$tsumegoId = $query['conditions']['tsumego_id'] ?? null;
		$processedIssues = [];
		$allCoordinates = [];

		foreach ($results as $issueRaw) {
			$issue = $issueRaw['TsumegoIssue'];

			// Load author
			/** @var User $userModel */
			$userModel = ClassRegistry::init('User');
			$author = $userModel->findById($issue['user_id']);
			$issue['author'] = $author ? $author['User'] : ['name' => '[deleted user]'];

			// Load comments for this issue
			$commentsData = $this->loadCommentsForIssue($tsumegoId, $issue['id']);
			$issue['comments'] = $commentsData['comments'];
			$allCoordinates = array_merge($allCoordinates, $commentsData['coordinates']);

			$processedIssues[] = $issue;
		}

		// Store coordinates in a special key that can be extracted later
		$processedIssues['_coordinates'] = $allCoordinates;

		return $processedIssues;
	}

	/**
	 * Load comments for a specific issue (with user data and processed messages).
	 *
	 * @param int|null $tsumegoId
	 * @param int $issueId
	 * @return array{comments: array, coordinates: array}
	 */
	public function loadCommentsForIssue(?int $tsumegoId, int $issueId): array
	{
		/** @var TsumegoComment $commentModel */
		$commentModel = ClassRegistry::init('TsumegoComment');

		$commentsRaw = $commentModel->find('all', [
			'conditions' => [
				'tsumego_id' => $tsumegoId,
				'tsumego_issue_id' => $issueId,
				'deleted' => false,
			],
			'order' => 'created ASC',
		]) ?: [];

		/** @var User $userModel */
		$userModel = ClassRegistry::init('User');

		$comments = [];
		$coordinates = [];
		foreach ($commentsRaw as $index => $commentRaw) {
			$comment = $commentRaw['TsumegoComment'];

			// Load user
			$user = $userModel->findById($comment['user_id']);
			$comment['user'] = $user ? $user['User'] : ['name' => '[deleted user]', 'admin' => 0];

			// Process message for coordinates
			$array = TsumegosController::commentCoordinates($comment['message'], $index + 1, true);
			$comment['message'] = $array[0];
			if (!empty($array[1]))
				$coordinates[] = $array[1];

			$comments[] = $comment;
		}

		return [
			'comments' => $comments,
			'coordinates' => $coordinates,
		];
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
}

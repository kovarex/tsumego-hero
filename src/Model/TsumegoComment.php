<?php

App::uses('TsumegosController', 'Controller');

/**
 * TsumegoComment Model
 *
 * Represents a comment on a tsumego problem, optionally linked to a TsumegoIssue.
 *
 * Table: tsumego_comment
 * Columns:
 *   - id (INT, PK, AUTO_INCREMENT)
 *   - tsumego_id (INT, FK -> tsumego)
 *   - tsumego_issue_id (INT NULL, FK -> tsumego_issue) - NULL means standalone comment
 *   - message (VARCHAR(2048))
 *   - created (DATETIME)
 *   - user_id (INT, FK -> user)
 *   - position (VARCHAR(300) NULL) - board position for the comment
 *   - deleted (BOOL, default 0)
 */
class TsumegoComment extends AppModel
{
	public $useTable = 'tsumego_comment';

	public $belongsTo = [
		'Tsumego',
		'TsumegoIssue',
		'User',
	];

	/**
	 * Load standalone comments (not associated with any issue) for a tsumego.
	 *
	 * @param int $tsumegoId
	 * @return array{comments: array, coordinates: array}
	 */
	public function loadStandaloneComments(int $tsumegoId): array
	{
		$commentsRaw = $this->find('all', [
			'conditions' => ['tsumego_id' => $tsumegoId, 'tsumego_issue_id' => null],
			'order' => 'created ASC',
		]) ?: [];

		/** @var User $userModel */
		$userModel = ClassRegistry::init('User');

		$comments = [];
		$coordinates = [];
		foreach ($commentsRaw as $index => $commentRaw)
		{
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
}

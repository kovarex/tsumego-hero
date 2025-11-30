<?php

App::uses('TsumegoIssue', 'Model');
App::uses('TsumegoComment', 'Model');
App::uses('User', 'Model');
App::uses('TsumegosController', 'Controller');

class Tsumego extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] = 'tsumego';
		parent::__construct($id, $table, $ds);
	}

	public $validate = [
		'title' => [
			'rule' => 'notBlank',
		],
		'sgf1' => [
			'rule' => 'notBlank',
		],
	];

	/**
	 * Load all comments and issues data for a tsumego.
	 *
	 * Returns issues with their comments, standalone comments (not associated with any issue),
	 * and parsed Go coordinates from comment messages.
	 *
	 * @param int $tsumegoId The tsumego ID to load comments for
	 * @return array{issues: array, plainComments: array, coordinates: array}
	 */
	public function loadCommentsData(int $tsumegoId): array
	{
		/** @var TsumegoIssue $issueModel */
		$issueModel = ClassRegistry::init('TsumegoIssue');
		/** @var TsumegoComment $commentModel */
		$commentModel = ClassRegistry::init('TsumegoComment');

		// Counter only increments when coordinates are found (matches $fn1 in play.ctp)
		$counter = 1;
		$allCoordinates = [];

		// Load issues with their author using Containable (exclude deleted issues)
		$issuesRaw = $issueModel->find('all', [
			'conditions' => [
				'tsumego_id' => $tsumegoId,
				'deleted' => false,
			],
			'order' => 'TsumegoIssue.created DESC',
			'contain' => ['User'],
		]) ?: [];

		$issues = [];
		foreach ($issuesRaw as $issueRaw)
		{
			$issue = $issueRaw['TsumegoIssue'];

			// Author loaded via Containable
			$issue['author'] = !empty($issueRaw['User']) ? $issueRaw['User'] : ['name' => '[deleted user]'];

			// Load comments for this issue with User via Containable
			$commentsRaw = $commentModel->find('all', [
				'conditions' => [
					'TsumegoComment.tsumego_id' => $tsumegoId,
					'TsumegoComment.tsumego_issue_id' => $issue['id'],
					'TsumegoComment.deleted' => false,
				],
				'order' => 'TsumegoComment.created ASC',
				'contain' => ['User'],
			]) ?: [];

			$issueComments = [];
			foreach ($commentsRaw as $commentRaw)
			{
				$comment = $commentRaw['TsumegoComment'];
				// User loaded via Containable
				$comment['user'] = !empty($commentRaw['User']) ? $commentRaw['User'] : ['name' => '[deleted user]', 'isAdmin' => 0];

				// Process message for coordinates with global counter
				$array = TsumegosController::commentCoordinates($comment['message'], $counter, true);
				$comment['message'] = $array[0];
				if (!empty($array[1]))
				{
					$allCoordinates[] = $array[1];
					$counter++; // Only increment when coordinates were added
				}

				$issueComments[] = $comment;
			}
			$issue['comments'] = $issueComments;
			$issues[] = $issue;
		}

		// Load standalone comments (not associated with any issue) with User via Containable
		$plainCommentsRaw = $commentModel->find('all', [
			'conditions' => [
				'TsumegoComment.tsumego_id' => $tsumegoId,
				'TsumegoComment.tsumego_issue_id' => null,
				'TsumegoComment.deleted' => false,
			],
			'order' => 'TsumegoComment.created ASC',
			'contain' => ['User'],
		]) ?: [];

		$plainComments = [];
		foreach ($plainCommentsRaw as $commentRaw)
		{
			$comment = $commentRaw['TsumegoComment'];
			// User loaded via Containable
			$comment['user'] = !empty($commentRaw['User']) ? $commentRaw['User'] : ['name' => '[deleted user]', 'isAdmin' => 0];

			// Process message for coordinates with global counter
			$array = TsumegosController::commentCoordinates($comment['message'], $counter, true);
			$comment['message'] = $array[0];
			if (!empty($array[1]))
			{
				$allCoordinates[] = $array[1];
				$counter++; // Only increment when coordinates were added
			}

			$plainComments[] = $comment;
		}

		return [
			'issues' => $issues,
			'plainComments' => $plainComments,
			'coordinates' => $allCoordinates,
		];
	}
}

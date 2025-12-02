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

		// Query 1: Load all issues with their authors
		$issuesRaw = $issueModel->find('all', [
			'conditions' => [
				'tsumego_id' => $tsumegoId,
				'deleted' => false,
			],
			'order' => 'TsumegoIssue.created DESC',
			'contain' => ['User'],
		]) ?: [];

		// Collect issue IDs for batch comment loading
		$issueIds = [];
		$issuesMap = [];
		foreach ($issuesRaw as $issueRaw)
		{
			$issue = $issueRaw['TsumegoIssue'];
			$issueIds[] = $issue['id'];
			$issue['author'] = !empty($issueRaw['User']) ? $issueRaw['User'] : ['name' => '[deleted user]'];
			$issue['comments'] = []; // Will be filled from batch query
			$issuesMap[$issue['id']] = $issue;
		}

		// Query 2: Load ALL comments for ALL issues in one query
		if (!empty($issueIds))
		{
			$issueCommentsRaw = $commentModel->find('all', [
				'conditions' => [
					'TsumegoComment.tsumego_issue_id' => $issueIds,
					'TsumegoComment.deleted' => false,
				],
				'order' => 'TsumegoComment.created ASC',
				'contain' => ['User'],
			]) ?: [];

			// Group comments by issue_id
			foreach ($issueCommentsRaw as $commentRaw)
			{
				$comment = $commentRaw['TsumegoComment'];
				$issueId = $comment['tsumego_issue_id'];
				$comment['user'] = !empty($commentRaw['User']) ? $commentRaw['User'] : ['name' => '[deleted user]', 'isAdmin' => 0];

				// Process message for coordinates with global counter
				$array = TsumegosController::commentCoordinates($comment['message'], $counter, true);
				$comment['message'] = $array[0];
				if (!empty($array[1]))
				{
					$allCoordinates[] = $array[1];
					$counter++;
				}

				$issuesMap[$issueId]['comments'][] = $comment;
			}
		}

		// Convert map back to ordered array (preserves original DESC order)
		$issues = [];
		foreach ($issuesRaw as $issueRaw)
		{
			$issueId = $issueRaw['TsumegoIssue']['id'];
			if (isset($issuesMap[$issueId]))
				$issues[] = $issuesMap[$issueId];
		}

		// Query 3: Load standalone comments (not associated with any issue)
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
			$comment['user'] = !empty($commentRaw['User']) ? $commentRaw['User'] : ['name' => '[deleted user]', 'isAdmin' => 0];

			// Process message for coordinates with global counter
			$array = TsumegosController::commentCoordinates($comment['message'], $counter, true);
			$comment['message'] = $array[0];
			if (!empty($array[1]))
			{
				$allCoordinates[] = $array[1];
				$counter++;
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

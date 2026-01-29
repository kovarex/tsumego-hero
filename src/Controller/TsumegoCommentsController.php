<?php

/**
 * Controller for managing tsumego comments (CRUD operations).
 *
 * Handles adding and deleting comments on tsumego problems.
 * Comments can be standalone or associated with a TsumegoIssue.

 */
class TsumegoCommentsController extends AppController
{
	/**
	 * Add a new comment to a tsumego.
	 *
	 * Expects POST data with:
	 * - Comment.tsumego_id: int - The tsumego to comment on
	 * - Comment.message: string - The comment text
	 * - Comment.tsumego_issue_id: int|null - Optional issue to attach to
	 * - Comment.position: string|null - Optional board position
	 * - Comment.redirect: string - URL to redirect after success
	 *
	 * @return CakeResponse|null
	 */
	public function add()
	{
		if (!$this->request->is('post'))
			throw new MethodNotAllowedException();

		if (!Auth::isLoggedIn())
		{
			$this->response->statusCode(401);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'You must be logged in to comment']));
			return $this->response;
		}

		$input = json_decode($this->request->input(), true);

		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$comment = [
			'tsumego_id' => $input['tsumego_id'],
			'message' => $input['text'],
			'tsumego_issue_id' => $input['issue_id'] ?? null,
			'position' => $input['position'] ?? null,
			'user_id' => Auth::getUserID(),
		];

		$TsumegoComment->create();
		if (!$TsumegoComment->save($comment))
		{
			$this->response->statusCode(422);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Failed to add comment']));
			return $this->response;
		}

		// Get saved comment with user data
		$savedComment = $TsumegoComment->find('first', [
			'conditions' => ['TsumegoComment.id' => $TsumegoComment->id],
			'contain' => ['User']
		]);

		$this->response->type('json');
		$this->response->body(json_encode([
			'id' => $savedComment['TsumegoComment']['id'],
			'text' => $savedComment['TsumegoComment']['message'],
			'user_id' => $savedComment['TsumegoComment']['user_id'],
			'user_name' => $savedComment['User']['name'] ?? null,
			'user_picture' => $savedComment['User']['picture'] ?? null,
			'user_rating' => $savedComment['User']['rating'] ?? null,
			'user_external_id' => $savedComment['User']['external_id'] ?? null,
			'isAdmin' => isset($savedComment['User']['isAdmin']) && $savedComment['User']['isAdmin'] ? true : false,
			'created' => $savedComment['TsumegoComment']['created'],
			'position' => $savedComment['TsumegoComment']['position'],
		]));
		return $this->response;
	}

	/**
	 * Delete a comment (soft delete).
	 *
	 * Only the comment author or an admin can delete a comment.
	 * If this was the last comment in an issue, the issue is also deleted.
	 *
	 * @param int $id Comment ID to delete
	 * @return CakeResponse|null
	 */
	public function delete($id)
	{
		error_log("[TsumegoCommentsController::delete] Called with ID: $id");

		if (!$this->request->is('post'))
		{
			error_log("[TsumegoCommentsController::delete] Not a POST request, method: " . $this->request->method());
			throw new MethodNotAllowedException();
		}

		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$comment = $TsumegoComment->findById($id);

		if (!$comment)
		{
			error_log("[TsumegoCommentsController::delete] Comment not found: $id");
			$this->response->statusCode(404);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Comment not found']));
			return $this->response;
		}

		// Only admin or comment author can delete
		$isOwner = $comment['TsumegoComment']['user_id'] === Auth::getUserID();
		error_log("[TsumegoCommentsController::delete] User ID: " . Auth::getUserID() . ", Comment owner: " . $comment['TsumegoComment']['user_id'] . ", Is owner: " . ($isOwner ? 'yes' : 'no'));

		if (!Auth::isAdmin() && !$isOwner)
		{
			error_log("[TsumegoCommentsController::delete] Unauthorized - not admin and not owner");
			$this->response->statusCode(403);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'You are not authorized to delete this comment']));
			return $this->response;
		}

		// Remember the issue ID before deleting
		$issueId = $comment['TsumegoComment']['tsumego_issue_id'];

		// Soft delete
		$TsumegoComment->id = $id;
		$saveResult = $TsumegoComment->saveField('deleted', true);
		error_log("[TsumegoCommentsController::delete] Save result: " . ($saveResult ? 'success' : 'failed'));

		if (!$saveResult)
		{
			error_log("[TsumegoCommentsController::delete] Failed to save deleted flag");
			$this->response->statusCode(500);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Failed to delete comment']));
			return $this->response;
		}

		// If comment was part of an issue, check if issue is now empty and delete it
		if (!empty($issueId))
		{
			$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
			$TsumegoIssue->deleteIfEmpty($issueId);
			error_log("[TsumegoCommentsController::delete] Checked if issue $issueId is empty");
		}

		error_log("[TsumegoCommentsController::delete] Delete successful");
		$this->response->type('json');
		$this->response->body(json_encode(['success' => true]));
		return $this->response;
	}

	/**
	 * Get comments data for a tsumego
	 *
	 * Returns issues and standalone comments in the same format as initial SSR data.
	 *
	 * @param int $tsumegoId The tsumego ID
	 * @return CakeResponse
	 */
	public function index($tsumegoId)
	{
		if (!$this->request->is('get'))
			throw new MethodNotAllowedException();

		$TsumegoIssue = ClassRegistry::init('TsumegoIssue');

		// Load issues with comments
		$issues = $TsumegoIssue->find('all', [
			'conditions' => ['TsumegoIssue.tsumego_id' => $tsumegoId],
			'contain' => [
				'TsumegoComment' => [
					'conditions' => ['TsumegoComment.deleted' => 0],
					'User'
				],
				'User'
			],
			'order' => 'TsumegoIssue.created DESC'
		]);

		// Load standalone comments
		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$plainComments = $TsumegoComment->find('all', [
			'conditions' => [
				'TsumegoComment.tsumego_id' => $tsumegoId,
				'TsumegoComment.tsumego_issue_id' => null,
				'TsumegoComment.deleted' => 0
			],
			'contain' => ['User'],
			'order' => 'TsumegoComment.created DESC'
		]);

		$issuesJson = [];
		foreach ($issues as $issue)
		{
			$comments = [];
			foreach ($issue['TsumegoComment'] as $comment)
				$comments[] = [
					'id' => $comment['id'],
					'text' => $comment['message'],
					'user_id' => $comment['user_id'],
					'user_name' => $comment['User']['name'] ?? null,
					'user_picture' => $comment['User']['picture'] ?? null,
					'user_rating' => $comment['User']['rating'] ?? null,
					'user_external_id' => $comment['User']['externalId'] ?? null,
					'isAdmin' => isset($comment['User']) && $comment['User']['isAdmin'] ? true : false,
					'created' => $comment['created'],
					'position' => $comment['position'],
				];


			$issuesJson[] = [
				'id' => $issue['TsumegoIssue']['id'],
				'tsumego_issue_status_id' => $issue['TsumegoIssue']['tsumego_issue_status_id'],
				'created' => $issue['TsumegoIssue']['created'],
				'user_id' => $issue['TsumegoIssue']['user_id'],
				'user_name' => $issue['User']['name'] ?? null,
				'user_picture' => $issue['User']['picture'] ?? null,
				'user_rating' => $issue['User']['rating'] ?? null,
				'user_external_id' => $issue['User']['externalId'] ?? null,
				'isAdmin' => isset($issue['User']) && $issue['User']['isAdmin'] ? true : false,
				'comments' => $comments,
			];
		}

		$standaloneJson = [];
		foreach ($plainComments as $comment)
			$standaloneJson[] = [
				'id' => $comment['TsumegoComment']['id'],
				'text' => $comment['TsumegoComment']['message'],
				'user_id' => $comment['TsumegoComment']['user_id'],
				'user_name' => $comment['User']['name'] ?? null,
				'user_picture' => $comment['User']['picture'] ?? null,
				'user_rating' => $comment['User']['rating'] ?? null,
				'user_external_id' => $comment['User']['externalId'] ?? null,
				'isAdmin' => isset($comment['User']) && $comment['User']['isAdmin'] ? true : false,
				'created' => $comment['TsumegoComment']['created'],
				'position' => $comment['TsumegoComment']['position'],
			];

		$counts = $TsumegoIssue->getCommentSectionCounts($tsumegoId);

		$this->response->type('json');
		$this->response->body(json_encode([
			'issues' => $issuesJson,
			'standalone' => $standaloneJson,
			'counts' => $counts,
		]));
		return $this->response;
	}
}

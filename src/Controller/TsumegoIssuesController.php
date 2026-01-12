<?php

App::uses('TsumegoIssue', 'Model');

/**
 * Controller for managing tsumego issues.
 *
 * Issues are reports about problems with tsumego solutions (missing moves, wrong answers, etc.).
 * Each issue contains one or more comments discussing the problem.
 */
class TsumegoIssuesController extends AppController
{
	/**
	 * API endpoint to fetch issues list with pagination and filtering.
	 *
	 * Query params:
	 * - status: 'opened', 'closed', 'all' (default: 'opened')
	 * - page: int (for pagination)
	 *
	 * @return CakeResponse|null
	 */
	public function api()
	{
		$this->loadModel('TsumegoIssue');

		$statusFilter = $this->request->query('status') ?: 'opened';
		$page = (int) ($this->request->query('page') ?: 1);
		$perPage = 20;

		$issues = $this->TsumegoIssue->findForIndex($statusFilter, $perPage, $page);
		$counts = $this->TsumegoIssue->getIndexCounts();

		// Debug log
		error_log("[API] statusFilter=$statusFilter, page=$page, issues_count=" . count($issues));

		$totalCount = match ($statusFilter)
		{
			'opened' => $counts['open'],
			'closed' => $counts['closed'],
			default => $counts['open'] + $counts['closed'],
		};
		$totalPages = (int) ceil($totalCount / $perPage);

		$this->response->type('json');
		$this->response->body(json_encode([
			'issues' => $issues,
			'counts' => $counts,
			'totalPages' => $totalPages,
			'currentPage' => $page,
		]));
		return $this->response;
	}

	/**
	 * List all issues page
	 *
	 * Query params:
	 * - status: 'opened', 'closed', 'all' (default: 'opened')
	 * - page: int (for pagination)
	 *
	 * React app fetches all data via /api endpoint, PHP just provides initial URL state.
	 *
	 * @return void
	 */
	public function index()
	{
		$this->set('_title', 'Tsumego Hero - Issues');
		$this->set('_page', 'issues');

		// Get filter and pagination params from URL (React uses these for initial state)
		$statusFilter = $this->request->query('status') ?: 'opened';
		$page = (int) ($this->request->query('page') ?: 1);

		$this->set(compact('statusFilter'));
		$this->set('currentPage', $page);
	}

	/**
	 * Create a new issue with an initial comment.
	 *
	 * POST data:
	 * - Issue.tsumego_id: int
	 * - Issue.message: string (first comment)
	 * - Issue.position: string|null (board position for first comment)
	 *
	 * @return CakeResponse|null
	 */
	public function create()
	{
		if (!$this->request->is('post'))
			throw new MethodNotAllowedException();

		if (!Auth::isLoggedIn())
		{
			$this->response->statusCode(401);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'You must be logged in to report an issue']));
			return $this->response;
		}

		// Parse JSON request body from React
		$input = json_decode($this->request->input(), true);
		$tsumegoId = $input['tsumego_id'] ?? null;
		$message = $input['text'] ?? null;
		$position = $input['position'] ?? null;

		if (empty($tsumegoId) || empty($message))
		{
			$this->response->statusCode(400);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Tsumego ID and message are required']));
			return $this->response;
		}

		$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
		$TsumegoComment = ClassRegistry::init('TsumegoComment');

		// Create the issue
		$issue = [
			'tsumego_id' => $tsumegoId,
			'user_id' => Auth::getUserID(),
			'tsumego_issue_status_id' => TsumegoIssue::$OPENED_STATUS,
		];

		$TsumegoIssue->create();
		if (!$TsumegoIssue->save($issue))
		{
			$this->response->statusCode(500);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Failed to create issue']));
			return $this->response;
		}

		$issueId = $TsumegoIssue->getLastInsertID();

		// Create the first comment attached to this issue
		$comment = [
			'tsumego_id' => $tsumegoId,
			'tsumego_issue_id' => $issueId,
			'message' => $message,
			'position' => $position,
			'user_id' => Auth::getUserID(),
		];

		$TsumegoComment->create();
		if (!$TsumegoComment->save($comment))
		{
			// Rollback: delete the issue if comment fails
			$TsumegoIssue->delete($issueId);
			$this->response->statusCode(422);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Failed to create issue']));
			return $this->response;
		}

		// Return full issue data for React to add to store
		$User = ClassRegistry::init('User');
		$user = $User->findById(Auth::getUserID());

		$issueData = [
			'id' => $issueId,
			'tsumego_issue_status_id' => TsumegoIssue::$OPENED_STATUS,
			'created' => date('Y-m-d H:i:s'),
			'user_id' => Auth::getUserID(),
			'user_name' => $user['User']['name'],
			'user_picture' => $user['User']['picture'],
			'user_rating' => $user['User']['rating'],
			'user_external_id' => $user['User']['externalId'],
			'isAdmin' => Auth::isAdmin(),
			'comments' => [[
				'id' => $TsumegoComment->getLastInsertID(),
				'text' => $message,
				'user_id' => Auth::getUserID(),
				'user_name' => $user['User']['name'],
				'user_picture' => $user['User']['picture'],
				'user_rating' => $user['User']['rating'],
				'user_external_id' => $user['User']['externalId'],
				'isAdmin' => Auth::isAdmin(),
				'created' => date('Y-m-d H:i:s'),
				'position' => $position,
			]],
		];

		$this->response->type('json');
		$this->response->body(json_encode(['success' => true, 'issue' => $issueData]));
		return $this->response;
	}

	/**
	 * Close an issue.
	 *
	 * Only admin or issue author can close.
	 * Optionally add a closing comment.
	 *
	 * @param int $id Issue ID
	 * @return CakeResponse|null
	 */
	public function close($id)
	{
		if (!$this->request->is('post'))
			throw new MethodNotAllowedException();

		$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
		$issue = $TsumegoIssue->findById($id);

		if (!$issue)
		{
			$this->response->statusCode(404);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Issue not found']));
			return $this->response;
		}

		// Only admin or issue author can close
		$isOwner = $issue['TsumegoIssue']['user_id'] === Auth::getUserID();
		if (!Auth::isAdmin() && !$isOwner)
		{
			$this->response->statusCode(403);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'You are not authorized to close this issue']));
			return $this->response;
		}

		// Update status to closed
		$TsumegoIssue->id = $id;
		if (!$TsumegoIssue->saveField('tsumego_issue_status_id', TsumegoIssue::$CLOSED_STATUS))
		{
			$this->response->statusCode(500);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Failed to close issue']));
			return $this->response;
		}

		// Add closing comment if provided
		$closingMessage = $this->request->data('Issue.message');
		if (!empty($closingMessage))
		{
			$TsumegoComment = ClassRegistry::init('TsumegoComment');
			$comment = [
				'tsumego_id' => $issue['TsumegoIssue']['tsumego_id'],
				'tsumego_issue_id' => $id,
				'message' => $closingMessage,
				'user_id' => Auth::getUserID(),
			];
			$TsumegoComment->create();
			$TsumegoComment->save($comment);
		}

		$this->response->type('json');
		$this->response->body(json_encode(['success' => true]));
		return $this->response;
	}

	/**
	 * Reopen a closed issue.
	 *
	 * Admin only.
	 *
	 * @param int $id Issue ID
	 * @return CakeResponse|null
	 */
	public function reopen($id)
	{
		if (!$this->request->is('post'))
			throw new MethodNotAllowedException();

		if (!Auth::isAdmin())
		{
			$this->response->statusCode(403);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Only admins can reopen issues']));
			return $this->response;
		}

		$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
		$issue = $TsumegoIssue->findById($id);

		if (!$issue)
		{
			$this->response->statusCode(404);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Issue not found']));
			return $this->response;
		}

		$TsumegoIssue->id = $id;
		if (!$TsumegoIssue->saveField('tsumego_issue_status_id', TsumegoIssue::$OPENED_STATUS))
		{
			$this->response->statusCode(500);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Failed to reopen issue']));
			return $this->response;
		}

		$this->response->type('json');
		$this->response->body(json_encode(['success' => true]));
		return $this->response;
	}

	/**
	 * Move an existing comment into an issue (new or existing), or make it standalone.
	 *
	 * Admin only.
	 *
	 * POST data:
	 * - Comment.tsumego_issue_id: 'standalone' | 'new' | int (issue ID)
	 *
	 * @param int $commentId Comment ID to move
	 * @return CakeResponse|null
	 */
	public function moveComment($commentId)
	{
		if (!$this->request->is('post'))
			throw new MethodNotAllowedException();

		if (!Auth::isAdmin())
		{
			$this->response->statusCode(403);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Only admins can move comments']));
			return $this->response;
		}

		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$comment = $TsumegoComment->findById($commentId);

		if (!$comment)
		{
			$this->response->statusCode(404);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Comment not found']));
			return $this->response;
		}

		$tsumegoId = $comment['TsumegoComment']['tsumego_id'];
		$targetIssueId = $this->request->data('Comment.tsumego_issue_id');
		$currentIssueId = $comment['TsumegoComment']['tsumego_issue_id'];

		// Handle 'standalone' - remove from issue
		if ($targetIssueId === 'standalone')
		{
			if (empty($currentIssueId))
			{
				$this->response->type('json');
				$this->response->body(json_encode(['success' => true]));
				return $this->response;
			}

			$TsumegoComment->id = $commentId;
			if ($TsumegoComment->saveField('tsumego_issue_id', null))
			{
				// Check if issue is now empty and delete it
				$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
				$TsumegoIssue->deleteIfEmpty($currentIssueId);
				$this->response->type('json');
				$this->response->body(json_encode(['success' => true]));
				return $this->response;
			}

			$this->response->statusCode(500);
			$this->response->type('json');
			$this->response->body(json_encode(['error' => 'Failed to remove comment from issue']));
			return $this->response;
		}

		// Handle 'new' - create new issue
		if ($targetIssueId === 'new')
		{
			$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
			$User = ClassRegistry::init('User');

			$issue = [
				'tsumego_id' => $comment['TsumegoComment']['tsumego_id'],
				'user_id' => $comment['TsumegoComment']['user_id'], // Original comment author becomes issue author
				'tsumego_issue_status_id' => TsumegoIssue::$OPENED_STATUS,
			];

			$TsumegoIssue->create();
			if (!$TsumegoIssue->save($issue))
			{
				$this->response->statusCode(500);
				$this->response->type('json');
				$this->response->body(json_encode(['error' => 'Failed to create new issue']));
				return $this->response;
			}
			$targetIssueId = $TsumegoIssue->getLastInsertID();

			// Move comment to this new issue
			$TsumegoComment->id = $commentId;
			$TsumegoComment->saveField('tsumego_issue_id', $targetIssueId);

			// Get issue author info for React response
			$issueUser = $User->findById($comment['TsumegoComment']['user_id']);
			// Get comment author info
			$commentUser = $User->findById($comment['TsumegoComment']['user_id']);

			// Return full issue data for React to add to store
			$issueData = [
				'id' => $targetIssueId,
				'status' => 'open',
				'created' => date('Y-m-d H:i:s'),
				'user_id' => $comment['TsumegoComment']['user_id'],
				'user_name' => $issueUser['User']['name'],
				'user_picture' => $issueUser['User']['picture'],
				'user_rating' => $issueUser['User']['rating'],
				'user_external_id' => $issueUser['User']['externalId'],
				'isAdmin' => ($issueUser['User']['isAdmin'] == 1),
				'comments' => [[
					'id' => $commentId,
					'text' => $comment['TsumegoComment']['message'],
					'user_id' => $comment['TsumegoComment']['user_id'],
					'user_name' => $commentUser['User']['name'],
					'user_picture' => $commentUser['User']['picture'],
					'user_rating' => $commentUser['User']['rating'],
					'user_external_id' => $commentUser['User']['externalId'],
					'isAdmin' => ($commentUser['User']['isAdmin'] == 1),
					'created' => $comment['TsumegoComment']['created'],
					'position' => $comment['TsumegoComment']['position'],
				]],
			];

			$this->response->type('json');
			$this->response->body(json_encode(['success' => true, 'issue' => $issueData, 'comment_id' => $commentId]));
			return $this->response;
		}

		// Check if moving to same issue
		if ($currentIssueId == $targetIssueId)
		{
			$this->response->type('json');
			$this->response->body(json_encode(['success' => true]));
			return $this->response;
		}

		// Move the comment to the target issue
		$TsumegoComment->id = $commentId;
		if ($TsumegoComment->saveField('tsumego_issue_id', $targetIssueId))
		{
			// Check if old issue is now empty and delete it
			if (!empty($currentIssueId))
			{
				$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
				$TsumegoIssue->deleteIfEmpty($currentIssueId);
			}
			$this->response->type('json');
			$this->response->body(json_encode(['success' => true]));
			return $this->response;
		}

		$this->response->statusCode(500);
		$this->response->type('json');
		$this->response->body(json_encode(['error' => 'Failed to move comment']));
		return $this->response;
	}
}

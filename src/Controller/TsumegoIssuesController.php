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
	public $components = ['Flash'];

	/**
	 * List all issues with optional filtering.
	 *
	 * Query params:
	 * - status: 'opened', 'closed', 'all' (default: 'opened')
	 * - page: int (for pagination)
	 *
	 * @return void
	 */
	public function index()
	{
		$this->loadModel('TsumegoIssue');

		$this->Session->write('title', 'Tsumego Hero - Issues');
		$this->Session->write('page', 'issues');

		// Get filter and pagination params
		$statusFilter = $this->request->query('status') ?: 'opened';
		$page = (int) ($this->request->query('page') ?: 1);

		// Single optimized query using model method
		$issues = $this->TsumegoIssue->findForIndex($statusFilter, 20, $page);

		// Get tab counts
		$counts = $this->TsumegoIssue->getIndexCounts();
		$openCount = $counts['open'];
		$closedCount = $counts['closed'];

		$this->set(compact('issues', 'statusFilter', 'openCount', 'closedCount'));
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
			$this->Flash->error('You must be logged in to report an issue.');
			return $this->redirect($this->referer());
		}

		$tsumegoId = $this->request->data('Issue.tsumego_id');
		$message = $this->request->data('Issue.message');
		$position = $this->request->data('Issue.position');

		if (empty($tsumegoId) || empty($message))
		{
			$this->Flash->error('Tsumego ID and message are required.');
			return $this->redirect($this->referer());
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
			$this->Flash->error('Failed to create issue.');
			return $this->redirect($this->referer());
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
			$this->Flash->error('Failed to create issue comment.');
			return $this->redirect($this->referer());
		}

		$this->Flash->success('Issue reported successfully.');
		$redirect = $this->request->data('Issue.redirect') ?: $this->referer();
		return $this->redirect($redirect);
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
			$this->Flash->error('Issue not found.');
			return $this->redirect($this->referer());
		}

		// Only admin or issue author can close
		$isOwner = $issue['TsumegoIssue']['user_id'] === Auth::getUserID();
		if (!Auth::isAdmin() && !$isOwner)
		{
			$this->Flash->error('You are not authorized to close this issue.');
			return $this->redirect($this->referer());
		}

		// Update status to closed
		$TsumegoIssue->id = $id;
		if (!$TsumegoIssue->saveField('tsumego_issue_status_id', TsumegoIssue::$CLOSED_STATUS))
		{
			$this->Flash->error('Failed to close issue.');
			return $this->redirect($this->referer());
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

		$this->Flash->success('Issue closed.');
		$redirect = $this->request->data('Issue.redirect') ?: $this->referer();
		return $this->redirect($redirect);
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
			$this->Flash->error('Only admins can reopen issues.');
			return $this->redirect($this->referer());
		}

		$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
		$issue = $TsumegoIssue->findById($id);

		if (!$issue)
		{
			$this->Flash->error('Issue not found.');
			return $this->redirect($this->referer());
		}

		$TsumegoIssue->id = $id;
		if ($TsumegoIssue->saveField('tsumego_issue_status_id', TsumegoIssue::$OPENED_STATUS))
			$this->Flash->success('Issue reopened.');
		else
			$this->Flash->error('Failed to reopen issue.');

		$redirect = $this->request->data('Issue.redirect') ?: $this->referer();
		return $this->redirect($redirect);
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
			$this->Flash->error('Only admins can move comments.');
			return $this->redirect($this->referer());
		}

		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$comment = $TsumegoComment->findById($commentId);

		if (!$comment)
		{
			$this->Flash->error('Comment not found.');
			return $this->redirect($this->referer());
		}

		$targetIssueId = $this->request->data('Comment.tsumego_issue_id');
		$currentIssueId = $comment['TsumegoComment']['tsumego_issue_id'];

		// Handle 'standalone' - remove from issue
		if ($targetIssueId === 'standalone')
		{
			if (empty($currentIssueId))
			{
				$this->Flash->info('Comment is already standalone.');
				$redirect = $this->request->data('Comment.redirect') ?: $this->referer();
				return $this->redirect($redirect);
			}

			$TsumegoComment->id = $commentId;
			if ($TsumegoComment->saveField('tsumego_issue_id', null))
			{
				$this->Flash->success('Comment removed from issue.');
				// Check if issue is now empty and delete it
				$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
				$TsumegoIssue->deleteIfEmpty($currentIssueId);
			}
			else
				$this->Flash->error('Failed to remove comment from issue.');

			$redirect = $this->request->data('Comment.redirect') ?: $this->referer();
			return $this->redirect($redirect);
		}

		// Handle 'new' - create new issue
		if ($targetIssueId === 'new')
		{
			$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
			$issue = [
				'tsumego_id' => $comment['TsumegoComment']['tsumego_id'],
				'user_id' => $comment['TsumegoComment']['user_id'], // Original comment author becomes issue author
				'tsumego_issue_status_id' => TsumegoIssue::$OPENED_STATUS,
			];

			$TsumegoIssue->create();
			if (!$TsumegoIssue->save($issue))
			{
				$this->Flash->error('Failed to create new issue.');
				return $this->redirect($this->referer());
			}
			$targetIssueId = $TsumegoIssue->getLastInsertID();
		}

		// Check if moving to same issue
		if ($currentIssueId == $targetIssueId)
		{
			$this->Flash->info('Comment is already in this issue.');
			$redirect = $this->request->data('Comment.redirect') ?: $this->referer();
			return $this->redirect($redirect);
		}

		// Move the comment to the target issue
		$TsumegoComment->id = $commentId;
		if ($TsumegoComment->saveField('tsumego_issue_id', $targetIssueId))
		{
			$this->Flash->success('Comment moved to issue.');
			// Check if old issue is now empty and delete it
			if (!empty($currentIssueId))
			{
				$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
				$TsumegoIssue->deleteIfEmpty($currentIssueId);
			}
		}
		else
			$this->Flash->error('Failed to move comment.');

		$redirect = $this->request->data('Comment.redirect') ?: $this->referer();
		return $this->redirect($redirect);
	}
}

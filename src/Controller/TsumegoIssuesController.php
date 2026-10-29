<?php

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Lib\Error\UnprocessableEntityException;
use App\Model\Table\TsumegoIssueTable;
//Controller for managing tsumego issues.
//Issues are reports about problems with tsumego solutions (missing moves, wrong answers, etc.).
//Each issue contains one or more comments discussing the problem.
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Query;

class TsumegoIssuesController extends AppController
{
	//API endpoint to fetch issues list with pagination and filtering.
	//Query params:
	//- status: 'opened', 'closed', 'all' (default: 'opened')
	//- page: int (for pagination)
	/** @return CakeResponse|null */
	#[HttpGet]
	public function api()
	{
		$statusFilter = $this->query('status') ?: 'opened';
		$page = (int) ($this->query('page') ?: 1);
		$perPage = 20;

		$issues = TsumegoIssueTable::findForIndex($statusFilter, $perPage, $page);
		$counts = TsumegoIssueTable::getIndexCounts();

		$totalCount = match ($statusFilter)
		{
			'opened' => $counts['open'],
			'closed' => $counts['closed'],
			default => $counts['open'] + $counts['closed'],
		};
		$totalPages = (int) ceil($totalCount / $perPage);

		return $this->respondJson([
			'issues' => $issues,
			'counts' => $counts,
			'totalPages' => $totalPages,
			'currentPage' => $page,
		]);
	}

	//List all issues page
	//Query params:
	//- status: 'opened', 'closed', 'all' (default: 'opened')
	//- page: int (for pagination)
	#[HttpGet]
	public function index(): void
	{
		$this->set('_title', 'Tsumego Hero - Issues');
		$this->set('_page', 'issues');

		// Get filter and pagination params from URL (used for initial state)
		$statusFilter = $this->query('status') ?: 'opened';
		$page = (int) ($this->query('page') ?: 1);

		$this->set(compact('statusFilter'));
		$this->set('currentPage', $page);
	}

	//Create a new issue with an initial comment.
	//POST data:
	//- Issue.tsumego_id: int
	//- Issue.message: string (first comment)
	//- Issue.position: string|null (board position for first comment)
	/** @return CakeResponse|null */
	#[HttpPost]
	public function create()
	{
		$this->Authorization->authorize('TsumegoIssue', 'create');

		// Parse JSON request body
		$input = json_decode($this->requestBody(), true);
		$tsumegoId = $input['tsumego_id'] ?? null;
		$message = $input['text'] ?? null;
		$position = $input['position'] ?? null;

		if (empty($tsumegoId) || empty($message))
			throw new BadRequestException('Tsumego ID and message are required');

		$tsumegoIssueTable = $this->fetchTable('TsumegoIssue');
		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');

		// Create the issue
		$issue = $tsumegoIssueTable->saveOrFail($tsumegoIssueTable->newEntity([
			'tsumego_id' => $tsumegoId,
			'user_id' => Auth::getUserID(),
			'tsumego_issue_status_id' => TsumegoIssueTable::OPENED_STATUS,
		]));
		$issueId = $issue->id;

		// Create the first comment attached to this issue
		$comment = $tsumegoCommentTable->newEntity([
			'tsumego_id' => $tsumegoId,
			'tsumego_issue_id' => $issueId,
			'message' => $message,
			'position' => $position,
			'user_id' => Auth::getUserID(),
		]);
		if (!$tsumegoCommentTable->save($comment))
		{
			// Rollback: delete the issue if comment fails
			$tsumegoIssueTable->deleteAll(['id' => $issueId]);
			throw new UnprocessableEntityException('Failed to create issue');
		}

		// Return full issue data for initial state
		$user = Auth::getUser();

		$issueData = [
			'id' => $issueId,
			'tsumego_issue_status_id' => TsumegoIssueTable::OPENED_STATUS,
			'created' => date('c'),
			'user_id' => Auth::getUserID(),
			'user_display_name' => $user['display_name'],
			'user_avatar_url' => UserTable::getAvatarUrl($user),
			'user_rating' => $user['rating'],
			'isAdmin' => Auth::isAdmin(),
			'comments' => [[
				'id' => $comment->id,
				'text' => $message,
				'user_id' => Auth::getUserID(),
				'user_display_name' => $user['display_name'],
				'user_avatar_url' => UserTable::getAvatarUrl($user),
				'user_rating' => $user['rating'],
				'isAdmin' => Auth::isAdmin(),
				'created' => date('c'),
				'position' => $position,
			]],
		];

		return $this->respondJson(['success' => true, 'issue' => $issueData]);
	}

	//Close an issue.
	//Only admin or issue author can close. Optionally add a closing comment.
	//$id: Issue ID
	/** @return CakeResponse|null */
	#[HttpPost]
	public function close(string $id)
	{
		$tsumegoIssueTable = $this->fetchTable('TsumegoIssue');
		$issue = $tsumegoIssueTable->find()->where(['id' => (int) $id])->first();

		if (!$issue)
			throw new NotFoundException('Issue not found');

		$this->Authorization->authorize(['TsumegoIssue' => $issue], 'close');

		// Update status to closed
		$issue->tsumego_issue_status_id = TsumegoIssueTable::CLOSED_STATUS;
		if (!$tsumegoIssueTable->save($issue))
			throw new InternalErrorException('Failed to close issue');

		// Add closing comment if provided
		$closingMessage = $this->input('message');
		if (!empty($closingMessage))
		{
			// a closing comment that fails the validation is left out, the issue is closed anyway
			$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
			$tsumegoCommentTable->save($tsumegoCommentTable->newEntity([
				'tsumego_id' => $issue->tsumego_id,
				'tsumego_issue_id' => $issue->id,
				'message' => $closingMessage,
				'user_id' => Auth::getUserID(),
			]));
		}

		return $this->respondJson(['success' => true]);
	}

	//Reopen a closed issue.
	//Admin only.
	//$id: Issue ID
	/** @return CakeResponse|null */
	#[HttpPost]
	public function reopen(string $id)
	{
		$tsumegoIssueTable = $this->fetchTable('TsumegoIssue');
		$issue = $tsumegoIssueTable->find()->where(['id' => (int) $id])->first();

		if (!$issue)
			throw new NotFoundException('Issue not found');

		$this->Authorization->authorize(['TsumegoIssue' => $issue], 'reopen');

		$issue->tsumego_issue_status_id = TsumegoIssueTable::OPENED_STATUS;
		if (!$tsumegoIssueTable->save($issue))
			throw new InternalErrorException('Failed to reopen issue');

		return $this->respondJson(['success' => true]);
	}

	//Move an existing comment into an issue (new or existing), or make it standalone.
	//Admin only.
	//POST data:
	//- Comment.tsumego_issue_id: 'standalone' | 'new' | int (issue ID)
	/** @return CakeResponse|null */
	#[HttpPost]
	public function moveComment(string $commentId)
	{
		$this->Authorization->authorize('TsumegoIssue', 'moveComment');

		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
		$comment = $tsumegoCommentTable->find()->where(['id' => (int) $commentId])->first();

		if (!$comment)
			throw new NotFoundException('Comment not found');

		$targetIssueId = $this->input('tsumego_issue_id');
		$currentIssueId = $comment->tsumego_issue_id;

		// Handle 'standalone' - remove from issue
		if ($targetIssueId === 'standalone')
		{
			if (empty($currentIssueId))
				return $this->respondJson(['success' => true]);

			$comment->tsumego_issue_id = null;
			if ($tsumegoCommentTable->save($comment))
			{
				// Check if issue is now empty and delete it
				TsumegoIssueTable::deleteIfEmpty($currentIssueId);
				return $this->respondJson(['success' => true]);
			}

			throw new InternalErrorException('Failed to remove comment from issue');
		}

		// Handle 'new' - create new issue
		if ($targetIssueId === 'new')
		{
			$tsumegoIssueTable = $this->fetchTable('TsumegoIssue');

			$issue = $tsumegoIssueTable->saveOrFail($tsumegoIssueTable->newEntity([
				'tsumego_id' => $comment->tsumego_id,
				'user_id' => $comment->user_id, // Original comment author becomes issue author
				'tsumego_issue_status_id' => TsumegoIssueTable::OPENED_STATUS,
			]));
			$targetIssueId = $issue->id;

			// Move comment to this new issue
			$comment->tsumego_issue_id = $targetIssueId;
			$tsumegoCommentTable->saveOrFail($comment);

			$commentUser = UserTable::row($comment->user_id);

			$issueData = [
				'id' => $targetIssueId,
				'status' => 'open',
				'created' => date('c'),
				'user_id' => $comment->user_id,
				'user_display_name' => UserTable::displayNameOrNull($commentUser),
				'user_avatar_url' => UserTable::avatarOrNull($commentUser),
				'user_rating' => $commentUser['rating'],
				'isAdmin' => UserTable::isAdmin($commentUser),
				'comments' => [[
					'id' => $commentId,
					'text' => $comment->message,
					'user_id' => $comment->user_id,
					'user_display_name' => UserTable::displayNameOrNull($commentUser),
					'user_avatar_url' => UserTable::avatarOrNull($commentUser),
					'user_rating' => $commentUser['rating'],
					'isAdmin' => UserTable::isAdmin($commentUser),
					'created' => $comment->created->format('c'),
					'position' => $comment->position,
				]],
			];

			return $this->respondJson(['success' => true, 'issue' => $issueData, 'comment_id' => $commentId]);
		}

		// Check if moving to same issue
		if ($currentIssueId == $targetIssueId)
			return $this->respondJson(['success' => true]);

		// Move the comment to the target issue
		$comment->tsumego_issue_id = (int) $targetIssueId;
		if ($tsumegoCommentTable->save($comment))
		{
			// Check if old issue is now empty and delete it
			if (!empty($currentIssueId))
				TsumegoIssueTable::deleteIfEmpty($currentIssueId);
			return $this->respondJson(['success' => true]);
		}

		throw new InternalErrorException('Failed to move comment');
	}
}

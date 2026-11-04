<?php

namespace App\Controller;

use Cake\Http\Response;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use Cake\Http\Exception\UnprocessableContentException;
use App\Model\Entity\TsumegoComment;
use App\Model\Entity\TsumegoIssue;
use App\Model\Table\TsumegoCommentTable;
use App\Model\Table\TsumegoIssueTable;
use App\Utility\Auth;

//Issues: reports of a problem with a tsumego (a missing move, a wrong answer, ...), each a thread
//of comments. The comment section of the play page (app/comments) and the issues page (app/issues)
//talk to the JSON actions here.
class TsumegoIssuesController extends AppController
{
	private const ISSUES_PER_PAGE = 20;

	//The issues of the issues page, as JSON.
	//?status: 'opened' (the default), 'closed' or 'all'
	//?page: from 1
	#[HttpGet]
	public function api(): Response
	{
		$statusFilter = $this->query('status') ?: 'opened';
		$page = $this->pageNumber();

		$counts = TsumegoIssueTable::getIndexCounts();
		$totalCount = match ($statusFilter)
		{
			'opened' => $counts['open'],
			'closed' => $counts['closed'],
			default => $counts['open'] + $counts['closed'],
		};

		return $this->respondJson([
			'issues' => TsumegoIssueTable::findForIndex($statusFilter, self::ISSUES_PER_PAGE, $page),
			'counts' => $counts,
			'totalPages' => (int) ceil($totalCount / self::ISSUES_PER_PAGE),
			'currentPage' => $page,
		]);
	}

	//The issues page, which loads its issues from api(); the query string is its first state, as there.
	#[HttpGet]
	public function index(): void
	{
		$this->set('_title', 'Tsumego Hero - Issues');
		$this->set('_page', 'issues');
		$this->set('statusFilter', $this->query('status') ?: 'opened');
		$this->set('currentPage', $this->pageNumber());
	}

	//The page of the issues the query string asks for, the first one for none or one before it.
	private function pageNumber(): int
	{
		return max(1, (int) $this->query('page'));
	}

	//Opens an issue with its first comment, from {"tsumego_id", "text", "position"}.
	#[HttpPost]
	public function create(): Response
	{
		$this->Authorization->authorize('TsumegoIssue', 'create');

		$input = json_decode($this->requestBody(), true);
		$tsumegoId = $input['tsumego_id'] ?? null;
		$message = $input['text'] ?? null;
		if (empty($tsumegoId) || empty($message))
			throw new BadRequestException('Tsumego ID and message are required');

		$comment = $this->fetchTable('TsumegoComment')->newEntity([
			'tsumego_id' => $tsumegoId,
			'message' => $message,
			'position' => $input['position'] ?? null,
			'user_id' => Auth::getUserID(),
		]);
		$issue = TsumegoIssueTable::openWith($comment);
		if (!$issue)
			throw new UnprocessableContentException('Failed to create issue');
		return $this->respondJson(['success' => true, 'issue' => self::issuePayload($issue, $comment)]);
	}

	//Closes an issue, with a closing comment when `message` is given. The author of the issue or an admin.
	#[HttpPost]
	public function close(string $id): Response
	{
		$issue = $this->findIssue($id);
		$this->Authorization->authorize(['TsumegoIssue' => $issue], 'close');
		$this->setStatus($issue, TsumegoIssueTable::CLOSED_STATUS);

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

	//Reopens a closed issue. Admin only.
	#[HttpPost]
	public function reopen(string $id): Response
	{
		$issue = $this->findIssue($id);
		$this->Authorization->authorize(['TsumegoIssue' => $issue], 'reopen');
		$this->setStatus($issue, TsumegoIssueTable::OPENED_STATUS);
		return $this->respondJson(['success' => true]);
	}

	//Moves a comment into an issue, into a new issue of its own or out of its issue. Admin only.
	//An issue left without comments is deleted.
	//tsumego_issue_id: an issue id, 'new' or 'standalone'
	#[HttpPost]
	public function moveComment(string $commentId): Response
	{
		$this->Authorization->authorize('TsumegoIssue', 'moveComment');

		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
		$comment = $tsumegoCommentTable->find()->where(['id' => (int) $commentId])->first();
		if (!$comment)
			throw new NotFoundException('Comment not found');

		$target = $this->input('tsumego_issue_id');
		$sourceIssueId = $comment->tsumego_issue_id;
		if ($target === 'new')
		{
			// the writer of the comment becomes the author of the issue
			$issue = TsumegoIssueTable::openWith($comment);
			if (!$issue)
				throw new UnprocessableContentException('Failed to create issue');
			return $this->respondJson(['success' => true, 'issue' => self::issuePayload($issue, $comment), 'comment_id' => $commentId]);
		}

		$targetIssueId = $target === 'standalone' ? null : (int) $target;
		if ($sourceIssueId === $targetIssueId)
			return $this->respondJson(['success' => true]);
		if ($targetIssueId !== null && !TsumegoIssueTable::isOfProblem($targetIssueId, $comment->tsumego_id))
			throw new UnprocessableContentException('The issue is not one of the problem of the comment.');
		$comment->tsumego_issue_id = $targetIssueId;
		$tsumegoCommentTable->saveOrFail($comment);
		if ($sourceIssueId !== null)
			TsumegoIssueTable::deleteIfEmpty($sourceIssueId);
		return $this->respondJson(['success' => true]);
	}

	private function findIssue(string $id): TsumegoIssue
	{
		$issue = $this->fetchTable('TsumegoIssue')->find()->where(['id' => (int) $id])->first();
		if (!$issue)
			throw new NotFoundException('Issue not found');
		return $issue;
	}

	private function setStatus(TsumegoIssue $issue, int $statusId): void
	{
		$issue->tsumego_issue_status_id = $statusId;
		$this->fetchTable('TsumegoIssue')->saveOrFail($issue);
	}

	//A new issue with its one comment, as the comment section shows it.
	/** @return array<string, mixed> */
	private static function issuePayload(TsumegoIssue $issue, TsumegoComment $comment): array
	{
		return TsumegoIssueTable::payload($issue, [$comment], TsumegoCommentTable::authorsOf([$issue, $comment]));
	}
}

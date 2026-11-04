<?php

namespace App\Controller;

use Cake\Http\Response;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use DateTimeImmutable;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use Cake\Http\Exception\UnprocessableContentException;
use App\Model\Entity\TsumegoComment;
use App\Model\Entity\TsumegoIssue;
use App\Model\Table\TsumegoCommentTable;
use App\Model\Table\TsumegoIssueTable;
use App\Utility\Auth;

//The comments of a problem, standalone or in an issue (TsumegoIssuesController), for the comment
//section of the play page (app/comments).
class TsumegoCommentsController extends AppController
{
	//Adds a comment from {"tsumego_id", "text", "issue_id", "position"}, the last two optional.
	#[HttpPost]
	public function add(): Response
	{
		$this->Authorization->authorize('TsumegoComment', 'add');

		$input = json_decode($this->requestBody(), true);
		$tsumegoId = (int) ($input['tsumego_id'] ?? 0);
		if ($tsumegoId <= 0 || !is_string($input['text'] ?? null))
			throw new BadRequestException('Tsumego ID and text are required');
		if (!$this->fetchTable('Tsumego')->exists(['id' => $tsumegoId]))
			throw new NotFoundException('Problem not found');
		$issueId = isset($input['issue_id']) ? (int) $input['issue_id'] : null;
		if ($issueId !== null && !TsumegoIssueTable::isOfProblem($issueId, $tsumegoId))
			throw new UnprocessableContentException('The issue is not one of this problem.');

		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
		$comment = $tsumegoCommentTable->newEntity([
			'tsumego_id' => $tsumegoId,
			'message' => $input['text'],
			'tsumego_issue_id' => $issueId,
			'position' => $input['position'] ?? null,
			'user_id' => Auth::getUserID(),
		]);
		if (!$tsumegoCommentTable->save($comment))
			throw new UnprocessableContentException('Failed to add comment');

		return $this->respondJson(TsumegoCommentTable::payload($comment, TsumegoCommentTable::authorsOf([$comment])[$comment->user_id] ?? null));
	}

	//Deletes a comment (soft delete): its writer or an admin. An issue left without comments is deleted.
	#[HttpPost]
	public function delete(string $id): Response
	{
		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
		$comment = $tsumegoCommentTable->find()->where(['id' => (int) $id])->first();

		if (!$comment)
			throw new NotFoundException('Comment not found');

		$this->Authorization->authorize(['TsumegoComment' => $comment], 'delete');

		// Remember the issue ID before deleting
		$issueId = $comment->tsumego_issue_id;

		// Soft delete
		$comment->deleted = new DateTimeImmutable();
		$tsumegoCommentTable->saveOrFail($comment);

		// If comment was part of an issue, check if issue is now empty and delete it
		if (!empty($issueId))
			TsumegoIssueTable::deleteIfEmpty($issueId);

		return $this->respondJson(['success' => true]);
	}

	//Get comments data for a tsumego
	//Returns issues and standalone comments in the same format as initial SSR data.
	#[HttpGet]
	public function index(string $tsumegoId): Response
	{
		// Load issues
		/** @var list<TsumegoIssue> $issues */
		$issues = $this->fetchTable('TsumegoIssue')->find()
			->where(['tsumego_id' => (int) $tsumegoId])
			->orderByDesc('created')
			->toArray();

		// Load the comments of the issues, in the order they were written
		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
		$issueComments = empty($issues) ? [] : $tsumegoCommentTable->find()
			->where(['tsumego_issue_id IN' => array_map(fn($issue) => $issue->id, $issues), 'deleted IS' => null])
			->orderBy('id')
			->toArray();

		// Load standalone comments
		$plainComments = $tsumegoCommentTable->find()
			->where(['tsumego_id' => (int) $tsumegoId, 'tsumego_issue_id IS' => null, 'deleted IS' => null])
			->orderByDesc('created')
			->toArray();

		$authors = TsumegoCommentTable::authorsOf([...$issues, ...$issueComments, ...$plainComments]);
		$commentsByIssue = [];
		foreach ($issueComments as $comment)
			$commentsByIssue[$comment->tsumego_issue_id][] = $comment;
		$issuesJson = array_map(fn(TsumegoIssue $issue) => TsumegoIssueTable::payload($issue, $commentsByIssue[$issue->id] ?? [], $authors), $issues);
		$standaloneJson = array_map(fn(TsumegoComment $comment) => TsumegoCommentTable::payload($comment, $authors[$comment->user_id] ?? null), $plainComments);

		$counts = TsumegoIssueTable::getCommentSectionCounts((int) $tsumegoId);

		return $this->respondJson([
			'issues' => $issuesJson,
			'standalone' => $standaloneJson,
			'counts' => $counts,
		]);
	}
}

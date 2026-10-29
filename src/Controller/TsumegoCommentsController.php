<?php

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use DateTimeImmutable;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Lib\Error\UnprocessableEntityException;
use App\Model\Entity\TsumegoComment;
use App\Model\Entity\TsumegoIssue;
use App\Model\Table\TsumegoIssueTable;
//Controller for managing tsumego comments (CRUD operations).
//Handles adding and deleting comments on tsumego problems.
//Comments can be standalone or associated with a TsumegoIssue.
use App\Model\Table\UserTable;
use App\Utility\Auth;
use Cake\ORM\TableRegistry;

class TsumegoCommentsController extends AppController
{
	//Add a new comment to a tsumego.
	//Expects POST data with:
	//- Comment.tsumego_id: int - The tsumego to comment on
	//- Comment.message: string - The comment text
	//- Comment.tsumego_issue_id: int|null - Optional issue to attach to
	//- Comment.position: string|null - Optional board position
	//- Comment.redirect: string - URL to redirect after success
	/** @return CakeResponse|null */
	#[HttpPost]
	public function add()
	{
		$this->Authorization->authorize('TsumegoComment', 'add');

		$input = json_decode($this->requestBody(), true);

		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
		$comment = $tsumegoCommentTable->newEntity([
			'tsumego_id' => $input['tsumego_id'],
			'message' => $input['text'],
			'tsumego_issue_id' => $input['issue_id'] ?? null,
			'position' => $input['position'] ?? null,
			'user_id' => Auth::getUserID(),
		]);
		if (!$tsumegoCommentTable->save($comment))
			throw new UnprocessableEntityException('Failed to add comment');

		$author = self::authorsOf([$comment])[$comment->user_id];
		return $this->respondJson([
			'id' => $comment->id,
			'text' => $comment->message,
			'user_id' => $comment->user_id,
			'user_display_name' => $author['display_name'] ?? null,
			'user_avatar_url' => UserTable::getAvatarUrl($author),
			'user_rating' => $author['rating'] ?? null,
			'isAdmin' => UserTable::isAdmin($author),
			'created' => $comment->created->format('c'),
			'position' => $comment->position,
		]);
	}

	//Delete a comment (soft delete).
	//Only the comment author or an admin can delete a comment.
	//If this was the last comment in an issue, the issue is also deleted.
	//$id: Comment ID to delete
	/** @return CakeResponse|null */
	#[HttpPost]
	public function delete(string $id)
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
	/** @return CakeResponse */
	#[HttpGet]
	public function index(string $tsumegoId)
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

		$authors = self::authorsOf([...$issues, ...$issueComments, ...$plainComments]);

		$issuesJson = [];
		foreach ($issues as $issue)
		{
			$comments = [];
			foreach ($issueComments as $comment)
				if ($comment->tsumego_issue_id === $issue->id)
					$comments[] = [
						'id' => $comment->id,
						'text' => $comment->message,
						'user_id' => $comment->user_id,
						'user_display_name' => UserTable::displayNameOrNull($authors[$comment->user_id]),
						'user_avatar_url' => UserTable::avatarOrNull($authors[$comment->user_id]),
						'user_rating' => $authors[$comment->user_id]['rating'] ?? null,
						'isAdmin' => UserTable::isAdmin($authors[$comment->user_id]),
						'created' => $comment->created->format('c'),
						'position' => $comment->position,
					];


			$issuesJson[] = [
				'id' => $issue->id,
				'tsumego_issue_status_id' => $issue->tsumego_issue_status_id,
				'created' => $issue->created->format('c'),
				'user_id' => $issue->user_id,
				'user_display_name' => UserTable::displayNameOrNull($authors[$issue->user_id]),
				'user_avatar_url' => UserTable::avatarOrNull($authors[$issue->user_id]),
				'user_rating' => $authors[$issue->user_id]['rating'] ?? null,
				'isAdmin' => UserTable::isAdmin($authors[$issue->user_id]),
				'comments' => $comments,
			];
		}

		$standaloneJson = [];
		foreach ($plainComments as $comment)
			$standaloneJson[] = [
				'id' => $comment->id,
				'text' => $comment->message,
				'user_id' => $comment->user_id,
				'user_display_name' => UserTable::displayNameOrNull($authors[$comment->user_id]),
				'user_avatar_url' => UserTable::avatarOrNull($authors[$comment->user_id]),
				'user_rating' => $authors[$comment->user_id]['rating'] ?? null,
				'isAdmin' => UserTable::isAdmin($authors[$comment->user_id]),
				'created' => $comment->created->format('c'),
				'position' => $comment->position,
			];

		$counts = TsumegoIssueTable::getCommentSectionCounts((int) $tsumegoId);

		return $this->respondJson([
			'issues' => $issuesJson,
			'standalone' => $standaloneJson,
			'counts' => $counts,
		]);
	}

	//The users who wrote the comments and issues, by id, as the rows of the user table
	/**
	 * @param list<TsumegoComment|TsumegoIssue> $writings
	 * @return array<int, array<string, mixed>>
	 */
	private static function authorsOf(array $writings): array
	{
		if (empty($writings))
			return [];
		$authors = [];
		$authorIDs = array_values(array_unique(array_map(fn($writing) => $writing->user_id, $writings)));
		foreach (TableRegistry::getTableLocator()->get('User')->find()->where(['id IN' => $authorIDs])->all() as $user)
			$authors[$user->id] = UserTable::rowOf($user);
		return $authors;
	}
}

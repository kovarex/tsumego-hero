<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Lib\Error\UnprocessableEntityException;
use App\Model\Entity\TsumegoComment;
//Controller for managing tsumego comments (CRUD operations).
//Handles adding and deleting comments on tsumego problems.
//Comments can be standalone or associated with a TsumegoIssue.
use App\Utility\Auth;
use App\Utility\Util;

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

		$input = json_decode($this->request->input(), true);

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
		$this->response->type('json');
		$this->response->body(json_encode([
			'id' => $comment->id,
			'text' => $comment->message,
			'user_id' => $comment->user_id,
			'user_display_name' => $author['display_name'] ?? null,
			'user_avatar_url' => User::getAvatarUrl($author),
			'user_rating' => $author['rating'] ?? null,
			'isAdmin' => User::isAdmin($author),
			'created' => $comment->created->format('c'),
			'position' => $comment->position,
		]));
		return $this->response;
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
		{
			$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
			$TsumegoIssue->deleteIfEmpty($issueId);
		}

		$this->response->type('json');
		$this->response->body(json_encode(['success' => true]));
		return $this->response;
	}

	//Get comments data for a tsumego
	//Returns issues and standalone comments in the same format as initial SSR data.
	/** @return CakeResponse */
	#[HttpGet]
	public function index(string $tsumegoId)
	{
		$TsumegoIssue = ClassRegistry::init('TsumegoIssue');

		// Load issues with their authors
		$issues = $TsumegoIssue->find('all', [
			'conditions' => ['TsumegoIssue.tsumego_id' => $tsumegoId],
			'contain' => ['User'],
			'order' => 'TsumegoIssue.created DESC'
		]);

		// Load the comments of the issues, in the order they were written
		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
		$issueComments = empty($issues) ? [] : $tsumegoCommentTable->find()
			->where(['tsumego_issue_id IN' => array_map(fn($issue) => (int) $issue['TsumegoIssue']['id'], $issues), 'deleted IS' => null])
			->orderBy('id')
			->toArray();

		// Load standalone comments
		$plainComments = $tsumegoCommentTable->find()
			->where(['tsumego_id' => (int) $tsumegoId, 'tsumego_issue_id IS' => null, 'deleted IS' => null])
			->orderByDesc('created')
			->toArray();

		$authors = self::authorsOf([...$issueComments, ...$plainComments]);

		$issuesJson = [];
		foreach ($issues as $issue)
		{
			$comments = [];
			foreach ($issueComments as $comment)
				if ($comment->tsumego_issue_id === (int) $issue['TsumegoIssue']['id'])
					$comments[] = [
						'id' => $comment->id,
						'text' => $comment->message,
						'user_id' => $comment->user_id,
						'user_display_name' => User::displayNameOrNull($authors[$comment->user_id]),
						'user_avatar_url' => User::avatarOrNull($authors[$comment->user_id]),
						'user_rating' => $authors[$comment->user_id]['rating'] ?? null,
						'isAdmin' => User::isAdmin($authors[$comment->user_id]),
						'created' => $comment->created->format('c'),
						'position' => $comment->position,
					];


			$issuesJson[] = [
				'id' => $issue['TsumegoIssue']['id'],
				'tsumego_issue_status_id' => $issue['TsumegoIssue']['tsumego_issue_status_id'],
				'created' => Util::toIso8601($issue['TsumegoIssue']['created']),
				'user_id' => $issue['TsumegoIssue']['user_id'],
				'user_display_name' => User::displayNameOrNull($issue['User']),
				'user_avatar_url' => User::avatarOrNull($issue['User']),
				'user_rating' => $issue['User']['rating'] ?? null,
				'isAdmin' => User::isAdmin($issue['User'] ?? null),
				'comments' => $comments,
			];
		}

		$standaloneJson = [];
		foreach ($plainComments as $comment)
			$standaloneJson[] = [
				'id' => $comment->id,
				'text' => $comment->message,
				'user_id' => $comment->user_id,
				'user_display_name' => User::displayNameOrNull($authors[$comment->user_id]),
				'user_avatar_url' => User::avatarOrNull($authors[$comment->user_id]),
				'user_rating' => $authors[$comment->user_id]['rating'] ?? null,
				'isAdmin' => User::isAdmin($authors[$comment->user_id]),
				'created' => $comment->created->format('c'),
				'position' => $comment->position,
			];

		$counts = $TsumegoIssue->getCommentSectionCounts((int) $tsumegoId);

		$this->response->type('json');
		$this->response->body(json_encode([
			'issues' => $issuesJson,
			'standalone' => $standaloneJson,
			'counts' => $counts,
		]));
		return $this->response;
	}

	//The users who wrote the comments, by id, as the rows of the user table
	/**
	 * @param list<TsumegoComment> $comments
	 * @return array<int, array<string, mixed>>
	 */
	private static function authorsOf(array $comments): array
	{
		if (empty($comments))
			return [];
		$authors = [];
		foreach (ClassRegistry::init('User')->find('all', ['conditions' => ['User.id' => array_values(array_unique(array_map(fn($comment) => $comment->user_id, $comments)))]]) as $user)
			$authors[(int) $user['User']['id']] = $user['User'];
		return $authors;
	}
}

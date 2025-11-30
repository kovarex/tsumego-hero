<?php

/**
 * Controller for managing tsumego comments (CRUD operations).
 *
 * Handles adding and deleting comments on tsumego problems.
 * Comments can be standalone or associated with a TsumegoIssue.
 */
class TsumegoCommentsController extends AppController
{
	public $components = ['Flash'];

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
			$this->Flash->error('You must be logged in to comment.');
			return $this->redirect($this->referer());
		}

		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$comment = [
			'tsumego_id' => $this->request->data('Comment.tsumego_id'),
			'message' => $this->request->data('Comment.message'),
			'tsumego_issue_id' => $this->request->data('Comment.tsumego_issue_id'),
			'position' => $this->request->data('Comment.position'),
			'user_id' => Auth::getUserID(),
		];

		$TsumegoComment->create();
		if ($TsumegoComment->save($comment))
			$this->Flash->success('Comment added successfully.');
		else
			$this->Flash->error('Failed to add comment.');

		$redirect = $this->request->data('Comment.redirect') ?: $this->referer();
		return $this->redirect($redirect);
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
		if (!$this->request->is('post'))
			throw new MethodNotAllowedException();

		$TsumegoComment = ClassRegistry::init('TsumegoComment');
		$comment = $TsumegoComment->findById($id);

		if (!$comment)
		{
			$this->Flash->error('Comment not found.');
			return $this->redirect($this->referer());
		}

		// Only admin or comment author can delete
		$isOwner = $comment['TsumegoComment']['user_id'] === Auth::getUserID();
		if (!Auth::isAdmin() && !$isOwner)
		{
			$this->Flash->error('You are not authorized to delete this comment.');
			return $this->redirect($this->referer());
		}

		// Remember the issue ID before deleting
		$issueId = $comment['TsumegoComment']['tsumego_issue_id'];

		// Soft delete
		$TsumegoComment->id = $id;
		if ($TsumegoComment->saveField('deleted', true))
		{
			$this->Flash->success('Comment deleted.');

			// If comment was part of an issue, check if issue is now empty and delete it
			if (!empty($issueId))
			{
				$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
				$TsumegoIssue->deleteIfEmpty($issueId);
			}
		}
		else
			$this->Flash->error('Failed to delete comment.');

		$redirect = $this->request->data('Comment.redirect') ?: $this->referer();
		return $this->redirect($redirect);
	}
}

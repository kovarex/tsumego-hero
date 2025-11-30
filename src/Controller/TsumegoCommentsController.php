<?php

/**
 * Controller for managing tsumego comments (CRUD operations).
 *
 * Handles adding and deleting comments on tsumego problems.
 * Comments can be standalone or associated with a TsumegoIssue.
 *
 * Uses idiomorph for React-like DOM diffing - htmx responses return the full
 * comments section and idiomorph handles efficient DOM updates.
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
		$tsumegoId = $this->request->data('Comment.tsumego_id');
		$comment = [
			'tsumego_id' => $tsumegoId,
			'message' => $this->request->data('Comment.message'),
			'tsumego_issue_id' => $this->request->data('Comment.tsumego_issue_id'),
			'position' => $this->request->data('Comment.position'),
			'user_id' => Auth::getUserID(),
		];

		$TsumegoComment->create();
		if (!$TsumegoComment->save($comment))
		{
			if ($this->isHtmxRequest())
			{
				$this->response->statusCode(422);
				$this->layout = false;
				$this->autoRender = false;
				$this->response->body('<div class="alert alert--error">Failed to add comment.</div>');
				return $this->response;
			}
			$this->Flash->error('Failed to add comment.');
			$redirect = $this->request->data('Comment.redirect') ?: $this->referer();
			return $this->redirect($redirect);
		}

		// For htmx requests, return the full comments section (idiomorph handles the diff)
		if ($this->isHtmxRequest())
			return $this->_renderCommentsSection($tsumegoId);

		$this->Flash->success('Comment added successfully.');
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
			if ($this->isHtmxRequest())
			{
				$this->response->statusCode(404);
				$this->layout = false;
				$this->autoRender = false;
				$this->response->body('');
				return $this->response;
			}
			$this->Flash->error('Comment not found.');
			return $this->redirect($this->referer());
		}

		// Only admin or comment author can delete
		$isOwner = $comment['TsumegoComment']['user_id'] === Auth::getUserID();
		if (!Auth::isAdmin() && !$isOwner)
		{
			if ($this->isHtmxRequest())
			{
				$this->response->statusCode(403);
				$this->layout = false;
				$this->autoRender = false;
				$this->response->body('');
				return $this->response;
			}
			$this->Flash->error('You are not authorized to delete this comment.');
			return $this->redirect($this->referer());
		}

		// Remember the issue ID and tsumego ID before deleting
		$issueId = $comment['TsumegoComment']['tsumego_issue_id'];
		$tsumegoId = $comment['TsumegoComment']['tsumego_id'];

		// Soft delete
		$TsumegoComment->id = $id;
		if ($TsumegoComment->saveField('deleted', true))
		{
			// If comment was part of an issue, check if issue is now empty and delete it
			if (!empty($issueId))
			{
				$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
				$TsumegoIssue->deleteIfEmpty($issueId);
			}

			// For htmx requests, return the full comments section (idiomorph handles the diff)
			if ($this->isHtmxRequest())
				return $this->_renderCommentsSection($tsumegoId);

			$this->Flash->success('Comment deleted.');
		}
		else
		{
			if ($this->isHtmxRequest())
			{
				$this->response->statusCode(500);
				$this->layout = false;
				$this->autoRender = false;
				$this->response->body('');
				return $this->response;
			}
			$this->Flash->error('Failed to delete comment.');
		}

		$redirect = $this->request->data('Comment.redirect') ?: $this->referer();
		return $this->redirect($redirect);
	}

	/**
	 * Render the morphable comments section content for htmx morph responses.
	 *
	 * Loads all comments data and renders just the inner content element.
	 *
	 * @param int $tsumegoId The tsumego ID
	 * @return CakeResponse
	 */
	protected function _renderCommentsSection(int $tsumegoId): CakeResponse
	{
		$Tsumego = ClassRegistry::init('Tsumego');
		$TsumegoIssue = ClassRegistry::init('TsumegoIssue');

		$commentsData = $Tsumego->loadCommentsData($tsumegoId);
		$counts = $TsumegoIssue->getCommentSectionCounts($tsumegoId);

		$this->set('tsumegoId', $tsumegoId);
		$this->set('issues', $commentsData['issues']);
		$this->set('plainComments', $commentsData['plainComments']);
		$this->set('totalCount', $counts['total']);
		$this->set('commentCount', $counts['comments']);
		$this->set('issueCount', $counts['issues']);
		$this->set('openIssueCount', $counts['openIssues']);

		$this->layout = false;
		return $this->render('/Elements/TsumegoComments/section-content');
	}
}

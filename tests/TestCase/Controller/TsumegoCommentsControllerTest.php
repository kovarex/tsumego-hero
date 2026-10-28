<?php

use App\Model\Entity\TsumegoComment;
use App\Utility\Avatar;

class TsumegoCommentsControllerTest extends IntegrationTestCase
{
	public function testAddRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->post('/tsumego-comments/add', json_encode(['tsumego_id' => 1, 'text' => 'hello']));

		$this->assertResponseCode(401);
	}

	public function testAddCommentSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
		]);

		$this->post('/tsumego-comments/add', json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'my comment']));

		$this->assertResponseCode(200);
		$body = json_decode($this->view, true);
		$this->assertSame('my comment', $body['text']);
	}

	public function testCommentByGoogleUserShowsDisplayName()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'GoogleCommenter'],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
		]);

		// Google accounts have no login name, only a display name
		$user = ClassRegistry::init('User');
		$user->id = $context->user['id'];
		$user->saveField('name', null);
		$user->saveField('external_id', '123456789012345678901');

		$this->post('/tsumego-comments/add', json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'from a google account']));

		$body = json_decode($this->view, true);
		$this->assertSame('DN_GoogleCommenter', $body['user_display_name']);
	}

	public function testCommentsIndexSendsAuthorAvatars()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'email' => 'kovarex@example.com'],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'issues' => [['message' => 'an issue']], 'comments' => [['message' => 'a comment']]],
		]);

		$this->get('/tsumego-comments/index/' . $context->tsumegos[0]['id']);

		$body = json_decode($this->view, true);
		$avatar = Avatar::url($context->user['display_name']);
		$this->assertSame($avatar, $body['issues'][0]['user_avatar_url']);
		$this->assertSame($avatar, $body['issues'][0]['comments'][0]['user_avatar_url']);
		$this->assertSame($avatar, $body['standalone'][0]['user_avatar_url']);
	}

	//Each issue lists its comments that are not deleted, in the order they were written
	public function testCommentsIndexGroupsTheCommentsByIssue()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => [
				'set_order' => 1,
				'status' => 'S',
				'issues' => [['message' => 'older issue', 'created' => '2026-01-01 10:00:00'], ['message' => 'newer issue', 'created' => '2026-01-02 10:00:00']],
				'comments' => [['message' => 'standalone']]],
		]);
		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
		foreach (['answer', 'deleted answer'] as $message)
			$tsumegoCommentTable->saveOrFail($tsumegoCommentTable->newEntity([
				'tsumego_id' => $context->tsumegos[0]['id'],
				'tsumego_issue_id' => $context->issues[0]['id'],
				'user_id' => $context->user['id'],
				'message' => $message,
				'deleted' => $message === 'deleted answer' ? '2026-01-03 10:00:00' : null]));

		$this->get('/tsumego-comments/index/' . $context->tsumegos[0]['id']);

		$body = json_decode($this->view, true);
		$this->assertSame([['newer issue'], ['older issue', 'answer']], array_map(fn($issue) => array_column($issue['comments'], 'text'), $body['issues']));
		$this->assertSame(['standalone'], array_column($body['standalone'], 'text'));
	}

	public function testCommentByGoogleUserShowsTheirGooglePicture()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'GoogleCommenter'],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
		]);

		// Google accounts show the picture of their Google profile once they pick it
		$user = ClassRegistry::init('User');
		$user->id = $context->user['id'];
		$user->saveField('external_id', '123456789012345678901');
		$user->saveField('picture', 'https://lh3.googleusercontent.com/a/abc123');
		$user->saveField('avatar_setting_id', User::AVATAR_GOOGLE);

		$this->post('/tsumego-comments/add', json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'from a google account']));

		$body = json_decode($this->view, true);
		$this->assertSame('https://lh3.googleusercontent.com/a/abc123=s48', $body['user_avatar_url']);
	}

	public function testDeleteNotFound()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->post('/tsumego-comments/delete/999999');

		$this->assertResponseCode(404);
	}

	public function testDeleteForbiddenForCommentByOtherUser()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [['name' => 'Ivan Detkov']],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'comments' => [['message' => 'hi']]],
		]);

		$this->fetchTable('TsumegoComment')->updateAll(['user_id' => $context->otherUsers[0]['id']], []);
		$comment = $this->fetchTable('TsumegoComment')->find()->firstOrFail();

		$this->post('/tsumego-comments/delete/' . $comment->id);

		$this->assertResponseCode(403);
	}

	public function testDeleteOwnCommentSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'comments' => [['message' => 'mine']]],
		]);

		$comment = $this->fetchTable('TsumegoComment')->find()->firstOrFail();

		$this->post('/tsumego-comments/delete/' . $comment->id);

		$this->assertResponseCode(200);
		/** @var TsumegoComment $deleted */
		$deleted = $this->fetchTable('TsumegoComment')->get($comment->id);
		$this->assertNotNull($deleted->deleted, 'The comment keeps the time it was deleted');
	}
}

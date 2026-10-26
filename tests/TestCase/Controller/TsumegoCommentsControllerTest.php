<?php

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
		$user->saveField('pref_avatar', User::AVATAR_GOOGLE);

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

		$comment = ClassRegistry::init('TsumegoComment')->find('first');
		$comment['TsumegoComment']['user_id'] = $context->otherUsers[0]['id'];
		ClassRegistry::init('TsumegoComment')->save($comment);

		$this->post('/tsumego-comments/delete/' . $comment['TsumegoComment']['id']);

		$this->assertResponseCode(403);
	}

	public function testDeleteOwnCommentSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'comments' => [['message' => 'mine']]],
		]);

		$comment = ClassRegistry::init('TsumegoComment')->find('first');

		$this->post('/tsumego-comments/delete/' . $comment['TsumegoComment']['id']);

		$this->assertResponseCode(200);
		$deleted = ClassRegistry::init('TsumegoComment')->findById($comment['TsumegoComment']['id']);
		$this->assertSame(1, (int) $deleted['TsumegoComment']['deleted']);
	}
}

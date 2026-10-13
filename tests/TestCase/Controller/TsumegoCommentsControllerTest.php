<?php

class TsumegoCommentsControllerTest extends ControllerTestCase
{
	public function testAddRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/tsumego-comments/add', [
			'method' => 'post',
			'data' => json_encode(['tsumego_id' => 1, 'text' => 'hello']),
		]);
	}

	public function testAddCommentSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
		]);

		$this->testAction('/tsumego-comments/add', [
			'method' => 'post',
			'data' => json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'my comment']),
		]);

		$this->assertSame(200, $this->controller->response->statusCode());
		$body = json_decode($this->controller->response->body(), true);
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

		$this->testAction('/tsumego-comments/add', [
			'method' => 'post',
			'data' => json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'from a google account']),
		]);

		$body = json_decode($this->controller->response->body(), true);
		$this->assertSame('DN_GoogleCommenter', $body['user_name']);
	}

	public function testCommentsIndexSendsAuthorAvatars()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'email' => 'kovarex@example.com'],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'issues' => [['message' => 'an issue']], 'comments' => [['message' => 'a comment']]],
		]);

		$this->testAction('/tsumego-comments/index/' . $context->tsumegos[0]['id'], ['method' => 'get']);

		$body = json_decode($this->controller->response->body(), true);
		$avatar = 'https://www.gravatar.com/avatar/' . md5('kovarex@example.com') . '?s=20&d=identicon';
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

		// Google accounts show the picture of their Google profile
		$user = ClassRegistry::init('User');
		$user->id = $context->user['id'];
		$user->saveField('external_id', '123456789012345678901');
		$user->saveField('picture', 'https://lh3.googleusercontent.com/a/abc123');

		$this->testAction('/tsumego-comments/add', [
			'method' => 'post',
			'data' => json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'from a google account']),
		]);

		$body = json_decode($this->controller->response->body(), true);
		$this->assertSame('https://lh3.googleusercontent.com/a/abc123=s20', $body['user_avatar_url']);
	}

	public function testDeleteNotFound()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->expectException(NotFoundException::class);

		$this->testAction('/tsumego-comments/delete/999999', ['method' => 'post']);
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

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tsumego-comments/delete/' . $comment['TsumegoComment']['id'], ['method' => 'post']);
	}

	public function testDeleteOwnCommentSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'comments' => [['message' => 'mine']]],
		]);

		$comment = ClassRegistry::init('TsumegoComment')->find('first');

		$this->testAction('/tsumego-comments/delete/' . $comment['TsumegoComment']['id'], ['method' => 'post']);

		$this->assertSame(200, $this->controller->response->statusCode());
		$deleted = ClassRegistry::init('TsumegoComment')->findById($comment['TsumegoComment']['id']);
		$this->assertSame(1, (int) $deleted['TsumegoComment']['deleted']);
	}
}

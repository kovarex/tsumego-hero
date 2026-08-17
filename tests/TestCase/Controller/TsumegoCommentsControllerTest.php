<?php

App::uses('ForbiddenException', 'Routing/Error');
App::uses('NotFoundException', 'Routing/Error');
App::uses('UnauthorizedException', 'Routing/Error');

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

<?php

App::uses('ForbiddenException', 'Routing/Error');
App::uses('UnauthorizedException', 'Routing/Error');

class TagConnectionControllerTest extends ControllerTestCase
{
	public function testAddRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/tagConnection/add/1/snapback', ['method' => 'post']);
	}

	public function testAddRequiresContributionPermission()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'lowrating', 'rating' => 100],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
			'tags' => [['name' => 'snapback']],
		]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/snapback', ['method' => 'post']);
	}

	public function testAddWithUnknownTag()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
		]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/nonexistent-tag', ['method' => 'post']);
	}

	public function testAddSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
			'tags' => [['name' => 'snapback']],
		]);

		$this->testAction('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/snapback', ['method' => 'post']);

		$this->assertSame(200, $this->controller->response->statusCode());
		$connection = ClassRegistry::init('TagConnection')->find('first', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertNotEmpty($connection);
	}

	public function testRemoveRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tagConnection/remove/1/snapback', ['method' => 'post']);
	}

	public function testRemoveSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'tags' => [['name' => 'snapback', 'approved' => 0]]],
		]);

		$this->testAction('/tagConnection/remove/' . $context->tsumegos[0]['id'] . '/snapback', ['method' => 'post']);

		$this->assertSame(200, $this->controller->response->statusCode());
		$connection = ClassRegistry::init('TagConnection')->find('first', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertEmpty($connection);
	}
}

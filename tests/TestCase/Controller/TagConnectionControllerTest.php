<?php

App::uses('ForbiddenException', 'Routing/Error');
App::uses('UnauthorizedException', 'Routing/Error');
App::uses('Constants', 'Utility');

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

	public function testAddBlockedWhenDailyLimitReached()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'rater', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
			'tags' => [],
		]);

		$tc = ClassRegistry::init('TagConnection');
		$tag = ClassRegistry::init('Tag');
		$today = gmdate('Y-m-d H:i:s');

		for ($i = 0; $i < Constants::$DAILY_TAG_LIMIT; $i++)
		{
			$tag->create();
			$tag->save(['Tag' => ['name' => "limit-tag-$i", 'description' => '']]);
			$tagId = $tag->getLastInsertId();

			$tc->create();
			$tc->save(['TagConnection' => [
				'tag_id' => $tagId,
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => $context->user['id'],
				'approved' => 0,
				'created' => $today,
			]]);
		}

		// Create one more tag to try to add
		$tag->create();
		$tag->save(['Tag' => ['name' => 'one-too-many', 'description' => '']]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/one-too-many', ['method' => 'post']);
	}

	public function testAddAllowedWhenUnderDailyLimit()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'underlimit', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
			'tags' => [['name' => 'fresh-tag']],
		]);

		$this->testAction('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/fresh-tag', ['method' => 'post']);

		$this->assertSame(200, $this->controller->response->statusCode());
	}
}

<?php

use App\Utility\Constants;

class TagConnectionControllerTest extends IntegrationTestCase
{
	public function testAddRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->post('/tagConnection/add/1/snapback');

		$this->assertResponseCode(401);
	}

	public function testAddRequiresContributionPermission()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'lowrating', 'rating' => 100],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
			'tags' => [['name' => 'snapback']],
		]);

		$this->post('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/snapback');

		$this->assertResponseCode(403);
	}

	public function testAddWithUnknownTag()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
		]);

		$this->post('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/nonexistent-tag');

		$this->assertResponseCode(403);
	}

	public function testAddSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
			'tags' => [['name' => 'snapback']],
		]);

		$this->post('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/snapback');

		$this->assertResponseCode(200);
		$connection = ClassRegistry::init('TagConnection')->find('first', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertNotEmpty($connection);
	}

	public function testRemoveRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->post('/tagConnection/remove/1/snapback');

		$this->assertResponseCode(403);
	}

	public function testRemoveSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'tags' => [['name' => 'snapback', 'approved' => 0]]],
		]);

		$this->post('/tagConnection/remove/' . $context->tsumegos[0]['id'] . '/snapback');

		$this->assertResponseCode(200);
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

		$this->post('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/one-too-many');

		$this->assertResponseCode(403);
	}

	public function testAddAllowedWhenUnderDailyLimit()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'underlimit', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
			'tags' => [['name' => 'fresh-tag']],
		]);

		$this->post('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/fresh-tag');

		$this->assertResponseCode(200);
	}
}

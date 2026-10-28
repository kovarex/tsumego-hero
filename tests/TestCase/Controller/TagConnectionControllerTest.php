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

	//Tags are hints, so only a player who solved the problem adds them (forum#94)
	public function testAddRequiresTheProblemSolved()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'V'],
			'tags' => [['name' => 'snapback']],
		]);

		$this->post('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/snapback');

		$this->assertResponseCode(403);
		$this->assertSame(0, $this->fetchTable('TagConnection')->find()->where(['tsumego_id' => $context->tsumegos[0]['id']])->count());
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
		$this->assertTrue($this->fetchTable('TagConnection')->exists(['tsumego_id' => $context->tsumegos[0]['id']]));
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
		$this->assertFalse($this->fetchTable('TagConnection')->exists(['tsumego_id' => $context->tsumegos[0]['id']]));
	}

	public function testAddBlockedWhenDailyLimitReached()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'rater', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
			'tags' => [],
		]);

		$tagConnectionTable = $this->fetchTable('TagConnection');
		$tagTable = $this->fetchTable('Tag');
		$today = gmdate('Y-m-d H:i:s');

		for ($i = 0; $i < Constants::$DAILY_TAG_LIMIT; $i++)
		{
			$tagId = $tagTable->saveOrFail($tagTable->newEntity(['name' => "limit-tag-$i", 'description' => '']))->id;

			$tagConnectionTable->saveOrFail($tagConnectionTable->newEntity([
				'tag_id' => $tagId,
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => $context->user['id'],
				'approved' => 0,
				'created' => $today,
			]));
		}

		// Create one more tag to try to add
		$tagTable->saveOrFail($tagTable->newEntity(['name' => 'one-too-many', 'description' => '']));

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

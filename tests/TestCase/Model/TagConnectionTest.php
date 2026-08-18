<?php

App::uses('TagConnection', 'Model');
App::uses('Constants', 'Utility');

class TagConnectionTest extends CakeTestCase
{
	public function testCountTodaysTagsReturnsZeroWhenNone()
	{
		new ContextPreparator(['user' => ['name' => 'counter', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE]]);

		$this->assertSame(0, TagConnection::countTodaysTags(Auth::getUserID()));
	}

	public function testCountTodaysTagsCountsOnlyToday()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'counter', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1],
			'tags' => [['name' => 'atari'], ['name' => 'snapback'], ['name' => 'ko']],
		]);

		$tc = ClassRegistry::init('TagConnection');
		$today = gmdate('Y-m-d H:i:s');
		$yesterday = gmdate('Y-m-d H:i:s', strtotime('-1 day'));

		// Two today
		$tc->create();
		$tc->save(['TagConnection' => [
			'tag_id' => $context->tags[0]['id'],
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => $context->user['id'],
			'approved' => 0,
			'created' => $today,
		]]);
		$tc->create();
		$tc->save(['TagConnection' => [
			'tag_id' => $context->tags[1]['id'],
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => $context->user['id'],
			'approved' => 0,
			'created' => $today,
		]]);

		// One yesterday
		$tc->create();
		$tc->save(['TagConnection' => [
			'tag_id' => $context->tags[2]['id'],
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => $context->user['id'],
			'approved' => 0,
			'created' => $yesterday,
		]]);

		$this->assertSame(2, TagConnection::countTodaysTags($context->user['id']));
	}

	public function testCanUserAddTagAllowsWhenUnderLimit()
	{
		new ContextPreparator(['user' => ['name' => 'under', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE]]);

		$this->assertTrue(TagConnection::canUserAddTag(Auth::getUserID()));
	}

	public function testCanUserAddTagBlocksAtLimit()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'atlimit', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1],
			'tags' => [],
		]);

		// Create DAILY_TAG_LIMIT tags today
		$tc = ClassRegistry::init('TagConnection');
		$tag = ClassRegistry::init('Tag');
		$today = gmdate('Y-m-d H:i:s');
		for ($i = 0; $i < Constants::$DAILY_TAG_LIMIT; $i++)
		{
			$tag->create();
			$tag->save(['Tag' => ['name' => "rate-tag-$i", 'description' => '']]);
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

		$this->assertSame(Constants::$DAILY_TAG_LIMIT, TagConnection::countTodaysTags($context->user['id']));
		$this->assertFalse(TagConnection::canUserAddTag($context->user['id']));
	}

	public function testCanUserAddTagBypassesLimitForAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'adminuser', 'admin' => true],
			'tsumego' => ['set_order' => 1],
			'tags' => [],
		]);

		$tc = ClassRegistry::init('TagConnection');
		$tag = ClassRegistry::init('Tag');
		$today = gmdate('Y-m-d H:i:s');
		for ($i = 0; $i < Constants::$DAILY_TAG_LIMIT + 5; $i++)
		{
			$tag->create();
			$tag->save(['Tag' => ['name' => "admin-tag-$i", 'description' => '']]);
			$tagId = $tag->getLastInsertId();

			$tc->create();
			$tc->save(['TagConnection' => [
				'tag_id' => $tagId,
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => $context->user['id'],
				'approved' => 1,
				'created' => $today,
			]]);
		}

		$this->assertTrue(TagConnection::canUserAddTag($context->user['id']));
	}

	public function testYesterdayTagsDoNotCountTowardTodayLimit()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'yesterday', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1],
			'tags' => [],
		]);

		$tc = ClassRegistry::init('TagConnection');
		$tag = ClassRegistry::init('Tag');
		$yesterday = gmdate('Y-m-d H:i:s', strtotime('-1 day'));

		// Create DAILY_TAG_LIMIT tags yesterday
		for ($i = 0; $i < Constants::$DAILY_TAG_LIMIT; $i++)
		{
			$tag->create();
			$tag->save(['Tag' => ['name' => "old-tag-$i", 'description' => '']]);
			$tagId = $tag->getLastInsertId();

			$tc->create();
			$tc->save(['TagConnection' => [
				'tag_id' => $tagId,
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => $context->user['id'],
				'approved' => 0,
				'created' => $yesterday,
			]]);
		}

		$this->assertSame(0, TagConnection::countTodaysTags($context->user['id']));
		$this->assertTrue(TagConnection::canUserAddTag($context->user['id']));
	}
}

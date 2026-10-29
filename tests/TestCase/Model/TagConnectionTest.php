<?php

use App\Model\Table\TagConnectionTable;
use App\Utility\Auth;
use App\Utility\Constants;
use Cake\ORM\TableRegistry;

class TagConnectionTest extends AppTestCase
{
	public function testCountTodaysTagsReturnsZeroWhenNone()
	{
		new ContextPreparator(['user' => ['name' => 'counter', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE]]);

		$this->assertSame(0, TagConnectionTable::countTodaysTags(Auth::getUserID()));
	}

	public function testCountTodaysTagsCountsOnlyToday()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'counter', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1],
			'tags' => [['name' => 'atari'], ['name' => 'snapback'], ['name' => 'ko']],
		]);

		$tagConnectionTable = TableRegistry::getTableLocator()->get('TagConnection');
		$today = gmdate('Y-m-d H:i:s');
		$yesterday = gmdate('Y-m-d H:i:s', strtotime('-1 day'));

		// Two today
		$tagConnectionTable->saveOrFail($tagConnectionTable->newEntity([
			'tag_id' => $context->tags[0]->id,
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => $context->user['id'],
			'approved' => 0,
			'created' => $today,
		]));
		$tagConnectionTable->saveOrFail($tagConnectionTable->newEntity([
			'tag_id' => $context->tags[1]->id,
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => $context->user['id'],
			'approved' => 0,
			'created' => $today,
		]));

		// One yesterday
		$tagConnectionTable->saveOrFail($tagConnectionTable->newEntity([
			'tag_id' => $context->tags[2]->id,
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => $context->user['id'],
			'approved' => 0,
			'created' => $yesterday,
		]));

		$this->assertSame(2, TagConnectionTable::countTodaysTags($context->user['id']));
	}

	public function testCanUserAddTagAllowsWhenUnderLimit()
	{
		new ContextPreparator(['user' => ['name' => 'under', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE]]);

		$this->assertTrue(TagConnectionTable::canCurrentUserAddTag());
	}

	public function testCanUserAddTagBlocksAtLimit()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'atlimit', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1],
			'tags' => [],
		]);

		// Create DAILY_TAG_LIMIT tags today
		$tagConnectionTable = TableRegistry::getTableLocator()->get('TagConnection');
		$tagTable = TableRegistry::getTableLocator()->get('Tag');
		$today = gmdate('Y-m-d H:i:s');
		for ($i = 0; $i < Constants::$DAILY_TAG_LIMIT; $i++)
		{
			$tagId = $tagTable->saveOrFail($tagTable->newEntity(['name' => "rate-tag-$i", 'description' => '']))->id;

			$tagConnectionTable->saveOrFail($tagConnectionTable->newEntity([
				'tag_id' => $tagId,
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => $context->user['id'],
				'approved' => 0,
				'created' => $today,
			]));
		}

		$this->assertSame(Constants::$DAILY_TAG_LIMIT, TagConnectionTable::countTodaysTags($context->user['id']));
		$this->assertFalse(TagConnectionTable::canCurrentUserAddTag());
	}

	public function testCanUserAddTagBypassesLimitForAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'adminuser', 'admin' => true],
			'tsumego' => ['set_order' => 1],
			'tags' => [],
		]);

		$tagConnectionTable = TableRegistry::getTableLocator()->get('TagConnection');
		$tagTable = TableRegistry::getTableLocator()->get('Tag');
		$today = gmdate('Y-m-d H:i:s');
		for ($i = 0; $i < Constants::$DAILY_TAG_LIMIT + 5; $i++)
		{
			$tagId = $tagTable->saveOrFail($tagTable->newEntity(['name' => "admin-tag-$i", 'description' => '']))->id;

			$tagConnectionTable->saveOrFail($tagConnectionTable->newEntity([
				'tag_id' => $tagId,
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => $context->user['id'],
				'approved' => 1,
				'created' => $today,
			]));
		}

		$this->assertTrue(TagConnectionTable::canCurrentUserAddTag());
	}

	public function testYesterdayTagsDoNotCountTowardTodayLimit()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'yesterday', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1],
			'tags' => [],
		]);

		$tagConnectionTable = TableRegistry::getTableLocator()->get('TagConnection');
		$tagTable = TableRegistry::getTableLocator()->get('Tag');
		$yesterday = gmdate('Y-m-d H:i:s', strtotime('-1 day'));

		// Create DAILY_TAG_LIMIT tags yesterday
		for ($i = 0; $i < Constants::$DAILY_TAG_LIMIT; $i++)
		{
			$tagId = $tagTable->saveOrFail($tagTable->newEntity(['name' => "old-tag-$i", 'description' => '']))->id;

			$tagConnectionTable->saveOrFail($tagConnectionTable->newEntity([
				'tag_id' => $tagId,
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => $context->user['id'],
				'approved' => 0,
				'created' => $yesterday,
			]));
		}

		$this->assertSame(0, TagConnectionTable::countTodaysTags($context->user['id']));
		$this->assertTrue(TagConnectionTable::canCurrentUserAddTag());
	}
}

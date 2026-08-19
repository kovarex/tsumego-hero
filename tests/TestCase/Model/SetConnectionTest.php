<?php

App::uses('SetConnection', 'Model');

class SetConnectionTest extends CakeTestCase
{
	public function testDisplayPrefersOfficialOverFavorite()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'display', 'rating' => 1000],
			'tsumego' => [
				'sets' => [
					['name' => 'official', 'public' => 1, 'order' => 10006, 'num' => 3],
					['name' => 'favorite', 'public' => 0, 'user_id' => 'self', 'order' => 5, 'num' => 1],
				],
			],
		]);

		$display = ClassRegistry::init('SetConnection')->findDisplaySetConnection($context->tsumegos[0]['id']);

		$this->assertSame($context->tsumegos[0]['sets'][0]['id'], $display['SetConnection']['set_id']);
	}

	public function testDisplayPrefersUnownedOverOwnedWhenPrivate()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'display', 'rating' => 1000],
			'tsumego' => [
				'sets' => [
					['name' => 'sandbox', 'public' => 0, 'order' => 999, 'num' => 1],
					['name' => 'favorite', 'public' => 0, 'user_id' => 'self', 'order' => 5, 'num' => 1],
				],
			],
		]);

		$display = ClassRegistry::init('SetConnection')->findDisplaySetConnection($context->tsumegos[0]['id']);

		$this->assertSame($context->tsumegos[0]['sets'][0]['id'], $display['SetConnection']['set_id']);
	}

	public function testDisplayPrefersLowerOrderAmongOfficial()
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'sets' => [
					['name' => 'later', 'public' => 1, 'order' => 20, 'num' => 1],
					['name' => 'earlier', 'public' => 1, 'order' => 5, 'num' => 1],
				],
			],
		]);

		$display = ClassRegistry::init('SetConnection')->findDisplaySetConnection($context->tsumegos[0]['id']);

		$this->assertSame($context->tsumegos[0]['sets'][1]['id'], $display['SetConnection']['set_id']);
	}

	public function testDisplayPrefersFavoriteOverDeleted()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'display', 'rating' => 1000],
			'tsumego' => [
				'sets' => [
					['name' => 'deleted', 'public' => -1, 'order' => 5, 'num' => 1],
					['name' => 'favorite', 'public' => 0, 'user_id' => 'self', 'order' => 5, 'num' => 1],
				],
			],
		]);

		$display = ClassRegistry::init('SetConnection')->findDisplaySetConnection($context->tsumegos[0]['id']);

		$this->assertSame($context->tsumegos[0]['sets'][1]['id'], $display['SetConnection']['set_id']);
	}

	public function testDisplayPutsNullOrderLast()
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'sets' => [
					['name' => 'ordered', 'public' => 1, 'order' => 5, 'num' => 1],
				],
			],
		]);

		$set = ClassRegistry::init('Set');
		$set->create();
		$set->save(['title' => 'unordered', 'public' => 1, 'order' => null]);

		$connection = ClassRegistry::init('SetConnection');
		$connection->create();
		$connection->save(['tsumego_id' => $context->tsumegos[0]['id'], 'set_id' => $set->id, 'num' => 1]);

		$display = $connection->findDisplaySetConnection($context->tsumegos[0]['id']);

		$this->assertSame($context->tsumegos[0]['sets'][0]['id'], $display['SetConnection']['set_id']);
	}

	public function testDisplayBreaksOrderTiesByEarliestSet()
	{
		$tsumego = ClassRegistry::init('Tsumego');
		$tsumego->create();
		$tsumego->save(['description' => 'tie']);

		$set = ClassRegistry::init('Set');
		$set->create();
		$set->save(['title' => 'earliest', 'public' => 1, 'order' => 10]);
		$earliestSetId = (int) $set->id;
		$set->create();
		$set->save(['title' => 'latest', 'public' => 1, 'order' => 10]);
		$latestSetId = (int) $set->id;

		$connection = ClassRegistry::init('SetConnection');
		$connection->create();
		$connection->save(['tsumego_id' => $tsumego->id, 'set_id' => $latestSetId, 'num' => 1]);
		$connection->create();
		$connection->save(['tsumego_id' => $tsumego->id, 'set_id' => $earliestSetId, 'num' => 1]);

		$display = $connection->findDisplaySetConnection($tsumego->id);

		$this->assertSame($earliestSetId, (int) $display['SetConnection']['set_id']);
	}
}

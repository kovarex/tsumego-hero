<?php

App::uses('TsumegoMerger', 'Utility');

class FavoritesViewTest extends TestCaseWithAuth
{
	public function testFavoritesRedirectsToDefaultSet()
	{
		new ContextPreparator(['user' => ['name' => 'nofavs', 'rating' => 1500]]);

		$this->testAction('/sets/view/favorites');

		// Should redirect to the default Favorites set (created lazily)
		$redirectUrl = $this->headers['Location'] ?? '';
		$this->assertNotEmpty($redirectUrl);
		$this->assertStringContainsString('/sets/view/', $redirectUrl);
	}

	public function testFavoritesWithMixedStatuses()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'favuser', 'rating' => 1500],
			'tsumegos' => [
				['set_order' => 1, 'status' => 'S', 'sets' => [['name' => 'Favorites', 'num' => 1, 'user_id' => 'self', 'public' => 0]]],
				['set_order' => 2, 'sets' => [['name' => 'Favorites', 'num' => 2, 'user_id' => 'self', 'public' => 0]]],
				['set_order' => 3, 'status' => 'W', 'sets' => [['name' => 'Favorites', 'num' => 3, 'user_id' => 'self', 'public' => 0]]],
			],
		]);

		$favSetId = $context->user['default_set_id'];
		$this->testAction('/sets/view/' . $favSetId, ['return' => 'view']);
		$this->assertTextContains('Favorites', $this->view);
		$this->assertStringContainsString('statusS', $this->view);
		$this->assertStringContainsString('statusN', $this->view);
		$this->assertStringContainsString('statusW', $this->view);
	}

	public function testFavoritesSolvedCount()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'solver', 'rating' => 1500],
			'tsumegos' => [
				['set_order' => 1, 'status' => 'S', 'sets' => [['name' => 'Favorites', 'num' => 1, 'user_id' => 'self', 'public' => 0]]],
				['set_order' => 2, 'status' => 'S', 'sets' => [['name' => 'Favorites', 'num' => 2, 'user_id' => 'self', 'public' => 0]]],
				['set_order' => 3, 'sets' => [['name' => 'Favorites', 'num' => 3, 'user_id' => 'self', 'public' => 0]]],
			],
		]);

		$favSetId = $context->user['default_set_id'];
		$this->testAction('/sets/view/' . $favSetId, ['return' => 'view']);
		$this->assertStringContainsString('66', $this->view);
		$this->assertStringContainsString('Favorites', $this->view);
	}

	// ── Heart flag: lit when in ANY set ──────────────────────────────────

	public function testHeartFlagTrueWhenInAnySet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'sets' => [['name' => 'Playable Set', 'num' => 1]]],
		]);
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];

		// Create a non-Favorites set and add the tsumego to it
		$setModel = ClassRegistry::init('Set');
		$setModel->create();
		$setModel->save(['Set' => [
			'user_id' => $context->user['id'],
			'title' => 'Hard Problems',
			'public' => 0,
			'order' => Constants::$DEFAULT_SET_ORDER,
		]]);
		$scModel = ClassRegistry::init('SetConnection');
		$scModel->create();
		$scModel->save(['SetConnection' => ['set_id' => $setModel->id, 'tsumego_id' => $tsumegoId, 'num' => 2]]);

		$result = $this->testAction('/tsumegos/play/' . $tsumegoId, ['return' => 'vars']);
		$sets = json_decode($result['userSetsJson'], true);
		$this->assertNotEmpty($sets);
		$this->assertTrue(in_array(true, array_column($sets, 'contains'), true));
	}

	public function testHeartFlagFalseWhenInNoSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'sets' => [['name' => 'Playable Set', 'num' => 1]]],
		]);

		$result = $this->testAction('/tsumegos/play/' . $context->tsumegos[0]['id'], ['return' => 'vars']);
		$sets = json_decode($result['userSetsJson'], true);
		// No user sets created, so userSetsJson should be empty
		$this->assertEmpty($sets);
	}

	public function testHeartFlagTrueWhenInMultipleSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'sets' => [['name' => 'Playable Set', 'num' => 1]]],
		]);
		$tsumegoId = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC'])['Tsumego']['id'];

		foreach (['Favorites', 'Hard Problems'] as $title)
		{
			$setModel = ClassRegistry::init('Set');
			$setModel->create();
			$setModel->save(['Set' => [
				'user_id' => $context->user['id'],
				'title' => $title,
				'public' => 0,
				'order' => Constants::$DEFAULT_SET_ORDER,
			]]);
			$scModel = ClassRegistry::init('SetConnection');
			$scModel->create();
			$scModel->save(['SetConnection' => ['set_id' => $setModel->id, 'tsumego_id' => $tsumegoId, 'num' => 2]]);
		}

		$result = $this->testAction('/tsumegos/play/' . $tsumegoId, ['return' => 'vars']);
		$sets = json_decode($result['userSetsJson'], true);
		$contains = array_column($sets, 'contains');
		$this->assertCount(2, array_filter($contains));
	}

	// ── Merge: favorites repointed when tsumegos merged ──────────────────

	public function testMergeFavoritesSlaveOnlyRepointedToMaster(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'merger'],
			'tsumegos' => [
				['sgf' => '(;GM[1]FF[4]SZ[19];B[aa])', 'sets' => [['name' => 'Playable Set', 'num' => 1]]],
				['sgf' => '(;GM[1]FF[4]SZ[19];B[bb])', 'sets' => [['name' => 'Playable Set', 'num' => 2],
					['name' => 'Favorites', 'num' => 1, 'user_id' => 'self', 'public' => 0]]],
			],
		]);

		$masterId = $context->tsumegos[0]['id'];
		$slaveId = $context->tsumegos[1]['id'];
		$favSetId = $context->user['default_set_id'];

		// Slave only is in Favorites — after merge, master should be
		(new TsumegoMerger($masterId, $slaveId))->execute();

		$conn = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $favSetId],
		]);
		$this->assertNotEmpty($conn);
		$this->assertEquals($masterId, $conn['SetConnection']['tsumego_id']);
	}

	public function testMergeFavoritesBothInSetDeduplicates(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'merger'],
			'tsumegos' => [
				['sgf' => '(;GM[1]FF[4]SZ[19];B[aa])', 'sets' => [['name' => 'Playable Set', 'num' => 1],
					['name' => 'Favorites', 'num' => 1, 'user_id' => 'self', 'public' => 0]]],
				['sgf' => '(;GM[1]FF[4]SZ[19];B[bb])', 'sets' => [['name' => 'Playable Set', 'num' => 2],
					['name' => 'Favorites', 'num' => 2, 'user_id' => 'self', 'public' => 0]]],
			],
		]);

		$masterId = $context->tsumegos[0]['id'];
		$slaveId = $context->tsumegos[1]['id'];
		$favSetId = $context->user['default_set_id'];

		(new TsumegoMerger($masterId, $slaveId))->execute();

		$connections = ClassRegistry::init('SetConnection')->find('all', [
			'conditions' => ['set_id' => $favSetId],
		]);
		$this->assertCount(1, $connections);
		$this->assertEquals($masterId, $connections[0]['SetConnection']['tsumego_id']);
	}

	public function testMergeFavoritesIgnoresSandboxSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'merger'],
			'tsumegos' => [
				['sgf' => '(;GM[1]FF[4]SZ[19];B[aa])', 'sets' => [['name' => 'Playable Set', 'num' => 1]]],
				['sgf' => '(;GM[1]FF[4]SZ[19];B[bb])', 'sets' => [['name' => 'Playable Set', 'num' => 2],
					['name' => 'sandbox set', 'num' => 1, 'public' => 0]]],
			],
		]);

		$masterId = $context->tsumegos[0]['id'];
		$slaveId = $context->tsumegos[1]['id'];

		// Sandbox set (user_id=null) should not be affected
		$sandboxSetId = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['title' => 'sandbox set'],
		])['Set']['id'];

		(new TsumegoMerger($masterId, $slaveId))->execute();

		// Sandbox connection untouched by mergeFavorites, slave cascade doesn't fire in tests
		$conn = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $sandboxSetId],
		]);
		$this->assertNotEmpty($conn);
	}
}

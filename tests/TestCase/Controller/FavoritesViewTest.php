<?php


use App\Utility\Constants;
use App\Utility\TsumegoMerger;

class FavoritesViewTest extends IntegrationTestCase
{
	public function testFavoritesRedirectsToDefaultSet()
	{
		new ContextPreparator(['user' => ['name' => 'nofavs', 'rating' => 1500]]);

		$this->get('/sets/view/favorites');

		// Should redirect to the default Favorites set (created lazily)
		$this->assertMatchesRegularExpression('#/sets/\d+$#', (string) $this->headerValue('Location'));
	}

	// ── Heart flag: lit when in ANY set ──────────────────────────────────

	public function testHeartFlagTrueWhenInAnySet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'sets' => [['name' => 'Playable Set', 'num' => 1]]],
		]);
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;

		// Create a non-Favorites set and add the tsumego to it
		$setTable = $this->fetchTable('Set');
		$createdSet = $setTable->saveOrFail($setTable->newEntity([
			'user_id' => $context->user['id'],
			'title' => 'Hard Problems',
			'public' => 0,
			'order' => Constants::$DEFAULT_SET_ORDER,
		]));
		$setConnectionTable = $this->fetchTable('SetConnection');
		$setConnectionTable->saveOrFail($setConnectionTable->newEntity(['set_id' => $createdSet->id, 'tsumego_id' => $tsumegoId, 'num' => 2]));

		$this->get('/tsumegos/play/' . $tsumegoId);
		$sets = json_decode($this->viewVariable('userSetsJson'), true);
		$this->assertNotEmpty($sets);
		$this->assertTrue(in_array(true, array_column($sets, 'contains'), true));
	}

	public function testHeartFlagFalseWhenInNoSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'sets' => [['name' => 'Playable Set', 'num' => 1]]],
		]);

		$this->get('/tsumegos/play/' . $context->tsumegos[0]['id']);
		$sets = json_decode($this->viewVariable('userSetsJson'), true);
		// No user sets created, so userSetsJson should be empty
		$this->assertEmpty($sets);
	}

	public function testHeartFlagTrueWhenInMultipleSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'sets' => [['name' => 'Playable Set', 'num' => 1]]],
		]);
		$tsumegoId = $this->fetchTable('Tsumego')->find()->orderByDesc('id')->first()->id;

		foreach (['Favorites', 'Hard Problems'] as $title)
		{
			$setTable = $this->fetchTable('Set');
			$createdSet = $setTable->saveOrFail($setTable->newEntity([
				'user_id' => $context->user['id'],
				'title' => $title,
				'public' => 0,
				'order' => Constants::$DEFAULT_SET_ORDER,
			]));
			$setConnectionTable = $this->fetchTable('SetConnection');
			$setConnectionTable->saveOrFail($setConnectionTable->newEntity(['set_id' => $createdSet->id, 'tsumego_id' => $tsumegoId, 'num' => 2]));
		}

		$this->get('/tsumegos/play/' . $tsumegoId);
		$sets = json_decode($this->viewVariable('userSetsJson'), true);
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
					['name' => 'Favorites', 'num' => 1, 'user_id' => 'self', 'public' => 0, 'default' => true]]],
			],
		]);

		$masterId = $context->tsumegos[0]['id'];
		$slaveId = $context->tsumegos[1]['id'];
		$favSetId = $context->user['default_set_id'];

		// Slave only is in Favorites — after merge, master should be
		(new TsumegoMerger($masterId, $slaveId))->execute();

		$setConnection = $this->fetchTable('SetConnection')->find()->where(['set_id' => $favSetId])->first();
		$this->assertNotEmpty($setConnection);
		$this->assertEquals($masterId, $setConnection->tsumego_id);
	}

	public function testMergeFavoritesBothInSetDeduplicates(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'merger'],
			'tsumegos' => [
				['sgf' => '(;GM[1]FF[4]SZ[19];B[aa])', 'sets' => [['name' => 'Playable Set', 'num' => 1],
					['name' => 'Favorites', 'num' => 1, 'user_id' => 'self', 'public' => 0, 'default' => true]]],
				['sgf' => '(;GM[1]FF[4]SZ[19];B[bb])', 'sets' => [['name' => 'Playable Set', 'num' => 2],
					['name' => 'Favorites', 'num' => 2, 'user_id' => 'self', 'public' => 0]]],
			],
		]);

		$masterId = $context->tsumegos[0]['id'];
		$slaveId = $context->tsumegos[1]['id'];
		$favSetId = $context->user['default_set_id'];

		(new TsumegoMerger($masterId, $slaveId))->execute();

		$connections = $this->fetchTable('SetConnection')->find()->where(['set_id' => $favSetId])->toArray();
		$this->assertCount(1, $connections);
		$this->assertEquals($masterId, $connections[0]->tsumego_id);
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
		$sandboxSetId = $this->fetchTable('Set')->find()->where(['title' => 'sandbox set'])->first()->id;

		(new TsumegoMerger($masterId, $slaveId))->execute();

		// Sandbox connection untouched by mergeFavorites, slave cascade doesn't fire in tests
		$setConnection = $this->fetchTable('SetConnection')->find()->where(['set_id' => $sandboxSetId])->first();
		$this->assertNotEmpty($setConnection);
	}
}

<?php

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
				['set_order' => 1, 'status' => 'S'],
				['set_order' => 2],
				['set_order' => 3, 'status' => 'W'],
			],
		]);

		// Add to favorites via the new set-based system
		$context->addFavorite($context->tsumegos[0]);
		$context->addFavorite($context->tsumegos[1]);
		$context->addFavorite($context->tsumegos[2]);

		// Get the default Favorites set and view it directly
		$favSet = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'title' => 'Favorites'],
		]);
		$this->assertNotEmpty($favSet);

		$this->testAction('/sets/view/' . $favSet['Set']['id'], ['return' => 'view']);
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
				['set_order' => 1, 'status' => 'S'],
				['set_order' => 2, 'status' => 'S'],
				['set_order' => 3],
			],
		]);

		$context->addFavorite($context->tsumegos[0]);
		$context->addFavorite($context->tsumegos[1]);
		$context->addFavorite($context->tsumegos[2]);

		$favSet = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'title' => 'Favorites'],
		]);
		$this->testAction('/sets/view/' . $favSet['Set']['id'], ['return' => 'view']);
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
}

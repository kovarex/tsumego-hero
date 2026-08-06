<?php

class FavoritesViewTest extends TestCaseWithAuth
{
	public function testEmptyFavorites()
	{
		new ContextPreparator(['user' => ['name' => 'nofavs', 'rating' => 1500]]);

		$this->testAction('/sets/view/favorites', ['return' => 'view']);
		$this->assertTextContains('Favorites', $this->view);
		$this->assertStringNotContainsString('statusN', $this->view);
		$this->assertStringNotContainsString('statusS', $this->view);
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

		$context->addFavorite($context->tsumegos[0]);
		$context->addFavorite($context->tsumegos[1]);
		$context->addFavorite($context->tsumegos[2]);

		$this->testAction('/sets/view/favorites', ['return' => 'view']);
		$this->assertTextContains('Favorites', $this->view);
		// All three tsumegos render as buttons
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

		$this->testAction('/sets/view/favorites', ['return' => 'view']);
		// 2 out of 3 solved — percent and solved count rendered
		$this->assertStringContainsString('66', $this->view);
		$this->assertStringContainsString('Favorites', $this->view);
	}
}

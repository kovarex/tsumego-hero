<?php

class UserStatsTest extends ControllerTestCase
{
	public function testUserstatsShowsRecentAttempts()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'statsuser', 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'stats set', 'public' => 1, 'num' => 7]],
				'attempt' => ['solved' => 1],
			],
		]);

		$this->testAction('/users/userstats', ['method' => 'get', 'return' => 'view']);

		$this->assertTextContains('statsuser', $this->view);
		$this->assertTextContains('stats set', $this->view);
	}

	public function testUserstatsFiltersToSingleUser()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'statsuser', 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'stats set', 'public' => 1, 'num' => 7]],
				'attempt' => ['solved' => 1],
			],
		]);

		$this->testAction('/users/userstats/' . $context->user['id'], ['method' => 'get', 'return' => 'view']);

		$this->assertTextContains('statsuser', $this->view);
	}

	public function testUserstats3ShowsSetAttempts()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'statsuser', 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'stats set', 'public' => 1, 'num' => 7]],
				'attempt' => ['solved' => 1],
			],
		]);
		$setId = $context->tsumegos[0]['set-connections'][0]['set_id'];

		$this->testAction('/users/userstats3/' . $setId, ['method' => 'get', 'return' => 'view']);

		$this->assertTextContains('statsuser', $this->view);
		$this->assertTextContains('stats set', $this->view);
	}

	public function testUserstats3ShowsAllAttemptsWithoutSet()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'statsuser', 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'stats set', 'public' => 1, 'num' => 7]],
				'attempt' => ['solved' => 1],
			],
		]);

		$this->testAction('/users/userstats3', ['method' => 'get', 'return' => 'view']);

		$this->assertTextContains('statsuser', $this->view);
	}
}

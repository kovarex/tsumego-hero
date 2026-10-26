<?php

class UserStatsTest extends IntegrationTestCase
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

		$this->get('/users/userstats');

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

		$this->get('/users/userstats/' . $context->user['id']);

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

		$this->get('/users/userstats3/' . $setId);

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

		$this->get('/users/userstats3');

		$this->assertTextContains('statsuser', $this->view);
	}
}

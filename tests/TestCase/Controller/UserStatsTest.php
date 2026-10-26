<?php

use App\Utility\Util;

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

	public function testAttemptsNameTheProblem()
	{
		new ContextPreparator([
			'user' => ['name' => 'statsuser', 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'stats set', 'public' => 1, 'num' => 7]],
				'attempt' => ['solved' => 1, 'misplays' => 2, 'seconds' => 31, 'gain' => 12]]]);

		$this->get('/users/userstats');

		$this->assertTextContains('stats set - 7', $this->view);
		$this->assertTextContains('<td>yes</td><td>2</td><td>31</td><td>12</td>', $this->view);
	}

	public function testUserstatsShowsOnlyThePlayerGiven()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'statsuser', 'admin' => true],
			'other-users' => [['name' => 'otherplayer']],
			'tsumegos' => [
				['sets' => [['name' => 'first set', 'num' => 1]], 'attempt' => ['solved' => 1]],
				['sets' => [['name' => 'second set', 'num' => 1]], 'attempt' => ['solved' => 1]]]]);
		Util::execute('UPDATE tsumego_attempt SET user_id = ? WHERE tsumego_id = ?', [$context->otherUsers[0]['id'], $context->tsumegos[1]['id']]);

		$this->get('/users/userstats/' . $context->otherUsers[0]['id']);

		$this->assertTextContains('Attempts of DN_otherplayer (1)', $this->view);
		$this->assertTextContains('second set - 1', $this->view);
		$this->assertTextNotContains('first set - 1', $this->view);
	}

	/**
	 * A problem of the set is named by its place in that set, wherever else it is shown.
	 */
	public function testUserstats3ShowsOnlyTheProblemsOfTheSet()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'statsuser', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'main set', 'num' => 4], ['name' => 'filtered set', 'num' => 9]], 'attempt' => ['solved' => 1]],
				['sets' => [['name' => 'main set', 'num' => 5]], 'attempt' => ['solved' => 1]]]]);
		$setID = $context->tsumegos[0]['set-connections'][1]['set_id'];

		$this->get('/users/userstats3/' . $setID);

		$this->assertTextContains('Attempts on filtered set (1)', $this->view);
		$this->assertTextContains('filtered set - 9', $this->view);
		$this->assertTextNotContains('main set - ', $this->view);
	}

	/**
	 * The page is read by the same queries however many attempts it lists.
	 */
	public function testTheQueriesDoNotGrowWithTheAttempts()
	{
		$queries = [];
		foreach ([1, 10] as $attemptCount)
		{
			$tsumegos = [];
			for ($i = 1; $i <= $attemptCount; $i++)
				$tsumegos[] = ['sets' => [['name' => 'stats set', 'num' => $i]], 'attempt' => ['solved' => 1]];
			new ContextPreparator(['user' => ['name' => 'statsuser', 'admin' => true], 'tsumegos' => $tsumegos]);

			$before = (int) Util::query("SHOW SESSION STATUS LIKE 'Questions'")[0]['Value'];
			$this->get('/users/userstats');
			$queries[$attemptCount] = (int) Util::query("SHOW SESSION STATUS LIKE 'Questions'")[0]['Value'] - $before;
			$this->assertSame($attemptCount, substr_count($this->view, '<td>yes</td>'));
		}
		$this->assertSame($queries[1], $queries[10]);
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

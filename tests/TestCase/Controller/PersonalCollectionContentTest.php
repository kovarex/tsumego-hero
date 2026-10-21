<?php

use App\Utility\TimeModeUtil;

/**
 * A collection a player assembled is not site content: the site's collections, pools and
 * counters never pick up its problems, whether it is shared or not.
 */
class PersonalCollectionContentTest extends TestCaseWithAuth
{
	public function testSetsPageListsOnlyTheSitesCollections(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$this->_createCollection('Site Collection', null, 1);
		$this->_createCollection('Player Collection', $context->user['id'], 1);
		$this->login('alice');

		$page = $this->testAction('/sets', ['return' => 'view']);

		$this->assertTextContains('Site Collection', $page);
		$this->assertTextNotContains('Player Collection', $page);
	}

	public function testProblemsFoundCountsOnlyTheSitesProblems(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$this->_createCollection('Player Collection', $context->user['id'], 1);
		$this->login('alice');

		$page = $this->testAction('/sets', ['return' => 'view']);

		$this->assertTextContains('Problems found: 0', $page);
	}

	public function testSitemapLeavesOutPlayerCollections(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$siteSetId = $this->_createCollection('Site Collection', null, 1);
		$playerSetId = $this->_createCollection('Player Collection', $context->user['id'], 1);
		Cache::delete('sitemap_xml', 'long');

		$sitemap = $this->testAction('/sitemap.xml', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('/sets/' . $siteSetId . '<', $sitemap);
		$this->assertStringNotContainsString('/sets/' . $playerSetId . '<', $sitemap);
	}

	public function testPublicProblemCountDoesNotCountPlayerProblems(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$total = fn(): int => Tsumego::publicProblemProgress($context->user['id'])['total'];
		$before = $total();

		$this->_createCollection('Site Collection', null, 1);
		$withSiteCollection = $total();

		$this->_createCollection('Player Collection', $context->user['id'], 1);
		$withPlayerCollection = $total();

		$this->assertSame($before + 1, $withSiteCollection);
		$this->assertSame($withSiteCollection, $withPlayerCollection);
	}

	public function testTimeModeNeverPicksAProblemFromAPlayerCollection(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
		]);
		$officialTsumegoId = (int) $context->tsumegos[0]['id'];
		$playerSetId = $this->_createCollection('Player Collection', $context->user['id'], 1);
		$playerTsumegoId = $this->_firstProblemOf($playerSetId);
		$this->login('alice');

		$this->testAction('/timeMode/start?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		$session = ClassRegistry::init('TimeModeSession')->find('first', [
			'conditions' => ['user_id' => $context->user['id']],
		]);
		$attempts = ClassRegistry::init('TimeModeAttempt')->find('all', [
			'conditions' => ['time_mode_session_id' => $session['TimeModeSession']['id']],
		]);
		$attemptTsumegoIds = array_map(fn($attempt) => (int) $attempt['TimeModeAttempt']['tsumego_id'], $attempts);

		$this->assertContains($officialTsumegoId, $attemptTsumegoIds);
		$this->assertNotContains($playerTsumegoId, $attemptTsumegoIds);
	}

	public function testRatingModeNeverPicksAProblemFromAPlayerCollection(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'alice', 'rating' => 1000],
			'tsumego' => ['rating' => 1000, 'sets' => [
				['name' => 'Player Collection', 'num' => 1, 'public' => 1, 'user_id' => 'self']]],
		]);
		$this->login('alice');

		$this->testAction('/ratingMode');

		$this->assertStringEndsWith('/sets', $this->headers['Location'] ?? '',
			'with no problem of the site to offer, rating mode sends the player back');
	}

	public function testSolvedProgressCountsOnlyTheProblemsOfTheSite(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['status' => 'S', 'sets' => [['name' => 'Site Collection', 'num' => 1]]],
			'tsumegos' => [['status' => 'S', 'sets' => [
				['name' => 'Player Collection', 'num' => 1, 'public' => 1, 'user_id' => 'self']]]],
		]);

		$this->assertSame(
			['total' => 1, 'solved' => 1],
			Tsumego::publicProblemProgress($context->user['id']));
	}

	public function testTimeModeRankCountsDoNotCountPlayerProblems(): void
	{
		foreach ([['5k'], ['5k', '1k']] as $ranks)
		{
			new ContextPreparator([
				'user' => ['name' => 'alice'],
				'tsumego' => ['rating' => 1000, 'sets' => [
					['name' => 'Site Collection', 'num' => 1, 'included_in_time_mode' => 1],
				]],
				'tsumegos' => [['rating' => 1000, 'sets' => [
					['name' => 'Player Collection', 'num' => 1, 'included_in_time_mode' => 1, 'public' => 1, 'user_id' => 'self'],
				]]],
				'time-mode-ranks' => $ranks,
			]);

			$vars = $this->testAction('/timeMode/overview', ['return' => 'vars']);

			$counts = array_map(fn($rank) => (int) $rank['tsumego_count'], $vars['timeModeRanks']);
			$this->assertSame(1, array_sum($counts));
		}
	}

	/**
	 * Set achievements belong to the site's collections, so a completed collection a player
	 * assembled records nothing for them.
	 */
	public function testCompletedPlayerCollectionRecordsNoSetAchievementCondition(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['status' => 'S', 'sets' => [
				['name' => 'Player Collection', 'num' => 1, 'public' => 0, 'user_id' => 'self']]],
		]);
		$playerSetId = (int) $context->tsumegos[0]['sets'][0]['id'];
		$this->login('alice');

		$this->testAction('/sets/' . $playerSetId, ['return' => 'view']);

		$this->assertSame(0, ClassRegistry::init('AchievementCondition')->find('count', [
			'conditions' => ['set_id' => $playerSetId],
		]));
	}

	public function testCompletedSiteCollectionRecordsItsSetAchievementCondition(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['status' => 'S', 'sets' => [
				['name' => 'Site Collection', 'num' => 1, 'public' => 1]]],
		]);
		$siteSetId = (int) $context->tsumegos[0]['sets'][0]['id'];
		$this->login('alice');

		$this->testAction('/sets/' . $siteSetId, ['return' => 'view']);

		$this->assertSame(1, ClassRegistry::init('AchievementCondition')->find('count', [
			'conditions' => ['set_id' => $siteSetId, 'category' => 's'],
		]));
	}

	/**
	 * A collection with one problem in it.
	 */
	private function _createCollection(string $title, ?int $userId, int $public): int
	{
		$set = ClassRegistry::init('Set');
		$set->create();
		$set->save(['Set' => [
			'user_id' => $userId,
			'title' => $title,
			'public' => $public,
			'order' => 999,
		]]);
		$setId = (int) $set->id;

		$tsumego = ClassRegistry::init('Tsumego');
		$tsumego->create();
		$tsumego->save(['Tsumego' => ['rating' => 1000]]);
		$tsumegoId = (int) $tsumego->id;

		$setConnection = ClassRegistry::init('SetConnection');
		$setConnection->create();
		$setConnection->save(['SetConnection' => ['set_id' => $setId, 'tsumego_id' => $tsumegoId, 'num' => 1]]);

		ClassRegistry::init('Sgf')->create();
		ClassRegistry::init('Sgf')->save(['Sgf' => [
			'tsumego_id' => $tsumegoId,
			'sgf' => '(;GM[1]FF[4]SZ[19])',
			'accepted' => 1,
		]]);

		return $setId;
	}

	private function _firstProblemOf(int $setId): int
	{
		$connection = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $setId],
			'order' => 'num ASC',
		]);

		return (int) $connection['SetConnection']['tsumego_id'];
	}
}

<?php

use App\Model\Table\SgfTable;
use App\Model\Table\AchievementConditionTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TsumegoTable;
use Cake\Cache\Cache;

//A collection a player assembled is not site content: the site's collections, pools and
//counters never pick up its problems, whether it is shared or not.
class PersonalCollectionContentTest extends IntegrationTestCase
{
	public function testSetsPageListsOnlyTheSitesCollections(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$this->_createCollection('Site Collection', null, 1);
		$this->_createCollection('Player Collection', $context->user['id'], 1);
		$this->login('alice');

		$this->get('/sets');
		$page = $this->view;

		$this->assertTextContains('Site Collection', $page);
		$this->assertTextNotContains('Player Collection', $page);
	}

	public function testProblemsFoundCountsOnlyTheSitesProblems(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$this->_createCollection('Player Collection', $context->user['id'], 1);
		$this->login('alice');

		$this->get('/sets');
		$page = $this->view;

		$this->assertTextContains('Problems found: 0', $page);
	}

	public function testSitemapLeavesOutPlayerCollections(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$siteSetId = $this->_createCollection('Site Collection', null, 1);
		$playerSetId = $this->_createCollection('Player Collection', $context->user['id'], 1);
		Cache::delete('sitemap_xml', 'long');

		$this->get('/sitemap.xml');
		$sitemap = $this->view;

		$this->assertStringContainsString('/sets/' . $siteSetId . '<', $sitemap);
		$this->assertStringNotContainsString('/sets/' . $playerSetId . '<', $sitemap);
	}

	public function testPublicProblemCountDoesNotCountPlayerProblems(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$total = fn(): int => TsumegoTable::publicProblemProgress($context->user['id'])['total'];
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

		$this->get('/timeMode/start?categoryID=' . TimeModeCategoryTable::SLOW
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		$sessionID = $this->fetchTable('TimeModeSession')->find()->where(['user_id' => $context->user['id']])->firstOrFail()->id;
		$attempts = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $sessionID])->toArray();
		$attemptTsumegoIds = array_map(fn($attempt) => (int) $attempt->tsumego_id, $attempts);

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

		$this->post('/ratingMode');

		$this->assertStringEndsWith('/sets', $this->headerValue('Location') ?? '',
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
			TsumegoTable::publicProblemProgress($context->user['id']));
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

			$this->get('/timeMode/overview');
			$counts = array_map(fn($rank) => (int) $rank['tsumego_count'], $this->viewVariable('timeModeRanks'));
			$this->assertSame(1, array_sum($counts));
		}
	}

	//Set achievements belong to the site's collections, so a completed collection a player
	//assembled records nothing for them.
	public function testCompletedPlayerCollectionRecordsNoSetAchievementCondition(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['status' => 'S', 'sets' => [
				['name' => 'Player Collection', 'num' => 1, 'public' => 0, 'user_id' => 'self']]],
		]);
		$playerSetId = (int) $context->tsumegos[0]['sets'][0]['id'];
		$this->login('alice');

		$this->get('/sets/' . $playerSetId);

		$this->assertSame(0, $this->fetchTable('AchievementCondition')->find()->where(['set_id' => $playerSetId])->count());
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

		$this->get('/sets/' . $siteSetId);

		$this->assertSame(1, $this->fetchTable('AchievementCondition')->find()->where(['set_id' => $siteSetId, 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_SET_TIME])->count());
	}

	//A collection with one problem in it.
	private function _createCollection(string $title, ?int $userId, int $public): int
	{
		$setTable = $this->fetchTable('Set');
		$set = $setTable->saveOrFail($setTable->newEntity([
			'user_id' => $userId,
			'title' => $title,
			'public' => $public,
			'order' => 999,
		]));
		$setId = (int) $set->id;

		$tsumegoTable = $this->fetchTable('Tsumego');
		$tsumegoId = $tsumegoTable->saveOrFail($tsumegoTable->newEntity(['rating' => 1000, 'author' => '']))->id;

		$setConnectionTable = $this->fetchTable('SetConnection');
		$setConnectionTable->saveOrFail($setConnectionTable->newEntity(['set_id' => $setId, 'tsumego_id' => $tsumegoId, 'num' => 1]));

		$this->fetchTable('Sgf')->saveOrFail($this->fetchTable('Sgf')->newEntity([
			'tsumego_id' => $tsumegoId,
			'sgf' => '(;GM[1]FF[4]SZ[19])',
			'sgf_status_id' => SgfTable::STATUS_ACCEPTED,
		]));

		return $setId;
	}

	private function _firstProblemOf(int $setId): int
	{
		$connection = $this->fetchTable('SetConnection')->find()->where(['set_id' => $setId])->orderBy('num')->first();

		return (int) $connection->tsumego_id;
	}
}

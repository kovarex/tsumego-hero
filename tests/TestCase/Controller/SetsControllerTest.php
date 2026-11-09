<?php

use App\Model\Table\AchievementConditionTable;
use App\Model\Table\ProgressDeletionTable;
use App\Model\Table\SetTable;
use App\Model\Table\SgfTable;
use App\Utility\AchievementId;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Rating;
use App\Utility\Util;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverSelect;
use PHPUnit\Framework\Attributes\Group;

class SetsControllerTest extends IntegrationTestCase
{
	public function testIndexLoggedIn(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'tsumego set 1', 'num' => '666'],
			['name' => 'tsumego set 2', 'num' => '777']]]], );
		$this->login($context->user['name']);
		$this->get('sets');
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
		$this->assertTextNotContains("Problems found 0", $this->view);
	}

	public function testIndexLoggedOff(): void
	{
		new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'tsumego set 1', 'num' => '666'],
			['name' => 'tsumego set 2', 'num' => '777']]]], );
		$this->get('sets');
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
		$this->assertTextNotContains("Problems found 0", $this->view);
	}

	//Finishing the last problem of a set unlocks achievements on the set page, and
	//those achievements pop up for the player.
	public function testSetPagePopsUpAchievementsUnlockedByTheSet(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'setViewer']]);
		foreach ([50, 52, 53, 54] as $setId)
		{
			$this->createFullySolvedSet($setId, $context->user['id']);
			$this->fetchTable('Set')->updateAll(['completion_achievement_id' => AchievementId::LIFE_DEATH_ELEMENTARY->value], ['id' => $setId]);
		}
		$_COOKIE['disable-achievements'] = false;

		$this->get('sets/50');

		$popups = array_column($this->viewVariable('achievementUpdates'), 'id');
		$this->assertContains(AchievementId::LIFE_DEATH_ELEMENTARY->value, $popups, 'The set page hands the achievements it unlocked to the layout');
		$this->assertTextContains('"id":' . AchievementId::LIFE_DEATH_ELEMENTARY->value, $this->view, 'The layout renders the achievements the set page unlocked');
	}

	//No set is singled out by its id: the sets the page once treated differently (the old beta2
	//sets, the themed sets without a cover, set 60 with a fixed height) look like any other set.
	public function testSetPageTreatsEverySetAlike(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'setViewer']]);
		foreach ([58, 60, 11969] as $setId)
		{
			$this->createFullySolvedSet($setId, $context->user['id']);
			$this->fetchTable('Set')->updateAll(['image' => 'cover' . $setId . '.png'], ['id' => $setId]);

			$this->get('sets/' . $setId);

			$this->assertResponseOk();
			$this->assertResponseContains('<div class="problem-nav__number">1</div>', 'Set ' . $setId . ' shows its problems');
			$this->assertResponseContains('src="/img/cover' . $setId . '.png"', 'Set ' . $setId . ' shows its cover');
			$this->assertResponseContains('<div id="content">', 'Set ' . $setId . ' has no fixed page height');
		}
	}

	//Create a public set with a single problem that the given user solved.
	private function createFullySolvedSet(int $setId, int $userId): void
	{
		$this->fetchTable('Set')->saveOrFail($this->fetchTable('Set')->newEntity(['id' => $setId, 'public' => 1, 'title' => 'set ' . $setId], ['accessibleFields' => ['id' => true]]));
		$tsumegoId = $this->fetchTable('Tsumego')->saveOrFail($this->fetchTable('Tsumego')->newEntity(['rating' => 1000, 'author' => '']))->id;
		$this->fetchTable('SetConnection')->saveOrFail($this->fetchTable('SetConnection')->newEntity(['set_id' => $setId, 'tsumego_id' => $tsumegoId, 'num' => 1]));
		$this->fetchTable('Sgf')->saveOrFail($this->fetchTable('Sgf')->newEntity(['tsumego_id' => $tsumegoId, 'sgf' => '(;GM[1]FF[4]SZ[19])', 'user_id' => $userId, 'sgf_status_id' => SgfTable::STATUS_ACCEPTED]));
		$this->fetchTable('TsumegoStatus')->saveOrFail($this->fetchTable('TsumegoStatus')->newEntity(['tsumego_id' => $tsumegoId, 'status' => 'S', 'user_id' => $userId]));
	}

	//Every approved tag is a tag tile on the sets index, whatever its name; an unapproved one is not
	public function testIndexOffersEveryApprovedTagAsATile(): void
	{
		new ContextPreparator(['tsumego' => [
			'sets' => [['name' => 'tsumego set 1', 'num' => 1]],
			'tags' => [['name' => 'Tsumego'], ['name' => 'snapback']]]]);
		$this->fetchTable('Tag')->saveOrFail($this->fetchTable('Tag')->newEntity(['name' => 'unapproved tag', 'description' => '', 'approved' => 0]));

		$this->get('sets');

		$tagTiles = $this->viewVariable('tagTiles');
		$this->assertContains('Tsumego', $tagTiles);
		$this->assertContains('snapback', $tagTiles);
		$this->assertNotContains('unapproved tag', $tagTiles);
		$this->assertResponseContains('>Tsumego</div>');
	}

	public function testIndexRankBased(): void
	{
		$contextParams = [];
		$contextParams['tsumegos'] = [];
		$contextParams['tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 1', 'num' => '1']]];
		$contextParams['tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'sandbox-set', 'num' => '1', 'public' => 0]]];
		$contextParams['tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('10k'),
			'sets' => [['name' => 'set 2', 'num' => '1']]];
		$context = new ContextPreparator($contextParams);

		$_COOKIE['query'] = 'difficulty';
		$_COOKIE['filtered_ranks'] = '15k';
		$this->get('sets');
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__title');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->textContent, '15k');

		$collectionMiddleLeft = $dom->querySelectorAll('.set-card__count');
		$this->assertCount(1, $collectionMiddleLeft);
		$this->assertSame($collectionMiddleLeft[0]->textContent, '1 problem'); // the sandbox problem isn't included

		$_COOKIE['filtered_ranks'] = '10k';
		$this->get('sets');
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__title');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->textContent, '10k');
	}

	public function testDifficultyIndexGivesEveryRankItsOwnBox(): void
	{
		$ranks = ['30k', '25k', '20k', '17k', '16k', '15k', '10k'];
		$contextParams = ['user' => ['query' => 'difficulty']];
		$contextParams['tsumegos'] = [];
		foreach ($ranks as $rank)
			$contextParams['tsumegos'][] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
				'sets' => [['name' => 'set ' . $rank, 'num' => '1']]];
		new ContextPreparator($contextParams);
		$this->get('sets');

		$boxTitles = [];
		foreach ($this->getStringDom()->querySelectorAll('.set-card__title') as $boxTitle)
			$boxTitles[] = $boxTitle->textContent;

		$this->assertSame($ranks, $boxTitles);
	}

	public function testDifficultyIndexGroupsProblemsBelowThirtyKyuInTheThirtyKyuBox(): void
	{
		$contextParams = ['user' => ['query' => 'difficulty']];
		$contextParams['tsumegos'] = [
			['rating' => -1200, 'sets' => [['name' => 'beginner set', 'num' => '1']]]];
		new ContextPreparator($contextParams);
		$this->get('sets');

		$boxTitles = [];
		foreach ($this->getStringDom()->querySelectorAll('.set-card__title') as $boxTitle)
			$boxTitles[] = $boxTitle->textContent;

		$this->assertSame(['30k'], $boxTitles);
	}

	public function testDifficultyIndexRankFilterShowsOnlyThatRank(): void
	{
		$contextParams['tsumegos'] = [
			['rating' => Rating::getRankMiddleRatingFromReadableRank('16k'), 'sets' => [['name' => 'set 1', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('15k'), 'sets' => [['name' => 'set 2', 'num' => '1']]]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'difficulty';
		$_COOKIE['filtered_ranks'] = '15k';
		$this->get('sets');

		$this->assertTextContains('Problems found: 1', $this->view);
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__title');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame('15k', $collectionTopDivs[0]->textContent);
		$this->assertSame('1 problem', $dom->querySelectorAll('.set-card__count')[0]->textContent);
	}

	public function testDifficultyIndexShowsTheOnlyPickedRankInTheRangeSelects(): void
	{
		$contextParams['tsumegos'] = [
			['rating' => Rating::getRankMiddleRatingFromReadableRank('5k'), 'sets' => [['name' => 'set 1', 'num' => '1']]]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'difficulty';
		$_COOKIE['filtered_ranks'] = '5k';
		$this->get('sets');

		$dom = $this->getStringDom();
		$this->assertSame('5k', $dom->querySelector('#difficulty-from option[selected]')->getAttribute('value'));
		$this->assertSame('5k', $dom->querySelector('#difficulty-to option[selected]')->getAttribute('value'));
	}

	public function testDifficultyIndexLeavesTheRangeSelectsOpenForSeveralPickedRanks(): void
	{
		$contextParams['tsumegos'] = [
			['rating' => Rating::getRankMiddleRatingFromReadableRank('17k'), 'sets' => [['name' => 'set 1', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('15k'), 'sets' => [['name' => 'set 2', 'num' => '1']]]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'difficulty';
		$_COOKIE['filtered_ranks'] = '17k@15k';
		$this->get('sets');

		$dom = $this->getStringDom();
		$this->assertCount(0, $dom->querySelectorAll('#difficulty-from option[selected]'));
		$this->assertCount(0, $dom->querySelectorAll('#difficulty-to option[selected]'));
	}

	public function testDifficultyIndexKeepsPickedRanksInTheirOwnBoxes(): void
	{
		$contextParams['tsumegos'] = [
			['rating' => Rating::getRankMiddleRatingFromReadableRank('17k'), 'sets' => [['name' => 'set 1', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('16k'), 'sets' => [['name' => 'set 2', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('15k'), 'sets' => [['name' => 'set 3', 'num' => '1']]]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'difficulty';
		$_COOKIE['filtered_ranks'] = '17k@16k';
		$this->get('sets');

		$this->assertTextContains('Problems found: 2', $this->view);
		$dom = $this->getStringDom();
		$boxTitles = [];
		foreach ($dom->querySelectorAll('.set-card__title') as $boxTitle)
			$boxTitles[] = $boxTitle->textContent;

		$this->assertSame(['17k', '16k'], $boxTitles);
	}

	public function testDifficultyIndexShowsAPickedRangeAsOneBox(): void
	{
		$contextParams['tsumegos'] = [
			['rating' => Rating::getRankMiddleRatingFromReadableRank('17k'), 'sets' => [['name' => 'set 1', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('16k'), 'sets' => [['name' => 'set 2', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('15k'), 'sets' => [['name' => 'set 3', 'num' => '1']]]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'difficulty';
		$_COOKIE['filtered_ranks'] = '17k-16k';
		$this->get('sets');

		$this->assertTextContains('Problems found: 2', $this->view);
		$dom = $this->getStringDom();
		$boxTitles = [];
		foreach ($dom->querySelectorAll('.set-card__title') as $boxTitle)
			$boxTitles[] = $boxTitle->textContent;

		$this->assertSame(['17k-16k'], $boxTitles);
		$this->assertSame('2 problems', $dom->querySelectorAll('.set-card__count')[0]->textContent);
	}

	public function testDifficultyIndexCombinesPickedRanksWithAPickedRange(): void
	{
		$contextParams['tsumegos'] = [
			['rating' => Rating::getRankMiddleRatingFromReadableRank('20k'), 'sets' => [['name' => 'set 1', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('10k'), 'sets' => [['name' => 'set 2', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('9k'), 'sets' => [['name' => 'set 3', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('5k'), 'sets' => [['name' => 'set 4', 'num' => '1']]]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'difficulty';
		$_COOKIE['filtered_ranks'] = '20k@10k-9k';
		$this->get('sets');

		$this->assertTextContains('Problems found: 3', $this->view);
		$boxTitles = [];
		foreach ($this->getStringDom()->querySelectorAll('.set-card__title') as $boxTitle)
			$boxTitles[] = $boxTitle->textContent;

		$this->assertSame(['20k', '10k-9k'], $boxTitles);
	}

	public function testDifficultyIndexKeepsSeparateRunsOfTheRankFilterApart(): void
	{
		$contextParams['tsumegos'] = [
			['rating' => Rating::getRankMiddleRatingFromReadableRank('17k'), 'sets' => [['name' => 'set 1', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('15k'), 'sets' => [['name' => 'set 2', 'num' => '1']]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('10k'), 'sets' => [['name' => 'set 3', 'num' => '1']]]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'difficulty';
		$_COOKIE['filtered_ranks'] = '17k@10k';
		$this->get('sets');

		$boxTitles = [];
		foreach ($this->getStringDom()->querySelectorAll('.set-card__title') as $boxTitle)
			$boxTitles[] = $boxTitle->textContent;

		$this->assertSame(['17k', '10k'], $boxTitles);
	}

	public function testTopicsIndexSplitsCollectionIntoPartitionBoxes(): void
	{
		$contextParams = ['user' => ['collection_size' => 10]];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 11; $i++)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'partitioned set', 'num' => $i + 1]]];
		$context = new ContextPreparator($contextParams);
		$this->get('sets');

		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__title');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame('partitioned set', $collectionTopDivs[0]->textContent);
		$this->assertSame('partitioned set', $collectionTopDivs[1]->textContent);
		// the part is the tab in the card's corner, not part of the name
		$parts = $dom->querySelectorAll('.set-card__part');
		$this->assertCount(2, $parts);
		$this->assertSame('1/2', $parts[0]->textContent);
		$this->assertSame('2/2', $parts[1]->textContent);

		$collectionMiddleLeft = $dom->querySelectorAll('.set-card__count');
		$this->assertSame('10 problems', $collectionMiddleLeft[0]->textContent);
		$this->assertSame('1 problem', $collectionMiddleLeft[1]->textContent);

		$boxLinks = $dom->querySelectorAll('.set-card__link');
		$setId = $context->tsumegos[0]['sets'][0]['id'];
		$this->assertSame('/sets/' . $setId, $boxLinks[0]->getAttribute('href'));
		$this->assertSame('/sets/' . $setId . '/2', $boxLinks[1]->getAttribute('href'));
	}

	public function testTopicsIndexSmallCollectionShowsSingleBox(): void
	{
		$contextParams = ['user' => ['collection_size' => 200]];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 3; $i++)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'small set', 'num' => $i + 1]]];
		$context = new ContextPreparator($contextParams);
		$this->get('sets');

		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__title');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame('small set', $collectionTopDivs[0]->textContent);

		$collectionMiddleLeft = $dom->querySelectorAll('.set-card__count');
		$this->assertSame('3 problems', $collectionMiddleLeft[0]->textContent);

		$boxLinks = $dom->querySelectorAll('.set-card__link');
		$this->assertSame('/sets/' . $context->tsumegos[0]['sets'][0]['id'], $boxLinks[0]->getAttribute('href'));
	}

	public function testTopicsIndexSortsNullOrderSetLast(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'ordered set', 'public' => 1, 'order' => 5, 'num' => 1]]]]);

		// a public set with no curated order holding the same tsumego
		$setTable = $this->fetchTable('Set');
		$set = $setTable->saveOrFail($setTable->newEntity(['title' => 'unordered set', 'public' => 1, 'order' => null]));
		$setConnectionTable = $this->fetchTable('SetConnection');
		$setConnectionTable->saveOrFail($setConnectionTable->newEntity(['tsumego_id' => $context->tsumegos[0]['id'], 'set_id' => $set->id, 'num' => 2]));

		$this->get('sets');

		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__title');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame('ordered set', $collectionTopDivs[0]->textContent);
		$this->assertSame('unordered set', $collectionTopDivs[1]->textContent);
	}

	public function testTopicsIndexProblemsFoundCountsAllPublicProblems(): void
	{
		$contextParams = ['tsumegos' => []];
		$contextParams['tsumegos'][] = ['sets' => [['name' => 'public set', 'num' => '1']]];
		$contextParams['tsumegos'][] = ['sets' => [['name' => 'public set', 'num' => '2']]];
		// a problem only in a private set is not shown, so not counted
		$contextParams['tsumegos'][] = ['sets' => [['name' => 'sandbox set', 'public' => 0, 'num' => '1']]];
		new ContextPreparator($contextParams);
		$this->get('sets');
		$this->assertTextContains('Problems found: 2', $this->view);
	}

	public function testDifficultyIndexProblemsFoundExcludesProblemsAboveNineDan(): void
	{
		$contextParams = ['tsumegos' => []];
		$contextParams['tsumegos'][] = ['rating' => Rating::getRankMiddleRatingFromReadableRank('15k'), 'sets' => [['name' => 'set', 'num' => '1']]];
		$contextParams['tsumegos'][] = ['rating' => Rating::getRankMiddleRatingFromReadableRank('15k'), 'sets' => [['name' => 'set', 'num' => '2']]];
		// rating 2810+ falls above the 9d band, so no difficulty box shows it
		$contextParams['tsumegos'][] = ['rating' => 2810, 'sets' => [['name' => 'set', 'num' => '3']]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'difficulty';
		$this->get('sets');
		$this->assertTextContains('Problems found: 2', $this->view);
	}

	public function testTagsIndexProblemsFoundCountsOnlyTaggedProblems(): void
	{
		$contextParams = ['tsumegos' => []];
		$contextParams['tsumegos'][] = ['sets' => [['name' => 'set', 'num' => '1']], 'tags' => [['name' => 'atari']]];
		$contextParams['tsumegos'][] = ['sets' => [['name' => 'set', 'num' => '2']], 'tags' => [['name' => 'atari']]];
		// a public problem with no tag is not shown in tags mode, so not counted
		$contextParams['tsumegos'][] = ['sets' => [['name' => 'set', 'num' => '3']]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'tags';
		$this->get('sets');
		$this->assertTextContains('Problems found: 2', $this->view);
	}

	public function testTopicsIndexExcludesDeletedProblems(): void
	{
		$contextParams = ['tsumegos' => []];
		$contextParams['tsumegos'][] = ['sets' => [['name' => 'set', 'num' => '1']]];
		// a deleted problem in the same public set is neither counted nor shown
		$contextParams['tsumegos'][] = ['deleted' => date('Y-m-d H:i:s'), 'sets' => [['name' => 'set', 'num' => '2']]];
		new ContextPreparator($contextParams);
		$this->get('sets');

		$this->assertTextContains('Problems found: 1', $this->view);
		$dom = $this->getStringDom();
		$this->assertCount(1, $dom->querySelectorAll('.set-card__title'));
		$this->assertSame('1 problem', $dom->querySelectorAll('.set-card__count')[0]->textContent);
	}

	public function testTagsIndexExcludesDeletedProblems(): void
	{
		$contextParams = ['tsumegos' => []];
		$contextParams['tsumegos'][] = ['sets' => [['name' => 'set', 'num' => '1']], 'tags' => [['name' => 'atari']]];
		// a deleted problem with the same tag is neither counted nor shown
		$contextParams['tsumegos'][] = ['deleted' => date('Y-m-d H:i:s'), 'sets' => [['name' => 'set', 'num' => '2']], 'tags' => [['name' => 'atari']]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'tags';
		$this->get('sets');

		$this->assertTextContains('Problems found: 1', $this->view);
		$dom = $this->getStringDom();
		$this->assertCount(1, $dom->querySelectorAll('.set-card__title'));
		$this->assertSame('1 problem', $dom->querySelectorAll('.set-card__count')[0]->textContent);
	}

	//A tag a player proposed is not on the problem until an admin accepts it, so its collection counts
	//and shows only the problems that carry it.
	public function testATagCollectionHasOnlyTheProblemsThatCarryIt(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'set', 'num' => '1']], 'tags' => [['name' => 'atari']]],
			['sets' => [['name' => 'set', 'num' => '2']], 'tags' => [['name' => 'atari']]],
			['sets' => [['name' => 'set', 'num' => '3']], 'tags' => [['name' => 'atari', 'approved' => 0]]]]]);
		$_COOKIE['query'] = 'tags';

		$this->get('sets');
		$this->assertTextContains('Problems found: 2', $this->view);
		$this->assertSame('2 problems', $this->getStringDom()->querySelectorAll('.set-card__count')[0]->textContent);

		$this->get('sets/atari');
		$shown = array_map(fn($button) => $button->tsumegoID, (array) $this->viewVariable('tsumegoButtons'));
		$this->assertSame([$context->tsumegos[0]['id'], $context->tsumegos[1]['id']], $shown);
	}

	//What the tag view shows when tags are filtered: a card for each picked tag only, with the problems its page
	//opens, and the problems found are the problems the cards hold.
	public function testTagFilterShowsTheCardsOfThePickedTagsWithTheProblemsTheirPagesOpen(): void
	{
		$tsumego = fn(string $rank, array $tags) => [
			'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
			'sets' => [['name' => 'set', 'num' => '1']],
			'tags' => array_map(fn($tag) => ['name' => $tag], $tags)];
		new ContextPreparator(['tsumegos' => [
			$tsumego('15k', ['bent four', 'nakade']),
			$tsumego('15k', ['bent four']),
			$tsumego('5k', ['bent four', 'ko']),
			$tsumego('15k', ['nakade']),
			$tsumego('15k', ['ko'])]]);
		$_COOKIE['query'] = 'tags';

		$cardsAndPages = function (): array {
			$this->get('sets');
			preg_match('/Problems found: (\d+)/', $this->view, $found);
			$cards = [];
			foreach ($this->viewVariable('setsSelector')->sets as $card)
				$cards[$card['name']] = $card['amount'];
			$pages = [];
			foreach (array_keys($cards) as $tag)
			{
				$this->get('sets/' . rawurlencode($tag));
				$pages[$tag] = count($this->viewVariable('tsumegoButtons'));
			}
			return [(int) $found[1], $cards, $pages];
		};

		$_COOKIE['filtered_tags'] = 'bent four';
		[$found, $cards, $pages] = $cardsAndPages();
		$this->assertSame(['bent four' => 3], $cards, 'only the card of the picked tag');
		$this->assertSame($cards, $pages);
		$this->assertSame(3, $found);

		$_COOKIE['filtered_tags'] = 'bent four@nakade';
		[$found, $cards, $pages] = $cardsAndPages();
		$this->assertSame(['bent four' => 3, 'nakade' => 2], $cards);
		$this->assertSame($cards, $pages);
		$this->assertSame(4, $found, 'a problem with both tags is found once');

		$_COOKIE['filtered_tags'] = 'bent four';
		$_COOKIE['filtered_ranks'] = '15k';
		[$found, $cards, $pages] = $cardsAndPages();
		$this->assertSame(['bent four' => 2], $cards);
		$this->assertSame($cards, $pages);
		$this->assertSame(2, $found);
	}

	public function testTopicsIndexShowsSolvedPercentPerPartition(): void
	{
		$contextParams = ['user' => ['collection_size' => 10]];
		$contextParams['tsumegos'] = [];
		foreach (array_merge(array_fill(0, 10, 'S'), ['S', 'N']) as $i => $status)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'solved set', 'num' => $i + 1]],
				'status' => $status];
		new ContextPreparator($contextParams);
		$this->get('sets');

		$this->assertTextContains('Problems found: 12', $this->view);

		$dom = $this->getStringDom();
		$percents = $dom->querySelectorAll('.set-card__percent');
		$this->assertCount(2, $percents);
		$this->assertSame('100%', $percents[0]->textContent);
		$this->assertSame('50%', $percents[1]->textContent);
		$this->assertCount(1, $dom->querySelectorAll('.set-card--finished'));
		// solved without any attempt recorded, which the collection's accuracy counts as a miss
		$this->assertCount(0, $dom->querySelectorAll('.set-card--perfect'));
	}

	//A finished card is framed when the stored best accuracy of its part is 100, whatever the attempts: the first part
	//has it, the second, solved as cleanly, has no best stored
	public function testTopicsIndexFramesTheCardsOfPartsWithABestOf100(): void
	{
		ProgressDeletionTable::$PARTITION_SIZE = 10;
		$contextParams = ['user' => ['collection_size' => 10], 'tsumegos' => [], 'achievement-conditions' => [
			['category' => AchievementConditionTable::CATEGORY_SET_ACCURACY, 'set' => 'accuracy set', 'partition' => 0, 'value' => 100],
			['category' => AchievementConditionTable::CATEGORY_SET_ACCURACY, 'set' => 'accuracy set', 'partition' => 2, 'value' => 99]]];
		for ($i = 0; $i < 30; $i++)
			$contextParams['tsumegos'][] = ['sets' => [['name' => 'accuracy set', 'num' => $i + 1]], 'status' => 'S', 'attempts' => [['solved' => true]]];
		new ContextPreparator($contextParams);
		$this->get('sets');

		$cards = $this->getStringDom()->querySelectorAll('.set-card');
		$this->assertCount(3, $cards);
		$this->assertSame([true, false, false], array_map(fn($card) => str_contains($card->getAttribute('class'), 'set-card--perfect'), iterator_to_array($cards)));
		$this->assertCount(3, $this->getStringDom()->querySelectorAll('.set-card--finished'));
	}

	//A card of another size than the parts is framed only when every part it covers has a best of 100
	public function testTopicsIndexFramesACardCoveringSeveralPartsWhenEachHas100(): void
	{
		ProgressDeletionTable::$PARTITION_SIZE = 10;
		$contextParams = ['user' => ['collection_size' => 20], 'tsumegos' => [], 'achievement-conditions' => [
			['category' => AchievementConditionTable::CATEGORY_SET_ACCURACY, 'set' => 'accuracy set', 'partition' => 0, 'value' => 100],
			['category' => AchievementConditionTable::CATEGORY_SET_ACCURACY, 'set' => 'accuracy set', 'partition' => 1, 'value' => 100],
			['category' => AchievementConditionTable::CATEGORY_SET_ACCURACY, 'set' => 'accuracy set', 'partition' => 2, 'value' => 100]]];
		for ($i = 0; $i < 40; $i++)
			$contextParams['tsumegos'][] = ['sets' => [['name' => 'accuracy set', 'num' => $i + 1]], 'status' => 'S'];
		new ContextPreparator($contextParams);
		$this->get('sets');

		$cards = $this->getStringDom()->querySelectorAll('.set-card');
		$this->assertCount(2, $cards);
		$this->assertStringContainsString('set-card--perfect', $cards[0]->getAttribute('class'), 'parts 0 and 1 have 100');
		$this->assertStringNotContainsString('set-card--perfect', $cards[1]->getAttribute('class'), 'part 3 has no best');
	}

	//A card not finished is not marked, however clean its attempts
	public function testTopicsIndexMarksNoUnfinishedCardAsPerfect(): void
	{
		$contextParams = ['user' => ['collection_size' => 10]];
		$contextParams['tsumegos'] = [];
		foreach (['S', 'N'] as $i => $status)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'half set', 'num' => $i + 1]],
				'status' => $status,
				'attempts' => $status === 'S' ? [['solved' => true]] : []];
		new ContextPreparator($contextParams);
		$this->get('sets');

		$dom = $this->getStringDom();
		$this->assertSame('50%', $dom->querySelectorAll('.set-card__percent')[0]->textContent);
		$this->assertCount(0, $dom->querySelectorAll('.set-card--finished'));
		$this->assertCount(0, $dom->querySelectorAll('.set-card--perfect'));
	}

	public function testTopicsIndexShowsDifficultyPerPartition(): void
	{
		$contextParams = ['user' => ['collection_size' => 10]];
		$contextParams['tsumegos'] = [];
		foreach (array_merge(
			array_fill(0, 10, Rating::getRankMiddleRatingFromReadableRank('15k')),
			[
				Rating::getRankMiddleRatingFromReadableRank('5k'),
				Rating::getRankMiddleRatingFromReadableRank('5k')]) as $i => $rating)
					$contextParams['tsumegos'] [] = [
						'sets' => [['name' => 'difficulty set', 'num' => $i + 1]],
						'rating' => $rating];
		new ContextPreparator($contextParams);
		$this->get('sets');

		// difficulty must be computed per partition, not per whole set:
		// partition 1 (ten 15k problems) shows 15k, partition 2 (two 5k) shows 5k.
		$dom = $this->getStringDom();
		$difficulties = $dom->querySelectorAll('.set-card__rank');
		$this->assertCount(2, $difficulties);
		$this->assertSame('15k', $difficulties[0]->textContent);
		$this->assertSame('5k', $difficulties[1]->textContent);
	}

	public function testTopicsIndexGuestShowsNoSolvedProgress(): void
	{
		$contextParams = ['user' => null];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 2; $i++)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'guest set', 'num' => $i + 1]]];
		new ContextPreparator($contextParams);
		$this->get('sets');

		$dom = $this->getStringDom();
		$percents = $dom->querySelectorAll('.set-card__percent');
		$this->assertCount(1, $percents);
		$this->assertSame('0%', $percents[0]->textContent);
		$this->assertCount(1, $dom->querySelectorAll('.set-card__title'));
		$this->assertCount(0, $dom->querySelectorAll('.set-card--finished'));
	}

	public function testDifficultyIndexSplitsRankIntoPartitionBoxes(): void
	{
		$contextParams = ['user' => ['query' => 'difficulty', 'collection_size' => 10]];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 11; $i++)
			$contextParams['tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
				'sets' => [['name' => 'set 1', 'num' => $i + 1]]];
		new ContextPreparator($contextParams);
		$this->get('sets');

		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__title');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame('15k', $collectionTopDivs[0]->textContent);
		$this->assertSame('15k', $collectionTopDivs[1]->textContent);
		// the part is the tab in the card's corner, not part of the name
		$parts = $dom->querySelectorAll('.set-card__part');
		$this->assertCount(2, $parts);
		$this->assertSame('1/2', $parts[0]->textContent);
		$this->assertSame('2/2', $parts[1]->textContent);

		$collectionMiddleLeft = $dom->querySelectorAll('.set-card__count');
		$this->assertSame('10 problems', $collectionMiddleLeft[0]->textContent);
		$this->assertSame('1 problem', $collectionMiddleLeft[1]->textContent);
	}

	public function testTagsIndexSplitsTagIntoPartitionBoxes(): void
	{
		$contextParams = ['user' => ['query' => 'tags', 'collection_size' => 10]];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 11; $i++)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'set 1', 'num' => $i + 1]],
				'tags' => [['name' => 'atari']]];
		new ContextPreparator($contextParams);
		$this->get('sets');

		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__title');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame('atari', $collectionTopDivs[0]->textContent);
		$this->assertSame('atari', $collectionTopDivs[1]->textContent);
		// the part is the tab in the card's corner, not part of the name
		$parts = $dom->querySelectorAll('.set-card__part');
		$this->assertCount(2, $parts);
		$this->assertSame('1/2', $parts[0]->textContent);
		$this->assertSame('2/2', $parts[1]->textContent);

		$collectionMiddleLeft = $dom->querySelectorAll('.set-card__count');
		$this->assertSame('10 problems', $collectionMiddleLeft[0]->textContent);
		$this->assertSame('1 problem', $collectionMiddleLeft[1]->textContent);
	}

	//A card of a tag or a rank is addressed by its name, and the card of a tag has no difficulty.
	public function testTagAndRankCardsAreAddressedByTheirNames(): void
	{
		$contextParams = ['user' => ['query' => 'tags', 'collection_size' => 10]];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 11; $i++)
			$contextParams['tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
				'sets' => [['name' => 'set 1', 'num' => $i + 1]],
				'tags' => [['name' => 'atari']]];
		new ContextPreparator($contextParams);

		$this->get('sets');
		$tagCards = $this->viewVariable('setsSelector')->sets;
		$this->assertSame(['atari', 'atari'], array_column($tagCards, 'id'));
		$this->assertSame([0, 1], array_column($tagCards, 'partition'));
		$this->assertSame([], array_column($tagCards, 'difficulty'));
		$this->assertStringContainsString('href="/sets/atari/2"', $this->view);

		$_COOKIE['query'] = 'difficulty';
		$this->get('sets');
		$rankCards = $this->viewVariable('setsSelector')->sets;
		$fifteenKyu = array_values(array_filter($rankCards, fn($card) => $card['name'] === '15k'));
		$this->assertSame(['15k', '15k'], array_column($fifteenKyu, 'id'));
		$this->assertSame(['15k', '15k'], array_column($fifteenKyu, 'difficulty'));
		$this->assertSame(['var(--rank-15k)', 'var(--rank-15k)'], array_column($fifteenKyu, 'color'));
	}

	public function testSetViewRankBased(): void
	{
		$contextParams = [];
		$contextParams['tsumegos'] = [];
		$contextParams['tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 1', 'num' => '1']]];

		// adding sandbox tsumego in the selected rank, to test that it isn't shown
		$contextParams['tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'sandbox-set', 'num' => '1', 'public' => 0]]];

		$contextParams['tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('10k'),
			'sets' => [['name' => 'set 2', 'num' => '1']]];
		$context = new ContextPreparator($contextParams);

		$this->get('sets/15k');
		$dom = $this->getStringDom();
		$setTitle = $dom->querySelectorAll('.set-view-title');
		$this->assertCount(1, $setTitle);
		$this->assertSame($setTitle[0]->textContent, '15k');

		$problemButtons = $dom->querySelectorAll('.problem-nav__number');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemLinks);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->get('sets/10k');
		$dom = $this->getStringDom();
		$setTitle = $dom->querySelectorAll('.set-view-title');
		$this->assertCount(1, $setTitle);
		$this->assertSame($setTitle[0]->textContent, '10k');

		$problemButtons = $dom->querySelectorAll('.problem-nav__number');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemLinks);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->tsumegos[2]['set-connections'][0]['id']);
	}

	public function testDifficultySetViewShowsRank(): void
	{
		$contextParams = [];
		$contextParams['tsumegos'] = [];
		$contextParams['tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('5k'),
			'sets' => [['name' => 'set 1', 'num' => '1']]];
		new ContextPreparator($contextParams);

		$this->get('sets/5k');
		$result = $this->getStringDom();

		$this->assertStringContainsString('<span class="rank-icon rank-icon-large">5k</span>',
			$result->saveHTML());
	}

	public function testDifficultySetViewShowsMergedRankRange(): void
	{
		$contextParams = [];
		$contextParams['tsumegos'] = [];
		foreach (['20k', '17k', '15k'] as $rank)
			$contextParams['tsumegos'][] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
				'sets' => [['name' => 'set ' . $rank, 'num' => '1']]];
		$context = new ContextPreparator($contextParams);

		$this->get('sets/view/20k-16k');
		$dom = $this->getStringDom();
		$setTitle = $dom->querySelectorAll('.set-view-title');
		$this->assertCount(1, $setTitle);
		$this->assertSame('20k-16k', $setTitle[0]->textContent);

		$problemButtons = $dom->querySelectorAll('.problem-nav__number');
		$this->assertCount(2, $problemButtons);

		$problemLinks = [];
		foreach ($dom->querySelectorAll('.tooltip') as $problemLink)
			$problemLinks[] = $problemLink->getAttribute('href');
		sort($problemLinks);
		$expectedLinks = [
			'/' . $context->tsumegos[0]['set-connections'][0]['id'],
			'/' . $context->tsumegos[1]['set-connections'][0]['id']];
		sort($expectedLinks);
		$this->assertSame($expectedLinks, $problemLinks);
	}

	public function testSetViewSetBased(): void
	{
		$contextParams = [];
		$contextParams['tsumegos'] = [];
		$contextParams['tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 1', 'num' => '1']]];
		$contextParams['tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('10k'),
			'sets' => [['name' => 'set 2', 'num' => '1']]];
		$context = new ContextPreparator($contextParams);

		$this->get('sets/' . $context->tsumegos[0]['sets'][0]['id']);
		$dom = $this->getStringDom();
		$setTitle = $dom->querySelectorAll('.set-view-title');
		$this->assertCount(1, $setTitle);
		$this->assertSame($setTitle[0]->textContent, 'set 1');

		$problemButtons = $dom->querySelectorAll('.problem-nav__number');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemLinks);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->get('sets/' . $context->tsumegos[1]['sets'][0]['id']);
		$dom = $this->getStringDom();
		$setTitle = $dom->querySelectorAll('.set-view-title');
		$this->assertCount(1, $setTitle);
		$this->assertSame($setTitle[0]->textContent, 'set 2');

		$problemButtons = $dom->querySelectorAll('.problem-nav__number');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemLinks);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->tsumegos[1]['set-connections'][0]['id']);
	}

	public function testSetViewPageLinksFollowProblemCountNotProblemNumbers(): void
	{
		// 15 problems numbered 1, 5, 9 … 57, as when problems were moved out of a collection:
		// two pages of 10, though the highest number would make it six.
		$contextParams = ['user' => ['collection_size' => 10]];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 15; $i++)
			$contextParams['tsumegos'] [] = ['sets' => [['name' => 'set with gaps', 'num' => $i * 4 + 1]]];
		$context = new ContextPreparator($contextParams);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$this->get(SetTable::url($setId));
		$dom = $this->getStringDom();
		$pageLinks = array_map(fn($node) => $node->textContent, iterator_to_array($dom->querySelectorAll('.pagination a, .pagination strong')));
		$this->assertSame(['1', '2'], $pageLinks);
		$this->assertCount(10, $dom->querySelectorAll('.problem-nav__number'));

		$this->get(SetTable::url($setId, 2));
		$dom = $this->getStringDom();
		$problemNumbers = array_map(fn($node) => $node->textContent, iterator_to_array($dom->querySelectorAll('.problem-nav__number')));
		$this->assertSame(['41', '45', '49', '53', '57'], $problemNumbers);
	}

	public function testSetViewSanitizesDescriptionOnDisplay(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'xss set', 'num' => '1',
				'description' => '<b onmouseover="alert(1)">hi</b><a href="javascript:alert(2)">click</a>']]]]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$this->get('sets/' . $setId);

		// The layout draws its own onmouseover on the logo, so only the injected one counts.
		$this->assertStringNotContainsString('onmouseover="alert', $this->view);
		$this->assertStringNotContainsString('javascript:alert(2)', $this->view);
		$this->assertStringContainsString('<b>hi</b>', $this->view);
	}

	public function testClearFiltersReloadsCurrentSetView(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '666']],
			],
		]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->getAnonymous('sets/' . $setId);
		$browser->driver->manage()->deleteAllCookies();
		$browser->driver->manage()->addCookie(['name' => 'filtered_ranks', 'value' => '15k']);
		$browser->getAnonymous('sets/' . $setId);

		// open the filters panel so the active tiles (and clear button) are visible
		$browser->find('#showFilters')->click();
		$browser->waitUntilCssSelectorDisplayed('#unselect-active-tiles');

		$browser->find('#unselect-active-tiles')->click();

		$this->assertSame(Util::getMyAddress() . '/sets/' . $setId, $browser->driver->getCurrentURL());
		$this->assertFalse($browser->idExists('unselect-active-tiles'));
	}

	public function testRemovingOneFilterTileKeepsTheOthers(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'sets' => [['name' => 'tsumego set 1', 'num' => '1']],
			],
		]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->getAnonymous('sets/' . $setId);
		$browser->driver->manage()->deleteAllCookies();
		$browser->driver->manage()->addCookie(['name' => 'filtered_ranks', 'value' => '15k@1d']);
		$browser->getAnonymous('sets/' . $setId);

		$browser->find('#showFilters')->click();
		$browser->waitUntilCssSelectorDisplayed('#active-rank-element1');
		$browser->find('#active-rank-element0')->click();

		$browser->waitUntilJs('document.getElementById("active-rank-element0") !== null && document.getElementById("active-rank-element1") === null');
		$this->assertSame('1d', $browser->driver->executeScript('return document.getElementById("active-rank-element0").textContent;'));
	}

	private function checkSetNavigationButtons($browser, int $count, $context, $indexFunction, $orderFunction): array
	{
		$buttons = $browser->getElements('div.set-view-main li');
		$this->assertCount($count, $buttons);
		foreach ($buttons as $key => $button)
			$this->checkNavigationButton($button, $context, $indexFunction($key), $orderFunction($key));
		return $buttons;
	}

	//$title is the name with the place of the problem, "test set 2/3". The title of a collection names it
	//alone, its place is the open problem button; a title through a filter still names the place.
	private function checkPlayTitle($browser, string $title)
	{
		$browser->find('#playTitle');
		$collectionTopDivs = $browser->getElements('#playTitle');
		$this->assertCount(1, $collectionTopDivs);
		$text = $collectionTopDivs[0]->getText();
		if (str_starts_with($text, $title))
			return;
		$this->assertMatchesRegularExpression('#^(.*) (\d+)/\d+$#', $title);
		preg_match('#^(.*) (\d+)/\d+$#', $title, $parts);
		$this->assertTextStartsWith($parts[1], $text);
		$this->assertSame($parts[2], $browser->find('#currentNavigationButton')->getText(), 'The open problem button shows the place of the problem');
	}

	public function testDifficultyRangeSelectionShowsOneMergedBox(): void
	{
		$contextParams['tsumegos'] = [];
		foreach (['17k', '16k', '15k'] as $rank)
			$contextParams['tsumegos'][] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
				'sets' => [['name' => 'set ' . $rank, 'num' => '1']]];
		new ContextPreparator($contextParams);

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->findById('difficulty-button')->click();
		(new WebDriverSelect($browser->findById('difficulty-from')))->selectByValue('17k');
		(new WebDriverSelect($browser->findById('difficulty-to')))->selectByValue('16k');
		$browser->findById('tile-difficulty-submit')->click();

		$this->assertSame('17k-16k', urldecode($browser->driver->manage()->getCookieNamed('filtered_ranks')->getValue()));
		$boxes = $browser->getElements('.set-card__title');
		$this->assertCount(1, $boxes);
		$this->assertSame('17k-16k', $boxes[0]->getText());
	}

	public function testDifficultyPillsSelectSeparateBoxes(): void
	{
		$contextParams['tsumegos'] = [];
		foreach (['17k', '16k', '15k'] as $rank)
			$contextParams['tsumegos'][] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
				'sets' => [['name' => 'set ' . $rank, 'num' => '1']]];
		new ContextPreparator($contextParams);

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->findById('difficulty-button')->click();
		$browser->findById('tile-difficulty' . array_search('17k', Rating::ranks()))->click();
		$browser->findById('tile-difficulty' . array_search('16k', Rating::ranks()))->click();
		$browser->findById('tile-difficulty-submit')->click();

		$this->assertSame('17k@16k', urldecode($browser->driver->manage()->getCookieNamed('filtered_ranks')->getValue()));
		$boxes = $browser->getElements('.set-card__title');
		$this->assertCount(2, $boxes);
		$this->assertSame('17k', $boxes[0]->getText());
		$this->assertSame('16k', $boxes[1]->getText());
	}

	//A picked rank is shown in the filter bar and survives the next page load, before Search is pressed.
	public function testAPickedRankIsKeptAcrossThePageLoad(): void
	{
		$contextParams['tsumegos'] = [];
		foreach (['17k', '16k'] as $rank)
			$contextParams['tsumegos'][] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
				'sets' => [['name' => 'set ' . $rank, 'num' => '1']]];
		new ContextPreparator($contextParams);

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->findById('difficulty-button')->click();
		$browser->findById('tile-difficulty' . array_search('17k', Rating::ranks()))->click();

		$picked = $browser->getElements('.active-tiles-container .dropdown__tile--difficulty');
		$this->assertCount(1, $picked);
		$this->assertSame('17k', $picked[0]->getText());

		$browser->get('sets');
		$kept = $browser->getElements('.active-tiles-container .dropdown__tile--difficulty');
		$this->assertCount(1, $kept);
		$this->assertSame('17k', $kept[0]->getText());
	}

	public function testFullProcessOfDifficultyBasedSelectionAndSolving(): void
	{
		$contextParams = ['user' => ['mode' => Constants::$LEVEL_MODE]];
		$contextParams['tsumegos'] = [];

		$statuses = ['V', 'S', 'C', 'W'];

		// three problems in the 15k range in the same set (will be included)
		for ($i = 0; $i < 3; $i++)
		{
			$contextParams['tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
				'sets' => [['name' => 'set 1', 'num' => $i + 1]],
				'status' => $statuses[$i]];
		}

		// other 15k problem from different set, will be also included
		$contextParams['tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 2', 'num' => 4]],
			'status' => $statuses[3]];

		// other problems with different difficulty, but in the same set, will be excluded
		for ($i = 0; $i < 3; $i++)
		{
			$contextParams['tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('10k'),
				'sets' => [['name' => 'set 1', 'num' => $i + 4]]];
		}

		$context = new ContextPreparator($contextParams);

		// first we select the difficulty of 15k
		$browser = Browser::instance();
		$browser->get("sets");
		$browser->findById('difficulty-button')->click();
		(new WebDriverSelect($browser->findById('difficulty-from')))->selectByValue('15k');
		(new WebDriverSelect($browser->findById('difficulty-to')))->selectByValue('15k');
		$browser->findById('tile-difficulty-submit')->click();

		// difficulty selected
		$this->assertSame($browser->driver->manage()->getCookieNamed('query')->getValue(), 'difficulty');
		$this->assertSame($browser->driver->manage()->getCookieNamed('filtered_ranks')->getValue(), '15k');

		// we check the set card and clicking
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/15k', $browser->driver->getCurrentURL());

		// now we are viewing the 15k set insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 4, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		});

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 1/4');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 4, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		}, 0, 'V');

		// clicking on next problem
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 2/4');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 4, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		}, 1, 'S');
	}

	public function testFullProcessOfPartitionedSetBasedSelection(): void
	{
		$contextParams = ['user' => ['collection_size' => 10]];
		$contextParams['tsumegos'] = [];
		$statuses = array_merge(array_fill(0, 10, 'N'), ['V']);

		// 11 problems in our set, so it is split into a 10-problem and a 1-problem partition
		for ($i = 0; $i < 11; $i++)
		{
			$contextParams['tsumegos'] [] = [
				'set_order' => ($i + 1),
				'status' => $statuses[$i]];
		}

		$context = new ContextPreparator($contextParams);

		$browser = Browser::instance();
		$browser->get("sets");

		// we check the set card and clicking
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(2, $collectionTopDivs); // 2 partitions of 10 and 1 problems
		$this->assertSame($collectionTopDivs[0]->getText(), 'test set');
		$this->assertSame($collectionTopDivs[1]->getText(), 'test set');
		$parts = $browser->getElements('.set-card__part');
		$this->assertSame($parts[0]->getText(), '1/2');
		$this->assertSame($parts[1]->getText(), '2/2');
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/' . $context->tsumegos[0]['sets'][0]['id'], $browser->driver->getCurrentURL());

		// now we are viewing the 'test set' and checking the buttons

		// there should be just the 10 problems of the first partition
		$buttons = $this->checkSetNavigationButtons($browser, 10, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		});

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 10, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		}, 0, 'V');

		// clicking on next problem
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'test set #1 2/11');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 10, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		}, 1, 'V');

		// now we go back to the sets selection and we visit the second partition of the set
		$browser->get('sets');
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(2, $collectionTopDivs); // 2 partitions of 10 and 1 problems
		$collectionTopDivs[1]->click();

		// now we are in the second partition of the set
		$this->assertSame(Util::getMyAddress() . '/sets/' . $context->tsumegos[0]['sets'][0]['id'] . '/2', $browser->driver->getCurrentURL());

		// there should be just the 1 problem of the second partition
		$buttons = $this->checkSetNavigationButtons($browser, 1, $context, function ($index) {
			return $index + 10;
		}, function ($index) {
			return $index + 11;
		});

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();
		$this->checkPlayTitle($browser, 'test set #2 11/11');
	}

	public function testEditTitleFormSavesBaseTitle(): void
	{
		$contextParams = ['user' => ['collection_size' => 2]];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 4; $i++)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'My Set', 'num' => $i + 1, 'user_id' => 'self', 'public' => 0]]];

		$context = new ContextPreparator($contextParams);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->get('sets/edit/' . $setId);

		$browser->findById('SetTitle')->clear();
		$browser->findById('SetTitle')->sendKeys('Renamed Set');
		$browser->find('#set-edit-details input[type="submit"]')->click();

		$browser->waitUntilCssSelectorExistsWithText('.set-edit-title', 'Renamed Set');

		$this->assertSame('Renamed Set', $this->fetchTable('Set')->get($setId)->title);
	}


	public function testOfVisiting2RankBasedSetsBothInTheFilters(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'difficulty',
			'filtered_ranks' => ['15k', '1d']]];

		$contextParams['tsumegos'] = [];

		// three problems in the 15k range in different sets (sets of the problem shouldn't play a role anyway)
		for ($i = 0; $i < 3; $i++)
		{
			$contextParams['tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
				'sets' => [['name' => 'set ' . ($i + 1), 'num' => 1]]];
		}

		// three problems in the 1d range in different sets
		for ($i = 0; $i < 3; $i++)
		{
			$contextParams['tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('1d'),
				'sets' => [['name' => 'set ' . ($i + 1), 'num' => 2]]];
		}

		// three completely unrelated problems
		for ($i = 0; $i < 3; $i++)
		{
			$contextParams['tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('5d'),
				'sets' => [['name' => 'set ' . ($i + 1), 'num' => 3]]];
		}

		$context = new ContextPreparator($contextParams);

		$browser = Browser::instance();

		// we open sets, and since we filtered 15k and 1d, this is the sets we should see
		$browser->get("sets");
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$this->assertSame($collectionTopDivs[1]->getText(), '1d');

		// first we visit the 15k one
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/15k', $browser->driver->getCurrentURL());
		$this->assertSame($this->getSetTitle($browser), '15k');

		// now we are viewing the 15k set insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 3, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		});

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 1/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 3, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		}, 0, 'V');

		// clicking on next problem
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 2/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 3, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		}, 1, 'V');

		// now we go to visit the 1d section, so back to sets
		$browser->get("sets");
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$this->assertSame($collectionTopDivs[1]->getText(), '1d');

		// first we visit the 1d one
		$collectionTopDivs[1]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/1d', $browser->driver->getCurrentURL());
		$this->assertSame($this->getSetTitle($browser), '1d');

		// now we are viewing the 1d set insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 3, $context, function ($index) {
			return $index + 3;
		}, function ($index) {
			return $index + 1;
		});

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[3]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '1d 1/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 3, $context, function ($index) {
			return $index + 3;
		}, function ($index) {
			return $index + 1;
		}, 0, 'V');
	}

	public function testQueringSetsByTopicButLimitedByRanks(): void
	{
		$browser = Browser::instance();
		// filter by topics, but limit by ranks
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'topics',
			'filtered_ranks' => ['15k', '1d']]];

		$contextParams['tsumegos'] = [];

		// three ranks, and three sets, each rank is in each set once.
		// note that 5d is first, to check that the navigation button numbers will keep its order
		// of 2 and 3 when 1 is filtered one.
		foreach (['5d', '15k', '1d'] as $rankIndex => $rank)
			for ($i = 0; $i < 3; $i++)
				$contextParams['tsumegos'] [] = [
					'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
					'sets' => [['name' => 'set ' . ($i + 1), 'num' => ($rankIndex + 1)]]];

		$context = new ContextPreparator($contextParams);

		// we open sets, we filtered 15k and 1d, but query by sets, so we should see:
		// all 3 sets with
		$browser->get("sets");
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(3, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'set 1');
		$this->assertSame($collectionTopDivs[1]->getText(), 'set 2');
		$this->assertSame($collectionTopDivs[2]->getText(), 'set 3');

		// with 2 problems each as the 5d problems should be already filtered out
		$collectionMiddleLeftDivs = $browser->getElements('.set-card__count');
		$this->assertCount(3, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getAttribute('title'), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[1]->getAttribute('title'), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[2]->getAttribute('title'), '2 problems');

		// first visit the 'set 1'
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/' . $context->tsumegos[0]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$this->assertSame($this->getSetTitle($browser), 'set 1');

		// now we are viewing the 'set 1' insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 2, $context, function ($index) {
			return ($index + 1) * 3;
		}, function ($index) {
			return $index + 2;
		});

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[3]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set 1 2/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return ($index + 1) * 3;
		}, function ($index) {
			return $index + 2;
		}, 0, 'V');

		// clicking on next problem
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[6]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set 1 3/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return ($index + 1) * 3;
		}, function ($index) {
			return $index + 2;
		}, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/sets/' . $context->tsumegos[0]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$browser->driver->getPageSource();
		$this->assertCount(2, $browser->getElements('.title4'));
		$this->assertSame($this->getSetTitle($browser), 'set 1');
	}

	public function testQueringSetsByRanksButLimitedByTopics(): void
	{

		// filter by topics, but limit by ranks
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'difficulty',
			'filtered_sets' => ['set 2', 'set 3']]];

		$contextParams['tsumegos'] = [];

		// three ranks, and three sets, each rank is in each set once.
		// note that 5d is first, to check that the navigation button numbers will keep its order
		// of 2 and 3 when 1 is filtered one.
		foreach (['5d', '15k', '1d'] as $rankIndex => $rank)
			for ($i = 0; $i < 3; $i++)
			{
				$contextParams['tsumegos'] [] = [
					'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
					'sets' => [['name' => 'set ' . ($i + 1), 'num' => ($rankIndex + 1)]]];
			}

		$context = new ContextPreparator($contextParams);

		$browser = Browser::instance();

		// we open sets, we filtered set 2 and set 3, but query by ranks, so we should see:

		// all 3 ranks with
		$browser->get("sets");
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(3, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$this->assertSame($collectionTopDivs[1]->getText(), '1d');
		$this->assertSame($collectionTopDivs[2]->getText(), '5d');

		// with 2 problems each as the set 1 problems should already be filtered out
		$collectionMiddleLeftDivs = $browser->getElements('.set-card__count');
		$this->assertCount(3, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getAttribute('title'), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[1]->getAttribute('title'), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[2]->getAttribute('title'), '2 problems');

		// first visit the 'set 15k'
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/15k', $browser->driver->getCurrentURL());
		$this->assertSame($this->getSetTitle($browser), '15k');

		// now we are viewing the 'set 2' insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 2, $context, function ($index) {
			return $index + 4;
		}, function ($index) {
			return $index + 1;
		});

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[4]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 1/2');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return $index + 4;
		}, function ($index) {
			return $index + 1;
		}, 0, 'V');

		// clicking on next problem
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[5]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 2/2');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return $index + 4;
		}, function ($index) {
			return $index + 1;
		}, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/sets/15k', $browser->driver->getCurrentURL());
		$this->assertSame($this->getSetTitle($browser), '15k');
	}

	//Collections sorted by difficulty -> click problem directly (no lastSet cookie) -> shows current set only.
	public function testDifficultyQueryWithoutLastSetShowsCurrentSet(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['query' => 'difficulty'],
			'tsumegos' => [
				['sets' => [['name' => 'Set A', 'num' => 1]]],
				['sets' => [['name' => 'Set A', 'num' => 2]]],
				['sets' => [['name' => 'Set B', 'num' => 1]]],
			]]);

		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// Title should show set context, not generic "Tsumego X/Y"
		$this->checkPlayTitle($browser, 'Set A 1/2');

		// Navigation should show 2 buttons (Set A: 1, 2), not 3 (mixing with Set B)
		$buttons = $browser->getElements('div.problem-nav__inner li');
		$this->assertCount(2, $buttons, 'Bug: navigation mixed problems from different sets');
	}

	public function testQueringSetsByRanksButWithWrongLastSetCookie(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'difficulty'],
			'tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);

		$browser->setCookie('lastSet', 'Hello world');
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->checkPlayTitle($browser, 'set 1 1/1');
	}

	//When query=difficulty but lastSet is an invalid rank (e.g. a topic set name left over from
	//a previous session), navigation should fall back to topic-based ordering within the current set.
	//The navigation buttons must appear in set_connection.num order, not tsumego.id order.
	public function testDifficultyWithInvalidLastSetShowsCorrectNumOrder(): void
	{
		$browser = Browser::instance();
		// Create 3 problems with non-sequential nums so tsumego_id order differs from num order
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'difficulty'],
			'tsumegos' => [
				['sets' => [['name' => 'test set', 'num' => 3]]],
				['sets' => [['name' => 'test set', 'num' => 1]]],
				['sets' => [['name' => 'test set', 'num' => 2]]],
			]]);

		$browser->setCookie('lastSet', 'test set');
		$browser->get('/' . $context->tsumegos[1]['set-connections'][0]['id']); // navigate to num=1

		$this->checkPlayTitle($browser, 'test set 1/3');

		// Buttons should be in num order: num=1 (tsumegos[1]), num=2 (tsumegos[2]), num=3 (tsumegos[0])
		$this->checkPlayNavigationButtons($browser, 3, $context,
			function ($index) { return [1, 2, 0][$index]; },
			function ($index) { return $index + 1; },
			0, 'V');
	}

	//When query=tags but lastSet is empty, navigation should fall back to topic-based ordering.
	public function testTagsWithEmptyLastSetShowsCorrectNumOrder(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'tags'],
			'tsumegos' => [
				['sets' => [['name' => 'test set', 'num' => 3]]],
				['sets' => [['name' => 'test set', 'num' => 1]]],
				['sets' => [['name' => 'test set', 'num' => 2]]],
			]]);

		$browser->get('/' . $context->tsumegos[1]['set-connections'][0]['id']); // navigate to num=1

		$this->checkPlayTitle($browser, 'test set 1/3');

		$this->checkPlayNavigationButtons($browser, 3, $context,
			function ($index) { return [1, 2, 0][$index]; },
			function ($index) { return $index + 1; },
			0, 'V');
	}

	//When query=tags but lastSet points to a nonexistent tag, navigation should fall back to topics.
	public function testTagsWithNonexistentTagShowsCorrectNumOrder(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'tags'],
			'tsumegos' => [
				['sets' => [['name' => 'test set', 'num' => 3]]],
				['sets' => [['name' => 'test set', 'num' => 1]]],
				['sets' => [['name' => 'test set', 'num' => 2]]],
			]]);

		$browser->setCookie('lastSet', 'nonexistent_tag_xyz');
		$browser->get('/' . $context->tsumegos[1]['set-connections'][0]['id']);

		$this->checkPlayTitle($browser, 'test set 1/3');

		$this->checkPlayNavigationButtons($browser, 3, $context,
			function ($index) { return [1, 2, 0][$index]; },
			function ($index) { return $index + 1; },
			0, 'V');
	}

	//When query=difficulty and lastSet is empty, navigation should fall back to topics with correct order.
	public function testDifficultyWithEmptyLastSetShowsCorrectNumOrder(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'difficulty'],
			'tsumegos' => [
				['sets' => [['name' => 'test set', 'num' => 3]]],
				['sets' => [['name' => 'test set', 'num' => 1]]],
				['sets' => [['name' => 'test set', 'num' => 2]]],
			]]);

		$browser->get('/' . $context->tsumegos[1]['set-connections'][0]['id']);

		$this->checkPlayTitle($browser, 'test set 1/3');

		$this->checkPlayNavigationButtons($browser, 3, $context,
			function ($index) { return [1, 2, 0][$index]; },
			function ($index) { return $index + 1; },
			0, 'V');
	}

	//The previous and next links follow the order of the set, and lead to the set at its edges.
	public function testPreviousAndNextLinksInTopicMode(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumegos' => [
				['sets' => [['name' => 'test set', 'num' => 1]]],
				['sets' => [['name' => 'test set', 'num' => 2]]],
				['sets' => [['name' => 'test set', 'num' => 3]]],
			]]);

		$browser->get('/' . $context->tsumegos[1]['set-connections'][0]['id']);
		$this->checkPlayTitle($browser, 'test set 2/3');
		$this->assertSame('/' . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->executeScript('return previousButtonLink;'));
		$this->assertSame('/' . $context->tsumegos[2]['set-connections'][0]['id'], $browser->driver->executeScript('return nextButtonLink;'));

		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertSame(SetTable::url($context->tsumegos[0]['sets'][0]['id']), $browser->driver->executeScript('return previousButtonLink;'));

		$browser->get('/' . $context->tsumegos[2]['set-connections'][0]['id']);
		$this->assertSame(SetTable::url($context->tsumegos[2]['sets'][0]['id']), $browser->driver->executeScript('return nextButtonLink;'));
	}

	//Navigation with a valid difficulty rank should show problems across sets in sequential order.
	public function testDifficultyNavigationWithValidRankShowsProblemsAcrossSets(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'difficulty'],
			'tsumegos' => [
				['rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
					'sets' => [['name' => 'set A', 'num' => 1]]],
				['rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
					'sets' => [['name' => 'set B', 'num' => 1]]],
				['rating' => Rating::getRankMiddleRatingFromReadableRank('10k'),
					'sets' => [['name' => 'set C', 'num' => 1]]],
			]]);

		$browser->setCookie('lastSet', '15k');
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->checkPlayTitle($browser, '15k 1/2');

		// Should show 2 buttons (both 15k problems), not 3 (10k excluded)
		$this->checkPlayNavigationButtons($browser, 2, $context,
			function ($index) { return $index; },
			function ($index) { return $index + 1; },
			0, 'V');
	}

	//The set filter is meant for the difficulty and tag collections, the fallback to the set of the
	//problem shows the whole set, as when the set is opened directly.
	public function testFallbackToTopicsIgnoresSetFilter(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'difficulty', 'filtered_sets' => ['other set']],
			'tsumegos' => [
				['sets' => [['name' => 'test set', 'num' => 2]]],
				['sets' => [['name' => 'test set', 'num' => 1]]],
				['sets' => [['name' => 'other set', 'num' => 1]]],
			]]);

		$browser->setCookie('lastSet', 'not a rank');
		$browser->get('/' . $context->tsumegos[1]['set-connections'][0]['id']);

		$this->checkPlayTitle($browser, 'test set 1/2');
		$this->checkPlayNavigationButtons($browser, 2, $context,
			function ($index) { return [1, 0][$index]; },
			function ($index) { return $index + 1; },
			0, 'V');
	}

	//When the opened problem is not part of the difficulty collection (another tab switched to another
	//rank, or its rating moved it to another rank), the navigation shows its set instead of a
	//collection without the current problem.
	public function testDifficultyFallsBackToSetWhenProblemIsNotInTheRank(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'difficulty'],
			'tsumegos' => [
				['rating' => Rating::getRankMiddleRatingFromReadableRank('10k'),
					'sets' => [['name' => 'test set', 'num' => 2]]],
				['rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
					'sets' => [['name' => 'test set', 'num' => 1]]],
			]]);

		$browser->setCookie('lastSet', '15k');
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->checkPlayTitle($browser, 'test set 2/2');
		$this->checkPlayNavigationButtons($browser, 2, $context,
			function ($index) { return [1, 0][$index]; },
			function ($index) { return $index + 1; },
			1, 'V');
		$this->assertSame(SetTable::url($context->tsumegos[0]['sets'][0]['id']), $browser->driver->executeScript('return nextButtonLink;'));
	}

	//Same as above, for a tag collection the problem doesn't have.
	public function testTagsFallBackToSetWhenProblemDoesNotHaveTheTag(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'tags'],
			'tsumegos' => [
				['sets' => [['name' => 'test set', 'num' => 1]]],
				['sets' => [['name' => 'test set', 'num' => 2]], 'tags' => [['name' => 'atari']]],
			]]);

		$browser->setCookie('lastSet', 'atari');
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->checkPlayTitle($browser, 'test set 1/2');
		$this->checkPlayNavigationButtons($browser, 2, $context,
			function ($index) { return $index; },
			function ($index) { return $index + 1; },
			0, 'V');
	}

	public function testClickingOnSetViewSwitchesTsumegoFiltersToSetViewAndShowsCorrectTsumego(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'difficulty'],
			'tsumegos' => [[
				'sets' => [['name' => 'set 1', 'num' => 2], ['name' => 'set 3', 'num' => 4]],
				'rating' => Rating::getRankMiddleRatingFromReadableRank('10k')]]]);

		$browser->get('sets');

		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '10k');

		// the problem is in 2 sets, but it shouldn't matter in this view and should show just 1 problem
		$collectionMiddleLeftDivs = $browser->getElements('.set-card__count');
		$this->assertCount(1, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getAttribute('title'), '1 problem');

		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/10k', $browser->driver->getCurrentURL());

		// now we are viewing the 10k set insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 1, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		});

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '10k 1/1');
	}

	public function testOpeningProblemOutsideCurrentFilters(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => [
				'mode' => Constants::$LEVEL_MODE,
				'query' => 'topics',
				'filtered_ranks' => ['10k']],
			'tsumegos' => [
				['rating' => Rating::getRankMiddleRatingFromReadableRank('5k'), 'sets' => [['name' => 'set 1', 'num' => 1]]],
				['rating' => Rating::getRankMiddleRatingFromReadableRank('10k'), 'sets' => [['name' => 'set 1', 'num' => 1]]]]]);

		// we filtered to 10k, but we are opening the 5k problem
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertEmpty($browser->getElements('#currentNavigationButton')); // no tsumego button is marked as current
	}

	public function testSelectingTagFilters(): void
	{
		$contextParams = ['user' => ['mode' => Constants::$LEVEL_MODE]];
		$contextParams['tsumegos'] = [];

		// each problem to introduce one tag type
		foreach (['snapback', 'atari', 'empty triangle'] as $tag)
			$contextParams['tsumegos'] [] = [
				'tags' => [['name' => $tag]]];

		$context = new ContextPreparator($contextParams);

		// first we select the difficulty of 15k
		$browser = Browser::instance();
		$browser->get("sets");
		$browser->findById('tags-button')->click();
		$tagSelectors = $browser->getElements('[id^="tile-tags"]:not([id*="select-all"]):not([id*="submit"])');
		$this->assertCount(3, $tagSelectors);
		$this->assertSame($tagSelectors[0]->getText(), 'snapback');
		$this->assertSame($tagSelectors[1]->getText(), 'atari');
		$this->assertSame($tagSelectors[2]->getText(), 'empty triangle');
		$tagSelectors[0]->click();
		$browser->findById('tile-tags-submit')->click();

		// difficulty selected
		$this->assertSame($browser->driver->manage()->getCookieNamed('query')->getValue(), 'tags');
		$this->assertSame($browser->driver->manage()->getCookieNamed('filtered_tags')->getValue(), 'snapback');
	}

	public function testVisitingTagBasedSets(): void
	{
		$browser = Browser::instance();
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'tags']];
		$contextParams['tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		foreach (['snapback', 'atari', 'empty triangle'] as $key => $tag)
			for ($i = 0; $i < 3 - $key; $i++)
			{
				$contextParams['tsumegos'] [] = [
					'sets' => [['name' => 'set 1', 'num' => $i + 1]],
					'tags' => [['name' => $tag]]];
			}

		$context = new ContextPreparator($contextParams);
		$browser->get("sets");
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(3, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'snapback');
		$this->assertSame($collectionTopDivs[1]->getText(), 'atari');
		$this->assertSame($collectionTopDivs[2]->getText(), 'empty triangle');
		$this->assertSame('Problems found: 6', $browser->find('#problems-found')->getText());
	}

	public function testVisitingTagBasedSetsRespectsTagFilters(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'tags',
			'filtered_tags' => ['atari', 'empty triangle']]];
		$contextParams['tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		foreach (['snapback', 'atari', 'empty triangle'] as $key => $tag)
			for ($i = 0; $i < 3 - $key; $i++)
			{
				$contextParams['tsumegos'] [] = [
					'sets' => [['name' => 'set 1', 'num' => $i + 1]],
					'tags' => [['name' => $tag]]];
			}

		$context = new ContextPreparator($contextParams);
		$browser = Browser::instance();
		$browser->get("sets");
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'atari');
		$this->assertSame($collectionTopDivs[1]->getText(), 'empty triangle');
		$this->assertSame('Problems found: 3', $browser->find('#problems-found')->getText());

		// going into the 'atari' set
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/atari', $browser->driver->getCurrentURL());
		$this->assertSame($this->getSetTitle($browser), 'atari');

		// now we are viewing the 'atari' insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 2, $context, function ($index) {
			return $index + 3;
		}, function ($index) {
			return $index + 1;
		});

		// entering the tsumego in the set
		$buttons[0]->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[3]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'atari 1/2');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return $index + 3;
		}, function ($index) {
			return $index + 1;
		}, 0, 'V');

		// clicking next to get to the second one
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[4]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'atari 2/2');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return $index + 3;
		}, function ($index) {
			return $index + 1;
		}, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/sets/atari', $browser->driver->getCurrentURL());
		$this->assertSame($this->getSetTitle($browser), 'atari');
	}

	public function testVisitingTopicBasedSetsRespectsTagFilters(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'topics',
			'filtered_tags' => ['atari', 'empty triangle']]];
		$contextParams['tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		foreach (['snapback', 'atari', 'empty triangle'] as $key => $tag)
			for ($i = 0; $i < 3 - $key; $i++)
			{
				$contextParams['tsumegos'] [] = [
					'sets' => [['name' => 'set ' . ($i + 1), 'num' => $key + 1]],
					'tags' => [['name' => $tag]]];
			}

		$context = new ContextPreparator($contextParams);
		$browser = Browser::instance();
		$browser->get("sets");
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'set 1');
		$this->assertSame($collectionTopDivs[1]->getText(), 'set 2');

		$collectionMiddleLeftDivs = $browser->getElements('.set-card__count');
		$this->assertCount(2, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getAttribute('title'), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[1]->getAttribute('title'), '1 problem');
		$this->assertSame('Problems found: 3', $browser->find('#problems-found')->getText());

		// going into the 'set 1'
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/' . $context->tsumegos[3]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$this->assertSame($this->getSetTitle($browser), 'set 1');

		// now we are viewing the 'set 1' insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 2, $context, function ($index) {
			return $index * 2 + 3;
		}, function ($index) {
			return $index + 2;
		});

		// entering the tsumego in the set
		$buttons[0]->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[3]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set 1 2/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return $index * 2 + 3;
		}, function ($index) {
			return $index + 2;
		}, 0, 'V');

		// clicking next to get to the second one
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[5]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set 1 3/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return $index * 2 + 3;
		}, function ($index) {
			return $index + 2;
		}, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->find('#besogo-next-button')->click();
		$this->assertSame(Util::getMyAddress() . '/sets/' . $context->tsumegos[3]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$this->assertSame($this->getSetTitle($browser), 'set 1');
	}

	private function checkSetFinishedPercent($browser, $index, $title, $percent): void
	{
		$this->assertSame($browser->getElements('.set-card__title')[$index]->getText(), $title);
		$this->assertSame($browser->getElements('.set-card__percent')[$index]->getText(), $percent);
	}

	public function testTopicBasedSetViewShowsSolvedPercentProperly(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'topics']];
		$contextParams['tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		// each have one unsolved
		foreach (['set 1', 'set 2', 'set 3', 'set 4', 'set 5'] as $key => $set)
			for ($i = 0; $i < 4; $i++)
			{
				$contextParams['tsumegos'] [] = [
					'sets' => [['name' => $set, 'num' => $i + 1]],
					'status' => ($i >= $key ? 'N' : 'S')];
			}

		$context = new ContextPreparator($contextParams);
		$browser = Browser::instance();
		$browser->get("sets");

		$browser->waitUntilJs('document.querySelectorAll(".set-card__percent")[4]?.textContent.trim() === "100%"');

		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(5, $collectionTopDivs);
		$this->checkSetFinishedPercent($browser, 0, 'set 1', '0%');
		$this->checkSetFinishedPercent($browser, 1, 'set 2', '25%');
		$this->checkSetFinishedPercent($browser, 2, 'set 3', '50%');
		$this->checkSetFinishedPercent($browser, 3, 'set 4', '75%');
		$this->checkSetFinishedPercent($browser, 4, 'set 5', '100%');
		$this->assertSame('Problems found: 20', $browser->find('#problems-found')->getText());
	}

	public function testPartitionedTopicBasedSetViewShowsSolvedPercentProperly(): void
	{
		$browser = Browser::instance();
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'topics']];
		$contextParams['tsumegos'] = [];

		// Fill 300 problems in reverse so tsumego ids are not sequential in the
		// set; the SQL must sort primarily by set connection num.
		for ($i = 299; $i >= 0; $i--)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'partitioned set', 'num' => $i + 1]],
				'status' => ($i < 200 ? ($i < 66 ? 'S' : 'N') : (($i - 200) < 66 ? 'S' : 'N'))];

		new ContextPreparator($contextParams);
		$browser->get("sets");

		$browser->waitUntilJs('document.querySelectorAll(".set-card__percent")[0]?.textContent.trim() === "33%"');

		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(2, $collectionTopDivs);
		$this->checkSetFinishedPercent($browser, 0, 'partitioned set', '33%');
		$this->checkSetFinishedPercent($browser, 1, 'partitioned set', '66%');
		// the part is the tab in the card's corner
		$parts = $browser->getElements('.set-card__part');
		$this->assertSame('1/2', $parts[0]->getText());
		$this->assertSame('2/2', $parts[1]->getText());
		$this->assertSame('Problems found: 300', $browser->find('#problems-found')->getText());
	}

	public function testTagBasedSetViewShowsSolvedPercentProperly(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'tags']];
		$contextParams['tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		// each have one unsolved
		foreach (['atari', 'bambule', 'empty triangle', 'snapback', 'zen'] as $key => $tag)
			for ($i = 0; $i < 4; $i++)
			{
				$contextParams['tsumegos'] [] = [
					'sets' => [['name' => 'set 1', 'num' => $i + 1]],
					'tags' => [['name' => $tag]],
					'status' => ($i >= $key ? 'N' : 'S')];
			}

		$context = new ContextPreparator($contextParams);
		$browser = Browser::instance();
		$browser->get("sets");

		$browser->waitUntilJs('document.querySelectorAll(".set-card__percent")[4]?.textContent.trim() === "100%"');

		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(5, $collectionTopDivs);
		$this->checkSetFinishedPercent($browser, 0, 'atari', '0%');
		$this->checkSetFinishedPercent($browser, 1, 'bambule', '25%');
		$this->checkSetFinishedPercent($browser, 2, 'empty triangle', '50%');
		$this->checkSetFinishedPercent($browser, 3, 'snapback', '75%');
		$this->checkSetFinishedPercent($browser, 4, 'zen', '100%');
	}

	public function testRankBasedSetViewShowsSolvedPercentProperly(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'difficulty']];
		$contextParams['tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		// each have one unsolved
		foreach (['15k', '10k', '5k', '1d', '5d'] as $key => $rank)
			for ($i = 0; $i < 4; $i++)
			{
				$contextParams['tsumegos'] [] = [
					'sets' => [['name' => 'set 1', 'num' => $i + 1]],
					'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
					'status' => ($i >= $key ? 'N' : 'S')];
			}

		$context = new ContextPreparator($contextParams);
		$browser = Browser::instance();
		$browser->get("sets");

		$browser->waitUntilJs('document.querySelectorAll(".set-card__percent")[4]?.textContent.trim() === "100%"');

		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(5, $collectionTopDivs);
		$this->checkSetFinishedPercent($browser, 0, '15k', '0%');
		$this->checkSetFinishedPercent($browser, 1, '10k', '25%');
		$this->checkSetFinishedPercent($browser, 2, '5k', '50%');
		$this->checkSetFinishedPercent($browser, 3, '1d', '75%');
		$this->checkSetFinishedPercent($browser, 4, '5d', '100%');
		$this->assertSame('Problems found: 20', $browser->find('#problems-found')->getText());

		$collectionTopDivs[1]->click();
		$this->assertTextContains('Difficulty: <b>10k</b>', $browser->driver->getPageSource());
		$this->assertTextContains('Solved: <b>25%</b>', $browser->driver->getPageSource());
	}

	public function testOnlyPublicSetsAreVisible(): void
	{
		new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'public set', 'public' => 1, 'num' => '666'],
			['name' => 'private set', 'public' => 0, 'num' => '777']]]]);

		$browser = Browser::instance();
		$browser->get('sets');
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'public set');
		$this->assertSame('Problems found: 1', $browser->find('#problems-found')->getText());
	}

	public function testOnlyPrivateSetsAreVisibleInSandbox(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'tsumegos' => [
				['sets' => [['name' => 'public set', 'public' => 1, 'num' => '666']]],
				['sets' => [['name' => 'private set', 'public' => 0, 'num' => '777']]]]]);
		$browser = Browser::instance();
		$browser->get('sets/sandbox');
		$collectionTopDivs = $browser->getElements('.set-card__title');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'private set');
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/' . $context->tsumegos[1]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());

		$problemButtons = $browser->getElements('.problem-nav__number');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->getText(), '777');
	}

	public function testAddingProblemInSandbox(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => 1],
			'tsumegos' => [['sets' => [['name' => 'private set', 'public' => 0, 'num' => '1']]]]]);
		$browser = Browser::instance();
		$browser->get('/sets/edit/' . $context->tsumegos[0]['set-connections'][0]['set_id']);

		$problemButtons = $browser->getElements('.problem-nav__number');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->getText(), '1');
		$browser->clickCssSelect('#TsumegoViewForm input[type="submit"]');

		// Wait for the page to reload with the new problem
		$browser->waitUntilJs('document.querySelectorAll(".problem-nav__number").length === 2');

		$problemButtons = $browser->getElements('.problem-nav__number');
		$this->assertCount(2, $problemButtons);
		$this->assertSame($problemButtons[0]->getText(), '1');
		$this->assertSame($problemButtons[1]->getText(), '2');
	}

	//Test set view Completed tab shows problem ORDER numbers (1, 2, 3...)
	//This is the default view - shows which problems exist in the set
	public function testSetViewCompletedTabShowsOrderNumbers()
	{
		// Create ONE set with THREE tsumegos
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['sets' => [['name' => 'Test Set', 'num' => 1]]],
				['sets' => [['name' => 'Test Set', 'num' => 2]]],
				['sets' => [['name' => 'Test Set', 'num' => 3]]],
			],
		]);

		$browser = Browser::instance();
		// Get set ID from first other-tsumego
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$browser->get("sets/{$setId}");

		// Completed tab should be active by default - find problem buttons
		$buttons = $browser->getElements('.problem-nav__number');

		// Should show problem numbers: 1, 2, 3
		$this->assertCount(3, $buttons, 'Should have 3 problems in set');
		$this->assertSame('1', trim($buttons[0]->getText()));
		$this->assertSame('2', trim($buttons[1]->getText()));
		$this->assertSame('3', trim($buttons[2]->getText()));

		// setViewButtons2 (accuracy) and setViewButtons3 (time) should be hidden
		$accuracyButtons = $browser->getElements('.problem-nav__ratio');
		foreach ($accuracyButtons as $btn)
			$this->assertFalse($btn->isDisplayed(), 'Accuracy buttons should be hidden on Completed tab');

		$timeButtons = $browser->getElements('.problem-nav__time');
		foreach ($timeButtons as $btn)
			$this->assertFalse($btn->isDisplayed(), 'Time buttons should be hidden on Completed tab');
	}

	//Test set view button CSS classes reflect problem status
	//Buttons should have statusN (not attempted), statusS (solved), statusF (failed), etc.
	public function testSetViewButtonStatusClasses()
	{
		// Create set with 3 problems: not attempted, solved, failed
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				[
					'sets' => [['name' => 'Test Set', 'num' => 1]]
					// Not attempted
				],
				[
					'sets' => [['name' => 'Test Set', 'num' => 2]],
					'status' => 'S',  // Solved
				],
				[
					'sets' => [['name' => 'Test Set', 'num' => 3]],
					'status' => 'F',  // Failed
				],
			],
		]);

		$browser = Browser::instance();
		$setId = $context->tsumegos[0]['sets'][0]['id'];
		$browser->get("sets/{$setId}");

		// Find the <li> elements (button containers with status classes)
		$listItems = $browser->getElements('li.problem-nav__item');
		$this->assertCount(3, $listItems, 'Should have 3 problem buttons');

		// Problem 1: Not attempted - should have statusN class
		$this->assertStringContainsString('problem-nav__item--N', $listItems[0]->getAttribute('class'), 'Problem 1 should have statusN (not attempted)');

		// Problem 2: Solved - should have statusS class
		$this->assertStringContainsString('problem-nav__item--S', $listItems[1]->getAttribute('class'), 'Problem 2 should have statusS (solved)');

		// Problem 3: Failed - should have statusF class
		$this->assertStringContainsString('problem-nav__item--F', $listItems[2]->getAttribute('class'), 'Problem 3 should have statusF (failed)');
	}

	//Test set view Accuracy tab shows success/failure ratio (e.g., "3/1" = 3 solved, 1 failed)
	//As per UI description: "The solved and failed (s/f) attempts are displayed."
	public function testSetViewAccuracyTabShowsSuccessFailureRatio()
	{
		// Create ONE set with TWO tsumegos, first has multiple attempts
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				[
					'sets' => [['name' => 'Test Set', 'num' => 1]],
					'attempts' => [
						['solved' => 1, 'seconds' => 10, 'gain' => 5],
						['solved' => 1, 'seconds' => 15, 'gain' => 5],
						['solved' => 1, 'seconds' => 12, 'gain' => 5],
						['solved' => 0, 'seconds' => 20, 'gain' => -5, 'misplays' => 1],
					],
				],
				['sets' => [['name' => 'Test Set', 'num' => 2]]],  // No attempts
			],
		]);

		$browser = Browser::instance();
		$setId = $context->tsumegos[0]['sets'][0]['id'];
		$browser->get("sets/{$setId}");

		// Click Accuracy tab
		$accuracyTab = $browser->findBy(WebDriverBy::xpath("//a[contains(text(), 'Accuracy')]"));
		$accuracyTab->click();
		// Wait for accuracy buttons to be visible
		$browser->waitUntilCssSelectorDisplayed('.problem-nav__ratio');

		// Check accuracy buttons are visible
		$accuracyButtons = $browser->getElements('.problem-nav__ratio');
		$this->assertCount(2, $accuracyButtons);

		// Problem 1 should show "3/1" (3 solved, 1 failed)
		$this->assertTrue($accuracyButtons[0]->isDisplayed());
		$this->assertSame('3/1', trim($accuracyButtons[0]->getText()), 'Problem 1 accuracy should be 3/1');

		// Problem 2 should show "-" (no attempts)
		$this->assertTrue($accuracyButtons[1]->isDisplayed());
		$this->assertSame('-', trim($accuracyButtons[1]->getText()), 'Problem 2 accuracy should be - (no attempts)');

		// Order numbers and time should be hidden
		$orderButtons = $browser->getElements('.problem-nav__number');
		foreach ($orderButtons as $btn)
			$this->assertFalse($btn->isDisplayed(), 'Order numbers should be hidden on Accuracy tab');
	}

	//Misplays on solved attempts are summed across all solve sessions.
	public function testSetViewAccuracyTabSumsMisplaysAcrossSolvedAttempts()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				[
					'sets' => [['name' => 'Test Set', 'num' => 1]],
					'attempts' => [
						['solved' => 1, 'seconds' => 10, 'gain' => 5, 'misplays' => 3],
						['solved' => 1, 'seconds' => 15, 'gain' => 5, 'misplays' => 2],
					],
				],
				['sets' => [['name' => 'Test Set', 'num' => 2]]],  // No attempts
			],
		]);

		$browser = Browser::instance();
		$setId = $context->tsumegos[0]['sets'][0]['id'];
		$browser->get("sets/{$setId}");

		// Click Accuracy tab
		$accuracyTab = $browser->findBy(WebDriverBy::xpath("//a[contains(text(), 'Accuracy')]"));
		$accuracyTab->click();
		// Wait for accuracy buttons to be visible
		$browser->waitUntilCssSelectorDisplayed('.problem-nav__ratio');

		$accuracyButtons = $browser->getElements('.problem-nav__ratio');
		$this->assertSame('2/5', trim($accuracyButtons[0]->getText()), 'Two solved attempts (3 + 2 misplays) should show 2/5');
	}

	//Test set view Time tab shows MINIMUM (best) solve time in seconds
	//As per UI description: "The time (in seconds) for solving is displayed."
	public function testSetViewTimeTabShowsMinimumSolveTime()
	{
		// Create ONE set with TWO tsumegos
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				[
					'sets' => [['name' => 'Test Set', 'num' => 1]],
					'attempts' => [
						['solved' => 1, 'seconds' => 10, 'gain' => 5],
						['solved' => 1, 'seconds' => 20, 'gain' => 5],
						['solved' => 1, 'seconds' => 30, 'gain' => 5],
					],
				],
				[
					'sets' => [['name' => 'Test Set', 'num' => 2]],
					'attempts' => [
						['solved' => 0, 'seconds' => 20, 'gain' => -5, 'misplays' => 1],
					],
				],
			],
		]);

		$browser = Browser::instance();
		$setId = $context->tsumegos[0]['sets'][0]['id'];
		$browser->get("sets/{$setId}");

		// Switch to Time tab
		$browser->findById('timeButton')->click();
		// Wait for time buttons to be visible
		$browser->waitUntilCssSelectorDisplayed('.problem-nav__time');

		// Check time buttons are visible
		$timeButtons = $browser->getElements('.problem-nav__time');
		$this->assertCount(2, $timeButtons);

		// Problem 1 should show "10s" (minimum/best of 10, 20, 30)
		$this->assertTrue($timeButtons[0]->isDisplayed());
		$this->assertSame('10s', trim($timeButtons[0]->getText()), 'Problem 1 time should be 10s (best time)');

		// Problem 2 should show "-" (no successful solves)
		$this->assertTrue($timeButtons[1]->isDisplayed());
		$this->assertSame('-', trim($timeButtons[1]->getText()), 'Problem 2 time should be - (no successful solves)');
	}

	//The average time is of the solved problems only, so solving another one faster than the
	//average brings it down (forum#149). A solve without a recorded time counts as a minute.
	public function testSetViewAverageTimeIsOfTheSolvedProblemsOnly(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['sets' => [['name' => 'Test Set', 'num' => 1]], 'status' => 'S', 'attempts' => [['solved' => 1, 'seconds' => 10]]],
				['sets' => [['name' => 'Test Set', 'num' => 2]], 'status' => 'S', 'attempts' => [['solved' => 1, 'seconds' => 20]]],
				['sets' => [['name' => 'Test Set', 'num' => 3]], 'status' => 'S'],
				['sets' => [['name' => 'Test Set', 'num' => 4]], 'status' => 'V', 'attempts' => [['solved' => 0, 'seconds' => 40, 'misplays' => 1]]],
				['sets' => [['name' => 'Test Set', 'num' => 5]]]]]);

		$this->get('sets/' . $context->tsumegos[0]['sets'][0]['id']);

		$this->assertEquals(30, $this->viewVariable('avgTime'), '(10 + 20 + 60) / 3');
	}

	public function testSetViewAverageTimeWithNothingSolvedIsZero(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [['sets' => [['name' => 'Test Set', 'num' => 1]]]]]);

		$this->get('sets/' . $context->tsumegos[0]['sets'][0]['id']);

		$this->assertEquals(0, $this->viewVariable('avgTime'));
	}

	//Each attempt that solved a problem counts as a solve, each misplay as a fail and an attempt that
	//failed without one as one fail. A solved problem without an attempt counts as one fail.
	public function testSetViewCountsTheAttemptsOfEachProblemAndTheAccuracy(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['sets' => [['name' => 'Test Set', 'num' => 1]], 'status' => 'S', 'attempts' => [
					['solved' => 1, 'seconds' => 30, 'misplays' => 2],
					['solved' => 1, 'seconds' => 12],
					['solved' => 0, 'seconds' => 5, 'misplays' => 0]]],
				['sets' => [['name' => 'Test Set', 'num' => 2]], 'status' => 'S'],
				['sets' => [['name' => 'Test Set', 'num' => 3]], 'status' => 'F', 'attempts' => [['solved' => 0, 'seconds' => 40, 'misplays' => 3]]],
				['sets' => [['name' => 'Test Set', 'num' => 4]]]]]);

		$this->get('sets/' . $context->tsumegos[0]['sets'][0]['id']);

		$buttons = $this->viewVariable('tsumegoButtons');
		$this->assertSame([2, 0, 0, 0], array_map(fn($button) => $button->solvedAttempts, (array) $buttons));
		$this->assertSame([3, 0, 3, 0], array_map(fn($button) => $button->failedAttempts, (array) $buttons));
		$this->assertEquals([12, 0, 0, 0], array_map(fn($button) => $button->seconds, (array) $buttons), 'the best solve time');
		$this->assertEquals(22.22, $this->viewVariable('accuracy'), '2 solves against 3 + 1 + 3 fails');
		$this->assertEquals(36, $this->viewVariable('avgTime'), '(12 + 60) / 2');
		$this->assertTextContains('<div class="problem-nav__ratio">2/3</div>', (string) $this->_response->getBody());
	}

	//The parts of these tests are of ten problems, the smallest collection size, so a page of that size is a part
	private const PART = 10;

	protected function tearDown(): void
	{
		ProgressDeletionTable::$PARTITION_SIZE = 200;
		parent::tearDown();
	}

	//The best scores of the part kept for the player
	/** @return array{accuracy: ?float, time: ?float} */
	private static function bestOf(int $setID, int $partition): array
	{
		$userID = (int) Auth::getUserID();
		return [
			'accuracy' => AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_ACCURACY, $setID, $partition),
			'time' => AchievementConditionTable::value($userID, AchievementConditionTable::CATEGORY_SET_TIME, $setID, $partition)];
	}

	//Opens each page (1 based) of the set at the player's collection size
	private function open(int $setID, int ...$pages): void
	{
		foreach ($pages as $page)
		{
			$this->get('sets/' . $setID . '/' . $page);
			$this->assertResponseOk();
		}
	}

	//A collection of one problem per item, solved with its attempts
	/** @param list<list<array<string, mixed>>> $attempts The attempts of each problem, in order */
	private static function collectionOf(array $attempts, array $user = [], array $more = []): array
	{
		$tsumegos = [];
		foreach ($attempts as $i => $problemAttempts)
			$tsumegos[] = ($more[$i] ?? []) + ['sets' => [['name' => 'best set', 'num' => $i + 1]], 'status' => 'S', 'attempts' => $problemAttempts];
		return ['user' => ['name' => 'trainer', 'collection_size' => self::PART] + $user, 'tsumegos' => $tsumegos];
	}

	//Each part keeps its own best, from its own problems only: the fail in the second part doesn't reach the first
	public function testEachPartKeepsItsOwnBest(): void
	{
		ProgressDeletionTable::$PARTITION_SIZE = self::PART;
		$attempts = array_fill(0, 20, [['solved' => true, 'seconds' => 10]]);
		$attempts[15] = [['solved' => false], ['solved' => true, 'seconds' => 30]];
		$context = new ContextPreparator(self::collectionOf($attempts));
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$this->open($setID, 1, 2);

		$this->assertEquals(['accuracy' => 100, 'time' => 10], self::bestOf($setID, 0));
		$this->assertEquals(['accuracy' => 90.91, 'time' => 12], self::bestOf($setID, 1), '10 solves against 1 fail, (9 * 10 + 30) / 10');
		$this->assertEquals(90.91, $this->viewVariable('bestAccuracy'), 'the page shows the best of its part');
		$this->assertSame(2, (int) Util::query('SELECT COUNT(*) AS c FROM achievement_condition WHERE set_id = ? AND achievement_condition_category_id = ?',
			[$setID, AchievementConditionTable::CATEGORY_SET_ACCURACY])[0]['c'], 'one row per part');
	}

	//The misplays of an attempt that ended solved are its fails, and a problem solved without an attempt is one fail
	public function testMisplaysOfSolvedAttemptsCount(): void
	{
		ProgressDeletionTable::$PARTITION_SIZE = self::PART;
		$attempts = array_fill(0, 10, [['solved' => true, 'seconds' => 10]]);
		$attempts[3] = [['solved' => true, 'seconds' => 10, 'misplays' => 2]];
		$attempts[7] = [];
		$context = new ContextPreparator(self::collectionOf($attempts));
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$this->open($setID, 1);

		$this->assertEquals(75, self::bestOf($setID, 0)['accuracy'], '9 solves against 2 misplays and the solve without an attempt');
	}

	//A part with a problem not solved has no best yet, whatever its attempts
	public function testAnUnfinishedPartKeepsNoBest(): void
	{
		ProgressDeletionTable::$PARTITION_SIZE = self::PART;
		$context = new ContextPreparator(self::collectionOf(array_fill(0, 10, [['solved' => true, 'seconds' => 10]]), [], [9 => ['status' => 'F']]));
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$this->open($setID, 1);

		$this->assertSame(['accuracy' => null, 'time' => null], self::bestOf($setID, 0));
	}

	//A reset of a part starts a new run through it: its fails before the reset count no more. The reset of another part
	//doesn't, and an old reset without a part is a reset of every part.
	public function testAResetStartsANewRunOfItsPartOnly(): void
	{
		ProgressDeletionTable::$PARTITION_SIZE = self::PART;
		$before = date('Y-m-d H:i:s', strtotime('-3 days'));
		$after = date('Y-m-d H:i:s', strtotime('-1 hour'));
		$run = [['solved' => false, 'created' => $before], ['solved' => true, 'seconds' => 20, 'created' => $after]];
		$context = new ContextPreparator(self::collectionOf(array_fill(0, 30, $run)) + ['progress-deletions' => [
			['set' => 'best set', 'partition' => 0, 'created' => date('Y-m-d H:i:s', strtotime('-2 days'))],
			['set' => 'best set', 'partition' => 2, 'created' => date('Y-m-d H:i:s', strtotime('-5 days'))]]]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$this->open($setID, 1, 2, 3);

		$this->assertEquals(100, self::bestOf($setID, 0)['accuracy'], 'reset after the fails');
		$this->assertEquals(50, self::bestOf($setID, 1)['accuracy'], 'the reset of another part');
		$this->assertEquals(50, self::bestOf($setID, 2)['accuracy'], 'reset before the fails');

		Util::execute('DELETE FROM achievement_condition');
		Util::execute('INSERT INTO progress_deletion (user_id, set_id, set_partition, created) VALUES (?, ?, NULL, ?)',
			[Auth::getUserID(), $setID, date('Y-m-d H:i:s', strtotime('-2 days'))]);
		$this->open($setID, 2, 3);
		$this->assertEquals(100, self::bestOf($setID, 1)['accuracy'], 'a reset without a part');
		$this->assertEquals(100, self::bestOf($setID, 2)['accuracy'], 'a reset without a part');
	}

	//At a collection size other than the parts' the page isn't a part: it shows the live accuracy and time only, and
	//keeps no best
	public function testAPageThatIsNotAPartShowsNoBest(): void
	{
		$context = new ContextPreparator(self::collectionOf(array_fill(0, 10, [['solved' => true, 'seconds' => 10]]))
			+ ['achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_SET_ACCURACY, 'set' => 'best set', 'partition' => 0, 'value' => 80]]]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$this->open($setID, 1);

		$this->assertNull($this->viewVariable('bestAccuracy'));
		$this->assertNull($this->viewVariable('bestTime'));
		$this->assertEquals(100, $this->viewVariable('accuracy'));
		$this->assertStringNotContainsString('Best:', (string) $this->_response->getBody());
		$this->assertEquals(['accuracy' => 80, 'time' => null], self::bestOf($setID, 0), 'kept as it was');
	}

	//The page of a filtered part isn't the part either
	public function testAFilteredPageKeepsNoBest(): void
	{
		ProgressDeletionTable::$PARTITION_SIZE = self::PART;
		$context = new ContextPreparator(self::collectionOf(array_fill(0, 10, [['solved' => true, 'seconds' => 10]]), [], [2 => ['rating' => 2400]]));
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$_COOKIE['filtered_ranks'] = '11k';
		$this->open($setID, 1);

		$this->assertNull($this->viewVariable('bestAccuracy'));
		$this->assertSame(['accuracy' => null, 'time' => null], self::bestOf($setID, 0));
	}

	//The resets that reduce the XP are those of the part the page is in, not of the part numbered like the page
	public function testTheResetsCountedAreOfThePartOfThePage(): void
	{
		$tsumegos = [];
		for ($i = 0; $i < 20; $i++)
			$tsumegos[] = ['sets' => [['name' => 'reset set', 'num' => $i + 1]], 'status' => 'S'];
		$context = new ContextPreparator(['user' => ['collection_size' => 10], 'tsumegos' => $tsumegos,
			'progress-deletions' => [['set' => 'reset set', 'partition' => 0, 'created' => date('Y-m-d H:i:s', strtotime('-1 day'))]]]);

		// the second page of ten is in the first part of 200
		$this->open($context->tsumegos[0]['sets'][0]['id'], 2);

		$this->assertSame(1, $this->viewVariable('pdCounter'));
		$this->assertTextContains('1 reset in the last month', (string) $this->_response->getBody());
	}

	public function testGuestSeesNoScoresOnASet(): void
	{
		$context = new ContextPreparator([
			'user' => null,
			'tsumegos' => [['sets' => [['name' => 'Test Set', 'num' => 1]]]]]);

		$this->get('sets/' . $context->tsumegos[0]['sets'][0]['id']);

		$this->assertFalse($this->viewVariable('scoring'));
		$this->assertSame(0, $this->viewVariable('pdCounter'));
		$this->assertNull($this->viewVariable('bestTime'));
	}

	public function testSetProgressDeletion()
	{
		foreach (['reset', 'hello'] as $resetInput)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator([
				'user' => ['name' => 'testuser'],
				'other-users' => [['name' => 'otheruser']],
				'tsumegos' => [
					[
						'sets' => [['name' => 'Test Set', 'num' => 1]],
						'statuses'
						=> [
							['name' => 'S', 'user' => 'testuser'], // tsumego from this test, progress should be deleted
							['name' => 'S', 'user' => 'otheruser'] // tsumego from this test of other user, should be preserved
						]
					],
					[ // second tsumego from this test, progress should be deleted
						'sets' => [['name' => 'Test Set', 'num' => 2]],
						'status' => 'S'
					],
					[ // tsumego from a different set, progress shouldn't be delted
						'sets' => [['name' => 'Test Set 2', 'num' => 2]],
						'status' => 'S'
					]]]);
			$browser->get('sets/' . $context->tsumegos[0]['sets'][0]['id']);
			$browser->clickId("showx");
			$browser->clickId("reset-textfield");
			$browser->driver->getKeyboard()->sendKeys($resetInput);

			$TsumegoStatus = $this->fetchTable('TsumegoStatus');

			$statusCurrentUserThisSet1 = ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']];
			$statusCurrentUserThisSet2 = ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[1]['id']];
			$statusCurrentUserOtherSet = ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[2]['id']];
			$statusOtherUserThisSet = ['user_id' => $context->otherUsers[0]['id'], 'tsumego_id' => $context->tsumegos[0]['id']];

			$this->assertNotEmpty($TsumegoStatus->find()->where($statusCurrentUserThisSet1)->toArray());
			$this->assertNotEmpty($TsumegoStatus->find()->where($statusCurrentUserThisSet2)->toArray());
			$this->assertNotEmpty($TsumegoStatus->find()->where($statusCurrentUserOtherSet)->toArray());
			$this->assertNotEmpty($TsumegoStatus->find()->where($statusOtherUserThisSet)->toArray());

			// the reset is done once the page it leads back to is there
			$submit = $browser->find('#reset-submit');
			$submit->click();
			$browser->waitUntilStale($submit);
			if ($resetInput == "reset")
			{
				$this->assertEmpty($TsumegoStatus->find()->where($statusCurrentUserThisSet1)->toArray());
				$this->assertEmpty($TsumegoStatus->find()->where($statusCurrentUserThisSet2)->toArray());
				$this->assertSame(1, $this->fetchTable('ProgressDeletion')->find()->count());
			}
			else
			{
				$this->assertNotEmpty($TsumegoStatus->find()->where($statusCurrentUserThisSet1)->toArray());
				$this->assertNotEmpty($TsumegoStatus->find()->where($statusCurrentUserThisSet2)->toArray());
				$this->assertSame(0, $this->fetchTable('ProgressDeletion')->find()->count());
				$this->assertTextContains('Reset check wasn\'t correctly typed', $browser->driver->getPageSource());
			}

			// other set tsumego progress not touched
			$this->assertNotEmpty($TsumegoStatus->find()->where($statusCurrentUserOtherSet)->toArray());
			// this set but other user tsumego status untouched
			$this->assertNotEmpty($TsumegoStatus->find()->where($statusOtherUserThisSet)->toArray());
		}
	}

	//Test reset button is NOT shown when partition has < 50% solved.
	public function testSetProgressDeletionNotOfferedWhenBelow50Percent()
	{
		$browser = Browser::instance();

		// Create 200 problems with 40% solved (below threshold)
		$contextParameters = ['tsumegos' => []];
		for ($i = 0; $i < 200; $i++)
		{
			$contextParameters['tsumegos'][] = [
				'sets' => [['name' => 'Test Set', 'num' => ($i + 1)]],
				'status' => $i < 80 ? 'S' : null  // 80/200 = 40% solved
			];
		}
		$context = new ContextPreparator($contextParameters);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		// With 40% solved, reset button should NOT be shown
		$browser->get('sets/' . $setId . '/1');
		$this->assertFalse($browser->idExists('showx'), "Reset button should not exist when below 50%");
		$this->assertTextContains('You need to complete 50% to reset', $browser->driver->getPageSource());
	}

	//Test reset button IS shown when partition has >= 50% solved.
	public function testSetProgressDeletionOfferedWhenAbove50Percent()
	{
		$browser = Browser::instance();

		// Create 200 problems with 51% solved (slightly above threshold)
		$contextParameters = ['tsumegos' => []];
		for ($i = 0; $i < 200; $i++)
		{
			$contextParameters['tsumegos'][] = [
				'sets' => [['name' => 'Test Set 50', 'num' => ($i + 1)]],
				'status' => $i < 102 ? 'S' : null  // 102/200 = 51% solved
			];
		}
		$context = new ContextPreparator($contextParameters);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		// Verify statuses were created correctly
		$statusCount = $this->fetchTable('TsumegoStatus')->find()->where(['user_id' => $context->user['id']])->count();
		$this->assertEquals(102, $statusCount, "Should have 102 solved statuses");

		// With 51% solved, reset button SHOULD be shown
		$browser->get('sets/' . $setId . '/1');
		$pageSource = $browser->driver->getPageSource();
		$this->assertStringNotContainsString('You need to complete 50% to reset', $pageSource, "Page says <50% but should be 51%");
		$this->assertTrue($browser->idExists('showx'), "Reset button should be shown at 51%");
	}

	public function testSetProgressDeletionOfPartitionedSet()
	{
		foreach ([1, 2] as $partition)
		{
			$browser = Browser::instance();
			$contextParameters = [];
			for ($i = 0; $i < 400; $i++)
				$contextParameters['tsumegos'][] = ['sets' => [['name' => 'Big set', 'num' => ($i + 1)]], 'status' => 'S'];
			$context = new ContextPreparator($contextParameters);

			// we open partition of the set
			$browser->get('sets/' . $context->tsumegos[0]['sets'][0]['id'] . '/' . $partition);
			$browser->clickId("showx");
			$browser->clickId("reset-textfield");
			$browser->driver->getKeyboard()->sendKeys('reset');
			// the reset is done once the page it leads back to is there
			$submit = $browser->find('#reset-submit');
			$submit->click();
			$browser->waitUntilStale($submit);
			$statuses = $this->fetchTable('TsumegoStatus')->find()->orderBy('id')->toArray();

			// 200 statuses left
			$this->assertSame(200, count($statuses));
			for ($i = 0; $i < 200; $i++)
				$this->assertSame($context->tsumegos[$i + ($partition == 1 ? 200 : 0)]['id'], $statuses[$i]->tsumego_id);
		}
	}

	public function testSetProgressDeletionNotOfferedWithNonStandardPartitionSize()
	{
		$browser = Browser::instance();
		$contextParameters = [];
		for ($i = 0; $i < 400; $i++)
			$contextParameters['tsumegos'][] = ['set_order' => ($i + 1), 'status' => 'S'];
		$contextParameters['user'] = ['collection_size' => 150];
		$context = new ContextPreparator($contextParameters);

		$browser->get('sets/' . $context->tsumegos[0]['sets'][0]['id']);
		$this->assertEmpty($browser->getElements("showx")); // no reset offered
		$this->assertTextContains('Reset is only possible when collection size is set to 200', $browser->driver->getPageSource());

		// we try to force it on the server anyway - redirect back to the same set
		$browser->post('/sets/resetProgress/' . $context->sets[0]['id'] . '/1', ['reset-check' => 'reset']);
		$this->assertSame(Util::getMyAddress() . '/sets/' . $context->sets[0]['id'], $browser->driver->getCurrentURL());
		$this->assertSame(400, count($this->fetchTable('TsumegoStatus')->find()->orderBy('id')->toArray()));
	}

	public function testChangeCollectionSize()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['collection_size' => 200]]);

		// we open partition of the set
		$browser->get('sets');
		$browser->clickId('set-size-input');
		$browser->driver->getKeyboard()->sendKeys([WebDriverKeys::CONTROL, 'a']);
		$browser->driver->getKeyboard()->sendKeys('60');
		$browser->clickId("submit-size-button");
		// the page comes back from the redirect with the stored size, the row this process
		// loaded at the login still has the old one
		$browser->waitUntilJs('document.getElementById("set-size-input")?.getAttribute("value") === "60"');
		$this->assertSame(60, $context->reloadUser()['collection_size']);
	}

	//Verify that Favorites menu item appears for all logged-in users, even those without any favorites yet.
	public function testFavoritesMenuVisibleForAllLoggedInUsers(): void
	{
		new ContextPreparator(['user' => ['name' => 'regularuser']]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('href="/sets/favorites"', $pageSource, 'Favorites link should be in the page HTML for logged-in users');
	}

	public function testIndexWithNonexistentFilteredSetAndRankFiltersDoesNotCrash(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'difficulty',
			'filtered_sets' => ['valid-set', 'nonexistent-set-that-was-deleted'],
			'filtered_ranks' => ['15k', '14k']]];
		$contextParams['tsumegos'] = [];
		$contextParams['tsumegos'][] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'valid-set', 'num' => '1']]];

		new ContextPreparator($contextParams);
		$this->get('sets');
		$this->assertTextContains('15k', $this->view);
		$this->assertTextNotContains('nonexistent-set-that-was-deleted', $this->view);
	}

	public function testIndexWithInvalidRankDoesNotCrash(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'difficulty',
			'filtered_ranks' => ['deleted', '15k']]];
		$contextParams['tsumegos'] = [];
		$contextParams['tsumegos'][] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'TestSet', 'num' => '1']]];

		new ContextPreparator($contextParams);
		$this->get('sets');
		$this->assertTextContains('15k', $this->view);
		$this->assertTextNotContains('deleted', $this->view);
	}

	//Verifies a sandbox tsumego's play page loads correctly for premium users.
	//Smoke test to ensure the $isSandbox code path in Play.php doesn't crash.
	#[Group('browser')]
	#[RetryOnBrowserFailure]
	public function testSandboxPlayPageLoads(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'premiumuser', 'mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'tsumego' => [
				'sets' => [['name' => 'Sandbox Play Test', 'public' => 0, 'num' => '1']],
				'sgf' => '(;GM[1]FF[4]SZ[19]AB[pd][dp]AW[pp][dd])',
			],
		]);

		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];
		$browser = Browser::instance();
		$browser->get('/' . $setConnectionId);

		// Page loads without error
		$this->assertTrue($browser->titleContains('Tsumego Hero'), 'Sandbox play page should load');

		// Play title exists (confirms the play page rendered fully)
		$this->assertTrue($browser->idExists('playTitle'), 'Play title should be present');
	}

	//Verifies non-premium users are blocked from sandbox play pages.
	//Creates sandbox set as premium owner, then switches to a non-premium user.
	#[Group('browser')]
	#[RetryOnBrowserFailure]
	public function testSandboxPlayPageBlocksNonPremium(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'owner', 'mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-users' => [['name' => 'regularuser']],
			'tsumego' => [
				'sets' => [['name' => 'Premium Only Set', 'public' => 0, 'num' => '1']],
				'sgf' => '(;GM[1]FF[4]SZ[19]AB[pd][dp]AW[pp][dd])',
			],
		]);

		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];

		// Navigate as non-premium user (getAnonymous avoids Browser's auto-auth cookie)
		$browser = Browser::instance();
		$browser->getAnonymous('empty.php');
		$browser->setCookie('hackedLoggedInUserID', (string) $context->otherUsers[0]['id']);
		$browser->getAnonymous('/' . $setConnectionId);

		// Server-side guard rejects the request with a 403 error page
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Forbidden', $pageSource,
			'Non-premium user should see the forbidden error page');
		$this->assertFalse($browser->idExists('playTitle'),
			'Play title should not render for non-premium user');
	}

	//The set-title heading carries the .set-view-title class (see templates/Sets/view.php). Return its text.
	private function getSetTitle($browser): string
	{
		$titles = $browser->getElements('.set-view-title');
		return $titles ? $titles[0]->getText() : '';
	}
}

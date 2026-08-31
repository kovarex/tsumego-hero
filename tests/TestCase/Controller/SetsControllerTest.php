<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverKeys;

App::uses('TsumegoFilters', 'Utility');

class SetsControllerTest extends TestCaseWithAuth
{
	public function testIndexLoggedIn(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'tsumego set 1', 'num' => '666'],
			['name' => 'tsumego set 2', 'num' => '777']]]], );
		$this->login($context->user['User']['name']);
		$this->testAction('sets', ['return' => 'view']);
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
		$this->assertTextNotContains("Problems found 0", $this->view);
	}

	public function testIndexLoggedOff(): void
	{
		new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'tsumego set 1', 'num' => '666'],
			['name' => 'tsumego set 2', 'num' => '777']]]], );
		$this->testAction('sets', ['return' => 'view']);
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
		$this->assertTextNotContains("Problems found 0", $this->view);
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
		$this->testAction('sets', ['return' => 'view']);
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__top');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->textContent, '15k');

		$collectionMiddleLeft = $dom->querySelectorAll('.set-card__middle-left');
		$this->assertCount(1, $collectionMiddleLeft);
		$this->assertSame($collectionMiddleLeft[0]->textContent, '1 problem'); // the sandbox problem isn't included

		$_COOKIE['filtered_ranks'] = '10k';
		$this->testAction('sets', ['return' => 'view']);
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__top');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->textContent, '10k');
	}

	public function testTopicsIndexSplitsCollectionIntoPartitionBoxes(): void
	{
		$contextParams = ['user' => ['collection_size' => 10]];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 11; $i++)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'partitioned set', 'num' => $i + 1]]];
		$context = new ContextPreparator($contextParams);
		$this->testAction('sets', ['return' => 'view']);

		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__top');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame('partitioned set #1', $collectionTopDivs[0]->textContent);
		$this->assertSame('partitioned set #2', $collectionTopDivs[1]->textContent);

		$collectionMiddleLeft = $dom->querySelectorAll('.set-card__middle-left');
		$this->assertSame('10 problems', $collectionMiddleLeft[0]->textContent);
		$this->assertSame('1 problem', $collectionMiddleLeft[1]->textContent);

		$boxLinks = $dom->querySelectorAll('.set-card__link');
		$setId = $context->tsumegos[0]['sets'][0]['id'];
		$this->assertSame('/sets/view/' . $setId . '/1', $boxLinks[0]->getAttribute('href'));
		$this->assertSame('/sets/view/' . $setId . '/2', $boxLinks[1]->getAttribute('href'));
	}

	public function testTopicsIndexSmallCollectionShowsSingleBox(): void
	{
		$contextParams = ['user' => ['collection_size' => 200]];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 3; $i++)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'small set', 'num' => $i + 1]]];
		$context = new ContextPreparator($contextParams);
		$this->testAction('sets', ['return' => 'view']);

		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__top');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame('small set', $collectionTopDivs[0]->textContent);

		$collectionMiddleLeft = $dom->querySelectorAll('.set-card__middle-left');
		$this->assertSame('3 problems', $collectionMiddleLeft[0]->textContent);

		$boxLinks = $dom->querySelectorAll('.set-card__link');
		$this->assertSame('/sets/view/' . $context->tsumegos[0]['sets'][0]['id'], $boxLinks[0]->getAttribute('href'));
	}

	public function testTopicsIndexSortsNullOrderSetLast(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'ordered set', 'public' => 1, 'order' => 5, 'num' => 1]]]]);

		// a public set with no curated order holding the same tsumego
		$set = ClassRegistry::init('Set');
		$set->create();
		$set->save(['title' => 'unordered set', 'public' => 1, 'order' => null]);
		$connection = ClassRegistry::init('SetConnection');
		$connection->create();
		$connection->save(['tsumego_id' => $context->tsumegos[0]['id'], 'set_id' => $set->id, 'num' => 2]);

		$this->testAction('sets', ['return' => 'view']);

		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__top');
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
		$this->testAction('sets', ['return' => 'view']);
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
		$this->testAction('sets', ['return' => 'view']);
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
		$this->testAction('sets', ['return' => 'view']);
		$this->assertTextContains('Problems found: 2', $this->view);
	}

	public function testTopicsIndexExcludesDeletedProblems(): void
	{
		$contextParams = ['tsumegos' => []];
		$contextParams['tsumegos'][] = ['sets' => [['name' => 'set', 'num' => '1']]];
		// a deleted problem in the same public set is neither counted nor shown
		$contextParams['tsumegos'][] = ['deleted' => date('Y-m-d H:i:s'), 'sets' => [['name' => 'set', 'num' => '2']]];
		new ContextPreparator($contextParams);
		$this->testAction('sets', ['return' => 'view']);

		$this->assertTextContains('Problems found: 1', $this->view);
		$dom = $this->getStringDom();
		$this->assertCount(1, $dom->querySelectorAll('.set-card__top'));
		$this->assertSame('1 problem', $dom->querySelectorAll('.set-card__middle-left')[0]->textContent);
	}

	public function testTagsIndexExcludesDeletedProblems(): void
	{
		$contextParams = ['tsumegos' => []];
		$contextParams['tsumegos'][] = ['sets' => [['name' => 'set', 'num' => '1']], 'tags' => [['name' => 'atari']]];
		// a deleted problem with the same tag is neither counted nor shown
		$contextParams['tsumegos'][] = ['deleted' => date('Y-m-d H:i:s'), 'sets' => [['name' => 'set', 'num' => '2']], 'tags' => [['name' => 'atari']]];
		new ContextPreparator($contextParams);
		$_COOKIE['query'] = 'tags';
		$this->testAction('sets', ['return' => 'view']);

		$this->assertTextContains('Problems found: 1', $this->view);
		$dom = $this->getStringDom();
		$this->assertCount(1, $dom->querySelectorAll('.set-card__top'));
		$this->assertSame('1 problem', $dom->querySelectorAll('.set-card__middle-left')[0]->textContent);
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
		$this->testAction('sets', ['return' => 'view']);

		$this->assertTextContains('Problems found: 12', $this->view);

		$dom = $this->getStringDom();
		$fills = $dom->querySelectorAll('.progress__fill');
		$this->assertCount(2, $fills);
		$this->assertTextContains('width: 100%', $fills[0]->getAttribute('style'));
		$this->assertTextContains('width: 50%', $fills[1]->getAttribute('style'));
		$this->assertCount(1, $dom->querySelectorAll('.set-card__completed'));
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
		$this->testAction('sets', ['return' => 'view']);

		// difficulty must be computed per partition, not per whole set:
		// partition 1 (ten 15k problems) shows 15k, partition 2 (two 5k) shows 5k.
		$dom = $this->getStringDom();
		$difficulties = $dom->querySelectorAll('.set-card__middle-right');
		$this->assertCount(2, $difficulties);
		$this->assertSame('~15k', $difficulties[0]->textContent);
		$this->assertSame('~5k', $difficulties[1]->textContent);
	}

	public function testTopicsIndexGuestShowsNoSolvedProgress(): void
	{
		$contextParams = ['user' => null];
		$contextParams['tsumegos'] = [];
		for ($i = 0; $i < 2; $i++)
			$contextParams['tsumegos'] [] = [
				'sets' => [['name' => 'guest set', 'num' => $i + 1]]];
		new ContextPreparator($contextParams);
		$this->testAction('sets', ['return' => 'view']);

		$dom = $this->getStringDom();
		$fills = $dom->querySelectorAll('.progress__fill');
		$this->assertCount(1, $fills);
		$this->assertTextContains('width: 0%', $fills[0]->getAttribute('style'));
		$this->assertCount(1, $dom->querySelectorAll('.set-card__top'));
		$this->assertCount(0, $dom->querySelectorAll('.set-card__completed'));
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
		$this->testAction('sets', ['return' => 'view']);

		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__top');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame('15k #1', $collectionTopDivs[0]->textContent);
		$this->assertSame('15k #2', $collectionTopDivs[1]->textContent);

		$collectionMiddleLeft = $dom->querySelectorAll('.set-card__middle-left');
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
		$this->testAction('sets', ['return' => 'view']);

		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.set-card__top');
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame('atari #1', $collectionTopDivs[0]->textContent);
		$this->assertSame('atari #2', $collectionTopDivs[1]->textContent);

		$collectionMiddleLeft = $dom->querySelectorAll('.set-card__middle-left');
		$this->assertSame('10 problems', $collectionMiddleLeft[0]->textContent);
		$this->assertSame('1 problem', $collectionMiddleLeft[1]->textContent);
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

		$this->testAction('sets/view/15k', ['return' => 'view']);
		$dom = $this->getStringDom();
		$titleDivs = $dom->querySelectorAll('.title4');
		$this->assertCount(2, $titleDivs);
		// The set title is one of the .title4 headings; don't assume its DOM index,
		// since the two columns (set info / problems) may render in either order.
		$setTitleFound = false;
		foreach ($titleDivs as $titleDiv)
			if ($titleDiv->textContent === '15k')
			{
				$setTitleFound = true;
			}
		$this->assertTrue($setTitleFound, 'Set title 15k should be rendered');

		$problemButtons = $dom->querySelectorAll('.problem-nav__number');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemLinks);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->testAction('sets/view/10k', ['return' => 'view']);
		$dom = $this->getStringDom();
		$titleDivs = $dom->querySelectorAll('.title4');
		$this->assertCount(2, $titleDivs);
		// The set title is one of the .title4 headings; don't assume its DOM index.
		$setTitleFound = false;
		foreach ($titleDivs as $titleDiv)
			if ($titleDiv->textContent === '10k')
			{
				$setTitleFound = true;
			}
		$this->assertTrue($setTitleFound, 'Set title 10k should be rendered');

		$problemButtons = $dom->querySelectorAll('.problem-nav__number');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemButtons);
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

		$this->testAction('sets/view/5k', ['return' => 'view']);
		$result = $this->getStringDom();

		$this->assertStringContainsString('<span class="rank-icon rank-icon-large">5k</span>',
			$result->saveHTML());
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

		$this->testAction('sets/view/' . $context->tsumegos[0]['sets'][0]['id'], ['return' => 'view']);
		$dom = $this->getStringDom();
		$titleDivs = $dom->querySelectorAll('.title4');
		$this->assertCount(2, $titleDivs);
		// The set title is one of the .title4 headings; don't assume its DOM index.
		$setTitleFound = false;
		foreach ($titleDivs as $titleDiv)
			if ($titleDiv->textContent === 'set 1')
			{
				$setTitleFound = true;
			}
		$this->assertTrue($setTitleFound, 'Set title set 1 should be rendered');

		$problemButtons = $dom->querySelectorAll('.problem-nav__number');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemLinks);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->testAction('sets/view/' . $context->tsumegos[1]['sets'][0]['id'], ['return' => 'view']);
		$dom = $this->getStringDom();
		$titleDivs = $dom->querySelectorAll('.title4');
		$this->assertCount(2, $titleDivs);
		// The set title is one of the .title4 headings; don't assume its DOM index.
		$setTitleFound = false;
		foreach ($titleDivs as $titleDiv)
			if ($titleDiv->textContent === 'set 2')
			{
				$setTitleFound = true;
			}
		$this->assertTrue($setTitleFound, 'Set title set 2 should be rendered');

		$problemButtons = $dom->querySelectorAll('.problem-nav__number');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->tsumegos[1]['set-connections'][0]['id']);
	}

	public function testSetViewSanitizesDescriptionOnDisplay(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'xss set', 'num' => '1',
				'description' => '<b onmouseover="alert(1)">hi</b><a href="javascript:alert(2)">click</a>']]]]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$this->testAction('sets/view/' . $setId, ['return' => 'view']);

		$this->assertStringNotContainsString('onmouseover', $this->view);
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
		$browser->getAnonymous('sets/view/' . $setId);
		$browser->driver->manage()->deleteAllCookies();
		$browser->driver->manage()->addCookie(['name' => 'filtered_ranks', 'value' => '15k']);
		$browser->getAnonymous('sets/view/' . $setId);

		// open the filters panel so the active tiles (and clear button) are visible
		$browser->driver->findElement(WebDriverBy::cssSelector('#showFilters'))->click();
		$browser->waitUntilCssSelectorDisplayed('#unselect-active-tiles');

		$browser->driver->findElement(WebDriverBy::cssSelector('#unselect-active-tiles'))->click();

		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $setId, $browser->driver->getCurrentURL());
		$this->assertFalse($browser->idExists('unselect-active-tiles'));
	}

	private function checkSetNavigationButtons($browser, int $count, $context, $indexFunction, $orderFunction): array
	{
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('div.set-view-main li'));
		$this->assertCount($count, $buttons);
		foreach ($buttons as $key => $button)
			$this->checkNavigationButton($button, $context, $indexFunction($key), $orderFunction($key));
		return $buttons;
	}

	private function checkPlayTitle($browser, string $title)
	{
		$browser->waitUntilCssSelectorExists('#playTitle');
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextStartsWith($title, $collectionTopDivs[0]->getText());
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
		$browser->driver->findElement(WebDriverBy::id('difficulty-button'))->click();
		$difficulty15kSelector = $browser->driver->findElement(WebDriverBy::id('tile-difficulty0'));
		$this->assertSame($difficulty15kSelector->getText(), '15k');
		$difficulty15kSelector->click();
		$browser->driver->findElement(WebDriverBy::id('tile-difficulty-submit'))->click();

		// difficulty selected
		$this->assertSame($browser->driver->manage()->getCookieNamed('query')->getValue(), 'difficulty');
		$this->assertSame($browser->driver->manage()->getCookieNamed('filtered_ranks')->getValue(), '15k');

		// we check the set card and clicking
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/15k', $browser->driver->getCurrentURL());

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
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(2, $collectionTopDivs); // 2 partitions of 10 and 1 problems
		$this->assertSame($collectionTopDivs[0]->getText(), 'test set #1');
		$this->assertSame($collectionTopDivs[1]->getText(), 'test set #2');
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->tsumegos[0]['sets'][0]['id'] . '/1', $browser->driver->getCurrentURL());

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
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'test set #1 2/11');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 10, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		}, 1, 'V');

		// now we go back to the sets selection and we visit the second partition of the set
		$browser->get('sets');
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(2, $collectionTopDivs); // 2 partitions of 10 and 1 problems
		$collectionTopDivs[1]->click();

		// now we are in the second partition of the set
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->tsumegos[0]['sets'][0]['id'] . '/2', $browser->driver->getCurrentURL());

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

		$browser->byId('SetTitle')->clear();
		$browser->byId('SetTitle')->sendKeys('Renamed Set');
		$browser->byCssSelector('#set-edit-details input[type="submit"]')->click();

		$browser->waitUntilCssSelectorExistsWithText('.set-edit-title', 'Renamed Set');

		$this->assertSame('Renamed Set', ClassRegistry::init('Set')->findById($setId)['Set']['title']);
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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$this->assertSame($collectionTopDivs[1]->getText(), '1d');

		// first we visit the 15k one
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/15k', $browser->driver->getCurrentURL());
		$this->assertSame($this->setTitleFrom($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))), '15k');

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
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 2/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 3, $context, function ($index) {
			return $index;
		}, function ($index) {
			return $index + 1;
		}, 1, 'V');

		// now we go to visit the 1d section, so back to sets
		$browser->get("sets");
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$this->assertSame($collectionTopDivs[1]->getText(), '1d');

		// first we visit the 1d one
		$collectionTopDivs[1]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/1d', $browser->driver->getCurrentURL());
		$this->assertSame($this->setTitleFrom($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))), '1d');

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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(3, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'set 1');
		$this->assertSame($collectionTopDivs[1]->getText(), 'set 2');
		$this->assertSame($collectionTopDivs[2]->getText(), 'set 3');

		// with 2 problems each as the 5d problems should be already filtered out
		$collectionMiddleLeftDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__middle-left'));
		$this->assertCount(3, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getText(), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[1]->getText(), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[2]->getText(), '2 problems');

		// first visit the 'set 1'
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->tsumegos[0]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$this->assertSame($this->setTitleFrom($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))), 'set 1');

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
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[6]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set 1 3/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return ($index + 1) * 3;
		}, function ($index) {
			return $index + 2;
		}, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->tsumegos[0]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$browser->driver->getPageSource();
		$this->assertCount(2, $browser->getCssSelect('.title4'));
		$this->assertSame($this->setTitleFrom($browser->getCssSelect('.title4')), 'set 1');
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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(3, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$this->assertSame($collectionTopDivs[1]->getText(), '1d');
		$this->assertSame($collectionTopDivs[2]->getText(), '5d');

		// with 2 problems each as the set 1 problems should already be filtered out
		$collectionMiddleLeftDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__middle-left'));
		$this->assertCount(3, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getText(), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[1]->getText(), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[2]->getText(), '2 problems');

		// first visit the 'set 15k'
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/15k', $browser->driver->getCurrentURL());
		$this->assertSame($this->setTitleFrom($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))), '15k');

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
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[5]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 2/2');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return $index + 4;
		}, function ($index) {
			return $index + 1;
		}, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/15k', $browser->driver->getCurrentURL());
		$this->assertSame($this->setTitleFrom($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))), '15k');
	}

	/**
	 * Collections sorted by difficulty -> click problem directly (no lastSet cookie) -> shows current set only.
	 */
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
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('div.problem-nav__inner li'));
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

	public function testClickingOnSetViewSwitchesTsumegoFiltersToSetViewAndShowsCorrectTsumego(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'difficulty'],
			'tsumegos' => [[
				'sets' => [['name' => 'set 1', 'num' => 2], ['name' => 'set 3', 'num' => 4]],
				'rating' => Rating::getRankMiddleRatingFromReadableRank('10k')]]]);

		$browser->get('sets');

		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '10k');

		// the problem is in 2 sets, but it shouldn't matter in this view and should show just 1 problem
		$collectionMiddleLeftDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__middle-left'));
		$this->assertCount(1, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getText(), '1 problem');

		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/10k', $browser->driver->getCurrentURL());

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
		$this->assertEmpty($browser->getCssSelect('#currentNavigationButton')); // no tsumego button is marked as current
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
		$browser->driver->findElement(WebDriverBy::id('tags-button'))->click();
		$tagSelectors = $browser->driver->findElements(WebDriverBy::cssSelector('[id^="tile-tags"]:not([id*="select-all"]):not([id*="submit"])'));
		$this->assertCount(3, $tagSelectors);
		$this->assertSame($tagSelectors[0]->getText(), 'snapback');
		$this->assertSame($tagSelectors[1]->getText(), 'atari');
		$this->assertSame($tagSelectors[2]->getText(), 'empty triangle');
		$tagSelectors[0]->click();
		$browser->driver->findElement(WebDriverBy::id('tile-tags-submit'))->click();

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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'atari');
		$this->assertSame($collectionTopDivs[1]->getText(), 'empty triangle');
		$this->assertSame('Problems found: 3', $browser->find('#problems-found')->getText());

		// going into the 'atari' set
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/atari', $browser->driver->getCurrentURL());
		$this->assertSame($this->setTitleFrom($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))), 'atari');

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
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[4]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'atari 2/2');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return $index + 3;
		}, function ($index) {
			return $index + 1;
		}, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/atari', $browser->driver->getCurrentURL());
		$this->assertSame($this->setTitleFrom($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))), 'atari');
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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'set 1');
		$this->assertSame($collectionTopDivs[1]->getText(), 'set 2');

		$collectionMiddleLeftDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__middle-left'));
		$this->assertCount(2, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getText(), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[1]->getText(), '1 problem');
		$this->assertSame('Problems found: 3', $browser->find('#problems-found')->getText());

		// going into the 'set 1'
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->tsumegos[3]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$this->assertSame($this->setTitleFrom($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))), 'set 1');

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
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[5]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set 1 3/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) {
			return $index * 2 + 3;
		}, function ($index) {
			return $index + 2;
		}, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->tsumegos[3]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$this->assertSame($this->setTitleFrom($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))), 'set 1');
	}

	private function checkSetFinishedPercent($browser, $index, $title, $percent): void
	{
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'))[$index]->getText(), $title);
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.progress__label'))[$index]->getText(), $percent);
		$barStyle = $browser->driver->findElements(WebDriverBy::cssSelector('.progress__fill'))[$index]->getAttribute('style');
		$this->assertTextContains('width: ' . $percent, $barStyle);
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

		$wait = new WebDriverWait($browser->driver, 10, 500); // (driver, timeout, polling interval)
		$wait->until(function () use ($browser) {
			return $browser->driver->findElements(WebDriverBy::cssSelector('.progress__label'))[4]->getText() == '100%';
		});

		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
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

		$wait = new WebDriverWait($browser->driver, 10, 50); // (driver, timeout, polling interval)
		$wait->until(function () use ($browser) {
			return $browser->driver->findElements(WebDriverBy::cssSelector('.progress__label'))[0]->getText() == '33%';
		});

		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(2, $collectionTopDivs);
		$this->checkSetFinishedPercent($browser, 0, 'partitioned set #1', '33%');
		$this->checkSetFinishedPercent($browser, 1, 'partitioned set #2', '66%');
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

		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 500); // (driver, timeout, polling interval)
		$wait->until(function () use ($browser) {
			return $browser->driver->findElements(WebDriverBy::cssSelector('.progress__label'))[4]->getText() == '100%';
		});

		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
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

		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 500); // (driver, timeout, polling interval)
		$wait->until(function () use ($browser) {
			return $browser->driver->findElements(WebDriverBy::cssSelector('.progress__label'))[4]->getText() == '100%';
		});

		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.set-card__top'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'private set');
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->tsumegos[1]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());

		$problemButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__number'));
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

		$problemButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__number'));
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->getText(), '1');
		$browser->clickCssSelect('#TsumegoViewForm input[type="submit"]');

		// Wait for the page to reload with the new problem
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function () use ($browser) {
			return count($browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__number'))) == 2;
		});

		$problemButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__number'));
		$this->assertCount(2, $problemButtons);
		$this->assertSame($problemButtons[0]->getText(), '1');
		$this->assertSame($problemButtons[1]->getText(), '2');
	}

	/**
	 * Test set view Completed tab shows problem ORDER numbers (1, 2, 3...)
	 * This is the default view - shows which problems exist in the set
	 */
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

		$browser->get("sets/view/{$setId}");

		// Completed tab should be active by default - find problem buttons
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__number'));

		// Should show problem numbers: 1, 2, 3
		$this->assertCount(3, $buttons, 'Should have 3 problems in set');
		$this->assertSame('1', trim($buttons[0]->getText()));
		$this->assertSame('2', trim($buttons[1]->getText()));
		$this->assertSame('3', trim($buttons[2]->getText()));

		// setViewButtons2 (accuracy) and setViewButtons3 (time) should be hidden
		$accuracyButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__ratio'));
		foreach ($accuracyButtons as $btn)
			$this->assertFalse($btn->isDisplayed(), 'Accuracy buttons should be hidden on Completed tab');

		$timeButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__time'));
		foreach ($timeButtons as $btn)
			$this->assertFalse($btn->isDisplayed(), 'Time buttons should be hidden on Completed tab');
	}

	/**
	 * Test set view button CSS classes reflect problem status
	 * Buttons should have statusN (not attempted), statusS (solved), statusF (failed), etc.
	 */
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
		$browser->get("sets/view/{$setId}");

		// Find the <li> elements (button containers with status classes)
		$listItems = $browser->driver->findElements(WebDriverBy::cssSelector('li.problem-nav__item'));
		$this->assertCount(3, $listItems, 'Should have 3 problem buttons');

		// Problem 1: Not attempted - should have statusN class
		$this->assertStringContainsString('problem-nav__item--N', $listItems[0]->getAttribute('class'), 'Problem 1 should have statusN (not attempted)');

		// Problem 2: Solved - should have statusS class
		$this->assertStringContainsString('problem-nav__item--S', $listItems[1]->getAttribute('class'), 'Problem 2 should have statusS (solved)');

		// Problem 3: Failed - should have statusF class
		$this->assertStringContainsString('problem-nav__item--F', $listItems[2]->getAttribute('class'), 'Problem 3 should have statusF (failed)');
	}

	/**
	 * Test set view Accuracy tab shows success/failure ratio (e.g., "3/1" = 3 solved, 1 failed)
	 * As per UI description: "The solved and failed (s/f) attempts are displayed."
	 */
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
		$browser->get("sets/view/{$setId}");

		// Click Accuracy tab
		$accuracyTab = $browser->driver->findElement(WebDriverBy::xpath("//a[contains(text(), 'Accuracy')]"));
		$accuracyTab->click();
		// Wait for accuracy buttons to be visible
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			$buttons = $driver->findElements(WebDriverBy::cssSelector('.problem-nav__ratio'));
			return count($buttons) > 0 && $buttons[0]->isDisplayed();
		});

		// Check accuracy buttons are visible
		$accuracyButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__ratio'));
		$this->assertCount(2, $accuracyButtons);

		// Problem 1 should show "3/1" (3 solved, 1 failed)
		$this->assertTrue($accuracyButtons[0]->isDisplayed());
		$this->assertSame('3/1', trim($accuracyButtons[0]->getText()), 'Problem 1 accuracy should be 3/1');

		// Problem 2 should show "-" (no attempts)
		$this->assertTrue($accuracyButtons[1]->isDisplayed());
		$this->assertSame('-', trim($accuracyButtons[1]->getText()), 'Problem 2 accuracy should be - (no attempts)');

		// Order numbers and time should be hidden
		$orderButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__number'));
		foreach ($orderButtons as $btn)
			$this->assertFalse($btn->isDisplayed(), 'Order numbers should be hidden on Accuracy tab');
	}

	/**
	 * Misplays on solved attempts are summed across all solve sessions.
	 */
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
		$browser->get("sets/view/{$setId}");

		// Click Accuracy tab
		$accuracyTab = $browser->driver->findElement(WebDriverBy::xpath("//a[contains(text(), 'Accuracy')]"));
		$accuracyTab->click();
		// Wait for accuracy buttons to be visible
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			$buttons = $driver->findElements(WebDriverBy::cssSelector('.problem-nav__ratio'));
			return count($buttons) > 0 && $buttons[0]->isDisplayed();
		});

		$accuracyButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__ratio'));
		$this->assertSame('2/5', trim($accuracyButtons[0]->getText()), 'Two solved attempts (3 + 2 misplays) should show 2/5');
	}

	/**
	 * Test set view Time tab shows MINIMUM (best) solve time in seconds
	 * As per UI description: "The time (in seconds) for solving is displayed."
	 */
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
		$browser->get("sets/view/{$setId}");

		// Switch to Time tab
		$browser->driver->findElement(WebDriverBy::id('timeButton'))->click();
		// Wait for time buttons to be visible
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			$buttons = $driver->findElements(WebDriverBy::cssSelector('.problem-nav__time'));
			return count($buttons) > 0 && $buttons[0]->isDisplayed();
		});

		// Check time buttons are visible
		$timeButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.problem-nav__time'));
		$this->assertCount(2, $timeButtons);

		// Problem 1 should show "10s" (minimum/best of 10, 20, 30)
		$this->assertTrue($timeButtons[0]->isDisplayed());
		$this->assertSame('10s', trim($timeButtons[0]->getText()), 'Problem 1 time should be 10s (best time)');

		// Problem 2 should show "-" (no successful solves)
		$this->assertTrue($timeButtons[1]->isDisplayed());
		$this->assertSame('-', trim($timeButtons[1]->getText()), 'Problem 2 time should be - (no successful solves)');
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
			$browser->get('sets/view/' . $context->tsumegos[0]['sets'][0]['id']);
			$browser->clickId("showx");
			$browser->clickId("reset-textfield");
			$browser->driver->getKeyboard()->sendKeys($resetInput);

			$TsumegoStatus = ClassRegistry::init('TsumegoStatus');

			$statusCurrentUserThisSet1 = ['conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']]];
			$statusCurrentUserThisSet2 = ['conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[1]['id']]];
			$statusCurrentUserOtherSet = ['conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[2]['id']]];
			$statusOtherUserThisSet = ['conditions' => ['user_id' => $context->otherUsers[0]['id'], 'tsumego_id' => $context->tsumegos[0]['id']]];

			$this->assertNotEmpty($TsumegoStatus->find('all', $statusCurrentUserThisSet1));
			$this->assertNotEmpty($TsumegoStatus->find('all', $statusCurrentUserThisSet2));
			$this->assertNotEmpty($TsumegoStatus->find('all', $statusCurrentUserOtherSet));
			$this->assertNotEmpty($TsumegoStatus->find('all', $statusOtherUserThisSet));

			$browser->clickId("reset-submit");
			if ($resetInput == "reset")
			{
				$this->assertEmpty($TsumegoStatus->find('all', $statusCurrentUserThisSet1));
				$this->assertEmpty($TsumegoStatus->find('all', $statusCurrentUserThisSet2));
				$this->assertSame(1, ClassRegistry::init('ProgressDeletion')->find('count'));
			}
			else
			{
				$this->assertNotEmpty($TsumegoStatus->find('all', $statusCurrentUserThisSet1));
				$this->assertNotEmpty($TsumegoStatus->find('all', $statusCurrentUserThisSet2));
				$this->assertSame(0, ClassRegistry::init('ProgressDeletion')->find('count'));
				$this->assertTextContains('Reset check wasn\'t correctly typed', $browser->driver->getPageSource());
			}

			// other set tsumego progress not touched
			$this->assertNotEmpty($TsumegoStatus->find('all', $statusCurrentUserOtherSet));
			// this set but other user tsumego status untouched
			$this->assertNotEmpty($TsumegoStatus->find('all', $statusOtherUserThisSet));
		}
	}

	/**
	 * Test reset button is NOT shown when partition has < 50% solved.
	 */
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
		$browser->get('sets/view/' . $setId . '/1');
		$this->assertFalse($browser->idExists('showx'), "Reset button should not exist when below 50%");
		$this->assertTextContains('You need to complete 50% to reset', $browser->driver->getPageSource());
	}

	/**
	 * Test reset button IS shown when partition has >= 50% solved.
	 */
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
		$statusCount = ClassRegistry::init('TsumegoStatus')->find('count', ['conditions' => ['user_id' => $context->user['id']]]);
		$this->assertEquals(102, $statusCount, "Should have 102 solved statuses");

		// With 51% solved, reset button SHOULD be shown
		$browser->get('sets/view/' . $setId . '/1');
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
			$browser->get('sets/view/' . $context->tsumegos[0]['sets'][0]['id'] . '/' . $partition);
			$browser->clickId("showx");
			$browser->clickId("reset-textfield");
			$browser->driver->getKeyboard()->sendKeys('reset');
			$browser->clickId("reset-submit");
			$statuses = ClassRegistry::init('TsumegoStatus')->find('all', ['order' => 'id']);

			// 200 statuses left
			$this->assertSame(200, count($statuses));
			for ($i = 0; $i < 200; $i++)
				$this->assertSame($context->tsumegos[$i + ($partition == 1 ? 200 : 0)]['id'], $statuses[$i]['TsumegoStatus']['tsumego_id']);
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

		// we open partition of the set
		$browser->get('sets/view/' . $context->tsumegos[0]['sets'][0]['id'] . '/1');
		$this->assertEmpty($browser->getCssSelect("showx")); // no reset offered
		$this->assertTextContains('Reset is only possible when collection size is set to 200', $browser->driver->getPageSource());

		// we try to force it on the server anyway - redirect back to same partition
		$browser->getWithPostData('/sets/resetProgress/' . $context->sets[0]['id'] . '/1', ['reset-check' => 'reset']);
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->sets[0]['id'] . '/1', $browser->driver->getCurrentURL());
		$this->assertSame(400, count(ClassRegistry::init('TsumegoStatus')->find('all', ['order' => 'id'])));
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
		$tsumegoFilters = new TsumegoFilters();
		$this->assertSame(60, $tsumegoFilters->collectionSize);
	}

	/**
	 * Verify that Favorites menu item appears for all logged-in users,
	 * even those without any favorites yet.
	 */
	public function testFavoritesMenuVisibleForAllLoggedInUsers(): void
	{
		new ContextPreparator(['user' => ['name' => 'regularuser']]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$pageSource = $browser->driver->getPageSource();
		$this->assertStringContainsString('href="/sets/view/favorites"', $pageSource, 'Favorites link should be in the page HTML for logged-in users');
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
		$this->testAction('sets', ['return' => 'view']);
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
		$this->testAction('sets', ['return' => 'view']);
		$this->assertTextContains('15k', $this->view);
		$this->assertTextNotContains('deleted', $this->view);
	}

	/**
	 * Verifies a sandbox tsumego's play page loads correctly for premium users.
	 * Smoke test to ensure the $isSandbox code path in Play.php doesn't crash.
	 *
	 * @group browser
	 * @retryAttempts 2
	 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
	 */
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

	/**
	 * Verifies non-premium users are blocked from sandbox play pages.
	 * Creates sandbox set as premium owner, then switches to a non-premium user.
	 *
	 * @group browser
	 * @retryAttempts 2
	 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
	 */
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

        /**
         * The set view renders two .title4 headings: "Problems" (the list column)
         * and the set title (the info column). Return the set title, which is the
         * one that isn't "Problems". Finds it by value so the assertion doesn't
         * depend on which column renders first in the DOM.
         *
         * @param array $titleDivs Selenium WebDriver elements matching .title4
         */
        private function setTitleFrom(array $titleDivs): string
        {
                foreach ($titleDivs as $titleDiv)
                {
                        $text = $titleDiv->getText();
                        if ($text !== 'Problems')
                        {
                                return $text;
                        }
                }
                return '';
        }
}

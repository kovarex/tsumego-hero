<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverWait;

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
		$contextParams['other-tsumegos'] = [];
		$contextParams['other-tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 1', 'num' => '1']]];
		$contextParams['other-tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'sandbox-set', 'num' => '1', 'public' => 0]]];
		$contextParams['other-tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('10k'),
			'sets' => [['name' => 'set 2', 'num' => '1']]];
		$context = new ContextPreparator($contextParams);

		$_COOKIE['query'] = 'difficulty';
		$_COOKIE['filtered_ranks'] = '15k';
		$this->testAction('sets', ['return' => 'view']);
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.collection-top');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->textContent, '15k');

		$collectionMiddleLeft = $dom->querySelectorAll('.collection-middle-left');
		$this->assertCount(1, $collectionMiddleLeft);
		$this->assertSame($collectionMiddleLeft[0]->textContent, '1 problem'); // the sandbox problem isn't included

		$_COOKIE['filtered_ranks'] = '10k';
		$this->testAction('sets', ['return' => 'view']);
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.collection-top');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->textContent, '10k');
	}

	public function testSetViewRankBased(): void
	{
		$contextParams = [];
		$contextParams['other-tsumegos'] = [];
		$contextParams['other-tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 1', 'num' => '1']]];

		// adding sandbox tsumego in the selected rank, to test that it isn't shown
		$contextParams['other-tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'sandbox-set', 'num' => '1', 'public' => 0]]];

		$contextParams['other-tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('10k'),
			'sets' => [['name' => 'set 2', 'num' => '1']]];
		$context = new ContextPreparator($contextParams);

		$this->testAction('sets/view/15k', ['return' => 'view']);
		$dom = $this->getStringDom();
		$titleDivs = $dom->querySelectorAll('.title4');
		$this->assertCount(2, $titleDivs);
		$this->assertSame($titleDivs[1]->textContent, '15k');

		$problemButtons = $dom->querySelectorAll('.setViewButtons1');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemLinks);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

		$this->testAction('sets/view/10k', ['return' => 'view']);
		$dom = $this->getStringDom();
		$titleDivs = $dom->querySelectorAll('.title4');
		$this->assertCount(2, $titleDivs);
		$this->assertSame($titleDivs[1]->textContent, '10k');

		$problemButtons = $dom->querySelectorAll('.setViewButtons1');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->otherTsumegos[2]['set-connections'][0]['id']);
	}

	public function testSetViewSetBased(): void
	{
		$contextParams = [];
		$contextParams['other-tsumegos'] = [];
		$contextParams['other-tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 1', 'num' => '1']]];
		$contextParams['other-tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('10k'),
			'sets' => [['name' => 'set 2', 'num' => '1']]];
		$context = new ContextPreparator($contextParams);

		$this->testAction('sets/view/' . $context->otherTsumegos[0]['sets'][0]['id'], ['return' => 'view']);
		$dom = $this->getStringDom();
		$titleDivs = $dom->querySelectorAll('.title4');
		$this->assertCount(2, $titleDivs);
		$this->assertSame($titleDivs[1]->textContent, 'set 1');

		$problemButtons = $dom->querySelectorAll('.setViewButtons1');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemLinks);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

		$this->testAction('sets/view/' . $context->otherTsumegos[1]['sets'][0]['id'], ['return' => 'view']);
		$dom = $this->getStringDom();
		$titleDivs = $dom->querySelectorAll('.title4');
		$this->assertCount(2, $titleDivs);
		$this->assertSame($titleDivs[1]->textContent, 'set 2');

		$problemButtons = $dom->querySelectorAll('.setViewButtons1');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->textContent, '1');

		$problemLinks = $dom->querySelectorAll('.tooltip');
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->otherTsumegos[1]['set-connections'][0]['id']);
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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextStartsWith($title, $collectionTopDivs[0]->getText());
	}

	public function testFullProcessOfDifficultyBasedSelectionAndSolving(): void
	{
		$contextParams = ['user' => ['mode' => Constants::$LEVEL_MODE]];
		$contextParams['other-tsumegos'] = [];

		$statuses = ['V', 'S', 'C', 'W'];

		// three problems in the 15k range in the same set (will be included)
		for ($i = 0; $i < 3; $i++)
		{
			$contextParams['other-tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
				'sets' => [['name' => 'set 1', 'num' => $i + 1]],
				'status' => $statuses[$i]];
		}

		// other 15k problem from different set, will be also included
		$contextParams['other-tsumegos'] [] = [
			'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 2', 'num' => 4]],
			'status' => $statuses[3]];

		// other problems with different difficulty, but in the same set, will be excluded
		for ($i = 0; $i < 3; $i++)
		{
			$contextParams['other-tsumegos'] [] = [
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
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/15k', $browser->driver->getCurrentURL());

		// now we are viewing the 15k set insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 4, $context, function ($index) { return $index; }, function ($index) { return $index + 1; });

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 1/4');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 4, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'V');

		// clicking on next problem
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 2/4');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 4, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 1, 'S');
	}

	public function testFullProcessOfPartitionedSetBasedSelection(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'collection_size' => 2]];
		$contextParams['other-tsumegos'] = [];
		$statuses = ['N', 'N', 'V', 'V'];

		// 4 problems in our set
		for ($i = 0; $i < 4; $i++)
		{
			$contextParams['other-tsumegos'] [] = [
				'sets' => [['name' => 'set hello world', 'num' => $i + 1]],
				'status' => $statuses[$i]];
		}

		$context = new ContextPreparator($contextParams);

		// first we select the difficulty of 15k
		$browser = Browser::instance();
		$browser->get("sets");

		// we check the set card and clicking
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(2, $collectionTopDivs); // 2 partitions of 2 problems
		$this->assertSame($collectionTopDivs[0]->getText(), 'set hello world #1');
		$this->assertSame($collectionTopDivs[1]->getText(), 'set hello world #2');
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->otherTsumegos[0]['sets'][0]['id'] . '/1', $browser->driver->getCurrentURL());

		// now we are viewing the 'set hello world' and checking the buttons

		// there should be just 2 of the 4 tsumegos, as we picked collection size of 2
		$buttons = $this->checkSetNavigationButtons($browser, 2, $context, function ($index) { return $index; }, function ($index) { return $index + 1; });

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'V');

		// clicking on next problem
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set hello world #1 2/4');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 1, 'V');

		// now we go back to the sets selection and we visit the second partition of the set
		$browser->get('sets');
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(2, $collectionTopDivs); // 2 partitions of 2 problems
		$collectionTopDivs[1]->click();

		// now we are in the second partition of the set
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->otherTsumegos[0]['sets'][0]['id'] . '/2', $browser->driver->getCurrentURL());

		// there should be just 2 of the 4 tsumegos, as we picked collection size of 2
		$buttons = $this->checkSetNavigationButtons($browser, 2, $context, function ($index) { return $index + 2; }, function ($index) { return $index + 3; });

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();
		$this->checkPlayTitle($browser, 'set hello world #2 3/4');
	}


	public function testOfVisiting2RankBasedSetsBothInTheFilters(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'difficulty',
			'filtered_ranks' => ['15k', '1d']]];

		$contextParams['other-tsumegos'] = [];

		// three problems in the 15k range in different sets (sets of the problem shouldn't play a role anyway)
		for ($i = 0; $i < 3; $i++)
		{
			$contextParams['other-tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('15k'),
				'sets' => [['name' => 'set ' . ($i + 1), 'num' => 1]]];
		}

		// three problems in the 1d range in different sets
		for ($i = 0; $i < 3; $i++)
		{
			$contextParams['other-tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('1d'),
				'sets' => [['name' => 'set ' . ($i + 1), 'num' => 2]]];
		}

		// three completely unrelated problems
		for ($i = 0; $i < 3; $i++)
		{
			$contextParams['other-tsumegos'] [] = [
				'rating' => Rating::getRankMiddleRatingFromReadableRank('5d'),
				'sets' => [['name' => 'set ' . ($i + 1), 'num' => 3]]];
		}

		$context = new ContextPreparator($contextParams);

		$browser = Browser::instance();

		// we open sets, and since we filtered 15k and 1d, this is the sets we should see
		$browser->get("sets");
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$this->assertSame($collectionTopDivs[1]->getText(), '1d');

		// first we visit the 15k one
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/15k', $browser->driver->getCurrentURL());
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), '15k');

		// now we are viewing the 15k set insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 3, $context, function ($index) { return $index; }, function ($index) { return $index + 1; });

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 1/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 3, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'V');

		// clicking on next problem
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 2/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 3, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 1, 'V');

		// now we go to visit the 1d section, so back to sets
		$browser->get("sets");
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$this->assertSame($collectionTopDivs[1]->getText(), '1d');

		// first we visit the 1d one
		$collectionTopDivs[1]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/1d', $browser->driver->getCurrentURL());
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), '1d');

		// now we are viewing the 1d set insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 3, $context, function ($index) { return $index + 3; }, function ($index) { return $index + 1; });

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[3]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '1d 1/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 3, $context, function ($index) { return $index + 3; }, function ($index) { return $index + 1; }, 0, 'V');
	}

	public function testQueringSetsByTopicButLimitedByRanks(): void
	{
		$browser = Browser::instance();
		// filter by topics, but limit by ranks
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'topics',
			'filtered_ranks' => ['15k', '1d']]];

		$contextParams['other-tsumegos'] = [];

		// three ranks, and three sets, each rank is in each set once.
		// note that 5d is first, to check that the navigation button numbers will keep its order
		// of 2 and 3 when 1 is filtered one.
		foreach (['5d', '15k', '1d'] as $rankIndex => $rank)
			for ($i = 0; $i < 3; $i++)
				$contextParams['other-tsumegos'] [] = [
					'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
					'sets' => [['name' => 'set ' . ($i + 1), 'num' => ($rankIndex + 1)]]];

		$context = new ContextPreparator($contextParams);

		// we open sets, we filtered 15k and 1d, but query by sets, so we should see:
		// all 3 sets with
		$browser->get("sets");
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(3, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'set 1');
		$this->assertSame($collectionTopDivs[1]->getText(), 'set 2');
		$this->assertSame($collectionTopDivs[2]->getText(), 'set 3');

		// with 2 problems each as the 5d problems should be already filtered out
		$collectionMiddleLeftDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-middle-left'));
		$this->assertCount(3, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getText(), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[1]->getText(), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[2]->getText(), '2 problems');

		// first visit the 'set 1'
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->otherTsumegos[0]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), 'set 1');

		// now we are viewing the 'set 1' insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 2, $context, function ($index) { return ($index + 1) * 3; }, function ($index) { return $index + 2; });

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[3]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set 1 2/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) { return ($index + 1) * 3; }, function ($index) { return $index + 2; }, 0, 'V');

		// clicking on next problem
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[6]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set 1 3/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) { return ($index + 1) * 3; }, function ($index) { return $index + 2; }, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->otherTsumegos[0]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$browser->driver->getPageSource();
		$this->assertCount(2, $browser->getCssSelect('.title4'));
		$this->assertSame($browser->getCssSelect('.title4')[1]->getText(), 'set 1');
	}

	public function testQueringSetsByRanksButLimitedByTopics(): void
	{

		// filter by topics, but limit by ranks
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'difficulty',
			'filtered_sets' => ['set 2', 'set 3']]];

		$contextParams['other-tsumegos'] = [];

		// three ranks, and three sets, each rank is in each set once.
		// note that 5d is first, to check that the navigation button numbers will keep its order
		// of 2 and 3 when 1 is filtered one.
		foreach (['5d', '15k', '1d'] as $rankIndex => $rank)
			for ($i = 0; $i < 3; $i++)
			{
				$contextParams['other-tsumegos'] [] = [
					'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
					'sets' => [['name' => 'set ' . ($i + 1), 'num' => ($rankIndex + 1)]]];
			}

		$context = new ContextPreparator($contextParams);

		$browser = Browser::instance();

		// we open sets, we filtered set 2 and set 3, but query by ranks, so we should see:

		// all 3 ranks with
		$browser->get("sets");
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(3, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), '15k');
		$this->assertSame($collectionTopDivs[1]->getText(), '1d');
		$this->assertSame($collectionTopDivs[2]->getText(), '5d');

		// with 2 problems each as the set 1 problems should already be filtered out
		$collectionMiddleLeftDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-middle-left'));
		$this->assertCount(3, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getText(), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[1]->getText(), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[2]->getText(), '2 problems');

		// first visit the 'set 15k'
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/15k', $browser->driver->getCurrentURL());
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), '15k');

		// now we are viewing the 'set 2' insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 2, $context, function ($index) { return $index + 4; }, function ($index) { return $index + 1; });

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[4]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 1/2');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) { return $index + 4; }, function ($index) { return $index + 1; }, 0, 'V');

		// clicking on next problem
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[5]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, '15k 2/2');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) { return $index + 4; }, function ($index) { return $index + 1; }, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/15k', $browser->driver->getCurrentURL());
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), '15k');
	}

	public function testQueringSetsByRanksButVisitingFromTopic(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'difficulty'],
			'other-tsumegos' => [['sets' => [['name' => 'set 1', 'num' => 1]]]]]);

		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->checkPlayTitle($browser, 'Tsumego 1/1');
	}

	public function testOpeningProblemOutsideCurrentFilters(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => [
				'mode' => Constants::$LEVEL_MODE,
				'query' => 'topics',
				'filtered_ranks' => ['10k']],
			'other-tsumegos' => [
			['rating' => Rating::getRankMiddleRatingFromReadableRank('5k'), 'sets' => [['name' => 'set 1', 'num' => 1]]],
			['rating' => Rating::getRankMiddleRatingFromReadableRank('10k'), 'sets' => [['name' => 'set 1', 'num' => 1]]]]]);

		// we filtered to 10k, but we are opening the 5k problem
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertEmpty($browser->getCssSelect('#currentNavigationButton')); // no tsumego button is marked as current
	}

	public function testSelectingTagFilters(): void
	{
		$contextParams = ['user' => ['mode' => Constants::$LEVEL_MODE]];
		$contextParams['other-tsumegos'] = [];

		// each problem to introduce one tag type
		foreach (['snapback', 'atari', 'empty triangle'] as $tag)
			$contextParams['other-tsumegos'] [] = [
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
		$contextParams['other-tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		foreach (['snapback', 'atari', 'empty triangle'] as $key => $tag)
			for ($i = 0; $i < 3 - $key; $i++)
			{
				$contextParams['other-tsumegos'] [] = [
					'sets' => [['name' => 'set 1', 'num' => $i + 1]],
					'tags' => [['name' => $tag]]];
			}

		$context = new ContextPreparator($contextParams);
		$browser->get("sets");
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
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
		$contextParams['other-tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		foreach (['snapback', 'atari', 'empty triangle'] as $key => $tag)
			for ($i = 0; $i < 3 - $key; $i++)
			{
				$contextParams['other-tsumegos'] [] = [
					'sets' => [['name' => 'set 1', 'num' => $i + 1]],
					'tags' => [['name' => $tag]]];
			}

		$context = new ContextPreparator($contextParams);
		$browser = Browser::instance();
		$browser->get("sets");
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'atari');
		$this->assertSame($collectionTopDivs[1]->getText(), 'empty triangle');
		$this->assertSame('Problems found: 3', $browser->find('#problems-found')->getText());

		// going into the 'atari' set
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/atari', $browser->driver->getCurrentURL());
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), 'atari');

		// now we are viewing the 'atari' insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 2, $context, function ($index) { return $index + 3; }, function ($index) { return $index + 1; });

		// entering the tsumego in the set
		$buttons[0]->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[3]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'atari 1/2');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) { return $index + 3; }, function ($index) { return $index + 1; }, 0, 'V');

		// clicking next to get to the second one
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[4]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'atari 2/2');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) { return $index + 3; }, function ($index) { return $index + 1; }, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/atari', $browser->driver->getCurrentURL());
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), 'atari');
	}

	public function testVisitingTopicBasedSetsRespectsTagFilters(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'topics',
			'filtered_tags' => ['atari', 'empty triangle']]];
		$contextParams['other-tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		foreach (['snapback', 'atari', 'empty triangle'] as $key => $tag)
			for ($i = 0; $i < 3 - $key; $i++)
			{
				$contextParams['other-tsumegos'] [] = [
					'sets' => [['name' => 'set ' . ($i + 1), 'num' => $key + 1]],
					'tags' => [['name' => $tag]]];
			}

		$context = new ContextPreparator($contextParams);
		$browser = Browser::instance();
		$browser->get("sets");
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(2, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'set 1');
		$this->assertSame($collectionTopDivs[1]->getText(), 'set 2');

		$collectionMiddleLeftDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-middle-left'));
		$this->assertCount(2, $collectionMiddleLeftDivs);
		$this->assertSame($collectionMiddleLeftDivs[0]->getText(), '2 problems');
		$this->assertSame($collectionMiddleLeftDivs[1]->getText(), '1 problem');
		$this->assertSame('Problems found: 3', $browser->find('#problems-found')->getText());

		// going into the 'set 1'
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->otherTsumegos[3]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), 'set 1');

		// now we are viewing the 'set 1' insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 2, $context, function ($index) { return $index * 2 + 3; }, function ($index) { return $index + 2; });

		// entering the tsumego in the set
		$buttons[0]->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[3]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set 1 2/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) { return $index * 2 + 3; }, function ($index) { return $index + 2; }, 0, 'V');

		// clicking next to get to the second one
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[5]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$this->checkPlayTitle($browser, 'set 1 3/3');
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 2, $context, function ($index) { return $index * 2 + 3; }, function ($index) { return $index + 2; }, 1, 'V');

		// clicking on next problem should get us back to the set
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->otherTsumegos[3]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), 'set 1');
	}

	private function checkSetFinishedPercent($browser, $index, $title, $percent): void
	{
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'))[$index]->getText(), $title);
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#number' . $index))->getText(), $percent);
		$barStyle = $browser->driver->findElement(WebDriverBy::cssSelector('#xp-bar-fill2' . $index))->getAttribute('style');
		$this->assertTextContains('width: ' . $percent, $barStyle);
	}

	public function testTopicBasedSetViewShowsSolvedPercentProperly(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'topics']];
		$contextParams['other-tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		// each have one unsolved
		foreach (['set 1', 'set 2', 'set 3', 'set 4', 'set 5'] as $key => $set)
			for ($i = 0; $i < 4; $i++)
			{
				$contextParams['other-tsumegos'] [] = [
					'sets' => [['name' => $set, 'num' => $i + 1]],
					'status' => ($i >= $key ? 'N' : 'S')];
			}

		$context = new ContextPreparator($contextParams);
		$browser = Browser::instance();
		$browser->get("sets");

		$wait = new WebDriverWait($browser->driver, 5, 500); // (driver, timeout, polling interval)
		$wait->until(function () use ($browser) {
			return $browser->driver->findElement(WebDriverBy::cssSelector('#number4'))->getText() == '100%';
		});

		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(5, $collectionTopDivs);
		$this->checkSetFinishedPercent($browser, 0, 'set 1', '0%');
		$this->checkSetFinishedPercent($browser, 1, 'set 2', '25%');
		$this->checkSetFinishedPercent($browser, 2, 'set 3', '50%');
		$this->checkSetFinishedPercent($browser, 3, 'set 4', '75%');
		$this->checkSetFinishedPercent($browser, 4, 'set 5', '100%');
		$this->assertSame('Problems found: 20', $browser->find('#problems-found')->getText());
	}

	public function testTagBasedSetViewShowsSolvedPercentProperly(): void
	{
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'tags']];
		$contextParams['other-tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		// each have one unsolved
		foreach (['atari', 'bambule', 'empty triangle', 'snapback', 'zen'] as $key => $tag)
			for ($i = 0; $i < 4; $i++)
			{
				$contextParams['other-tsumegos'] [] = [
					'sets' => [['name' => 'set 1', 'num' => $i + 1]],
					'tags' => [['name' => $tag]],
					'status' => ($i >= $key ? 'N' : 'S')];
			}

		$context = new ContextPreparator($contextParams);
		$browser = Browser::instance();
		$browser->get("sets");

		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 5, 500); // (driver, timeout, polling interval)
		$wait->until(function () use ($browser) {
			return $browser->driver->findElement(WebDriverBy::cssSelector('#number4'))->getText() == '100%';
		});

		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
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
		$contextParams['other-tsumegos'] = [];

		// 3 problems in stanpback, 2 in atari and 1 in empty triangle
		// we sort by count so, this will ensure they are shown in this order as well
		// each have one unsolved
		foreach (['15k', '10k', '5k', '1d', '5d'] as $key => $rank)
			for ($i = 0; $i < 4; $i++)
			{
				$contextParams['other-tsumegos'] [] = [
					'sets' => [['name' => 'set 1', 'num' => $i + 1]],
					'rating' => Rating::getRankMiddleRatingFromReadableRank($rank),
					'status' => ($i >= $key ? 'N' : 'S')];
			}

		$context = new ContextPreparator($contextParams);
		$browser = Browser::instance();
		$browser->get("sets");

		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 5, 500); // (driver, timeout, polling interval)
		$wait->until(function () use ($browser) {
			return $browser->driver->findElement(WebDriverBy::cssSelector('#number4'))->getText() == '100%';
		});

		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(5, $collectionTopDivs);
		$this->checkSetFinishedPercent($browser, 0, '15k', '0%');
		$this->checkSetFinishedPercent($browser, 1, '10k', '25%');
		$this->checkSetFinishedPercent($browser, 2, '5k', '50%');
		$this->checkSetFinishedPercent($browser, 3, '1d', '75%');
		$this->checkSetFinishedPercent($browser, 4, '5d', '100%');
		$this->assertSame('Problems found: 20', $browser->find('#problems-found')->getText());
	}

	public function testAddingToFavoritesAndViewingIt(): void
	{
		ClassRegistry::init('Favorite')->deleteAll(['1 = 1']);
		$contextParams = [];
		$contextParams['user'] = ['mode' => Constants::$LEVEL_MODE];
		for ($i = 0; $i < 3; $i++)
			$contextParams ['other-tsumegos'] [] = ['sets' => [['name' => 'set ' . $i, 'num' => $i]]];
		$context = new ContextPreparator($contextParams);

		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->driver->findElement(WebDriverBy::cssSelector('#favButton'))->click();
		$browser->get('/sets/view/favorites');
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), 'Favorites');

		// now we are viewing the 'favorites' insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; });
		$buttons[0]->click();

		// opening the favorites problem
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'V');

		// next will get us back to favorites
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/favorites', $browser->driver->getCurrentURL());
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), 'Favorites');
	}

	public function testRemovingFavorites(): void
	{
		ClassRegistry::init('Favorite')->deleteAll(['1 = 1']);
		$contextParams = [];
		$contextParams['user'] = ['mode' => Constants::$LEVEL_MODE];
		for ($i = 0; $i < 3; $i++)
			$contextParams ['other-tsumegos'] [] = ['sets' => [['name' => 'set ' . $i, 'num' => $i]]];
		$context = new ContextPreparator($contextParams);
		$context->addFavorite($context->otherTsumegos[0]);

		$browser = Browser::instance();
		$browser->get('sets/view/favorites');
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), 'Favorites');

		// now we are viewing the 'favorites' insides and checking the buttons
		$buttons = $this->checkSetNavigationButtons($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; });
		$buttons[0]->click(); // opening the favorites problem

		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'V');
		$browser->driver->findElement(WebDriverBy::cssSelector('#favButton'))->click();

		// going back to favorites, which should be empty now
		$browser->get('sets/view/favorites');
		$buttons = $this->checkSetNavigationButtons($browser, 0, $context, function ($index) { return $index; }, function ($index) { return $index + 1; });
	}

	public function testGoingFromFavoritesToSetIndexResetsTheFavoritesQuery(): void
	{
		ClassRegistry::init('Favorite')->deleteAll(['1 = 1']);
		$contextParams = [];
		$contextParams['user'] = ['mode' => Constants::$LEVEL_MODE, 'query' => 'favorites'];
		for ($i = 0; $i < 3; $i++)
			$contextParams ['other-tsumegos'] [] = ['sets' => [['name' => 'set ' . $i, 'num' => $i]]];
		$context = new ContextPreparator($contextParams);

		$browser = Browser::instance();
		$browser->get('sets');
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(3, $collectionTopDivs); // the 3 sets are visible even when not in favorites, as top index ignores favorites
		$tsumegoFilters = new TsumegoFilters();
		$this->assertTrue($tsumegoFilters->query != 'favorites');
	}

	public function testBrowsingFavoritesByNextButton(): void
	{
		ClassRegistry::init('Favorite')->deleteAll(['1 = 1']);
		$contextParams = [];
		$contextParams['user'] = ['mode' => Constants::$LEVEL_MODE, 'query' => 'favorites'];
		for ($i = 0; $i < 5; $i++)
			$contextParams ['other-tsumegos'] [] = ['sets' => [['name' => 'set ' . $i, 'num' => $i]]];
		$context = new ContextPreparator($contextParams);

		// only 3 out of 5 are favorites
		for ($i = 0; $i < 3; $i++)
			$context->addFavorite($context->otherTsumegos[$i]);

		$browser = Browser::instance();
		$browser->get('sets/view/favorites');
		// now we are viewing the 'favorites' insides and checking the buttons
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), 'Favorites');
		$buttons = $this->checkSetNavigationButtons($browser, 3, $context, function ($index) { return $index; }, function ($index) { return $index + 1; });
		$buttons[0]->click();

		// first favorite
		for ($i = 0; $i < 3; $i++)
		{
			$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[$i]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
			$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 3, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, $i, 'V');
			$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		}
		$this->assertSame(Util::getMyAddress() . '/sets/view/favorites', $browser->driver->getCurrentURL());
	}

	public function testOnlyPublicSetsAreVisible(): void
	{
		new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'public set', 'public' => 1, 'num' => '666'],
			['name' => 'private set', 'public' => 0, 'num' => '777']]]]);

		$browser = Browser::instance();
		$browser->get('sets');
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'public set');
		$this->assertSame('Problems found: 1', $browser->find('#problems-found')->getText());
	}

	public function testOnlyPrivateSetsAreVisibleInSandbox(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'premium' => 1],
			'other-tsumegos' => [
				['sets' => [['name' => 'public set', 'public' => 1, 'num' => '666']]],
				['sets' => [['name' => 'private set', 'public' => 0, 'num' => '777']]]]]);
		$browser = Browser::instance();
		$browser->get('sets/sandbox');
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->getText(), 'private set');
		$collectionTopDivs[0]->click();
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->otherTsumegos[1]['set-connections'][0]['set_id'], $browser->driver->getCurrentURL());

		$problemButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.setViewButtons1'));
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->getText(), '777');
	}

	public function testAddingProblemInSandbox(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => 1],
			'other-tsumegos' => [['sets' => [['name' => 'private set', 'public' => 0, 'num' => '1']]]]]);
		$browser = Browser::instance();
		$browser->get('/sets/view/' . $context->otherTsumegos[0]['set-connections'][0]['set_id']);

		$problemButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.setViewButtons1'));
		$this->assertCount(1, $problemButtons);
		$this->assertSame($problemButtons[0]->getText(), '1');
		$browser->clickCssSelect('#TsumegoViewForm input[type="submit"]');

		$problemButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.setViewButtons1'));
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
			'other-tsumegos' => [
				['sets' => [['name' => 'Test Set', 'num' => 1]]],
				['sets' => [['name' => 'Test Set', 'num' => 2]]],
				['sets' => [['name' => 'Test Set', 'num' => 3]]],
			],
		]);

		$browser = Browser::instance();
		// Get set ID from first other-tsumego
		$setId = $context->otherTsumegos[0]['sets'][0]['id'];

		$browser->get("sets/view/{$setId}");

		// Completed tab should be active by default - find problem buttons
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('.setViewButtons1'));

		// Should show problem numbers: 1, 2, 3
		$this->assertCount(3, $buttons, 'Should have 3 problems in set');
		$this->assertSame('1', trim($buttons[0]->getText()));
		$this->assertSame('2', trim($buttons[1]->getText()));
		$this->assertSame('3', trim($buttons[2]->getText()));

		// setViewButtons2 (accuracy) and setViewButtons3 (time) should be hidden
		$accuracyButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.setViewButtons2'));
		foreach ($accuracyButtons as $btn)
			$this->assertFalse($btn->isDisplayed(), 'Accuracy buttons should be hidden on Completed tab');

		$timeButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.setViewButtons3'));
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
			'other-tsumegos' => [
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
		$setId = $context->otherTsumegos[0]['sets'][0]['id'];
		$browser->get("sets/view/{$setId}");

		// Find the <li> elements (button containers with status classes)
		$listItems = $browser->driver->findElements(WebDriverBy::cssSelector('li[class*="status"]'));
		$this->assertCount(3, $listItems, 'Should have 3 problem buttons');

		// Problem 1: Not attempted - should have statusN class
		$this->assertStringContainsString('statusN', $listItems[0]->getAttribute('class'), 'Problem 1 should have statusN (not attempted)');

		// Problem 2: Solved - should have statusS class
		$this->assertStringContainsString('statusS', $listItems[1]->getAttribute('class'), 'Problem 2 should have statusS (solved)');

		// Problem 3: Failed - should have statusF class
		$this->assertStringContainsString('statusF', $listItems[2]->getAttribute('class'), 'Problem 3 should have statusF (failed)');
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
			'other-tsumegos' => [
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
		$setId = $context->otherTsumegos[0]['sets'][0]['id'];
		$browser->get("sets/view/{$setId}");

		// Click Accuracy tab
		$accuracyTab = $browser->driver->findElement(WebDriverBy::xpath("//a[contains(text(), 'Accuracy')]"));
		$accuracyTab->click();
		sleep(1);

		// Check accuracy buttons are visible
		$accuracyButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.setViewButtons2'));
		$this->assertCount(2, $accuracyButtons);

		// Problem 1 should show "3/1" (3 solved, 1 failed)
		$this->assertTrue($accuracyButtons[0]->isDisplayed());
		$this->assertSame('3/1', trim($accuracyButtons[0]->getText()), 'Problem 1 accuracy should be 3/1');

		// Problem 2 should show "-" (no attempts)
		$this->assertTrue($accuracyButtons[1]->isDisplayed());
		$this->assertSame('-', trim($accuracyButtons[1]->getText()), 'Problem 2 accuracy should be - (no attempts)');

		// Order numbers and time should be hidden
		$orderButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.setViewButtons1'));
		foreach ($orderButtons as $btn)
			$this->assertFalse($btn->isDisplayed(), 'Order numbers should be hidden on Accuracy tab');
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
			'other-tsumegos' => [
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
		$setId = $context->otherTsumegos[0]['sets'][0]['id'];
		$browser->get("sets/view/{$setId}");

		// Click Time tab - use specific selector to avoid Time Mode menu link
		$timeTab = $browser->driver->findElement(WebDriverBy::xpath("//a[contains(@class, 'setViewTime') or (contains(text(), 'Time') and not(contains(@href, 'timeMode')))]"));
		$browser->driver->executeScript("arguments[0].click();", [$timeTab]);
		sleep(1);

		// Check time buttons are visible
		$timeButtons = $browser->driver->findElements(WebDriverBy::cssSelector('.setViewButtons3'));
		$this->assertCount(2, $timeButtons);

		// Problem 1 should show "10s" (minimum/best of 10, 20, 30)
		$this->assertTrue($timeButtons[0]->isDisplayed());
		$this->assertSame('10s', trim($timeButtons[0]->getText()), 'Problem 1 time should be 10s (best time)');

		// Problem 2 should show "-" (no successful solves)
		$this->assertTrue($timeButtons[1]->isDisplayed());
		$this->assertSame('-', trim($timeButtons[1]->getText()), 'Problem 2 time should be - (no successful solves)');
	}

}

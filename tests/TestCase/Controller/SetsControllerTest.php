<?php

use Facebook\WebDriver\WebDriverBy;

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../ContextPreparator.php');

class SetsControllerTest extends TestCaseWithAuth {
	public function testIndexLoggedIn(): void {
		$context = new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'tsumego set 1', 'num' => '666'],
			['name' => 'tsumego set 2', 'num' => '777']]]], );
		$this->login($context->user['User']['name']);
		$this->testAction('sets', ['return' => 'view']);
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
		$this->assertTextNotContains("Problems found 0", $this->view);
	}

	public function testIndexLoggedOff(): void {
		new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'tsumego set 1', 'num' => '666'],
			['name' => 'tsumego set 2', 'num' => '777']]]], );
		$this->testAction('sets', ['return' => 'view']);
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
		$this->assertTextNotContains("Problems found 0", $this->view);
	}

	public function testIndexRankBased(): void {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);
		$contextParams = [];
		$contextParams['other-tsumegos'] = [];
		$contextParams['other-tsumegos'] [] = [
			'title' => '15k problem',
			'rating' => Rating::getRankMinimalRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 1', 'num' => '1']]];
		$contextParams['other-tsumegos'] [] = [
			'title' => '10k problem',
			'rating' => Rating::getRankMinimalRatingFromReadableRank('10k'),
			'sets' => [['name' => 'set 2', 'num' => '1']]];
		$context = new ContextPreparator($contextParams);

		$_COOKIE['query'] = 'difficulty';
		$_COOKIE['filtered_ranks'] = '15k';
		$this->testAction('sets', ['return' => 'view']);
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.collection-top');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->textContent, '15k');

		$_COOKIE['filtered_ranks'] = '10k';
		$this->testAction('sets', ['return' => 'view']);
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.collection-top');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->textContent, '10k');
	}

	public function testSetViewRankBased(): void {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);
		$contextParams = [];
		$contextParams['other-tsumegos'] = [];
		$contextParams['other-tsumegos'] [] = [
			'title' => '15k problem',
			'rating' => Rating::getRankMinimalRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 1', 'num' => '1']]];
		$contextParams['other-tsumegos'] [] = [
			'title' => '10k problem',
			'rating' => Rating::getRankMinimalRatingFromReadableRank('10k'),
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
		$this->assertSame($problemLinks[0]->getAttribute('href'), '/' . $context->otherTsumegos[1]['set-connections'][0]['id']);
	}

	public function testSetViewSetBased(): void {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);
		$contextParams = [];
		$contextParams['other-tsumegos'] = [];
		$contextParams['other-tsumegos'] [] = [
			'title' => '15k problem',
			'rating' => Rating::getRankMinimalRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 1', 'num' => '1']]];
		$contextParams['other-tsumegos'] [] = [
			'title' => '10k problem',
			'rating' => Rating::getRankMinimalRatingFromReadableRank('10k'),
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

	private function checkSetNavigationButtons($browser, int $count, $context, $indexFunction, $orderFunction): array {
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('div.set-view-main li'));
		$this->assertCount($count, $buttons);
		foreach ($buttons as $key => $button) {
			$this->checkNavigationButton($button, $context, $indexFunction($key), $orderFunction($key));
		}
		return $buttons;
	}

	private function checkPlayNavigationButtons($browser, int $count, $context, $indexFunction, $orderFunction, int $currentIndex, string $currentStatus): void {
		$navigationButtons = $browser->driver->findElements(WebDriverBy::cssSelector('div.tsumegoNavi2 li'));

		// removing the hole after first problem (the dividng empty li)
		if (count($navigationButtons) > 1) {
			array_splice($navigationButtons, 1, 1);
		}

		// removing the hole before last
		if (count($navigationButtons) > 2) {
			array_splice($navigationButtons, count($navigationButtons) - 2, 1);
		}

		$this->assertCount($count, $navigationButtons); // 4 testing ones and two 'empty' borders
		foreach ($navigationButtons as $key => $button) {
			$this->checkNavigationButton($button, $context, $indexFunction($key), $orderFunction($key), $indexFunction($currentIndex), $currentStatus);
		}
	}

	private function checkNavigationButtonsBeforeAndAfterSolving($browser, int $count, $context, $indexFunction, $orderFunction, int $currentIndex, string $currentStatus): void {
		$this->checkPlayNavigationButtons($browser, $count, $context, $indexFunction, $orderFunction, $currentIndex, $currentStatus);
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // mark the problem solved
		$this->checkPlayNavigationButtons($browser, $count, $context, $indexFunction, $orderFunction, $currentIndex, 'S');
	}

	private function checkNavigationButton($button, $context, int $index, int $order, ?int $currentIndex = null, ?string $currentStatus = null): void {
		$this->assertSame($button->getText(), strval($order));
		if (is_null($currentIndex) || $index != $currentIndex) {
			$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[$index]['id']]]);
			$statusValue = $status ? $status['TsumegoStatus']['status'] : 'N';
		} else {
			$statusValue = $currentStatus;
		}

		$this->assertSame($button->getAttribute('class'), 'set' . $statusValue . '1');
		$link = $button->findElement(WebDriverBy::tagName('a'));
		$this->assertSame($link->getAttribute('href'), '/' . $context->otherTsumegos[$index]['set-connections'][0]['id']);
	}

	private function checkPlayTitle($browser, string $title) {
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextStartsWith($title, $collectionTopDivs[0]->getText());
	}

	public function testFullProcessOfDifficultyBasedSelectionAndSolving(): void {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);
		$contextParams = ['user' => ['mode' => Constants::$LEVEL_MODE]];
		$contextParams['other-tsumegos'] = [];

		$statuses = ['V', 'S', 'C', 'W'];

		// three problems in the 15k range in the same set (will be included)
		for ($i = 0; $i < 3; $i++) {
			$contextParams['other-tsumegos'] [] = [
				'title' => '15k problem',
				'rating' => Rating::getRankMinimalRatingFromReadableRank('15k'),
				'sets' => [['name' => 'set 1', 'num' => $i + 1]],
				'status' => $statuses[$i]];
		}

		// other 15k problem from different set, will be also included
		$contextParams['other-tsumegos'] [] = [
			'title' => '15k problem from set 2',
			'rating' => Rating::getRankMinimalRatingFromReadableRank('15k'),
			'sets' => [['name' => 'set 2', 'num' => 4]],
			'status' => $statuses[3]];

		// other problems with different difficulty, but in the same set, will be excluded
		for ($i = 0; $i < 3; $i++) {
			$contextParams['other-tsumegos'] [] = [
				'title' => '10k problem',
				'rating' => Rating::getRankMinimalRatingFromReadableRank('10k'),
				'sets' => [['name' => 'set 1', 'num' => $i + 4]]];
		}

		$context = new ContextPreparator($contextParams);

		// first we select the difficulty of 15k
		$browser = new Browser();
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

	public function testFullProcessOfPartitionedSetBasedSelection(): void {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'collection_size' => 2]];
		$contextParams['other-tsumegos'] = [];
		$statuses = ['N', 'N', 'V', 'V'];

		// 4 problems in our set
		for ($i = 0; $i < 4; $i++) {
			$contextParams['other-tsumegos'] [] = [
				'title' => 'a problem',
				'sets' => [['name' => 'set hello world', 'num' => $i + 1]],
				'status' => $statuses[$i]];
		}

		$context = new ContextPreparator($contextParams);

		// first we select the difficulty of 15k
		$browser = new Browser();
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


	public function testOfVisiting2RankBasedSetsBothInTheFilters(): void {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'difficulty',
			'filtered_ranks' => ['15k', '1d']]];

		$contextParams['other-tsumegos'] = [];

		// three problems in the 15k range in different sets (sets of the problem shouldn't play a role anyway)
		for ($i = 0; $i < 3; $i++) {
			$contextParams['other-tsumegos'] [] = [
				'title' => '15k problem',
				'rating' => Rating::getRankMinimalRatingFromReadableRank('15k'),
				'sets' => [['name' => 'set ' . ($i + 1), 'num' => 1]]];
		}

		// three problems in the 1d range in different sets
		for ($i = 0; $i < 3; $i++) {
			$contextParams['other-tsumegos'] [] = [
				'title' => '1d problem',
				'rating' => Rating::getRankMinimalRatingFromReadableRank('1d'),
				'sets' => [['name' => 'set ' . ($i + 1), 'num' => 2]]];
		}

		// three completely unrelated problems
		for ($i = 0; $i < 3; $i++) {
			$contextParams['other-tsumegos'] [] = [
				'title' => '5d problem',
				'rating' => Rating::getRankMinimalRatingFromReadableRank('5d'),
				'sets' => [['name' => 'set ' . ($i + 1), 'num' => 3]]];
		}

		$context = new ContextPreparator($contextParams);

		$browser = new Browser();

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

	public function testQueringSetsByTopicButLimitedByRanks(): void {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);

		// filter by topics, but limit by ranks
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'topics',
			'filtered_ranks' => ['15k', '1d']]];

		$contextParams['other-tsumegos'] = [];

		// three ranks, and three sets, each rank is in each set once.
		// note that 5d is first, to check that the navigation button numbers will keep its order
		// of 2 and 3 when 1 is filtered one.
		foreach (['5d', '15k', '1d'] as $rankIndex => $rank) {
			for ($i = 0; $i < 3; $i++) {
				$contextParams['other-tsumegos'] [] = [
					'title' => $rank . ' problem',
					'rating' => Rating::getRankMinimalRatingFromReadableRank($rank),
					'sets' => [['name' => 'set ' . ($i + 1), 'num' => ($rankIndex + 1)]]];
			}
		}

		$context = new ContextPreparator($contextParams);

		$browser = new Browser();

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
		$this->assertSame($browser->driver->findElements(WebDriverBy::cssSelector('.title4'))[1]->getText(), 'set 1');
	}

	public function testQueringSetsByRanksButLimitedByTopics(): void {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);

		// filter by topics, but limit by ranks
		$contextParams = ['user' => [
			'mode' => Constants::$LEVEL_MODE,
			'query' => 'difficulty',
			'filtered_sets' => ['set 2', 'set 3']]];

		$contextParams['other-tsumegos'] = [];

		// three ranks, and three sets, each rank is in each set once.
		// note that 5d is first, to check that the navigation button numbers will keep its order
		// of 2 and 3 when 1 is filtered one.
		foreach (['5d', '15k', '1d'] as $rankIndex => $rank) {
			for ($i = 0; $i < 3; $i++) {
				$contextParams['other-tsumegos'] [] = [
					'title' => $rank . ' problem',
					'rating' => Rating::getRankMinimalRatingFromReadableRank($rank),
					'sets' => [['name' => 'set ' . ($i + 1), 'num' => ($rankIndex + 1)]]];
			}
		}

		$context = new ContextPreparator($contextParams);

		$browser = new Browser();

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

	public function testSelectingTagFilters(): void {
		ClassRegistry::init('Tsumego')->deleteAll(['1 = 1']);
		ClassRegistry::init('TagName')->deleteAll(['1 = 1']);
		ClassRegistry::init('Tag')->deleteAll(['1 = 1']);
		$contextParams = ['user' => ['mode' => Constants::$LEVEL_MODE]];
		$contextParams['other-tsumegos'] = [];

		// three problems in the 15k range in the same set (will be included)
		foreach (['snapback', 'atari', 'empty triangle'] as $tag) {
			for ($i = 0; $i < 3; $i++) {
				$contextParams['other-tsumegos'] [] = [
					'title' => '15k problem',
					'sets' => [['name' => 'set 1', 'num' => $i + 1]],
					'tags' => [['name' => $tag]]];
			}
		}

		$context = new ContextPreparator($contextParams);

		// first we select the difficulty of 15k
		$browser = new Browser();
		$browser->get("sets");
		$browser->driver->findElement(WebDriverBy::id('tags-button'))->click();
		$tagSelectors = $browser->driver->findElements(WebDriverBy::cssSelector('[id^="tile-tags"]:not([id*="select-all"]):not([id*="submit"])'));
        $this->assertCount( 3, $tagSelectors);
		$this->assertSame($tagSelectors[0]->getText(), 'snapback');
		$this->assertSame($tagSelectors[1]->getText(), 'atari');
		$this->assertSame($tagSelectors[2]->getText(), 'empty triangle');
		$tagSelectors[0]->click();
		$browser->driver->findElement(WebDriverBy::id('tile-tags-submit'))->click();

		// difficulty selected
		$this->assertSame($browser->driver->manage()->getCookieNamed('query')->getValue(), 'tags');
		$this->assertSame($browser->driver->manage()->getCookieNamed('filtered_tags')->getValue(), 'snapback');
	}
}

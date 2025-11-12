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
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('div.set-view-main li'));
		$this->assertCount(4, $buttons);
		foreach ($buttons as $key => $button) {
			$this->assertSame($button->getText(), strval($key + 1));
			$this->assertSame($button->getAttribute('class'), 'set' . $statuses[$key] . '1');
			$link = $button->findElement(WebDriverBy::tagName('a'));
			$this->assertSame($link->getAttribute('href'), '/' . $context->otherTsumegos[$key]['set-connections'][0]['id']);
		}

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$navigationButtons = $browser->driver->findElements(WebDriverBy::cssSelector('div.tsumegoNavi2 li'));
		$this->assertCount(6, $navigationButtons); // 4 testing ones and two 'empty' borders

		// checking that the title is correctly mentioning 15k and is 1/4
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextContains('15k', $collectionTopDivs[0]->getText());
		$this->assertTextContains('1/4', $collectionTopDivs[0]->getText());

		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setV1'); // only visited
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // mark the problem solved
		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setS1'); // solved

		// clicking on next problem
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());

		// proper title 15k and 2/4
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextContains('15k', $collectionTopDivs[0]->getText());
		$this->assertTextContains('2/4', $collectionTopDivs[0]->getText());

		$navigationButtons = $browser->driver->findElements(WebDriverBy::cssSelector('div.tsumegoNavi2 li'));
		$this->assertCount(6, $navigationButtons); // 4 testing ones and two 'empty' borders

		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setS1'); // the previous was solved
		// $navigationButton[1] is the black dividing edge and inner buttons
		$this->assertSame($navigationButtons[2]->getAttribute('class'), 'setS1'); // the current one already marked as solved
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
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('div.set-view-main li'));

		// there should be just 2 of the 4 tsumegos, as we picked collection size of 2
		$this->assertCount(2, $buttons);
		foreach ($buttons as $key => $button) {
			$this->assertSame($button->getText(), strval($key + 1));
			$this->assertSame($button->getAttribute('class'), 'set' . $statuses[$key] . '1');
			$link = $button->findElement(WebDriverBy::tagName('a'));
			$this->assertSame($link->getAttribute('href'), '/' . $context->otherTsumegos[$key]['set-connections'][0]['id']);
		}

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$navigationButtons = $browser->driver->findElements(WebDriverBy::cssSelector('div.tsumegoNavi2 li'));
		$this->assertCount(3, $navigationButtons); // 2 testing ones and one 'empty' border

		// checking that the title is correctly mentioning set hello world, and also the set partition
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextContains('set hello world', $collectionTopDivs[0]->getText());
		$this->assertTextContains('1/4', $collectionTopDivs[0]->getText());
		$this->assertTextContains('#1', $collectionTopDivs[0]->getText());

		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setV1'); // marked as visited
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // mark the problem solved
		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setS1'); // Not visited

		// clicking on next problem
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());

		// proper title
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextContains('set hello world #1', $collectionTopDivs[0]->getText());
		$this->assertTextContains('2/4', $collectionTopDivs[0]->getText());
		$this->assertTextContains('#1', $collectionTopDivs[0]->getText());

		$navigationButtons = $browser->driver->findElements(WebDriverBy::cssSelector('div.tsumegoNavi2 li'));
		$this->assertCount(3, $navigationButtons); // 2 testing ones and one merged border

		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setS1'); // the previous was solved
		// $navigationButton[1] is the black dividing edge and inner buttons
		$this->assertSame($navigationButtons[2]->getAttribute('class'), 'setV1'); // the current one is marked as visited

		// now we go back to the sets selection and we visit the second partition of the set
		$browser->get('sets');
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('.collection-top'));
		$this->assertCount(2, $collectionTopDivs); // 2 partitions of 2 problems
		$collectionTopDivs[1]->click();

		// now we are in the second partition of the set
		$this->assertSame(Util::getMyAddress() . '/sets/view/' . $context->otherTsumegos[0]['sets'][0]['id'] . '/2', $browser->driver->getCurrentURL());
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('div.set-view-main li'));

		// there should be just 2 of the 4 tsumegos, as we picked collection size of 2
		$this->assertCount(2, $buttons);
		foreach ($buttons as $key => $button) {
			$this->assertSame($button->getText(), strval($key + 3));
			$this->assertSame($button->getAttribute('class'), 'set' . $statuses[$key + 2] . '1');
			$link = $button->findElement(WebDriverBy::tagName('a'));
			$this->assertSame($link->getAttribute('href'), '/' . $context->otherTsumegos[$key + 2]['set-connections'][0]['id']);
		}

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextContains('set hello world #2', $collectionTopDivs[0]->getText());
		$this->assertTextContains('3/4', $collectionTopDivs[0]->getText());
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
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('div.set-view-main li'));
		$this->assertCount(3, $buttons);
		foreach ($buttons as $key => $button) {
			$this->assertSame($button->getText(), strval($key + 1));
			$link = $button->findElement(WebDriverBy::tagName('a'));
			$this->assertSame($link->getAttribute('href'), '/' . $context->otherTsumegos[$key]['set-connections'][0]['id']);
		}

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$navigationButtons = $browser->driver->findElements(WebDriverBy::cssSelector('div.tsumegoNavi2 li'));
		$this->assertCount(5, $navigationButtons); // 3 testing ones and two 'empty' borders

		// checking that the title is correctly mentioning 15k and is 1/3
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextContains('15k', $collectionTopDivs[0]->getText());
		$this->assertTextContains('1/3', $collectionTopDivs[0]->getText());

		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setV1'); // only visited
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // mark the problem solved
		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setS1'); // solved

		// clicking on next problem
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[1]['set-connections'][0]['id'], $browser->driver->getCurrentURL());

		// proper title 15k and 2/4
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextContains('15k', $collectionTopDivs[0]->getText());
		$this->assertTextContains('2/3', $collectionTopDivs[0]->getText());

		$navigationButtons = $browser->driver->findElements(WebDriverBy::cssSelector('div.tsumegoNavi2 li'));
		$this->assertCount(5, $navigationButtons); // 3 testing ones and two 'empty' borders

		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setS1'); // the previous was solved
		// $navigationButton[1] is the black dividing edge and inner buttons
		$this->assertSame($navigationButtons[2]->getAttribute('class'), 'setV1'); // the current one already marked as solved

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
		$buttons = $browser->driver->findElements(WebDriverBy::cssSelector('div.set-view-main li'));
		$this->assertCount(3, $buttons);
		foreach ($buttons as $key => $button) {
			$this->assertSame($button->getText(), strval($key + 1));
			$link = $button->findElement(WebDriverBy::tagName('a'));
			// the links should be from the second triad
			$this->assertSame($link->getAttribute('href'), '/' . $context->otherTsumegos[$key + 3]['set-connections'][0]['id']);
		}

		// clicking to get inside the set to play it
		$buttons[0]->findElement(WebDriverBy::tagName('a'))->click();

		// now we are in the problem
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[3]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$navigationButtons = $browser->driver->findElements(WebDriverBy::cssSelector('div.tsumegoNavi2 li'));
		$this->assertCount(5, $navigationButtons); // 3 testing ones and two 'empty' borders

		// checking that the title is correctly mentioning 15k and is 1/3
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextContains('1d', $collectionTopDivs[0]->getText());
		$this->assertTextContains('1/3', $collectionTopDivs[0]->getText());

		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setV1'); // only visited
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // mark the problem solved
		$this->assertSame($navigationButtons[0]->getAttribute('class'), 'setS1'); // solved
	}
}

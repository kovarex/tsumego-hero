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
		$this->assertSame(Util::getMyAddress() . '/' . $context->otherTsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$navigationButtons = $browser->driver->findElements(WebDriverBy::cssSelector('div.tsumegoNavi2 li'));
		$this->assertCount(6, $navigationButtons); // 4 testing ones and two 'empty' borders

		// checking that the title is correctly mentioning 15k
		$collectionTopDivs = $browser->driver->findElements(WebDriverBy::cssSelector('#playTitle'));
		$this->assertCount(1, $collectionTopDivs);
		$this->assertTextContains('15k', $collectionTopDivs[0]->getText());
	}
}

<?php

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
		$_COOKIE['search2'] = '15k';
		$this->testAction('sets', ['return' => 'view']);
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.collection-top');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->textContent, '15k');

		$_COOKIE['search2'] = '10k';
		$this->testAction('sets', ['return' => 'view']);
		$dom = $this->getStringDom();
		$collectionTopDivs = $dom->querySelectorAll('.collection-top');
		$this->assertCount(1, $collectionTopDivs);
		$this->assertSame($collectionTopDivs[0]->textContent, '10k');
	}
}

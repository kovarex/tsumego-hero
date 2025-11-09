<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');

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
		$this->testAction('sets', ['return' => 'view']);
		$this->assertTextContains('15k', $this->view);
		$this->assertTextNotContains('10k', $this->view);

		$this->testAction('sets', ['return' => 'view']);
		$this->assertTextNotContains('15k', $this->view);
		$this->assertTextContains('10k', $this->view);
	}
}

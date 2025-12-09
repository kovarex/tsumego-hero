<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

class TsumegosControllerTest extends TestCaseWithAuth
{
	public function testSetNameAndNumIsVisible()
	{
		foreach ([false, true] as $openBySetConnectionID)
		{
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '666']]]]);
			$this->testAction(
				$openBySetConnectionID
				? ('/' . $context->tsumego['set-connections'][0]['id'])
				: ('tsumegos/play/' . $context->tsumego['id']),
				['return' => 'view']);
			$this->assertTextContains("tsumego set 1", $this->view);

			$dom = $this->getStringDom();
			$href = $dom->querySelector('#playTitleA');
			$this->assertTextContains('tsumego set 1', $href->textContent);
			$this->assertTextContains('666', $href->textContent);
		}
	}

	public function testViewingTsumegoInMoreSets()
	{
		$context = new ContextPreparator(
			['tsumego' => [
				'sets' => [
					['name' => 'tsumego set 1', 'num' => '666'],
					['name' => 'tsumego set 2', 'num' => '777']]]]);
		$tsumegoID = $context->tsumego['id'];
		$this->testAction('tsumegos/play/' . $tsumegoID, ['return' => 'view']);

		// The first one was selected into the title
		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 1', $href->textContent);
		$this->assertTextContains('666', $href->textContent);

		$duplicateTable = $dom->querySelector('.duplicateTable');
		$links = $duplicateTable->getElementsByTagName('a');
		$this->assertSame(count($links), 2);
		$this->assertTextContains('/' . $context->tsumego['set-connections'][0]['id'], $links[0]->getAttribute('href'));
		$this->assertTextContains('tsumego set 1', $links[0]->textContent);
		$this->assertTextContains('666', $links[0]->textContent);
		$this->assertTextContains('/' . $context->tsumego['set-connections'][1]['id'], $links[1]->getAttribute('href'));
		$this->assertTextContains('tsumego set 2', $links[1]->textContent);
		$this->assertTextContains('777', $links[1]->textContent);
	}

	public function testViewingTsumegoInMoreSetsAndSpecifyingWhichOneIsTheMainOne()
	{
		$context = new ContextPreparator(
			['tsumego' => [
				'sets' => [
					['name' => 'tsumego set 1', 'num' => '666'],
					['name' => 'tsumego set 2', 'num' => '777']]]]);

		$this->testAction('tsumegos/play/' . $context->tsumego['id'] . '?sid=' . $context->tsumego['sets'][1]['id'], ['return' => 'view']);

		// The second one was selected by the sid parameter
		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 2', $href->textContent);
		$this->assertTextContains('777', $href->textContent);

		// all of them are listed in duplicite locations
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("666", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
		$this->assertTextContains("777", $this->view);
	}

	public function testViewingTsumegoWithoutAnySGF()
	{
		$context = new ContextPreparator(
			['tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '666']]]]);

		$this->testAction('tsumegos/play/' . $context->tsumego['id'], ['return' => 'view']);

		$dom = $this->getStringDom();
		$href = $dom->querySelector('#playTitleA');
		$this->assertTextContains('tsumego set 1', $href->textContent);
		$this->assertTextContains('666', $href->textContent);
	}

	// testing the same things as testViewingTsumegoInMoreSets, but using the web driver to do so
	// if this test fails, it probably means something is wrong with the web driver configuration
	public function testViewingTsumegoInMoreSetsUsingWebDriver()
	{
		$context = new ContextPreparator(
			['tsumego' => [
				'sets' => [
					['name' => 'tsumego set 1', 'num' => '666'],
					['name' => 'tsumego set 2', 'num' => '777']]]]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$href = $browser->driver->findElement(WebDriverBy::cssSelector('#playTitleA'));
		$this->assertTextContains('set 1', $href->getText());
		$this->assertTextContains('666', $href->getText());
	}

	public function testTheNextAndBackButtonLinsWhenBothPointToOtherTsumegos()
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '2']]],
			'other-tsumegos' => [
				['sets' => [['name' => 'tsumego set 1', 'num' => '1']]],
				['sets' => [['name' => 'tsumego set 1', 'num' => '3']]]]]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$backButton = $browser->driver->findElement(WebDriverBy::cssSelector('#besogo-back-button'));
		$this->assertSame($backButton->getAttribute('href'), '/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

		$nextButton = $browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'));
		$this->assertSame($nextButton->getAttribute('href'), '/' . $context->otherTsumegos[1]['set-connections'][0]['id']);
	}

	public function testShowFullHearts()
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '2']]]]);
		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$fullHearts = $browser->getCssSelect('img[title="Heart"]');
		$emptyHearts = $browser->getCssSelect('img[title="Empty Heart"]');
		$this->assertCount(0, $emptyHearts);
		$this->assertCount(Util::getHealthBasedOnLevel(Auth::getUser()['level']), $fullHearts);
	}

	public function testShowFullPartialHearts()
	{
		$context = new ContextPreparator([
			'user' => ['damage' => '1'],
			'tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '2']]]]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$fullHearts = $browser->getCssSelect('img[title="Heart"]');
		$emptyHearts = $browser->getCssSelect('img[title="Empty Heart"]');
		$this->assertCount(1, $emptyHearts);
		$this->assertCount(Util::getHealthBasedOnLevel(Auth::getUser()['level']) - 1, $fullHearts);
	}

	public function testShowHeartsWithDamageHigherThanHealth()
	{
		$context = new ContextPreparator([
			'user' => ['damage' => '10000'],
			'tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => '2']]]]);

		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$fullHearts = $browser->getCssSelect('img[title="Heart"]');
		$emptyHearts = $browser->getCssSelect('img[title="Empty Heart"]');
		$this->assertCount(Util::getHealthBasedOnLevel(Auth::getUser()['level']), $emptyHearts);
		$this->assertCount(0, $fullHearts);
	}

	public function testCommentCoordinatesHaveHoverSpans()
	{
		// Create a tsumego with a comment containing coordinates
		$context = new ContextPreparator([
			'user' => ['admin' => true], // Admin so comments are visible
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => '1']]],
		]);

		// Add a comment with coordinates
		$comment = ClassRegistry::init('TsumegoComment');
		$comment->save([
			'tsumego_id' => $context->tsumego['id'],
			'user_id' => $context->user['id'],
			'message' => 'Try playing at R19 or S18, they both work.',
		]);

		$this->testAction('tsumegos/play/' . $context->tsumego['id'], ['return' => 'view']);

		// Check that coordinate spans exist in the HTML
		$this->assertTextContains('go-coord', $this->view);
		$this->assertTextContains('onmouseover="ccIn', $this->view);

		// Check the JavaScript functions are generated
		$this->assertTextContains('function ccIn', $this->view);
		$this->assertTextContains('showCoordPopup', $this->view);
	}

	public function testFailingWithLastHeartLocksBoardAndShowsTryAgainTomorrow()
	{
		// Create a tsumego with a comment containing coordinates
		$context = new ContextPreparator([
			'user' => ['premium' => true, 'health' => 1], // Admin so comments are visible
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])', 'sets' => [['name' => 'test set', 'num' => '1']]],
		]);
		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$this->assertTextContains("Try again tomorrow", $browser->driver->getPageSource());
		$this->assertSame(true, $browser->driver->executeScript("return window.tryAgainTomorrow;"));
		$this->assertSame(1, $browser->driver->executeScript("return window.boardLockValue;"));
		$this->checkPlayNavigationButtons($browser, 1, $context, function ($index) { return 0; }, function ($index) { return 1;}, 0, 'F');
	}

	public function testSolveByClicking()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])', 'sets' => [['name' => 'test set', 'num' => '1']]],
		]);
		$browser = Browser::instance();
		$browser->get($context->tsumego['set-connections'][0]['id']);
		$browser->clickBoard(1, 1);
		usleep(500 * 1000);
		$this->assertSame(false, $browser->driver->executeScript('return window.problemSolved;'));
		$browser->clickBoard(2, 1);
		usleep(200 * 1000);
		$this->assertSame(true, $browser->driver->executeScript('return window.problemSolved;'));
	}

	public function testResetAddsFailWhenSomethingWasPlayed()
	{
		foreach ([true, false] as $playBeforeReset) {
			$context = new ContextPreparator([
				'user' => ['rating' => 1000, 'mode' => Constants::$LEVEL_MODE],
				'tsumego' => [
					'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])',
					'rating' => 1000,
					'sets' => [['name' => 'test set', 'num' => '1']]]]);
			$browser = Browser::instance();
			$browser->setCookie('showInAccountWidget', 'rating');
			$browser->get($context->tsumego['set-connections'][0]['id']);

			if ($playBeforeReset)
			{
				// click one move
				$browser->clickBoard(1, 1);
				usleep(500 * 1000);
				$this->assertSame(false, $browser->driver->executeScript('return window.problemSolved;'));
			}

			// reset without the result being shown
			$browser->clickId('besogo-reset-button');

			// check that rating on account widget was immediatelly updated
			$expectedRatingChange = $playBeforeReset ? Rating::calculateRatingChange(1000, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER) : 0;
			$browser->driver->executeScript("window.scrollTo(0, 0);");
			$browser->hover($browser->find('#account-bar-xp'));
			$this->assertSame(strval(round(1000 + $expectedRatingChange)), $browser->find('#account-bar-xp')->getText());

			// changes are applied after refresh
			$browser->get($context->tsumego['set-connections'][0]['id']);
			$this->assertSame($playBeforeReset ? 1 : 0, $context->reloadUser()['damage']);
			$this->assertLessThan(0.1, abs($context->reloadUser()['rating'] - (1000 + $expectedRatingChange)));
		}
	}
}

<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

App::uses('HeroPowers', 'Utility');

class HeroPowersTest extends TestCaseWithAuth
{
	public function testRefinementGoldenTsumego()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['premium' => 1, 'rating' => Rating::getRankMiddleRatingFromReadableRank('10k')],
			'tsumegos' => [
				['set_order' => 1, 'rating' => Rating::getRankMiddleRatingFromReadableRank('12k')],
				['set_order' => 2, 'rating' => Rating::getRankMiddleRatingFromReadableRank('10k')],
				['set_order' => 3, 'rating' => Rating::getRankMiddleRatingFromReadableRank('8k')]]]);
		$context->unlockAchievementsWithoutEffect();

		$goldenTsumego = $context->tsumegos[1];
		$originalTsumegoXPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($goldenTsumego['id'])['Tsumego'], Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER);

		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$browser->clickId('refinement');

		// the tsumego with index '1' was selected, as it is the one with my rank
		$this->assertSame(Util::getMyAddress() . '/' . $goldenTsumego['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $goldenTsumego['id'],
			'user_id' => Auth::getUserID()]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'G');
		$this->assertSame($context->reloadUser()['used_refinement'], 1); // the power is used up

		// the reported xp is normal golden
		$this->checkNavigationButtonsBeforeAndAfterSolving($browser, 3, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 1, 'G');
		$browser->get('sets');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $goldenTsumego['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'S');
		$this->assertSame($context->XPGained(), $originalTsumegoXPValue);
	}

	public function testRefinementGoldenTsumegoSelectsRandomProblemWhenCloseToRatingDoesntExist()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['premium' => 1, 'rating' => Rating::getRankMiddleRatingFromReadableRank('9d')],
			'tsumegos' => [
				['set_order' => 1, 'rating' => Rating::getRankMiddleRatingFromReadableRank('20k')]]]);
		$context->unlockAchievementsWithoutEffect();

		$originalTsumegoXPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->tsumegos[0]['id'])['Tsumego'], Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER);

		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		// the reported xp is normal
		$browser->clickId('refinement');

		$goldenTsumego = $context->tsumegos[0];

		// the tsumego with index '1' was selected, as it is the one with my rank
		$this->assertSame(Util::getMyAddress() . '/' . $goldenTsumego['set-connections'][0]['id'], $browser->driver->getCurrentURL());
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $goldenTsumego['id'],
			'user_id' => Auth::getUserID()]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'G');
		$this->assertSame($context->reloadUser()['used_refinement'], 1); // the power is used up
	}

	public function testGoldenTsumegoFail()
	{
		$context = new ContextPreparator(['user' => ['premium' => 1], 'tsumego' => ['status' => 'G', 'set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		// Wait for displayResult to be available
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return typeof displayResult === "function";');
		});

		// Grabbing this just to detect page reload later
		$oldBodyElement = $browser->driver->findElement(WebDriverBy::cssSelector('body'));
		$browser->driver->executeScript("displayResult('F')"); // fail the problem
		// the display result should refresh the page
		$browser->driver->wait(10)->until(WebDriverExpectedCondition::stalenessOf($oldBodyElement));
		$this->assertSame(Util::getMyAddress() . '/' . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL());

		// Wait for navigation buttons to load
		$browser->driver->wait(10)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('div.tsumegoNavi2 li')));

		$this->checkPlayNavigationButtons($browser, 1, $context, function ($index) { return $index; }, function ($index) { return $index + 1; }, 0, 'V');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'V');
	}

	public function testSprint()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 1]);
		HeroPowers::changeUserSoSprintCanBeUsed();
		$context->unlockAchievementsWithoutEffect();
		$context->XPGained(); // to reset the lastXPgained for the final test
		$tsumego = ClassRegistry::init("Tsumego")->findById($context->tsumegos[0]['id'])['Tsumego'];
		$originalTsumegoXPValue = TsumegoUtil::getXpValue($tsumego);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('sprint');
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 5, 100);
		$wait->until(function () use ($browser) {
			return $browser->driver->executeScript('return window.xpStatus.isSprintActive();');
		});
		$browser->driver->executeScript("displayResult('S')"); // solve the problem
		$browser->get('sets');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame($status['TsumegoStatus']['status'], 'S');
		$this->assertSame($context->XPGained(), Constants::$SPRINT_MULTIPLIER * $originalTsumegoXPValue);
		$this->assertSame($context->user['used_sprint'], 1);
	}

	public function testSprintPersistsToNextTsumego()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumegos' => [1, 2]]);
		$originalTsumego0XPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->tsumegos[0]['id'])['Tsumego']);
		$originalTsumego1XPValue = TsumegoUtil::getXpValue(ClassRegistry::init("Tsumego")->findById($context->tsumegos[1]['id'])['Tsumego']);
		HeroPowers::changeUserSoSprintCanBeUsed();
		$context->unlockAchievementsWithoutEffect();
		$context->XPGained(); // to reset the lastXPgained for the final test
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('sprint');
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 5, 100);
		$wait->until(function () use ($browser) {
			return $browser->driver->executeScript('return window.xpStatus.isSprintActive();');
		});
		$browser->driver->executeScript("displayResult('S')"); // solve the problem

		// clicking next after solving, sprint is still visible:
		$browser->clickId('besogo-next-button');
		$this->assertSame($context->XPGained(), Constants::$SPRINT_MULTIPLIER * $originalTsumego0XPValue);
		$this->assertTextContains('Sprint', $browser->driver->findElement(WebDriverBy::cssSelector('#xpDisplay'))->getText());
		$browser->playWithResult('S'); // solve the problem

		// clicking next after solving again, sprint is applied on xp still
		$browser->clickId('besogo-next-button');
		$this->assertSame($context->XPGained(), Constants::$SPRINT_MULTIPLIER * $originalTsumego1XPValue);
	}

	private function checkPowerIsInactive($browser, $name)
	{
		$element = $browser->driver->findElement(WebDriverBy::cssSelector('#' . $name));
		$this->assertNull($browser->driver->executeScript("return document.getElementById('$name').onclick;"));
		$this->assertNull($browser->driver->executeScript("return document.getElementById('$name').onmouseover;"));
		$this->assertNull($browser->driver->executeScript("return document.getElementById('$name').onmouseout;"));
		$this->assertSame($element->getCssValue('cursor'), 'auto');
	}

	private function checkPowerIsActive($browser, $name)
	{
		$element = $browser->driver->findElement(WebDriverBy::cssSelector('#' . $name));
		$this->assertNotNull($browser->driver->executeScript("return document.getElementById('$name').onclick;"));
		$this->assertNotNull($browser->driver->executeScript("return document.getElementById('$name').onmouseover;"));
		$this->assertNotNull($browser->driver->executeScript("return document.getElementById('$name').onmouseout;"));
		$this->assertSame($element->getCssValue('cursor'), 'pointer');
	}

	public function testSprintLinkNotPresentWhenSprintIsUsedUp()
	{
		$context = new ContextPreparator(['user' => ['used_sprint' => 1], 'tsumego' => 1]);
		$browser = Browser::instance();
		HeroPowers::changeUserSoSprintCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->checkPowerIsInactive($browser, 'sprint');
	}

	public function testUseRevelation()
	{
		foreach (['logged-off', 'used-up', 'normal'] as $testCase)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator(['tsumego' => 1]);
			$context->unlockAchievementsWithoutEffect();
			$context->XPGained(); // to reload the current xp to be able to tell the gained later

			if ($testCase == 'logged-off')
			{
				// When logged off, hero power buttons don't render at all
				$browser->logoff();
				$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
				$this->assertFalse($browser->idExists('revelation'));
				Auth::init();
				continue;
			}

			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			$this->assertSame(1, $browser->driver->executeScript("return window.revelationUseCount;"));

			if ($testCase == 'used-up')
			{
				ClassRegistry::init('User')->updateAll(
					['used_revelation' => 10],
					['id' => $context->user['id']]
				);

				$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

				$this->checkPowerIsInactive($browser, 'revelation');
				continue;
			}

			$this->checkPowerIsActive($browser, 'revelation');

			$browser->clickId('revelation');
			$browser->driver->wait(10, 50)->until(function () use ($browser) {
				return $browser->driver->executeScript("return window.revelationUseCount;") == 0;
			});
			$this->checkPowerIsInactive($browser, 'revelation');
			$expectedUsedCount = $testCase == 'used-up' ? 10 : ($testCase == 'not-available' ? 0 : 1);
			$this->assertSame($context->reloadUser()['used_revelation'], $expectedUsedCount);
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			if ($testCase != 'not-available')
				$this->checkPowerIsInactive($browser, 'revelation');
			$this->assertSame($context->reloadUser()['used_revelation'], $expectedUsedCount);

			// status was changed to 'S' (solved)
			$tsumegoStatuses = ClassRegistry::init('TsumegoStatus')->find('all');
			$this->assertSame(1, count($tsumegoStatuses));
			$this->assertSame($testCase == 'normal' ? 'S' : 'V', $tsumegoStatuses[0]['TsumegoStatus']['status']);

			$this->assertSame($context->xpgained(), 0); // no xp was gained
			$this->assertSame($context->reloadUser()['rating'], ContextPreparator::$DEFAULT_USER_RATING); // xp wasn't changed
			$this->assertSame(0, count(ClassRegistry::init('TsumegoAttempt')->find('all'))); // no attempt was recorded
		}
	}

	public function testUseIntuition()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 1]);
		HeroPowers::changeUserSoIntuitionCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertFalse($browser->driver->executeScript("return window.besogo.intuitionActive;"));
		$this->checkPowerIsActive($browser, 'intuition');
		$browser->clickId('intuition');
		$browser->driver->wait(10, 500)->until(function () use ($browser) { return $browser->driver->executeScript("return window.besogo.intuitionActive;"); });
		$this->checkPowerIsInactive($browser, 'intuition');
		$this->assertSame($context->reloadUser()['used_intuition'], 1);
	}

	public function testIntuitionShowsCorrectSolution()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]RU[Japanese]SZ[19]AW[jb][cc][dc][kc][ed][gd][jd][fe][ie][df][gf]AB[bc][fc][gc][hc][ic][cd][fd][be][cg](;B[ee];W[de](;B[dd];W[ec];B[cb];W[eb])(;B[cb];W[db];B[dd];W[ec];B[da];W[ef];B[eb]C[+]))(;B[cb];W[db];B[ee];W[bb];B[ca];W[ac];B[bd];W[ba](;B[de];W[ab])(;B[ab];W[de]))(;B[dd];W[ec])(;B[ec];W[dd]))']]);
		HeroPowers::changeUserSoIntuitionCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->checkPowerIsActive($browser, 'intuition');
		$browser->clickId('intuition');
		$browser->driver->wait(10, 500)->until(function () use ($browser) { return $browser->driver->executeScript("return window.besogo.intuitionActive;"); });
		$circles = $browser->driver->executeScript("
			return Array.from(document.querySelectorAll('#nextMoveGroup circle'))
				.map(c => ({
					cx: c.getAttribute('cx'),
					cy: c.getAttribute('cy'),
					r: c.getAttribute('r'),
					fill: c.getAttribute('fill')
				}));");
		$this->assertCount(4, $circles);
		$this->assertCount(1, array_filter($circles, fn($c) => $c['fill'] === 'green'));
	}

	public function testIntuitionPowerIsInactiveWhenIntuitionIsUsedUp()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['used_intuition' => 1], 'tsumego' => 1]);
		HeroPowers::changeUserSoIntuitionCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->checkPowerIsInactive($browser, 'intuition');
	}

	public function testRejuvenationRestoresHealthIntuitionAndFailedTsumegos()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['used_intuition' => 1, 'damage' => 7],
			'tsumegos' => [
				['set_order' => 1, 'status' => 'F'],
				['set_order' => 2, 'status' => 'X']]]);
		HeroPowers::changeUserSoRejuvenationCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertSame($context->user['damage'], 7);
		$this->checkPowerIsActive($browser, 'rejuvenation');
		$this->checkPowerIsInactive($browser, 'intuition');
		$browser->clickId('rejuvenation');
		$browser->driver->wait(10, 500)->until(function () use ($context) { return $context->reloadUser()['damage'] == 0; });
		$this->assertSame($context->user['used_rejuvenation'], 1);
		$this->assertSame($context->user['used_intuition'], 0);
		// Wait for UI to update after AJAX response
		$browser->driver->wait(10, 200)->until(function () use ($browser) {
			return $browser->find('#rejuvenation')->getCssValue('cursor') === 'auto';
		});
		$this->checkPowerIsInactive($browser, 'rejuvenation');
		$this->checkPowerIsActive($browser, 'intuition');
		$status1 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame($status1['TsumegoStatus']['status'], 'V');
		$status2 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[1]['id']]]);
		$this->assertSame($status2['TsumegoStatus']['status'], 'W');
	}

	/**
	 * Full rejuvenation flow: start with 1 heart, lose it to lock the board,
	 * rejuvenate to restore all hearts, reset and fail again to verify hearts can still be lost.
	 */
	public function testRejuvenationClearsTryAgainTomorrowMessage()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['level' => HeroPowers::$REJUVENATION_MINIMUM_LEVEL, 'health' => 1],
			'tsumegos' => [
				['set_order' => 1]]]);
		HeroPowers::changeUserSoRejuvenationCanBeUsed();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->driver->wait(10, 500)->until(function ($driver) {
			return $driver->executeScript('return typeof displayResult === "function";');
		});

		$maxHealth = Util::getHealthBasedOnLevel(Auth::getUser()['level']);
		$this->assertSame(1, $browser->driver->executeScript('return remainingHealth - misplays;'));
		$this->assertSame(0, $browser->driver->executeScript('return boardLockValue;'));

		// Lose the last heart to trigger "Try again tomorrow"
		$browser->driver->executeScript("displayResult('F')");
		$browser->driver->wait(10, 200)->until(function ($driver) {
			return $driver->executeScript('return window.tryAgainTomorrow === true;');
		});

		$statusText = $browser->driver->findElement(WebDriverBy::id('status'))->getText();
		$this->assertStringContainsString('Try again tomorrow', $statusText);
		$this->assertSame(1, $browser->driver->executeScript('return boardLockValue;'));
		for ($i = 0; $i < $maxHealth; $i++)
		{
			$heartSrc = $browser->driver->findElement(WebDriverBy::id('heart' . $i))->getAttribute('src');
			$this->assertStringContainsString('heart2small', $heartSrc, "heart$i should be empty after losing the last heart");
		}

		// Rejuvenate to restore all hearts
		$this->checkPowerIsActive($browser, 'rejuvenation');
		$browser->clickId('rejuvenation');
		$browser->driver->wait(10, 500)->until(function () use ($context) { return $context->reloadUser()['damage'] == 0; });
		$browser->driver->wait(10, 200)->until(function () use ($browser) {
			return $browser->find('#rejuvenation')->getCssValue('cursor') === 'auto';
		});

		$statusText = $browser->driver->findElement(WebDriverBy::id('status'))->getText();
		$this->assertStringNotContainsString('Try again tomorrow', $statusText);
		$this->assertSame(0, $browser->driver->executeScript('return boardLockValue;'));
		$this->assertFalse($browser->driver->executeScript('return tryAgainTomorrow;'));
		$this->assertSame($maxHealth, $browser->driver->executeScript('return remainingHealth;'));
		for ($i = 0; $i < $maxHealth; $i++)
		{
			$heartSrc = $browser->driver->findElement(WebDriverBy::id('heart' . $i))->getAttribute('src');
			$this->assertStringContainsString('heart1small', $heartSrc, "heart$i should be a full heart after rejuvenation");
		}

		// Press Reset to clear freePlayMode, then verify a heart can be lost
		$browser->clickId('besogo-reset-button');

		$browser->driver->executeScript("displayResult('F')");
		$browser->driver->wait(10, 200)->until(function ($driver) {
			return $driver->executeScript('return window.misplays > 0;');
		});
		$lastHeartSrc = $browser->driver->findElement(WebDriverBy::id('heart' . ($maxHealth - 1)))->getAttribute('src');
		$this->assertStringContainsString('heart2small', $lastHeartSrc, "last heart should be lost after failing post-rejuvenation");
	}

	public function testRevelationAllowsReviewAfterRunningOutOfHearts()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'tsumego' => ['status' => 'F', 'set_order' => 1]]);
		$context->unlockAchievementsWithoutEffect();

		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];
		$browser->get('/' . $setConnectionId);

		// Wait for the page to fully load
		$browser->driver->wait(10, 500)->until(function ($driver) {
			return $driver->executeScript('return typeof displayResult === "function";');
		});

		// Verify failed/locked state: review button is inactive
		$this->assertTrue($browser->idExists('besogo-review-button-inactive'));
		$this->assertFalse($browser->idExists('besogo-review-button'));
		$this->assertSame(1, $browser->driver->executeScript('return window.boardLockValue;'));

		// Use Revelation to solve the problem (AJAX, no page refresh)
		$browser->clickId('revelation');
		$browser->driver->wait(10, 50)->until(function () use ($browser) {
			return $browser->driver->executeScript("return window.revelationUseCount;") == 0;
		});

		// After Revelation: review button must be active (user-visible)
		$this->assertFalse($browser->idExists('besogo-review-button-inactive'));
		$this->assertTrue($browser->idExists('besogo-review-button'));

		// Clicking review unlocks the board so user can explore moves
		$browser->clickId('besogo-review-button');
		$this->assertSame(0, $browser->driver->executeScript('return window.boardLockValue;'));

		// Board click stays on the page (doesn't navigate to next puzzle)
		$currentUrl = $browser->driver->getCurrentURL();
		$browser->clickCssSelect('.besogo-board');
		$this->assertSame($currentUrl, $browser->driver->getCurrentURL());

		// Refresh and verify state persists from the database
		$browser->get('/' . $setConnectionId);
		$browser->driver->wait(10, 500)->until(function ($driver) {
			return $driver->executeScript('return typeof displayResult === "function";');
		});

		// After refresh: review is still available, board is not locked
		$this->assertTrue($browser->idExists('besogo-review-button'));
		$browser->clickId('besogo-review-button');
		$this->assertSame(0, $browser->driver->executeScript('return window.boardLockValue;'));
	}
}

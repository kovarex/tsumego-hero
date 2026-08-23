<?php

use Facebook\WebDriver\WebDriverBy;

App::uses('Util', 'Utility');

/**
 * Browser tests for the play page's interaction with the result AJAX endpoint:
 * result banners ("Correct!"/"Incorrect"/"locked until"), board locking, the
 * reset button, hearts/potion UI and the XP display.
 *
 * The server-side result processing itself is covered by
 * PlayResultProcessorComponentTest (pure AJAX POSTs).
 */
class PlayResultBrowserTest extends TestCaseWithAuth
{
	private function playUrl(ContextPreparator $context): string
	{
		return '/' . $context->tsumegos[0]['set-connections'][0]['id'];
	}

	public function testPostSolveMistakesAreNotPenalized(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalRating = $context->user['rating'];
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Prove competence
		$browser->playWithResult('S');
		$this->assertTrue(
			$browser->driver->executeScript('return problemSolved;'));

		// Post-solve exploration (reset + mistake) - must be harmless
		$browser->clickId('besogo-reset-button');
		$browser->playWithResult('F');

		$this->assertGreaterThan($originalRating,
			(float) $context->reloadUser()['rating'],
			'Rating must increase from solve; post-solve errors must not drag it down');

		$this->assertSame($originalDamage,
			(int) $context->user['damage'],
			'Hearts must not be lost for mistakes made after solving');

		$attempts = ClassRegistry::init('TsumegoAttempt')->find('all', [
			'conditions' => [
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => $context->user['id'],
			],
		]);
		$this->assertCount(1, $attempts,
			'Must produce exactly one attempt record');
		$this->assertTrue($attempts[0]['TsumegoAttempt']['solved'],
			'Attempt must be marked solved');
		$this->assertSame(0, (int) $attempts[0]['TsumegoAttempt']['misplays'],
			'Post-solve mistakes must not appear in attempt history');
	}

	public function testResetAfterSolveDoesntCauseDamage(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Solve the puzzle
		$browser->driver->executeScript("displayResult('S');");
		$browser->waitForSubmitResult();

		// Reset the puzzle
		$browser->clickId('besogo-reset-button');

		// Verify no damage - puzzle was already solved, noXP guard protects
		$this->assertSame($originalDamage, (int) $context->reloadUser()['damage'],
			'Resetting after solve should not cause damage');
	}

	public function testResetAfterFailDoesntCauseDuplicateDamage(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Fail once
		$browser->playWithResult('F');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'First fail should cause damage');

		// Reset - should NOT cause additional damage
		$browser->clickId('besogo-reset-button');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Reset after fail should not cause duplicate damage');
	}

	public function testResetAtStartDoesntCauseDamage(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Reset immediately without making any moves
		$browser->clickId('besogo-reset-button');

		// Verify no damage
		$this->assertSame($originalDamage, (int) $context->reloadUser()['damage'],
			'Resetting at start should not cause damage');
	}

	public function testMultipleFailsThenResetThenSolve(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Fail twice (second fail blocked by failAlreadyReported)
		$browser->playWithResult('F');
		$browser->playWithResult('F');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Only first fail should cause damage');

		// Reset - should not cause additional damage
		$browser->clickId('besogo-reset-button');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Reset should not cause additional damage');

		// Solve
		$browser->playWithResult('S');

		// Verify final state
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Damage should only be from the first fail');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame('S', $status['TsumegoStatus']['status'],
			'Status should be solved');
	}

	public function testSolveShowsCorrectAndUpdatesXP(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('S');

		$this->assertStringContainsString('Correct!', $browser->driver->findElement(WebDriverBy::id('status'))->getText());
		$this->assertTrue($browser->driver->executeScript('return window.problemSolved;'));
		$this->assertTrue($browser->driver->executeScript('return window.noXP;'));
		$this->assertSame(1, $browser->driver->executeScript('return window.boardLockValue;'));

		// Account widget should reflect server state
		$this->assertGreaterThan(0, $browser->driver->executeScript('return window.accountWidget.xp;'));
		$this->assertNotNull($browser->driver->executeScript('return window.accountWidget.rating;'));
	}

	public function testFailShowsIncorrectAndLosesHeart(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('F');

		$this->assertStringContainsString('Incorrect', $browser->driver->findElement(WebDriverBy::id('status'))->getText());
		$this->assertTrue($browser->driver->executeScript('return window.failAlreadyReported;'));
		$this->assertSame(1, $browser->driver->executeScript('return window.misplays;'));
		$this->assertSame(1, $context->reloadUser()['damage']);
	}

	public function testRunOutOfHeartsShowsLockedMessage(): void
	{
		$context = new ContextPreparator(['tsumego' => 1, 'user' => ['health' => 0]]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('F');

		$this->assertStringContainsString('locked until', $browser->driver->findElement(WebDriverBy::id('status'))->getText());
		$this->assertTrue($browser->driver->executeScript('return window.tryAgainTomorrow;'));
		$this->assertSame(1, $browser->driver->executeScript('return window.boardLockValue;'));
	}

	public function testResetAfterFailDoesNotCostExtraHeart(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('F');
		$this->assertSame(1, $context->reloadUser()['damage']);

		$browser->clickId('besogo-reset-button');
		$this->assertSame(1, $context->reloadUser()['damage'],
			'Reset should not cause additional damage');
		$this->assertFalse($browser->driver->executeScript('return window.failAlreadyReported;'));
	}

	public function testResetAfterSolveDoesNotCostHeart(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('S');
		$browser->clickId('besogo-reset-button');

		$this->assertSame(0, $context->reloadUser()['damage'],
			'Resetting after solve should not cause damage');
	}

	public function testSolvedPuzzleCannotBeFailedOrReSolved(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('S');
		$originalDamage = (int) $context->reloadUser()['damage'];
		$originalXp = $context->reloadUser()['xp'];

		// Try to fail - should be harmless
		$browser->playWithResult('F');
		$this->assertSame($originalDamage, (int) $context->reloadUser()['damage'],
			'Fail on solved puzzle should not cause damage');

		// Try to solve again - should be harmless
		$browser->playWithResult('S');
		$this->assertSame($originalXp, $context->reloadUser()['xp'],
			'Re-solving should not grant more XP');
	}

	public function testSolveUpdatesXPDisplay(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('S');

		// XP display should show solved state
		$this->assertTrue($browser->driver->executeScript('return window.xpStatus !== undefined;'));
		$xpText = $browser->driver->findElement(WebDriverBy::id('xpDisplayText'))->getText();
		$this->assertNotEmpty($xpText, 'XP display should show XP gained');
	}

	public function testPotionTriggerRestoresHeartsAndShowsAlert(): void
	{
		$maxHealth = Util::getHealthBasedOnLevel(50);
		$context = new ContextPreparator([
			'user' => ['level' => 50, 'damage' => $maxHealth + 200],
			'tsumego' => ['set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('F');

		// Potion should trigger: hearts restored, alert visible
		$this->assertSame(0, $browser->driver->executeScript('return window.misplays;'),
			'Potion should reset misplays to 0');
		$this->assertSame($maxHealth, $browser->driver->executeScript('return window.remainingHealth;'),
			'Potion should restore remainingHealth to max');
		$this->assertTrue($browser->driver->executeScript(
			'return document.getElementById("potionAlerts").style.display !== "none";'),
			'Potion alert should be visible');
	}
}

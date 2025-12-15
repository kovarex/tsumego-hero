<?php

use Facebook\WebDriver\WebDriverBy;

App::uses('Constants', 'Utility');

class PlayResultProcessorComponentTest extends TestCaseWithAuth
{
	private $PAGES = ['sets', 'tsumego'];

	private static function getUrlFromPage(string $page, $context): string
	{
		if ($page === 'sets')
			return '/sets/index';
		if ($page === 'tsumego')
			return '/' . $context->tsumego['set-connections'][0]['id'];
		throw new Exception("Unknown page: " . $page);
	}

	private function performVisit(ContextPreparator &$context, $page): void
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init(); // Re-init to recognize the user
		$_COOKIE['previousTsumegoID'] = $context->tsumego['id'];
		$this->testAction(self::getUrlFromPage($page, $context));
		$context->checkNewTsumegoStatusCoreValues($this);
	}

	private function performSolve(ContextPreparator &$context, $page): void
	{
		$_COOKIE['mode'] = '1';
		$_COOKIE['solvedCheck'] = Util::encrypt($context->tsumego['id'] . '-' . time());
		$_COOKIE['secondsCheck'] = $context->tsumego['id'] * 7900 * 0.01;
		$this->performVisit($context, $page);
		$this->assertEmpty($_COOKIE['score']); // should be processed and cleared
	}

	private function performMisplay(ContextPreparator &$context, $page): void
	{
		$_COOKIE['misplays'] = '1';
		$_COOKIE['secondsCheck'] = $context->tsumego['id'] * 7900 * 0.01;
		$this->performVisit($context, $page);
		$this->assertEmpty($_COOKIE['misplays']); // should be processed and cleared
	}

	public function testVisitFromEmpty(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performVisit($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'V');
		}
	}

	public function testSolveFromEmpty(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performSolve($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'S');
		}
	}

	public function testSolveFromEmptyByWebDriver(): void
	{
		$browser = Browser::instance();
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => ['mode' => Constants::$LEVEL_MODE],
				'tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
			$browser->playWithResult('S');
			$browser->get(self::getUrlFromPage($page, $context));
			$statuses = ClassRegistry::init('TsumegoStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumego['id']]]);
			$this->assertSame(count($statuses), 1);
			$this->assertSame($statuses[0]['TsumegoStatus']['status'], 'S');
		}
	}

	public function testVisitFromSolved(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['status' => 'S', 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performVisit($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'S');
		}
	}

	public function testHalfXpStatusToDoubleSolved(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = (new ContextPreparator(['tsumego' => ['status' => 'W', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performSolve($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'C');
		}
	}

	public function testNoSolveFromFailed(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = (new ContextPreparator(['tsumego' => ['status' => 'F', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performSolve($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'F');
		}
	}

	public function testFailFromFailed(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = (new ContextPreparator(['tsumego' => ['status' => 'F', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'F');
		}
	}

	public function testFailFromSolved(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = (new ContextPreparator(['tsumego' => ['status' => 'S', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'S'); // shouldn't be affected
		}
	}

	public function testFailFromDoubleSolved(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = (new ContextPreparator(['tsumego' => ['status' => 'C', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'C'); // shouldn't be affected
		}
	}

	public function testSolvingAddsRatingOfPlayerAndDecreasesRatingOfTsumego(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => [
					'rating' => 1000,
					'mode' => Constants::$RATING_MODE],
				'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$originalRating = $context->user['rating'];
			$this->performSolve($context, $page);
			// user rating is increased
			$this->assertGreaterThan($originalRating, $context->reloadUser()['rating']);
			$this->assertWithinMargin($originalRating, $context->user['rating'], 100); // shouldn't move more than 100 points
			$expectedChange = Rating::calculateRatingChange(1000, 1000, 1, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
			$this->assertLessThan(0.1, abs($originalRating + $expectedChange - $context->reloadUser()['rating']));
			// tsumego rating is decreased
			$this->assertLessThan(1000, ClassRegistry::init('Tsumego')->findById($context->tsumego['id'])['Tsumego']['rating']);
		}
	}

	public function testSolvingCantDecreaseTsumegoRatingUnderItsMinimum(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => [
					'rating' => 1000,
					'mode' => Constants::$RATING_MODE],
				'tsumego' => ['rating' => 1000, 'minimum_rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$originalRating = $context->user['rating'];
			$this->performSolve($context, $page);

			//user rating is increased
			$this->assertGreaterThan($originalRating, $context->reloadUser()['rating']);
			$this->assertWithinMargin($originalRating, $context->user['rating'], 100); // shouldn't move more than 100 points

			// tsumego can't get any lower
			$this->assertSame(1000.0, ClassRegistry::init('Tsumego')->findById($context->tsumego['id'])['Tsumego']['rating']);
		}
	}

	public function testFailingDropsRatingOfPlayerAndIncreasesRatingOfTsumego(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => [
					'rating' => 1000,
					'mode' => Constants::$RATING_MODE],
				'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$originalRating = $context->user['rating'];
			$this->performMisplay($context, $page);
			// user rating is decreased
			$this->assertLessThan($originalRating, $context->reloadUser()['rating']);
			$this->assertWithinMargin($originalRating, $context->user['rating'], 100); // shouldn't move more than 100 points
			// tsumego rating is increased
			$this->assertGreaterThan(1000, ClassRegistry::init('Tsumego')->findById($context->tsumego['id'])['Tsumego']['rating']);
		}
	}

	public function testFailingCantIncreaseTsumegoRatingOverItsMaximum(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => [
					'rating' => 1000,
					'mode' => Constants::$RATING_MODE],
				'tsumego' => ['rating' => 1000, 'maximum_rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$originalRating = $context->user['rating'];
			$this->performMisplay($context, $page);
			$this->assertLessThan($originalRating, $context->reloadUser()['rating']);

			// player still looses rating
			$this->assertWithinMargin($originalRating, $context->user['rating'], 100); // shouldn't move more than 100 points
			$this->assertGreaterThan(100, ClassRegistry::init('Tsumego')->findById($context->tsumego['id'])['Tsumego']['rating']);

			// but tsumego can't get higher anymore
			$this->assertSame(1000.0, ClassRegistry::init('Tsumego')->findById($context->tsumego['id'])['Tsumego']['rating']);
		}
	}

	public function testSolvingAddsXP(): void
	{
		foreach (['V', 'W'] as $status)
			foreach ($this->PAGES as $page)
			{
				$context = new ContextPreparator([
					'user' => [
						'rating' => 1000,
						'mode' => Constants::$RATING_MODE],
					'tsumego' => ['status' => $status, 'rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
				$this->performSolve($context, $page);
				$this->assertSame($context->XPGained(), intval(ceil(($status == 'W' ? Constants::$SECOND_SOLVE_XP_MULTIPLIER : 1) *  TsumegoUtil::getXpValue($context->tsumego))));
			}
	}

	public function testSolvingSolvedDoesntAddXP(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => [
					'rating' => 1000,
					'mode' => Constants::$RATING_MODE],
				'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]], 'status' => 'S']]);
			$this->performSolve($context, $page);
			$this->assertSame($context->XPGained(), 0);
		}
	}

	public function testSolvingDoubleSolvedDoesntAddXP(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => [
					'rating' => 1000,
					'mode' => Constants::$RATING_MODE],
				'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]], 'status' => 'C']]);
			$this->performSolve($context, $page);
			$this->assertSame($context->XPGained(), 0);
		}
	}

	public function testSolvingSolvedDoesntAddRating(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => [
					'rating' => 1000,
					'mode' => Constants::$RATING_MODE],
				'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]], 'status' => 'S']]);
			$this->performSolve($context, $page);
			$this->assertSame($context->reloadUser()['rating'], 1000.0);
		}
	}

	public function testSolvingDoubleSolvedDoesntAddRating(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => [
					'rating' => 1000,
					'mode' => Constants::$RATING_MODE],
				'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]], 'status' => 'C']]);
			$this->performSolve($context, $page);
			$this->assertSame($context->reloadUser()['rating'], 1000.0);
		}
	}

	public function testSolvingAddsNewTsumegoAttempt(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);

			$this->performSolve($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 0);
		}
	}

	public function testSolvingUpdatesExistingNotSolvedTsumegoAttempt(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => [
				'attempt' => ['solved' => false, 'misplays' => 66],
				'sets' => [['name' => 'set 1', 'num' => 1]]]]);

			$this->performSolve($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // the existing one should be updated
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 66);
		}
	}

	public function testSolvingDoesntUpdateExistingSolvedTsumegoAttempt(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => [
				'attempt' => ['solved' => true, 'misplays' => 66],
				'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performSolve($context, $page);
			$tsumegoAttempts = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($tsumegoAttempts), 2); // the solved one wasn't updated
			$this->assertSame($tsumegoAttempts[0]['TsumegoAttempt']['solved'], true);
			$this->assertSame($tsumegoAttempts[0]['TsumegoAttempt']['misplays'], 66);
			$this->assertSame($tsumegoAttempts[1]['TsumegoAttempt']['solved'], true);
			$this->assertSame($tsumegoAttempts[1]['TsumegoAttempt']['misplays'], 0);
		}
	}

	public function testFailingAddsNewTsumegoAttempt(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performMisplay($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], false);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 1);
		}
	}

	public function testFailingUpdatesExistingNotSolvedTsumegoAttempt(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => [
				'attempt' => ['solved' => false, 'misplays' => 66],
				'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performMisplay($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], false);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 67);
		}
	}

	public function testFailingDoesntUpdateExistingSolvedTsumegoAttempt(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['attempt' => ['solved' => true, 'misplays' => 66], 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performMisplay($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 2); // exactly one should be created
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 66);
			$this->assertSame($newTsumegoAttempt[1]['TsumegoAttempt']['solved'], false);
			$this->assertSame($newTsumegoAttempt[1]['TsumegoAttempt']['misplays'], 1);
		}
	}

	public function testFailAddsDamage(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$originalDamage = intval($context->user['damage']);
			$this->performMisplay($context, $page);
			$this->assertSame($originalDamage + 1, $context->reloadUser()['damage']);
		}
	}

	public function testFailAddsDamageUsingWebDriver(): void
	{
		$browser = Browser::instance();
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]],
				'user' => ['mode' => Constants::$LEVEL_MODE]]);
			$originalDamage = intval($context->user['damage']);

			$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
			$browser->playWithResult('F');
			$browser->get(self::getUrlFromPage($page, $context));
			$this->assertSame($originalDamage + 1, $context->reloadUser()['damage']);
		}
	}

	public function testProblemDoesntGetFailedWhenHeartsAreStillPresent(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]],
			'user' => ['mode' => Constants::$LEVEL_MODE]]);
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$context->checkNewTsumegoStatusCoreValues($this);
		$this->assertSame($context->resultTsumegoStatus['status'], 'V');
	}

	public function testProblemGetsFailedWhenHeartsAreGonePresent(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]],
			'user' => ['mode' => Constants::$LEVEL_MODE, 'damage' => Util::getHealthBasedOnLevel(1)]]);
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$context->checkNewTsumegoStatusCoreValues($this);
		$this->assertSame($context->resultTsumegoStatus['status'], 'F');
	}

	public function testSolvedIncreasedBySolvingNotSolved(): void
	{
		foreach (['N', 'S'] as $previousStatus)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator([
				'tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]], 'status' => $previousStatus],
				'user' => ['mode' => Constants::$LEVEL_MODE, 'solved' => 66]]);
			$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
			$browser->playWithResult('S');
			$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
			$context->checkNewTsumegoStatusCoreValues($this);
			$this->assertSame($context->reloadUser()['solved'], $previousStatus == 'S' ? 66 : 67);
		}
	}

	public function testFailingResetAndSolvingAppliesBothFailAndSolve(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'rating' => 1000,
				'mode' => Constants::$RATING_MODE],
			'tsumego' => ['rating' => 500, 'maximum_rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
		$originalRating = $context->user['rating'];
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$browser->clickId("besogo-reset-button");
		$browser->playWithResult('S');

		// reopen the page
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);

		// player has 500 more rating than the problem, so losss + win should lose rating
		$this->assertLessThan($originalRating, $context->reloadUser()['rating']);

		// tsumego has 500 less rating than user, so loss + win should move it up
		$this->assertGreaterThan(500, ClassRegistry::init('Tsumego')->findById($context->tsumego['id'])['Tsumego']['rating']);

		$this->assertSame($context->user['damage'], 1); // damage was applied
		$this->assertGreaterThan(0, $context->user['xp']); // xp was gained
	}

	public function testFailingTwiceResetAndSolvingAppliesBothFailAndSolve(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'rating' => 1000,
				'mode' => Constants::$RATING_MODE],
			'tsumego' => ['rating' => 1000, 'maximum_rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
		$originalRating = $context->user['rating'];
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$browser->clickId("besogo-reset-button");
		$browser->playWithResult('F');
		$browser->clickId("besogo-reset-button");
		$browser->playWithResult('S');

		// reopen the page
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);

		$expectedRatingChangeForOneLoss = Rating::calculateRatingChange(1000, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
		$ratingChange = $context->reloadUser()['rating'] - $originalRating;
		// two losses and one win with the same rating should more or less result in one loss
		$this->assertLessThan(5, abs($expectedRatingChangeForOneLoss - $ratingChange));

		$this->assertSame($context->user['damage'], 2); // damage was applied
		$this->assertGreaterThan(0, $context->user['xp']); // xp was gained
	}

	/**
	 * CRITICAL TEST: Simulates the user's bug report scenario.
	 * User fails a puzzle, then clicks htmx buttons (like issue filter),
	 * and rating should NOT change from the htmx request.
	 *
	 * Without the htmx fix, the htmx request would trigger checkPreviousPlay
	 * and potentially process the fail result at an unexpected time.
	 */
	public function testHtmxActionsAfterFailDoNotTriggerRatingDrop(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'rating' => 1500,
				'mode' => Constants::$RATING_MODE],
			'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
		$originalRating = $context->user['rating'];

		$browser = Browser::instance();

		// Load the puzzle page
		$browser->get('/' . $context->tsumego['set-connections'][0]['id']);

		// Fail the puzzle - this sets cookies but doesn't immediately process
		$browser->playWithResult('F');

		// At this point, cookies are set but result hasn't been processed yet
		// because we're still on the same page (no navigation)

		// Make an htmx request using htmx.ajax() API
		// This is exactly how htmx works internally - sends HX-Request header
		$browser->driver->executeScript("
			htmx.ajax('GET', '/tsumego-issues', { target: 'body' });
		");
		usleep(1500000); // Wait for htmx request to complete

		// Rating should still be original - htmx shouldn't process the result
		$this->assertSame($originalRating, (float) $context->reloadUser()['rating']);

		// NOW navigate to a different page - THIS should process the result
		$browser->get('/sets/index');

		// NOW rating should have dropped
		$this->assertLessThan($originalRating, (float) $context->reloadUser()['rating']);
	}
}

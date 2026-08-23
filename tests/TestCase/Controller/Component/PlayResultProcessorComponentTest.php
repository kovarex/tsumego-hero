<?php

use Facebook\WebDriver\WebDriverBy;

App::uses('Constants', 'Utility');
App::uses('HeroPowers', 'Utility');
App::uses('AchievementChecker', 'Utility');
App::uses('Achievement', 'Model');

class PlayResultProcessorComponentTest extends TestCaseWithAuth
{
	private $PAGES = ['tsumego'];

	private static function getUrlFromPage(string $page, $context): string
	{
		if ($page === 'sets')
			return '/sets/index';
		if ($page === 'tsumego')
			return '/' . $context->tsumegos[0]['set-connections'][0]['id'];
		throw new Exception("Unknown page: " . $page);
	}

	private function loginAs(ContextPreparator &$context): void
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
	}

	private function processResult(ContextPreparator &$context, array $params): void
	{
		$this->loginAs($context);
		$this->testAction('/tsumegos/result', [
			'method' => 'POST',
			'data' => $params,
		]);
		$this->loginAs($context);
	}

	private function performVisit(ContextPreparator &$context, $page): void
	{
		$this->loginAs($context);
		$this->testAction(self::getUrlFromPage($page, $context));
		$context->checkNewTsumegoStatusCoreValues($this);
	}

	private function performSolve(ContextPreparator &$context, $page): void
	{
		$this->processResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => true,
			'mode' => 1,
		]);
		$this->performVisit($context, $page);
	}

	private function performMisplay(ContextPreparator &$context, $page): void
	{
		$this->processResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => false,
			'mode' => 1,
		]);
		$this->performVisit($context, $page);
	}

	private function performSolveWithMisplays(ContextPreparator &$context, $page, int $misplays = 1): void
	{
		// Simulate individual fail calls before the solve
		for ($i = 0; $i < $misplays; $i++)
			$this->processResult($context, [
				'tsumego_id' => $context->tsumegos[0]['id'],
				'seconds' => 0,
				'solved' => false,
				'mode' => 1,
			]);
		$this->processResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => true,
			'mode' => 1,
		]);
		$this->performVisit($context, $page);
	}

	public function testVisitFromEmpty(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => 1]);
			$this->performVisit($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'V');
		}
	}

	public function testSolveFromEmpty(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => 1]);
			$this->performSolve($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'S');
		}
	}

	public function testSolveFromEmptyByWebDriver(): void
	{
		$browser = Browser::instance();
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => 1]);
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			$browser->playWithResult('S');
			$browser->get(self::getUrlFromPage($page, $context));
			$statuses = ClassRegistry::init('TsumegoStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']]]);
			$this->assertSame(count($statuses), 1);
			$this->assertSame($statuses[0]['TsumegoStatus']['status'], 'S');
		}
	}

	public function testVisitFromSolved(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['status' => 'S', 'set_order' => 1]]);
			$this->performVisit($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'S');
		}
	}

	public function testHalfXpStatusToDoubleSolved(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = (new ContextPreparator(['tsumego' => ['status' => 'W', 'set_order' => 1]]));
			$this->performSolve($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'C');
		}
	}

	public function testNoSolveFromFailed(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = (new ContextPreparator(['tsumego' => ['status' => 'F', 'set_order' => 1]]));
			$this->performSolve($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'F');
		}
	}

	public function testFailFromFailed(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = (new ContextPreparator(['tsumego' => ['status' => 'F', 'set_order' => 1]]));
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'F');
		}
	}

	public function testFailFromSolved(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = (new ContextPreparator(['tsumego' => ['status' => 'S', 'set_order' => 1]]));
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'S'); // shouldn't be affected
		}
	}

	public function testFailFromDoubleSolved(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = (new ContextPreparator(['tsumego' => ['status' => 'C', 'set_order' => 1]]));
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'C'); // shouldn't be affected
		}
	}

	public function testSolvingAddsRatingOfPlayerAndDecreasesRatingOfTsumego(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
			$originalRating = $context->user['rating'];
			$this->performSolve($context, $page);
			// user rating is increased
			$this->assertGreaterThan($originalRating, $context->reloadUser()['rating']);
			$this->assertWithinMargin($originalRating, $context->user['rating'], 100); // shouldn't move more than 100 points
			$expectedChange = Rating::calculateRatingChange(1000, 1000, 1, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
			$this->assertLessThan(0.1, abs($originalRating + $expectedChange - $context->reloadUser()['rating']));
			// tsumego rating is decreased
			$this->assertLessThan(1000, ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id'])['Tsumego']['rating']);
		}
	}

	public function testSolvingCantDecreaseTsumegoRatingUnderItsMinimum(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['rating' => 1000, 'minimum_rating' => 1000, 'set_order' => 1]]);
			$originalRating = $context->user['rating'];
			$this->performSolve($context, $page);

			//user rating is increased
			$this->assertGreaterThan($originalRating, $context->reloadUser()['rating']);
			$this->assertWithinMargin($originalRating, $context->user['rating'], 100); // shouldn't move more than 100 points

			// tsumego can't get any lower
			$this->assertSame(1000.0, ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id'])['Tsumego']['rating']);
		}
	}

	public function testFailingDropsRatingOfPlayerAndIncreasesRatingOfTsumego(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
			$originalRating = $context->user['rating'];
			$this->performMisplay($context, $page);
			// user rating is decreased
			$this->assertLessThan($originalRating, $context->reloadUser()['rating']);
			$this->assertWithinMargin($originalRating, $context->user['rating'], 100); // shouldn't move more than 100 points
			// tsumego rating is increased
			$this->assertGreaterThan(1000, ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id'])['Tsumego']['rating']);
		}
	}

	public function testFailingCantIncreaseTsumegoRatingOverItsMaximum(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['rating' => 1000, 'maximum_rating' => 1000, 'set_order' => 1]]);
			$originalRating = $context->user['rating'];
			$this->performMisplay($context, $page);
			$this->assertLessThan($originalRating, $context->reloadUser()['rating']);

			// player still looses rating
			$this->assertWithinMargin($originalRating, $context->user['rating'], 100); // shouldn't move more than 100 points
			$this->assertGreaterThan(100, ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id'])['Tsumego']['rating']);

			// but tsumego can't get higher anymore
			$this->assertSame(1000.0, ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id'])['Tsumego']['rating']);
		}
	}

	public function testSolvingAddsXP(): void
	{
		foreach (['V', 'W'] as $status)
			foreach ($this->PAGES as $page)
			{
				$context = new ContextPreparator([
					'user' => ['rating' => 1000],
					'tsumego' => ['status' => $status, 'rating' => 1000, 'set_order' => 1]]);
				$this->performSolve($context, $page);
				$this->assertSame($context->XPGained(), intval(ceil(($status == 'W' ? Constants::$SECOND_SOLVE_XP_MULTIPLIER : 1) *  TsumegoUtil::getXpValue($context->tsumegos[0]))));
			}
	}

	public function testGoldenSolveIgnoresProgressDeletionReduction(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['status' => 'G', 'rating' => 1000, 'set_order' => 1],
			'progress-deletions' => [
				['set' => 'test set', 'created' => date('Y-m-d H:i:s')],
				['set' => 'test set', 'created' => date('Y-m-d H:i:s')],
				['set' => 'test set', 'created' => date('Y-m-d H:i:s')],
			],
		]);

		$this->performSolve($context, 'sets');

		$this->assertSame($context->XPGained(), TsumegoUtil::getXpValue($context->tsumegos[0], Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER));
	}

	public function testSolvingSolvedDoesntAddXP(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['rating' => 1000, 'set_order' => 1, 'status' => 'S']]);
			$this->performSolve($context, $page);
			$this->assertSame($context->XPGained(), 0);
		}
	}

	public function testSolvingDoubleSolvedDoesntAddXP(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['rating' => 1000, 'set_order' => 1, 'status' => 'C']]);
			$this->performSolve($context, $page);
			$this->assertSame($context->XPGained(), 0);
		}
	}

	public function testSolvingSolvedDoesntAddRating(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['rating' => 1000, 'set_order' => 1, 'status' => 'S']]);
			$this->performSolve($context, $page);
			$this->assertSame($context->reloadUser()['rating'], 1000.0);
		}
	}

	public function testSolvingDoubleSolvedDoesntAddRating(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['rating' => 1000, 'set_order' => 1, 'status' => 'C']]);
			$this->performSolve($context, $page);
			$this->assertSame($context->reloadUser()['rating'], 1000.0);
		}
	}

	public function testSolvingTwiceCountsGoldenSolveOnce(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1],
		]);

		$params = [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => true,
			'mode' => 1,
			'type' => 'g',
		];
		$this->processResult($context, $params);
		$this->processResult($context, $params);

		$goldenCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'category' => 'golden'],
		]);
		$this->assertNotNull($goldenCondition, 'golden achievement condition must be created');
		$this->assertSame(1, (int) $goldenCondition['AchievementCondition']['value'],
			'Solving the same tsumego twice must count the golden solve only once');
	}

	public function testSolvingAddsNewTsumegoAttempt(): void
	{
		foreach (['V', 'W', 'S', 'C'] as $status)
			foreach ($this->PAGES as $page)
			{
				$context = new ContextPreparator(['tsumego' => ['status' => $status, 'set_order' => 1]]);

				$this->performSolve($context, $page);
				$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id'], 'user_id' => $context->user['id']]]);
				if ($status == 'S' || $status == 'C')
					$this->assertSame(count($newTsumegoAttempt), 0); // exactly one should be created
				else
				{
					$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
					$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
					$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 0);
				}
			}
	}

	public function testSolvingUpdatesExistingNotSolvedTsumegoAttempt(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['attempt' => ['solved' => false, 'misplays' => 66], 'set_order' => 1]]);
			$this->performSolve($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // the existing one should be updated
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 66);
		}
	}

	public function testSolvingDoesntUpdateExistingSolvedTsumegoAttempt(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['attempt' => ['solved' => true, 'misplays' => 66], 'set_order' => 1]]);
			$this->performSolve($context, $page);
			$tsumegoAttempts = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id'], 'user_id' => $context->user['id']]]);
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
			$context = new ContextPreparator(['tsumego' => 1]);
			$this->performMisplay($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], false);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 1);
		}
	}

	public function testFailingUpdatesExistingNotSolvedTsumegoAttempt(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['attempt' => ['solved' => false, 'misplays' => 66], 'set_order' => 1]]);
			$this->performMisplay($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], false);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 67);
		}
	}

	public function testFailingDoesntUpdateExistingSolvedTsumegoAttempt(): void
	{
		foreach ($this->PAGES as $page)
		{
			$context = new ContextPreparator(['tsumego' => ['attempt' => ['solved' => true, 'misplays' => 66], 'set_order' => 1]]);
			$this->performMisplay($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id'], 'user_id' => $context->user['id']]]);
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
			$context = new ContextPreparator(['tsumego' => 1]);
			$originalDamage = intval($context->user['damage']);
			$this->performMisplay($context, $page);
			$this->assertSame($originalDamage + 1, $context->reloadUser()['damage']);
		}
	}

	public function testFailAddsDamageInNonSolvedProblem(): void
	{
		$browser = Browser::instance();
		foreach (['V', 'W', 'F', 'S', 'C'] as $status)
		{
			$context = new ContextPreparator(['tsumego' => ['status' => $status, 'set_order' => 1]]);
			$originalDamage = intval($context->user['damage']);

			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			$browser->playWithResult('F');
			$browser->get('sets');
			$this->assertSame($originalDamage + (($status == 'S' || $status == 'C') ? 0 : 1), $context->reloadUser()['damage']);
		}
	}

	public function testProblemDoesntGetFailedWhenHeartsAreStillPresent(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$context->checkNewTsumegoStatusCoreValues($this);
		$this->assertSame($context->resultTsumegoStatus['status'], 'V');
	}

	public function testProblemGetsFailedWhenHeartsAreGonePresent(): void
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => 1, 'user' => ['damage' => Util::getHealthBasedOnLevel(1)]]);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$context->checkNewTsumegoStatusCoreValues($this);
		$this->assertSame($context->resultTsumegoStatus['status'], 'F');
	}

	public function testSolvedIncreasedBySolvingNotSolved(): void
	{
		foreach (['N', 'S'] as $previousStatus)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator([
				'tsumego' => ['set_order' => 1, 'status' => $previousStatus],
				'user' => ['solved' => 66]]);
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			$browser->playWithResult('S');
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			$context->checkNewTsumegoStatusCoreValues($this);
			$this->assertSame($context->reloadUser()['solved'], $previousStatus == 'S' ? 66 : 67);
		}
	}

	public function testFailingResetAndSolvingAppliesBothFailAndSolve(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 500, 'maximum_rating' => 1000, 'set_order' => 1]]);
		$originalRating = $context->user['rating'];
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->playWithResult('F');

		// Fail is processed immediately via AJAX, no navigation needed
		$this->assertLessThan($originalRating, (float) $context->reloadUser()['rating']);

		$browser->clickId("besogo-reset-button");
		$browser->playWithResult('S');

		// reopen the page
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// player has 500 more rating than the problem, so losss + win should lose rating
		$this->assertLessThan($originalRating, $context->reloadUser()['rating']);

		// tsumego has 500 less rating than user, so loss + win should move it up
		$this->assertGreaterThan(500, ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id'])['Tsumego']['rating']);

		$this->assertSame($context->user['damage'], 1); // damage was applied
		$this->assertGreaterThan(0, $context->user['xp']); // xp was gained
	}

	public function testFailingTwiceResetAndSolvingAppliesBothFailAndSolve(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'maximum_rating' => 1000, 'set_order' => 1]]);
		$originalRating = $context->user['rating'];
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$browser->clickId("besogo-reset-button");
		$browser->playWithResult('F');
		$browser->clickId("besogo-reset-button");
		$browser->playWithResult('S');

		// reopen the page
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$expectedRatingChangeForOneLoss = Rating::calculateRatingChange(1000, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
		$ratingChange = $context->reloadUser()['rating'] - $originalRating;
		// two losses and one win with the same rating should more or less result in one loss
		$this->assertLessThan(5, abs($expectedRatingChangeForOneLoss - $ratingChange));

		$this->assertSame($context->user['damage'], 2); // damage was applied
		$this->assertGreaterThan(0, $context->user['xp']); // xp was gained
	}

	public function testSolveWithMisplaysResetsNoErrorStreak(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'achievement-conditions' => [['category' => 'err', 'value' => 9]],  // One away from NO_ERROR_STREAK_I
		]);

		$this->performSolveWithMisplays($context, 'sets');

		// The err counter should be reset to 0, not incremented to 10
		$errCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'category' => 'err'],
		]);
		$this->assertSame(0, (int) $errCondition['AchievementCondition']['value'],
			'No-error streak should reset to 0 when solving with misplays');
	}

	public function testSolveWithoutMisplaysIncrementsNoErrorStreak(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'achievement-conditions' => [['category' => 'err', 'value' => 9]],
		]);

		$this->performSolve($context, 'sets');

		// The err counter should be incremented to 10
		$errCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'category' => 'err'],
		]);
		$this->assertSame(10, (int) $errCondition['AchievementCondition']['value'],
			'No-error streak should increment when solving without misplays');
	}

	public function testPostSolveMistakesAreNotPenalized(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalRating = $context->user['rating'];
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// Prove competence
		$browser->playWithResult('S');
		$this->assertTrue(
			$browser->driver->executeScript('return problemSolved;'));

		// Post-solve exploration (reset + mistake) - must be harmless
		$browser->clickId('besogo-reset-button');
		$browser->playWithResult('F');

		// Trigger server processing
		$browser->get('/sets/index');

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

	public function testMisplayOnAlreadySolvedProblemIsHarmless(): void
	{
		foreach (['S', 'C'] as $status)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['rating' => 1000, 'status' => $status, 'set_order' => 1]]);
			$originalRating = (float) $context->user['rating'];
			$originalDamage = (int) $context->user['damage'];

			$this->performMisplay($context, 'sets');

			// Rating must not change at all for an already-solved problem
			$this->assertSame($originalRating,
				(float) $context->reloadUser()['rating'],
				"Rating must not change when misplaying on status '$status'");

			// Damage must not increase
			$this->assertSame($originalDamage,
				(int) $context->user['damage'],
				"Damage must not increase when misplaying on status '$status'");

			// No attempt record should be created for the misplay
			$unsolvedAttempts = ClassRegistry::init('TsumegoAttempt')->find('all', [
				'conditions' => [
					'tsumego_id' => $context->tsumegos[0]['id'],
					'user_id' => $context->user['id'],
					'solved' => false,
				],
			]);
			$this->assertCount(0, $unsolvedAttempts,
				"No unsolved attempt should be created for status '$status'");
		}
	}

	/**
	 * Verifies the Bad Potion counter integration: when a user has potion active
	 * (used_potion=0, level >= 50, exactly at max health) and the previous
	 * problem was failed, the achievement_condition.value counter for
	 * category='potion' increments because chance = 0 x 0.5% = 0%.
	 */
	public function testPotionConditionIncrementsOnPreviousFail(): void
	{
		$maxHealth = Util::getHealthBasedOnLevel(50);
		$context = new ContextPreparator([
			'user' => [
				'level' => 50,
				'used_potion' => 0,
				'damage' => $maxHealth,
			],
			'tsumego' => ['set_order' => 1]]);

		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();

		// Process a fail: damage == maxHealth, excessDeaths == 0, chance == 0%
		$this->processResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => false,
			'mode' => 1,
		]);

		$condition = ClassRegistry::init('AchievementCondition')->find('first', [
			'conditions' => [
				'user_id' => $context->user['id'],
				'category' => 'potion',
			],
		]);
		$this->assertNotNull($condition, 'potion achievement_condition must be created');
		$this->assertSame(1, (int) $condition['AchievementCondition']['value'],
			'potion condition value must increment to 1 on first miss');
	}

	/**
	 * Verifies the potion trigger path: when damage far exceeds max health,
	 * the progressive chance reaches 100% and potion always heals.
	 * Also verifies the banner renders in the view output.
	 */
	public function testPotionTriggersAndConsumesOnPreviousFail(): void
	{
		$maxHealth = Util::getHealthBasedOnLevel(50);
		$context = new ContextPreparator([
			'user' => [
				'level' => 50,
				'used_potion' => 0,
				'damage' => $maxHealth + 200,
			],
			'tsumego' => ['set_order' => 1]]);

		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();

		// Process a fail: excessDeaths = 200, chance = 100%
		$this->processResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => false,
			'mode' => 1,
		]);

		$user = $context->reloadUser();
		$this->assertSame(1, (int) $user['used_potion'],
			'used_potion must be set to 1 when potion triggers');
		$this->assertSame(0, (int) $user['damage'],
			'damage must be cleared to 0 when potion heals');
	}

	public function testResetAfterSolveDoesntCauseDamage(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

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
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

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
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

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
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

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
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// Verify final state
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Damage should only be from the first fail');
		$status = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame('S', $status['TsumegoStatus']['status'],
			'Status should be solved');
	}

	public function testFailWithOneHeartLeftKeepsStatusVisited(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'user' => ['damage' => Util::getHealthBasedOnLevel(1) - 1]]);
		$this->performMisplay($context, 'tsumego');
		$this->assertSame('V', $context->resultTsumegoStatus['status'],
			'Failing with one heart left should keep status V (spending your last heart)');
	}

	public function testFailWithTwoHeartsLeftKeepsStatusVisited(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'user' => ['damage' => Util::getHealthBasedOnLevel(1) - 2]]);
		$this->performMisplay($context, 'tsumego');
		$this->assertSame('V', $context->resultTsumegoStatus['status'],
			'Failing with two hearts left should keep status V');
	}

	public function testFailWithZeroHeartsSetsStatusToFailed(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'user' => ['damage' => Util::getHealthBasedOnLevel(1)]]);
		$this->performMisplay($context, 'tsumego');
		$this->assertSame('F', $context->resultTsumegoStatus['status'],
			'Failing with zero hearts should set status to F');
	}

	public function testSolveShowsCorrectAndUpdatesXP(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

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
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

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
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->playWithResult('F');

		$this->assertStringContainsString('locked until', $browser->driver->findElement(WebDriverBy::id('status'))->getText());
		$this->assertTrue($browser->driver->executeScript('return window.tryAgainTomorrow;'));
		$this->assertSame(1, $browser->driver->executeScript('return window.boardLockValue;'));
	}

	public function testResetAfterFailDoesNotCostExtraHeart(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

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
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->playWithResult('S');
		$browser->clickId('besogo-reset-button');

		$this->assertSame(0, $context->reloadUser()['damage'],
			'Resetting after solve should not cause damage');
	}

	public function testFailThenSolveAppliesBothEffects(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->playWithResult('F');
		$browser->clickId('besogo-reset-button');
		$browser->playWithResult('S');

		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertSame(1, $context->reloadUser()['damage'],
			'Damage from fail should persist');
		$this->assertGreaterThan(0, $context->reloadUser()['xp'],
			'XP should be gained from solve');
		$this->assertSame('S', ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']]])['TsumegoStatus']['status']);
	}

	public function testSolvedPuzzleCannotBeFailedOrReSolved(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->playWithResult('S');
		$originalDamage = (int) $context->reloadUser()['damage'];
		$originalXp = $context->reloadUser()['xp'];

		// Try to fail — should be harmless
		$browser->playWithResult('F');
		$this->assertSame($originalDamage, (int) $context->reloadUser()['damage'],
			'Fail on solved puzzle should not cause damage');

		// Try to solve again — should be harmless
		$browser->playWithResult('S');
		$this->assertSame($originalXp, $context->reloadUser()['xp'],
			'Re-solving should not grant more XP');
	}

	public function testSolveUpdatesXPDisplay(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

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
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

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

	private function sprintConditionValue(ContextPreparator $context): int
	{
		$condition = ClassRegistry::init('AchievementCondition')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'category' => 'sprint'],
		]);
		return $condition ? (int) $condition['AchievementCondition']['value'] : 0;
	}

	public function testSolveDuringSprintIncrementsSprintCounter(): void
	{
		$context = new ContextPreparator([
			'user' => ['sprint_start' => date('Y-m-d H:i:s')],
			'tsumego' => 1,
		]);
		$this->processResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => true,
			'mode' => 1,
		]);
		$this->assertSame(1, $this->sprintConditionValue($context));
	}

	public function testSolveDuringSprintAccumulatesAcrossProblems(): void
	{
		$context = new ContextPreparator([
			'user' => ['sprint_start' => date('Y-m-d H:i:s')],
			'tsumegos' => [1, 2],
		]);
		foreach ($context->tsumegos as $tsumego)
			$this->processResult($context, [
				'tsumego_id' => $tsumego['id'],
				'seconds' => 0,
				'solved' => true,
				'mode' => 1,
			]);
		$this->assertSame(2, $this->sprintConditionValue($context));
	}

	public function testSolveOutsideSprintResetsSprintCounter(): void
	{
		$context = new ContextPreparator([
			'user' => ['level' => HeroPowers::$SPRINT_MINIMUM_LEVEL],
			'tsumego' => 1,
			'achievement-conditions' => [['category' => 'sprint', 'value' => 12]],
		]);
		$this->processResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => true,
			'mode' => 1,
		]);
		$this->assertSame(0, $this->sprintConditionValue($context));
	}

	public function testFailDuringSprintDoesNotIncrementSprintCounter(): void
	{
		$context = new ContextPreparator([
			'user' => ['sprint_start' => date('Y-m-d H:i:s')],
			'tsumego' => 1,
		]);
		$this->processResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => false,
			'mode' => 1,
		]);
		$this->assertSame(0, $this->sprintConditionValue($context));
	}

	public function testSprintAchievementUnlocksAfterThirtySolvesDuringSprint(): void
	{
		$context = new ContextPreparator([
			'user' => ['sprint_start' => date('Y-m-d H:i:s')],
			'tsumego' => 1,
			'achievement-conditions' => [['category' => 'sprint', 'value' => 29]],
		]);
		$this->processResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => true,
			'mode' => 1,
		]);
		$this->assertSame(30, $this->sprintConditionValue($context));
		$this->loginAs($context);
		new AchievementChecker()->checkDanSolveAchievements();
		$unlocked = ClassRegistry::init('AchievementStatus')->find('count', [
			'conditions' => ['user_id' => $context->user['id'], 'achievement_id' => Achievement::SPRINT],
		]);
		$this->assertGreaterThan(0, $unlocked, 'Sprint achievement should unlock at 30 solves within a sprint');
	}
}

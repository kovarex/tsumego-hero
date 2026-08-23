<?php

App::uses('Constants', 'Utility');
App::uses('HeroPowers', 'Utility');
App::uses('AchievementChecker', 'Utility');
App::uses('Achievement', 'Model');

class PlayResultProcessorComponentTest extends TestCaseWithAuth
{
	private function loginAs(ContextPreparator &$context): void
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
	}

	private function postResult(ContextPreparator &$context, array $params): array
	{
		$this->loginAs($context);
		$this->testAction('/tsumegos/result', [
			'method' => 'POST',
			'data' => $params,
		]);
		$this->loginAs($context);
		return json_decode($this->controller->response->body(), true) ?? [];
	}

	private function solve(ContextPreparator &$context): array
	{
		return $this->postResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => true,
		]);
	}

	private function failResult(ContextPreparator &$context): array
	{
		return $this->postResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => false,
		]);
	}

	private function solveWithMisplays(ContextPreparator &$context, int $misplays = 1): array
	{
		// Each misplay is its own fail call before the final solve
		for ($i = 0; $i < $misplays; $i++)
			$this->failResult($context);
		return $this->solve($context);
	}

	private function statusOf(ContextPreparator $context): string
	{
		return ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']],
		])['TsumegoStatus']['status'];
	}

	private function attemptsOf(ContextPreparator $context): array
	{
		return ClassRegistry::init('TsumegoAttempt')->find('all', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id'], 'user_id' => $context->user['id']],
		]);
	}

	private function tsumegoRating(ContextPreparator $context): float
	{
		return (float) ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id'])['Tsumego']['rating'];
	}

	public function testVisitFromEmpty(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$this->loginAs($context);
		$this->testAction('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertSame('V', $this->statusOf($context));
	}

	public function testSolveFromEmpty(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$response = $this->solve($context);
		$this->assertSame('S', $this->statusOf($context));
		$this->assertSame('S', $response['status'], 'The response should tell the client the new status');
	}

	public function testVisitFromSolved(): void
	{
		$context = new ContextPreparator(['tsumego' => ['status' => 'S', 'set_order' => 1]]);
		$this->loginAs($context);
		$this->testAction('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertSame('S', $this->statusOf($context));
	}

	public function testHalfXpStatusToDoubleSolved(): void
	{
		$context = (new ContextPreparator(['tsumego' => ['status' => 'W', 'set_order' => 1]]));
		$response = $this->solve($context);
		$this->assertSame('C', $this->statusOf($context));
		$this->assertSame('C', $response['status']);
	}

	public function testNoSolveFromFailed(): void
	{
		$context = (new ContextPreparator(['tsumego' => ['status' => 'F', 'set_order' => 1]]));
		$this->solve($context);
		$this->assertSame('F', $this->statusOf($context));
	}

	public function testFailFromFailed(): void
	{
		$context = (new ContextPreparator(['tsumego' => ['status' => 'F', 'set_order' => 1]]));
		$this->failResult($context);
		$this->assertSame('F', $this->statusOf($context));
	}

	public function testFailFromSolved(): void
	{
		$context = (new ContextPreparator(['tsumego' => ['status' => 'S', 'set_order' => 1]]));
		$this->failResult($context);
		$this->assertSame('S', $this->statusOf($context)); // shouldn't be affected
	}

	public function testFailFromDoubleSolved(): void
	{
		$context = (new ContextPreparator(['tsumego' => ['status' => 'C', 'set_order' => 1]]));
		$this->failResult($context);
		$this->assertSame('C', $this->statusOf($context)); // shouldn't be affected
	}

	public function testSolvingAddsRatingOfPlayerAndDecreasesRatingOfTsumego(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$response = $this->solve($context);
		$expectedChange = Rating::calculateRatingChange(1000, 1000, 1, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
		$this->assertLessThan(0.1, abs(1000 + $expectedChange - (float) $response['new_rating']));
		// tsumego rating is decreased
		$this->assertLessThan(1000, $this->tsumegoRating($context));
	}

	public function testSolvingCantDecreaseTsumegoRatingUnderItsMinimum(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'minimum_rating' => 1000, 'set_order' => 1]]);
		$this->solve($context);
		// user rating is increased
		$this->assertGreaterThan(1000.0, (float) $context->reloadUser()['rating']);
		// tsumego can't get any lower
		$this->assertSame(1000.0, $this->tsumegoRating($context));
	}

	public function testFailingDropsRatingOfPlayerAndIncreasesRatingOfTsumego(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$response = $this->failResult($context);
		// user rating is decreased
		$this->assertLessThan(1000.0, (float) $response['new_rating']);
		// tsumego rating is increased
		$this->assertGreaterThan(1000, $this->tsumegoRating($context));
	}

	public function testFailingCantIncreaseTsumegoRatingOverItsMaximum(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'maximum_rating' => 1000, 'set_order' => 1]]);
		$response = $this->failResult($context);
		// player still loses rating
		$this->assertLessThan(1000.0, (float) $response['new_rating']);
		// but tsumego can't get higher anymore
		$this->assertSame(1000.0, $this->tsumegoRating($context));
	}

	public function testSolvingAddsXP(): void
	{
		foreach (['V', 'W'] as $status)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['status' => $status, 'rating' => 1000, 'set_order' => 1]]);
			$response = $this->solve($context);
			$multiplier = $status == 'W' ? Constants::$SECOND_SOLVE_XP_MULTIPLIER : 1;
			$this->assertSame(TsumegoUtil::getXpValue($context->tsumegos[0], $multiplier), (int) $response['xp_gained']);
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

		$response = $this->solve($context);

		$this->assertSame(TsumegoUtil::getXpValue($context->tsumegos[0], Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER), (int) $response['xp_gained']);
	}

	public function testSolvingSolvedDoesntAddXP(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1, 'status' => 'S']]);
		$response = $this->solve($context);
		$this->assertSame(0, (int) $response['xp_gained']);
	}

	public function testSolvingDoubleSolvedDoesntAddXP(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1, 'status' => 'C']]);
		$response = $this->solve($context);
		$this->assertSame(0, (int) $response['xp_gained']);
	}

	public function testSolvingSolvedDoesntAddRating(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1, 'status' => 'S']]);
		$response = $this->solve($context);
		$this->assertSame(1000.0, (float) $response['new_rating']);
	}

	public function testSolvingDoubleSolvedDoesntAddRating(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1, 'status' => 'C']]);
		$response = $this->solve($context);
		$this->assertSame(1000.0, (float) $response['new_rating']);
	}

	public function testSolvingTwiceCountsGoldenSolveOnce(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'status' => 'G', 'set_order' => 1],
		]);

		$params = [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => true,
		];
		$this->postResult($context, $params);
		$this->postResult($context, $params);

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
		{
			$context = new ContextPreparator(['tsumego' => ['status' => $status, 'set_order' => 1]]);

			$this->solve($context);
			$attempts = $this->attemptsOf($context);
			if ($status == 'S' || $status == 'C')
				$this->assertSame(count($attempts), 0); // no new attempt for already-solved problems
			else
			{
				$this->assertSame(count($attempts), 1); // exactly one should be created
				$this->assertSame($attempts[0]['TsumegoAttempt']['solved'], true);
				$this->assertSame($attempts[0]['TsumegoAttempt']['misplays'], 0);
			}
		}
	}

	public function testSolvingUpdatesExistingNotSolvedTsumegoAttempt(): void
	{
		$context = new ContextPreparator(['tsumego' => ['attempt' => ['solved' => false, 'misplays' => 66], 'set_order' => 1]]);
		$this->solve($context);
		$attempts = $this->attemptsOf($context);
		$this->assertSame(count($attempts), 1); // the existing one should be updated
		$this->assertSame($attempts[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($attempts[0]['TsumegoAttempt']['misplays'], 66);
	}

	public function testSolvingDoesntUpdateExistingSolvedTsumegoAttempt(): void
	{
		$context = new ContextPreparator(['tsumego' => ['attempt' => ['solved' => true, 'misplays' => 66], 'set_order' => 1]]);
		$this->solve($context);
		$attempts = $this->attemptsOf($context);
		$this->assertSame(count($attempts), 2); // the solved one wasn't updated
		$this->assertSame($attempts[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($attempts[0]['TsumegoAttempt']['misplays'], 66);
		$this->assertSame($attempts[1]['TsumegoAttempt']['solved'], true);
		$this->assertSame($attempts[1]['TsumegoAttempt']['misplays'], 0);
	}

	public function testFailingAddsNewTsumegoAttempt(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$this->failResult($context);
		$attempts = $this->attemptsOf($context);
		$this->assertSame(count($attempts), 1); // exactly one should be created
		$this->assertSame($attempts[0]['TsumegoAttempt']['solved'], false);
		$this->assertSame($attempts[0]['TsumegoAttempt']['misplays'], 1);
	}

	public function testFailingUpdatesExistingNotSolvedTsumegoAttempt(): void
	{
		$context = new ContextPreparator(['tsumego' => ['attempt' => ['solved' => false, 'misplays' => 66], 'set_order' => 1]]);
		$this->failResult($context);
		$attempts = $this->attemptsOf($context);
		$this->assertSame(count($attempts), 1); // exactly one should be created
		$this->assertSame($attempts[0]['TsumegoAttempt']['solved'], false);
		$this->assertSame($attempts[0]['TsumegoAttempt']['misplays'], 67);
	}

	public function testFailingDoesntUpdateExistingSolvedTsumegoAttempt(): void
	{
		$context = new ContextPreparator(['tsumego' => ['attempt' => ['solved' => true, 'misplays' => 66], 'set_order' => 1]]);
		$this->failResult($context);
		$attempts = $this->attemptsOf($context);
		$this->assertSame(count($attempts), 2); // exactly one should be created
		$this->assertSame($attempts[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($attempts[0]['TsumegoAttempt']['misplays'], 66);
		$this->assertSame($attempts[1]['TsumegoAttempt']['solved'], false);
		$this->assertSame($attempts[1]['TsumegoAttempt']['misplays'], 1);
	}

	public function testFailAddsDamage(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$originalDamage = (int) $context->user['damage'];
		$response = $this->failResult($context);
		$this->assertSame($originalDamage + 1, (int) $response['new_damage']);
	}

	public function testFailAddsDamageInNonSolvedProblem(): void
	{
		foreach (['V', 'W', 'F', 'S', 'C'] as $status)
		{
			$context = new ContextPreparator(['tsumego' => ['status' => $status, 'set_order' => 1]]);
			$originalDamage = (int) $context->user['damage'];

			$this->failResult($context);
			$this->assertSame($originalDamage + (($status == 'S' || $status == 'C') ? 0 : 1), (int) $context->reloadUser()['damage']);
		}
	}

	public function testProblemDoesntGetFailedWhenHeartsAreStillPresent(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$this->failResult($context);
		$this->assertSame('V', $this->statusOf($context));
	}

	public function testProblemGetsFailedWhenHeartsAreGonePresent(): void
	{
		$context = new ContextPreparator(['tsumego' => 1, 'user' => ['damage' => Util::getHealthBasedOnLevel(1)]]);
		$this->failResult($context);
		$this->assertSame('F', $this->statusOf($context));
	}

	public function testSolvedIncreasedBySolvingNotSolved(): void
	{
		foreach (['N', 'S'] as $previousStatus)
		{
			$context = new ContextPreparator([
				'tsumego' => ['set_order' => 1, 'status' => $previousStatus],
				'user' => ['solved' => 66]]);
			$this->solve($context);
			$this->assertSame($previousStatus == 'S' ? 66 : 67, (int) $context->reloadUser()['solved']);
		}
	}

	public function testFailingThenSolvingAppliesBothEffects(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 500, 'maximum_rating' => 1000, 'set_order' => 1]]);
		$originalRating = (float) $context->user['rating'];

		$this->failResult($context);
		// Fail is processed immediately via AJAX
		$this->assertLessThan($originalRating, (float) $context->reloadUser()['rating']);

		$this->solve($context);

		// player has 500 more rating than the problem, so loss + win should lose rating
		$this->assertLessThan($originalRating, (float) $context->reloadUser()['rating']);

		// tsumego has 500 less rating than user, so loss + win should move it up
		$this->assertGreaterThan(500, $this->tsumegoRating($context));

		$this->assertSame(1, (int) $context->reloadUser()['damage']); // damage was applied
		$this->assertGreaterThan(0, (int) $context->reloadUser()['xp']); // xp was gained
	}

	public function testFailingTwiceThenSolvingAppliesBothEffects(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'maximum_rating' => 1000, 'set_order' => 1]]);
		$originalRating = (float) $context->user['rating'];

		$this->failResult($context);
		$this->failResult($context);
		$this->solve($context);

		$expectedRatingChangeForOneLoss = Rating::calculateRatingChange(1000, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
		$ratingChange = (float) $context->reloadUser()['rating'] - $originalRating;
		// two losses and one win with the same rating should more or less result in one loss
		$this->assertLessThan(5, abs($expectedRatingChangeForOneLoss - $ratingChange));

		$this->assertSame(2, (int) $context->reloadUser()['damage']); // damage was applied
		$this->assertGreaterThan(0, (int) $context->reloadUser()['xp']); // xp was gained
	}

	public function testSolveWithMisplaysResetsNoErrorStreak(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'achievement-conditions' => [['category' => 'err', 'value' => 9]],  // One away from NO_ERROR_STREAK_I
		]);

		$this->solveWithMisplays($context);

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

		$this->solve($context);

		// The err counter should be incremented to 10
		$errCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'category' => 'err'],
		]);
		$this->assertSame(10, (int) $errCondition['AchievementCondition']['value'],
			'No-error streak should increment when solving without misplays');
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

			$response = $this->failResult($context);

			// Rating must not change at all for an already-solved problem
			$this->assertSame($originalRating,
				(float) $response['new_rating'],
				"Rating must not change when misplaying on status '$status'");

			// Damage must not increase
			$this->assertSame($originalDamage,
				(int) $response['new_damage'],
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
		$this->failResult($context);

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
		$this->failResult($context);

		$user = $context->reloadUser();
		$this->assertSame(1, (int) $user['used_potion'],
			'used_potion must be set to 1 when potion triggers');
		$this->assertSame(0, (int) $user['damage'],
			'damage must be cleared to 0 when potion heals');
	}

	public function testFailWithOneHeartLeftKeepsStatusVisited(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'user' => ['damage' => Util::getHealthBasedOnLevel(1) - 1]]);
		$this->failResult($context);
		$this->assertSame('V', $this->statusOf($context),
			'Failing with one heart left should keep status V (spending your last heart)');
	}

	public function testFailWithTwoHeartsLeftKeepsStatusVisited(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'user' => ['damage' => Util::getHealthBasedOnLevel(1) - 2]]);
		$this->failResult($context);
		$this->assertSame('V', $this->statusOf($context),
			'Failing with two hearts left should keep status V');
	}

	public function testFailWithZeroHeartsSetsStatusToFailed(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'user' => ['damage' => Util::getHealthBasedOnLevel(1)]]);
		$this->failResult($context);
		$this->assertSame('F', $this->statusOf($context),
			'Failing with zero hearts should set status to F');
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
		$this->solve($context);
		$this->assertSame(1, $this->sprintConditionValue($context));
	}

	public function testSolveDuringSprintAccumulatesAcrossProblems(): void
	{
		$context = new ContextPreparator([
			'user' => ['sprint_start' => date('Y-m-d H:i:s')],
			'tsumegos' => [1, 2],
		]);
		foreach ($context->tsumegos as $tsumego)
			$this->postResult($context, [
				'tsumego_id' => $tsumego['id'],
				'seconds' => 0,
				'solved' => true,
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
		$this->solve($context);
		$this->assertSame(0, $this->sprintConditionValue($context));
	}

	public function testFailDuringSprintDoesNotIncrementSprintCounter(): void
	{
		$context = new ContextPreparator([
			'user' => ['sprint_start' => date('Y-m-d H:i:s')],
			'tsumego' => 1,
		]);
		$this->failResult($context);
		$this->assertSame(0, $this->sprintConditionValue($context));
	}

	public function testSprintAchievementUnlocksAfterThirtySolvesDuringSprint(): void
	{
		$context = new ContextPreparator([
			'user' => ['sprint_start' => date('Y-m-d H:i:s')],
			'tsumego' => 1,
			'achievement-conditions' => [['category' => 'sprint', 'value' => 29]],
		]);
		$this->solve($context);
		$this->assertSame(30, $this->sprintConditionValue($context));
		$this->loginAs($context);
		new AchievementChecker()->checkDanSolveAchievements();
		$unlocked = ClassRegistry::init('AchievementStatus')->find('count', [
			'conditions' => ['user_id' => $context->user['id'], 'achievement_id' => Achievement::SPRINT],
		]);
		$this->assertGreaterThan(0, $unlocked, 'Sprint achievement should unlock at 30 solves within a sprint');
	}

	public function testModeIsCorrectAfterSwitchThenImmediateSolve(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => 1,
		]);

		// Switch to rating mode -> DB mode must be rating immediately (same request).
		$this->loginAs($context);
		$this->testAction('/ratingMode');
		$this->assertSame(Constants::$RATING_MODE, (int) $context->reloadUser()['mode'],
			'DB mode should be RATING right after visiting /ratingMode');

		// Switch back to level mode by opening a problem directly -> DB mode must be level immediately.
		$this->loginAs($context);
		$this->testAction('/' . $context->tsumegos[0]['set-connections'][0]['id'], ['return' => 'view']);
		$this->assertSame(Constants::$LEVEL_MODE, (int) $context->reloadUser()['mode'],
			'DB mode should be LEVEL right after opening a problem directly');
		$this->assertTextContains('var mode = 1;', $this->view,
			'Play page should render as level mode (no one-request lag)');

		// Solve immediately; the mode used for the result must still be level (XP gained, mode unchanged).
		$this->solve($context);
		$this->assertSame(Constants::$LEVEL_MODE, (int) $context->reloadUser()['mode'],
			'DB mode should still be LEVEL after the solve');
		$this->assertGreaterThan(0, (int) $context->reloadUser()['xp'],
			'XP should be gained when solving in level mode');
	}

	public function testRatingModeSolveAfterImmediateSwitchProcessesAsRating(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => 1,
		]);

		// Switch to rating mode and solve immediately, without touching level mode in between.
		$this->loginAs($context);
		$this->testAction('/ratingMode');
		$this->assertSame(Constants::$RATING_MODE, (int) $context->reloadUser()['mode'],
			'DB mode should be RATING after visiting /ratingMode');

		$ratingBefore = (int) $context->reloadUser()['rating'];
		$this->solve($context);
		$this->assertSame(Constants::$RATING_MODE, (int) $context->reloadUser()['mode'],
			'DB mode should still be RATING after solving in rating mode');
		$this->assertNotSame($ratingBefore, (int) $context->reloadUser()['rating'],
			'Rating should change when solving in rating mode');
	}

	public function testSolveResponseIncludesNewlyUnlockedAchievement(): void
	{
		$context = new ContextPreparator([
			'user' => ['solved' => 999],
			'tsumego' => 1,
		]);
		$body = $this->postResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'solved' => true,
		]);
		$this->assertNotEmpty($body['achievement_updates'] ?? [], 'Response should include newly unlocked achievements');
		$unlocked = array_column($body['achievement_updates'], 'id');
		$this->assertContains(Achievement::PROBLEMS_1000, $unlocked, 'Solving the 1000th problem should return PROBLEMS_1000 in the response');
		foreach ($body['achievement_updates'] as $achievementUpdate)
		{
			$this->assertArrayNotHasKey('html', $achievementUpdate, 'The response should be pure data, not pre-rendered HTML');
			foreach (['name', 'description', 'xp', 'image', 'color'] as $field)
				$this->assertArrayHasKey($field, $achievementUpdate, "Each update should carry '$field' for client-side rendering");
		}
	}
}

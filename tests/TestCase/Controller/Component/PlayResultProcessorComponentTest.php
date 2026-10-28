<?php

use App\Model\Table\AchievementConditionTable;
use App\Model\Table\AchievementTable;
use App\Utility\AchievementChecker;
use App\Utility\AchievementId;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\HeroPowers;
use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use App\Utility\Rating;
use App\Utility\RungInsignia;
use App\Utility\SetCompletionCounter;
use App\Utility\TsumegoUtil;
use App\Utility\Util;

class PlayResultProcessorComponentTest extends IntegrationTestCase
{
	private function loginAs(ContextPreparator &$context): void
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();
	}

	private function postResult(ContextPreparator &$context, array $params): array
	{
		$this->loginAs($context);
		$this->post('/tsumegos/result', $params);
		$this->loginAs($context);
		return json_decode($this->view, true) ?? [];
	}

	private function postOutcome(ContextPreparator &$context, PlayOutcome $outcome): array
	{
		return $this->postResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'outcome' => $outcome->value,
		]);
	}

	public function testAGuestCannotReportAResult(): void
	{
		$context = new ContextPreparator(['user' => null, 'tsumego' => 1]);

		$this->post('/tsumegos/result', [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'outcome' => 'solved',
		]);

		$this->assertResponseCode(401);
	}

	private function solveWithMisplays(ContextPreparator &$context, int $misplays = 1): array
	{
		// The play page reports every misplay as its own failure and the final solve as
		// solved after a mistake
		for ($i = 0; $i < $misplays; $i++)
			$this->failResult($context);
		return $this->solveAfterMistake($context);
	}

	private function solve(ContextPreparator &$context): array
	{
		return $this->postOutcome($context, PlayOutcome::SolvedClean);
	}

	private function solveAfterMistake(ContextPreparator &$context): array
	{
		return $this->postOutcome($context, PlayOutcome::SolvedAfterMistake);
	}

	private function failResult(ContextPreparator &$context): array
	{
		return $this->postOutcome($context, PlayOutcome::Failed);
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
		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
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
		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
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

	public function testGainingRatingRaisesTheHighestRating(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$this->solve($context);
		$user = $context->reloadUser();
		$this->assertGreaterThan(1000.0, (float) $user['rating']);
		$this->assertSame((float) $user['rating'], (float) $user['highest_rating']);
	}

	public function testLosingRatingKeepsTheHighestRating(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000, 'highest_rating' => 1200],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$this->failResult($context);
		$user = $context->reloadUser();
		$this->assertLessThan(1000.0, (float) $user['rating']);
		$this->assertSame(1200.0, (float) $user['highest_rating']);
	}

	public function testOnlyTheFirstFailsInARowChangeTheRatings(): void
	{
		foreach ([Constants::$LEVEL_MODE, Constants::$RATING_MODE] as $mode)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000, 'mode' => $mode],
				'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
			for ($i = 0; $i < Constants::$MAXIMUM_RATED_FAILS_IN_A_ROW; $i++)
			{
				$ratingBefore = (float) $context->reloadUser()['rating'];
				$tsumegoRatingBefore = $this->tsumegoRating($context);
				$this->failResult($context);
				$this->assertLessThan($ratingBefore, (float) $context->reloadUser()['rating'], 'mode ' . $mode . ', fail ' . ($i + 1));
				$this->assertGreaterThan($tsumegoRatingBefore, $this->tsumegoRating($context), 'mode ' . $mode . ', fail ' . ($i + 1));
			}

			$ratingBefore = (float) $context->reloadUser()['rating'];
			$tsumegoRatingBefore = $this->tsumegoRating($context);
			$damageBefore = (int) $context->reloadUser()['damage'];
			$response = $this->failResult($context);
			$this->failResult($context);

			$this->assertSame($ratingBefore, (float) $context->reloadUser()['rating'], 'mode ' . $mode);
			$this->assertSame($ratingBefore, (float) $response['new_rating'], 'mode ' . $mode);
			$this->assertSame($tsumegoRatingBefore, $this->tsumegoRating($context), 'mode ' . $mode);
			// the fails are still fails, only the ratings stay
			$this->assertSame(Constants::$MAXIMUM_RATED_FAILS_IN_A_ROW + 2, (int) $this->attemptsOf($context)[0]['TsumegoAttempt']['misplays']);
			if ($mode == Constants::$LEVEL_MODE)
				$this->assertSame($damageBefore + 2, (int) $context->reloadUser()['damage']);
		}
	}

	public function testASolveAfterManyFailsStillRaisesTheRating(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1, 'attempt' => ['misplays' => Constants::$MAXIMUM_RATED_FAILS_IN_A_ROW]]]);
		$response = $this->solveAfterMistake($context);
		$this->assertGreaterThan(1000.0, (float) $response['new_rating']);
	}

	public function testASolveStartsTheFailsInARowOver(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1, 'status' => 'W', 'attempts' => [
				['misplays' => Constants::$MAXIMUM_RATED_FAILS_IN_A_ROW, 'solved' => true]]]]);
		$response = $this->failResult($context);
		$this->assertLessThan(1000.0, (float) $response['new_rating']);
	}

	public function testFailsInARowOnAnotherProblemDontMatter(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1],
			'tsumegos' => [['rating' => 1000, 'set_order' => 2, 'attempt' => ['misplays' => Constants::$MAXIMUM_RATED_FAILS_IN_A_ROW]]]]);
		$response = $this->failResult($context);
		$this->assertLessThan(1000.0, (float) $response['new_rating']);
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
			'outcome' => PlayOutcome::SolvedClean->value,
		];
		$this->postResult($context, $params);
		$this->postResult($context, $params);

		$goldenCondition = $this->fetchTable('AchievementCondition')->find()->where(['user_id' => $context->user['id'], 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_GOLDEN])->first();
		$this->assertNotNull($goldenCondition, 'golden achievement condition must be created');
		$this->assertSame(1, (int) $goldenCondition->value,
			'Solving the same tsumego twice must count the golden solve only once');
	}

	private function goldenRunOf(ContextPreparator $context): int
	{
		$goldenCondition = $this->fetchTable('AchievementCondition')->find()->where(['user_id' => $context->user['id'], 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_GOLDEN])->first();
		return $goldenCondition ? (int) $goldenCondition->value : 0;
	}

	public function testFailingAGoldenTsumegoRestartsTheGoldenRun(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['status' => 'G'],
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_GOLDEN, 'value' => 5]],
		]);

		$this->failResult($context);

		$this->assertSame(0, $this->goldenRunOf($context), 'Failing a golden tsumego must start the golden run over');
	}

	public function testSolvingAGoldenTsumegoAfterAMistakeDoesNotExtendTheGoldenRun(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['status' => 'G'],
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_GOLDEN, 'value' => 5]],
		]);

		$this->solveWithMisplays($context);

		$this->assertSame(0, $this->goldenRunOf($context), 'The misplay ends the run, the later solve must not count');
	}

	public function testFailingANormalTsumegoKeepsTheGoldenRun(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['status' => 'V'],
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_GOLDEN, 'value' => 5]],
		]);

		$this->failResult($context);

		$this->assertSame(5, $this->goldenRunOf($context), 'Only a golden tsumego can break the golden run');
	}

	public function testTenthGoldenTsumegoSolvedInARowUnlocksGoldDigger(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['status' => 'G'],
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_GOLDEN, 'value' => AchievementTable::GOLD_DIGGER_COUNT - 1]],
		]);

		$response = $this->solve($context);

		$this->assertSame(AchievementTable::GOLD_DIGGER_COUNT, $this->goldenRunOf($context));
		$this->assertContains(AchievementId::GOLD_DIGGER->value, array_column($response['achievement_updates'] ?? [], 'id'));
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

		$this->solveAfterMistake($context);

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
		$this->solveAfterMistake($context);

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
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_NO_ERROR_STREAK, 'value' => 9]],  // One away from NO_ERROR_STREAK_I
		]);

		$this->failResult($context);
		$this->solveAfterMistake($context);

		// The err counter should be reset to 0, not incremented to 10
		$errCondition = $this->fetchTable('AchievementCondition')->find()->where(['user_id' => $context->user['id'], 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_NO_ERROR_STREAK])->first();
		$this->assertSame(0, (int) $errCondition->value,
			'No-error streak should reset to 0 when solving with misplays');
	}

	public function testSolveWithoutMisplaysIncrementsNoErrorStreak(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_NO_ERROR_STREAK, 'value' => 9]],
		]);

		$this->solve($context);

		// The err counter should be incremented to 10
		$errCondition = $this->fetchTable('AchievementCondition')->find()->where(['user_id' => $context->user['id'], 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_NO_ERROR_STREAK])->first();
		$this->assertSame(10, (int) $errCondition->value,
			'No-error streak should increment when solving without misplays');
	}

	//The streak counts the problems solved since the last error, so a problem
	//misplayed in an earlier play counts once the player solves it cleanly.
	public function testStreakCountsASolveOfAProblemMisplayedInAnEarlierPlay(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_NO_ERROR_STREAK, 'value' => 0]],
		]);

		// An earlier play of this problem ended in a mistake, which broke the streak then
		$this->failResult($context);

		// Solving it in a later play is a clean solve
		$this->solve($context);

		$errCondition = $this->fetchTable('AchievementCondition')->find()->where(['user_id' => $context->user['id'], 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_NO_ERROR_STREAK])->first();
		$this->assertSame(1, (int) $errCondition->value,
			'A clean solve extends the streak');
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

	//Verifies the Bad Potion counter integration: when a user has potion active
	//(used_potion=0, level >= 50, exactly at max health) and the previous
	//problem was failed, the achievement_condition.value counter for
	//category CATEGORY_POTION increments because chance = 0 x 0.5% = 0%.
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

		$condition = $this->fetchTable('AchievementCondition')->find()->where([
			'user_id' => $context->user['id'],
			'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_POTION,
		])->first();
		$this->assertNotNull($condition, 'potion achievement_condition must be created');
		$this->assertSame(1, (int) $condition->value,
			'potion condition value must increment to 1 on first miss');
	}

	//Verifies the potion trigger path: when damage far exceeds max health,
	//the progressive chance reaches 100% and potion always heals.
	//Also verifies the banner renders in the view output.
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
		$condition = $this->fetchTable('AchievementCondition')->find()->where(['user_id' => $context->user['id'], 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_SPRINT])->first();
		return $condition ? (int) $condition->value : 0;
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
				'outcome' => PlayOutcome::SolvedClean->value,
			]);
		$this->assertSame(2, $this->sprintConditionValue($context));
	}

	public function testSolveOutsideSprintResetsSprintCounter(): void
	{
		$context = new ContextPreparator([
			'user' => ['level' => HeroPowers::$SPRINT_MINIMUM_LEVEL],
			'tsumego' => 1,
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_SPRINT, 'value' => 12]],
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
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_SPRINT, 'value' => 29]],
		]);
		$this->solve($context);
		$this->assertSame(30, $this->sprintConditionValue($context));
		$this->loginAs($context);
		new AchievementChecker()->checkDanSolveAchievements();
		$unlocked = $this->fetchTable('AchievementStatus')->find()
			->where(['user_id' => $context->user['id'], 'achievement_id' => AchievementId::SPRINT->value])->count();
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
		$this->post('/ratingMode');
		$this->assertSame(Constants::$RATING_MODE, (int) $context->reloadUser()['mode_id'],
			'DB mode should be RATING right after visiting /ratingMode');

		// Switch back to level mode by opening a problem directly -> DB mode must be level immediately.
		$this->loginAs($context);
		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertSame(Constants::$LEVEL_MODE, (int) $context->reloadUser()['mode_id'],
			'DB mode should be LEVEL right after opening a problem directly');
		$this->assertTextContains('var mode = 1;', $this->view,
			'Play page should render as level mode (no one-request lag)');

		// Solve immediately; the mode used for the result must still be level (XP gained, mode unchanged).
		$this->solve($context);
		$this->assertSame(Constants::$LEVEL_MODE, (int) $context->reloadUser()['mode_id'],
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
		$this->post('/ratingMode');
		$this->assertSame(Constants::$RATING_MODE, (int) $context->reloadUser()['mode_id'],
			'DB mode should be RATING after visiting /ratingMode');

		$ratingBefore = (int) $context->reloadUser()['rating'];
		$this->solve($context);
		$this->assertSame(Constants::$RATING_MODE, (int) $context->reloadUser()['mode_id'],
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
			'outcome' => PlayOutcome::SolvedClean->value,
		]);
		$this->assertNotEmpty($body['achievement_updates'] ?? [], 'Response should include newly unlocked achievements');
		$unlocked = array_column($body['achievement_updates'], 'id');
		$this->assertContains(AchievementId::PROBLEMS_1000->value, $unlocked, 'Solving the 1000th problem should return PROBLEMS_1000 in the response');
		foreach ($body['achievement_updates'] as $achievementUpdate)
		{
			$this->assertArrayNotHasKey('html', $achievementUpdate, 'The response should be pure data, not pre-rendered HTML');
			foreach (['name', 'description', 'xp', 'image', 'color'] as $field)
				$this->assertArrayHasKey($field, $achievementUpdate, "Each update should carry '$field' for client-side rendering");
		}
	}

	private function completedSetsCondition(ContextPreparator $context): ?int
	{
		$condition = $this->fetchTable('AchievementCondition')->find()->where(['user_id' => $context->user['id'], 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_COMPLETED_SETS])->first();
		return $condition ? (int) $condition->value : null;
	}

	public function testSolveCompletingSetPartUpdatesCompletedSetsCount(): void
	{
		$originalPartitionSize = SetCompletionCounter::$PARTITION_SIZE;
		SetCompletionCounter::$PARTITION_SIZE = 2;
		try
		{
			$context = new ContextPreparator([
				'tsumego' => ['sets' => [['name' => 'big set', 'num' => 1]]],
				'tsumegos' => [
					['sets' => [['name' => 'big set', 'num' => 2]], 'status' => 'S'],
					['sets' => [['name' => 'big set', 'num' => 3]]],
				],
			]);
			$this->solve($context);
			$this->assertSame(1, $this->completedSetsCondition($context));
		}
		finally
		{
			SetCompletionCounter::$PARTITION_SIZE = $originalPartitionSize;
		}
	}

	public function testSolveCompletingNonOfficialSetDoesNotCount(): void
	{
		$originalPartitionSize = SetCompletionCounter::$PARTITION_SIZE;
		SetCompletionCounter::$PARTITION_SIZE = 2;
		try
		{
			foreach ([['public' => 0], ['public' => 0, 'user_id' => 'self'], ['public' => 1, 'user_id' => 'self']] as $set)
			{
				$context = new ContextPreparator([
					'tsumego' => ['sets' => [array_merge(['name' => 'other set', 'num' => 1], $set)]],
					'tsumegos' => [['sets' => [array_merge(['name' => 'other set', 'num' => 2], $set)], 'status' => 'S']],
				]);
				$this->solve($context);
				$this->assertSame('S', $this->statusOf($context));
				$this->assertNull($this->completedSetsCondition($context), 'Completing a set that is not an official public one must not count: ' . json_encode($set));
			}
		}
		finally
		{
			SetCompletionCounter::$PARTITION_SIZE = $originalPartitionSize;
		}
	}

	public function testSolveNotCompletingSetPartDoesNotRecount(): void
	{
		$originalPartitionSize = SetCompletionCounter::$PARTITION_SIZE;
		SetCompletionCounter::$PARTITION_SIZE = 2;
		try
		{
			$context = new ContextPreparator([
				'tsumego' => ['sets' => [['name' => 'big set', 'num' => 1]]],
				'tsumegos' => [['sets' => [['name' => 'big set', 'num' => 2]]]],
				'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_COMPLETED_SETS, 'value' => 7]],
			]);
			$this->solve($context);
			$this->assertSame(7, $this->completedSetsCondition($context), 'The stored count is only recomputed when a part gets completed');
		}
		finally
		{
			SetCompletionCounter::$PARTITION_SIZE = $originalPartitionSize;
		}
	}

	public function testSolveCompletingTenthSetUnlocksAchievement(): void
	{
		$originalPartitionSize = SetCompletionCounter::$PARTITION_SIZE;
		SetCompletionCounter::$PARTITION_SIZE = 1;
		try
		{
			$solvedProblems = [];
			for ($num = 2; $num <= AchievementTable::COMPLETE_SETS_I_SETS_COUNT; $num++)
				$solvedProblems[] = ['sets' => [['name' => 'big set', 'num' => $num]], 'status' => 'S'];
			$context = new ContextPreparator([
				'tsumego' => ['sets' => [['name' => 'big set', 'num' => 1]]],
				'tsumegos' => $solvedProblems,
			]);
			$body = $this->solve($context);
			$this->assertSame(AchievementTable::COMPLETE_SETS_I_SETS_COUNT, $this->completedSetsCondition($context));
			$this->assertContains(AchievementId::COMPLETE_SETS_I->value, array_column($body['achievement_updates'] ?? [], 'id'));
		}
		finally
		{
			SetCompletionCounter::$PARTITION_SIZE = $originalPartitionSize;
		}
	}

	// ==================== Mistake Training Entry Hook ====================

	public function testMisplayOnFirstEncounterDoesNotEnterPool(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$this->failResult($context);
		$this->assertNull(
			MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']),
			'A problem that was not solved belongs to practice, not to the training pool'
		);
	}

	public function testSolveWithMisplaysOnFirstEncounterEntersPool(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$this->solveWithMisplays($context);
		$this->assertNotNull(
			MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']),
			'Solve with misplays on first encounter should enter the training pool'
		);
	}

	public function testCleanSolveOnFirstEncounterDoesNotEnterPool(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$this->solve($context);
		$this->assertNull(
			MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']),
			'Clean solve on first encounter should not enter the pool'
		);
	}

	public function testVisitOnlyDoesNotEnterPool(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$this->loginAs($context);
		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertNull(
			MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']),
			'Visit only should not enter the pool'
		);
	}

	public function testFailPushesReviewToFuture(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);

		$this->solveWithMisplays($context);
		$row = MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']);
		$this->assertNotNull($row, 'Should be in the pool after solving with misplays');

		$this->failResult($context);
		$row = MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']);
		$this->assertNotNull($row, 'Should still be in the pool after a fail');
		$this->assertGreaterThan(
			date('Y-m-d H:i:s'),
			$row['next_due'],
			'next_due should be in the future after fail, so problem drops from due queue'
		);
	}

	public function testCleanSolveAdvancesReview(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);

		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		$this->solveWithMisplays($context);

		// Reset and solve again in the level mode, before the review day
		$this->solve($context);
		$this->assertSame(0, (int) MistakeTraining::getPoolRow($userId, $tsumegoId)['rung'],
			'A clean replay before the review day is won by short-term memory and does not climb');

		MistakeTraining::makeDueNow($userId, $tsumegoId);
		$this->solve($context);

		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertSame(1, (int) $row['rung'], 'A clean solve on the review day climbs one rung');
		$this->assertGreaterThan(
			date('Y-m-d H:i:s'),
			$row['next_due'],
			'next_due should be in the future after clean solve'
		);
	}

	public function testTrainingDoesNotRecordAttempt(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);
		$this->failResult($context);

		$this->assertSame(0, count($this->attemptsOf($context)), 'Training should not write to tsumego_attempt (like time mode)');
		$this->assertNotNull(MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']), 'But it should update the pool');
	}

	public function testTrainingUpdatesPoolNotAttempts(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		$this->failResult($context);
		$this->failResult($context);

		$this->assertSame(0, count($this->attemptsOf($context)), 'Training should not write to tsumego_attempt');
		$row = MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']);
		$this->assertSame(0, (int) $row['rung'], 'Repeated training fails keep the problem at the daily rung');
	}

	public function testTrainingReviewUpdatesPoolRegardlessOfStatus(): void
	{
		// A problem can stay in the pool even after being solved in another mode;
		// a training lapse updates the pool (kept at the daily rung), and training
		// deliberately ignores tsumego status.
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'S', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		$this->failResult($context);

		$row = MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']);
		$this->assertNotNull($row, 'A training lapse keeps the problem in the pool');
		$this->assertSame(0, (int) $row['rung'], 'A training lapse keeps the problem at the daily rung');
	}

	public function testTrainingGraduationStopsSchedulingTheProblem(): void
	{
		// A clean solve at the top rung of the review ladder ends training: the
		// problem is no longer scheduled, but its status stays exactly as the normal
		// modes left it, since training is a consequence-free mode.
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		$beforeSolved = (int) Auth::getUser()['solved'];

		// Eight reviews, each on its own day: a second solve on the day of a review is a
		// replay that counts for nothing.
		for ($i = 0; $i < 8; $i++)
		{
			MistakeTraining::makeDueNow($context->user['id'], (int) $context->tsumegos[0]['id']);
			$this->solve($context);
		}

		$this->assertSame(0, MistakeTraining::totalInTraining($context->user['id']),
			'Graduation takes the problem out of the training');
		$this->assertSame('V', $this->statusOf($context), 'Graduation should leave the status untouched');
		$this->assertSame($beforeSolved, (int) Auth::getUser()['solved'], 'Graduation should not bump the solved counter');
	}

	//The board locks on a training mistake, but the problem is not gone for the day:
	//it stays due, grey in the row, until a later try solves it.
	public function testATrainingMistakeKeepsTheProblemDueUntilItIsSolved(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		$this->failResult($context);

		$this->assertTrue(MistakeTraining::isDue($userId, $tsumegoId), 'A mistake keeps the problem in today\'s training');
		$this->assertSame(0, (int) MistakeTraining::getPoolRow($userId, $tsumegoId)['rung']);

		$this->solve($context);

		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertFalse(MistakeTraining::isRowDue($row), 'Solving it ends the review for today');
		$this->assertSame(0, (int) $row['rung'], 'A solve after a mistake of the same day earns no rung');
		$this->assertSame(
			date('Y-m-d', strtotime(MistakeTraining::today() . ' +1 day')),
			date('Y-m-d', strtotime($row['next_due'])),
			'And the problem comes back tomorrow');
	}

	//The play page is not reloaded after a result, so the result tells it what the
	//button of the problem shows now: a mistake takes the rung badge to the bottom
	//and keeps the button grey, the solve that follows turns it green.
	public function testATrainingResultSaysWhatTheButtonOfTheProblemShowsNow(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3],
			],
		]);
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		$button = $this->failResult($context)['mistake_training_button'];
		$this->assertSame('N', $button['status'], 'A miss stays grey until it is solved');
		$this->assertSame('Missed today, try again', $button['label']);
		$this->assertSame('<div class="problem-nav__rung problem-nav__rung--chevron problem-nav__rung--missed" data-rung="0" role="img" aria-label="Rung 1 of 8, missed today">'
			. RungInsignia::render(RungInsignia::CHEVRON, 1) . '</div>', $button['badge'], 'The mistake took the problem to the bottom rung, one chevron, ringed red for the miss');

		$button = $this->solve($context)['mistake_training_button'];
		$this->assertSame('S', $button['status']);
		$this->assertSame('Solved today', $button['label']);
		$this->assertStringContainsString('next review tomorrow', $button['description']);
		$this->assertStringContainsString('data-rung="0" role="img" aria-label="Rung 1 of 8"', $button['badge'], 'The retry earned no rung');
		$this->assertStringNotContainsString('problem-nav__rung--missed', $button['badge'], 'And solving it takes the red ring away');
	}

	public static function cleanSolveBadgeProvider(): array
	{
		return [
			'the fourth chevron climbs to the first star' => [3, 'data-rung="4" role="img" aria-label="Rung 5 of 8">' . RungInsignia::render(RungInsignia::STAR, 1)],
			'the first star climbs to the second' => [4, 'data-rung="5" role="img" aria-label="Rung 6 of 8">' . RungInsignia::render(RungInsignia::STAR, 2)],
			'the last star graduates to the crown' => [7, 'problem-nav__rung--graduated" role="img" aria-label="Graduated">' . RungInsignia::renderCrown()],
		];
	}

	//A clean solve climbs a rung, and the badge the result redraws the button with shows the rank it climbed to.
	/** @dataProvider cleanSolveBadgeProvider */
	public function testACleanSolveInTheTrainingRedrawsTheBadgeOneRankUp(int $rung, string $badge): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => $rung],
			],
		]);
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		$button = $this->solve($context)['mistake_training_button'];

		$this->assertSame('S', $button['status']);
		$this->assertStringEndsWith($badge . '</div>', $button['badge']);
	}

	public function testAResultOutsideTheTrainingSaysNothingAboutItsButtons(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'S', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$this->assertArrayNotHasKey('mistake_training_button', $this->failResult($context),
			'The buttons of the level mode are not the training row');
	}

	//A problem solved today stays in the training row and can be opened from it, but
	//whatever happens on that replay must not touch the review the day already did.
	public function testATrainingReplayOfAProblemSolvedTodayCountsForNothing(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 1],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		$this->solve($context);
		$reviewed = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertSame(2, (int) $reviewed['rung']);

		$this->failResult($context);
		$this->solve($context);

		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertSame(2, (int) $row['rung'], 'A replay neither takes the problem down nor up');
		$this->assertSame($reviewed['next_due'], $row['next_due']);
		$this->assertSame(MistakeTraining::REVIEW_SOLVED, $row['last_mistake_training_result_id']);
	}

	//A problem that is due is reviewed wherever it is played, so a mistake in the
	//level mode keeps it in today's training too.
	public function testALevelMistakeOnADueProblemKeepsItDueToday(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'S', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 2],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->failResult($context);

		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung']);
		$this->assertTrue(MistakeTraining::isRowDue($row), 'The problem waits in today\'s training until it is solved');
		$this->assertSame(MistakeTraining::REVIEW_MISSED, $row['last_mistake_training_result_id']);

		$this->solveAfterMistake($context);

		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertFalse(MistakeTraining::isRowDue($row), 'The solve of the same play ends the review for today');
		$this->assertSame(0, (int) $row['rung']);
		$this->assertSame(MistakeTraining::REVIEW_SOLVED, $row['last_mistake_training_result_id']);
	}

	public function testPoolProblemStaysRegardlessOfStatus(): void
	{
		// A problem already in the pool stays there even after it gets solved
		// elsewhere. Only graduation removes it from the pool.
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$this->solve($context);

		$status = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertSame('S', $status['TsumegoStatus']['status'], 'Level solve should mark it solved');
		$this->assertNotNull(
			MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']),
			'A solved pool problem must stay in the pool regardless of its status'
		);
	}

	// ==================== Mode side-effect matrix ====================

	public function testTrainingSolveGivesOnlyTheTrainingXp(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
				'attempt' => ['solved' => false, 'misplays' => 1], // real pool member (entered via fail)
			],
		]);
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		$beforeXp = Auth::getUser()['xp'];
		$beforeRating = Auth::getUser()['rating'];
		$beforeDamage = Auth::getUser()['damage'];
		$beforeDailySolved = Auth::getUser()['daily_solved'];
		$beforeDailyXp = Auth::getUser()['daily_xp'];
		$xp = max(1, Rating::ratingToXP($this->tsumegoRating($context), MistakeTraining::REVIEW_XP_SHARE));

		$this->solve($context);

		$this->assertSame($beforeXp + $xp, Auth::getUser()['xp'], 'Training solve grants the training XP only');
		$this->assertSame($beforeRating, Auth::getUser()['rating'], 'Training solve should not change rating');
		$this->assertSame($beforeDamage, Auth::getUser()['damage'], 'Training solve should not change damage');
		$this->assertSame($beforeDailySolved + 1, Auth::getUser()['daily_solved'], 'A review counts as a solve of the day');
		$this->assertSame($beforeDailyXp + $xp, Auth::getUser()['daily_xp'], 'And its XP as XP of the day');

		$status = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertSame('V', $status['TsumegoStatus']['status'], 'Training solve should not change tsumego status');

		// Training does not write to tsumego_attempt (like time mode); it only
		// advances the pool. A clean solve climbs one rung.
		$poolRow = MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']);
		$this->assertSame(1, (int) $poolRow['rung'], 'A clean training solve should climb the review ladder');
	}

	public function testTrainingFailDoesNotPunish(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		$beforeDamage = Auth::getUser()['damage'];

		$this->failResult($context);

		$this->assertSame($beforeDamage, Auth::getUser()['damage'], 'Training fail should not add damage');

		$status = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertSame('V', $status['TsumegoStatus']['status'], 'Training fail should not change tsumego status');

		// Training does not write to tsumego_attempt (like time mode); it only
		// moves the pool. A fail keeps the problem at the daily rung.
		$poolRow = MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']);
		$this->assertSame(0, (int) $poolRow['rung'], 'A training fail should keep the problem at the daily rung');
	}

	public function testLevelSolveGrantsProgress(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);

		$beforeXp = Auth::getUser()['xp'];
		$beforeRating = Auth::getUser()['rating'];
		$beforeDailySolved = Auth::getUser()['daily_solved'];

		$this->solve($context);

		$this->assertGreaterThan($beforeXp, Auth::getUser()['xp'], 'Level solve should grant XP');
		$this->assertGreaterThan($beforeDailySolved, Auth::getUser()['daily_solved'], 'Level solve should bump daily solves');
		$this->assertNotEquals($beforeRating, Auth::getUser()['rating'], 'Level solve should change rating');

		$status = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertSame('S', $status['TsumegoStatus']['status'], 'Level solve should mark the problem solved');

		$attempt = ClassRegistry::init('TsumegoAttempt')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']],
			'order' => 'id DESC',
		]);
		$this->assertSame(true, (bool) $attempt['TsumegoAttempt']['solved'], 'Level solve should record a solved attempt');
	}

	// ==================== The mode of the page ====================

	private function postOutcomeFromPage(ContextPreparator &$context, PlayOutcome $outcome, int $pageMode): array
	{
		return $this->postResult($context, [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'outcome' => $outcome->value,
			'mode' => $pageMode,
		]);
	}

	//The stored mode is the training because another tab opened it, but the problem
	//was played on a level page that was still open.
	public function testALevelPageCountsInTheLevelModeWhileAnotherTabIsInTheTraining(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);
		$beforeXp = Auth::getUser()['xp'];

		$this->postOutcomeFromPage($context, PlayOutcome::SolvedClean, Constants::$LEVEL_MODE);

		$this->assertGreaterThan($beforeXp, Auth::getUser()['xp'], 'The level page gives its XP');
		$this->assertSame('S', $this->statusOf($context), 'And marks the problem solved');
		$this->assertCount(1, $this->attemptsOf($context));
		$this->assertSame(Constants::$MISTAKE_TRAINING_MODE, Auth::getMode(), 'The other tab stays in the training');
	}

	//The stored mode is the level mode because another tab opened a problem there,
	//but this one was played on the training page.
	public function testATrainingPageCountsAsTrainingAfterAnotherTabOpenedTheLevelMode(): void
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$beforeXp = Auth::getUser()['xp'];
		$beforeDamage = Auth::getUser()['damage'];

		$response = $this->postOutcomeFromPage($context, PlayOutcome::Failed, Constants::$MISTAKE_TRAINING_MODE);

		$this->assertSame($beforeXp, Auth::getUser()['xp']);
		$this->assertSame($beforeDamage, Auth::getUser()['damage'], 'A training mistake costs no heart');
		$this->assertSame('V', $this->statusOf($context));
		$this->assertCount(0, $this->attemptsOf($context));
		$this->assertArrayHasKey('mistake_training_button', $response, 'The training page redraws its row');
		$row = MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']);
		$this->assertSame(MistakeTraining::REVIEW_MISSED, $row['last_mistake_training_result_id']);
	}

	public function testClaimingTheTrainingForAProblemOutsideItSparesNoHeart(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$beforeDamage = Auth::getUser()['damage'];

		$this->postOutcomeFromPage($context, PlayOutcome::Failed, Constants::$MISTAKE_TRAINING_MODE);

		$this->assertGreaterThan($beforeDamage, Auth::getUser()['damage'],
			'Only a problem in today\'s training is played as training');
	}

	public function testLevelFailAddsDamage(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);

		$beforeDamage = Auth::getUser()['damage'];

		$this->failResult($context);

		$this->assertGreaterThan($beforeDamage, Auth::getUser()['damage'], 'Level fail should add damage');

		$attempt = ClassRegistry::init('TsumegoAttempt')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']],
			'order' => 'id DESC',
		]);
		$this->assertSame(false, (bool) $attempt['TsumegoAttempt']['solved'], 'Level fail should record an unsolved attempt');
	}

	//A play outside the training reports its first mistake and then the solve, so
	//the pool sees both outcomes. On a problem waiting for its review the failure
	//takes it back to the bottom and the solve that needed a retry must not earn a
	//rung.
	public function testARetryAfterAFailureLeavesTheLadderWhereTheFailurePutIt(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->solveAfterMistake($context);
		$this->assertSame(0, (int) MistakeTraining::getPoolRow($userId, $tsumegoId)['rung'],
			'A solve after a mistake enters the ladder at the bottom');

		MistakeTraining::makeDueNow($userId, $tsumegoId);
		$this->solve($context);
		$this->assertSame(1, (int) MistakeTraining::getPoolRow($userId, $tsumegoId)['rung'],
			'A clean solve on the review day climbs one rung');

		Util::execute('UPDATE tsumego_status SET status = ? WHERE user_id = ? AND tsumego_id = ?',
			[TsumegoStatus::$REVIEW, $userId, $tsumegoId]);
		$this->failResult($context);
		$this->solveAfterMistake($context);

		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'The retry earns no rung the failure already took back');
		$this->assertSame(
			date('Y-m-d', strtotime(MistakeTraining::today() . ' +1 day')),
			date('Y-m-d', strtotime($row['next_due'])),
			'A problem solved after a mistake comes back tomorrow');
	}

	//A solved problem is the player's to play with freely in the level mode, so a
	//mistake on it neither brings it into the training nor takes it down there.
	public function testAMistakeOnASolvedProblemInTheLevelModeIsFreePlay(): void
	{
		$context = new ContextPreparator(['tsumego' => ['status' => 'S']]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->solveWithMisplays($context);

		$this->assertNull(MistakeTraining::getPoolRow($userId, $tsumegoId));
	}

	// ==================== The XP of the training ====================

	//A problem rated 1500 gives 45 XP in the level mode (Rating::ratingToXP), so a
	//review in the training earns 9 and a graduation 23 on top.
	private function dueInTraining(int $rung = 0, int $setOrder = 1): array
	{
		return [
			'rating' => 1500,
			'set_order' => $setOrder,
			'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => $rung],
		];
	}

	private function playInTraining(ContextPreparator &$context, PlayOutcome $outcome, int $index = 0): array
	{
		return $this->postResult($context, [
			'tsumego_id' => $context->tsumegos[$index]['id'],
			'seconds' => 0,
			'outcome' => $outcome->value,
			'mode' => Constants::$MISTAKE_TRAINING_MODE,
		]);
	}

	private function trainingDay(ContextPreparator $context): ?array
	{
		$rows = Util::query('SELECT * FROM mistake_training_day WHERE user_id = ? AND day = ?', [$context->user['id'], MistakeTraining::today()]);
		return $rows[0] ?? null;
	}

	public function testACleanReviewEarnsAFifthOfTheXpOfTheProblem(): void
	{
		$context = new ContextPreparator(['tsumego' => $this->dueInTraining(3)]);
		$beforeXp = (int) Auth::getUser()['xp'];

		$response = $this->playInTraining($context, PlayOutcome::SolvedClean);

		$this->assertSame(['review' => 9, 'graduation' => 0, 'clear' => 0], $response['mistake_training_xp']);
		$this->assertSame(9, (int) $response['xp_gained']);
		$this->assertSame($beforeXp + 9, (int) Auth::getUser()['xp']);
	}

	public function testAMissAndTheRetryAfterItEarnNoXp(): void
	{
		$context = new ContextPreparator(['tsumego' => $this->dueInTraining(3)]);
		$beforeXp = (int) Auth::getUser()['xp'];

		$missed = $this->playInTraining($context, PlayOutcome::Failed);
		$retried = $this->playInTraining($context, PlayOutcome::SolvedAfterMistake);
		$cleanLater = $this->playInTraining($context, PlayOutcome::SolvedClean);

		foreach ([$missed, $retried, $cleanLater] as $response)
			$this->assertSame(0, array_sum($response['mistake_training_xp']));
		$this->assertSame($beforeXp, (int) Auth::getUser()['xp'],
			'Making a mistake on purpose to come back to the problem earns nothing');
	}

	public function testAReplayOfAProblemSolvedTodayEarnsNoXp(): void
	{
		$context = new ContextPreparator(['tsumego' => $this->dueInTraining(3)]);
		$beforeXp = (int) Auth::getUser()['xp'];

		$this->playInTraining($context, PlayOutcome::SolvedClean);
		$replay = $this->playInTraining($context, PlayOutcome::SolvedClean);

		$this->assertSame(0, array_sum($replay['mistake_training_xp']));
		$this->assertSame($beforeXp + 9, (int) Auth::getUser()['xp'], 'Only the review counts');
	}

	public function testAReviewInTheLevelModeEarnsNoTrainingXp(): void
	{
		$context = new ContextPreparator(['tsumego' => $this->dueInTraining(3)]);

		$response = $this->solve($context);

		$this->assertArrayNotHasKey('mistake_training_xp', $response, 'The level mode gives its own XP');
		$this->assertSame('0', (string) Util::query('SELECT xp FROM mistake_training_review WHERE user_id = ?', [$context->user['id']])[0]['xp']);
	}

	public function testAGraduationEarnsHalfTheXpOfTheProblemOnTop(): void
	{
		$context = new ContextPreparator(['tsumego' => $this->dueInTraining(count(MistakeTraining::ladder()) - 1)]);

		$response = $this->playInTraining($context, PlayOutcome::SolvedClean);

		$this->assertSame(['review' => 9, 'graduation' => 23, 'clear' => 0], $response['mistake_training_xp']);
		$this->assertSame(32, (int) $response['xp_gained']);
	}

	private function trainingOf(int $problems): ContextPreparator
	{
		$tsumegos = [];
		for ($i = 1; $i < $problems; $i++)
			$tsumegos[] = $this->dueInTraining(3, $i + 1);
		return new ContextPreparator(['tsumego' => $this->dueInTraining(3), 'tsumegos' => $tsumegos]);
	}

	public function testClearingTheDayEarnsHalfOfTheXpOfItsReviewsOnce(): void
	{
		$context = $this->trainingOf(MistakeTraining::CLEAR_DAY_MIN_PROBLEMS);
		$beforeDailyXp = (int) Auth::getUser()['daily_xp'];
		$beforeDailySolved = (int) Auth::getUser()['daily_solved'];

		for ($i = 0; $i < MistakeTraining::CLEAR_DAY_MIN_PROBLEMS - 1; $i++)
			$this->assertSame(0, $this->playInTraining($context, PlayOutcome::SolvedClean, $i)['mistake_training_xp']['clear']);
		$last = $this->playInTraining($context, PlayOutcome::SolvedClean, MistakeTraining::CLEAR_DAY_MIN_PROBLEMS - 1);

		$this->assertSame(['review' => 9, 'graduation' => 0, 'clear' => 23], $last['mistake_training_xp'], 'Half of the 45 the five reviews earned');
		$this->assertSame($beforeDailyXp + 45 + 23, (int) Auth::getUser()['daily_xp'], 'The bonus is XP of the day');
		$this->assertSame($beforeDailySolved + 5, (int) Auth::getUser()['daily_solved'], 'The bonus is not a solve');
		$day = $this->trainingDay($context);
		$this->assertSame(1, (int) $day['cleared']);
		$this->assertNotNull($day['cleared_at']);
		$this->assertSame(68, (int) $day['xp']);

		// A problem that comes due again later in the day is reviewed, but the day
		// was already cleared.
		MistakeTraining::makeDueNow($context->user['id'], (int) $context->tsumegos[0]['id']);
		Util::execute('UPDATE mistake_training_pool SET last_review_day = NULL, last_mistake_training_result_id = NULL WHERE user_id = ? AND tsumego_id = ?',
			[$context->user['id'], $context->tsumegos[0]['id']]);
		$again = $this->playInTraining($context, PlayOutcome::SolvedClean);
		$this->assertSame(['review' => 9, 'graduation' => 0, 'clear' => 0], $again['mistake_training_xp']);
	}

	public function testASmallTrainingDayEarnsNoClearBonus(): void
	{
		$context = $this->trainingOf(MistakeTraining::CLEAR_DAY_MIN_PROBLEMS - 1);

		for ($i = 0; $i < MistakeTraining::CLEAR_DAY_MIN_PROBLEMS - 1; $i++)
			$response = $this->playInTraining($context, PlayOutcome::SolvedClean, $i);

		$this->assertSame(0, $response['mistake_training_xp']['clear']);
		$this->assertSame(1, (int) $this->trainingDay($context)['cleared'], 'The day is cleared all the same');
	}

	public function testADayIsClearedOnlyWhenNothingIsLeftDue(): void
	{
		$context = $this->trainingOf(MistakeTraining::CLEAR_DAY_MIN_PROBLEMS);

		$this->playInTraining($context, PlayOutcome::Failed, 0);
		for ($i = 1; $i < MistakeTraining::CLEAR_DAY_MIN_PROBLEMS; $i++)
			$this->playInTraining($context, PlayOutcome::SolvedClean, $i);
		$this->assertSame(0, (int) $this->trainingDay($context)['cleared'], 'The missed problem is still due');

		$retry = $this->playInTraining($context, PlayOutcome::SolvedAfterMistake, 0);

		$this->assertSame(['review' => 0, 'graduation' => 0, 'clear' => 18], $retry['mistake_training_xp'],
			'Half of the 36 the four clean reviews earned');
		$this->assertSame(1, (int) $this->trainingDay($context)['cleared']);
	}
}

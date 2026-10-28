<?php

use App\Utility\Auth;
use App\Utility\HeroPowers;
use App\Utility\Util;

class HeroControllerTest extends IntegrationTestCase
{
	public function testSprintRequiresLevel()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'level' => 1]]);

		$this->post('/hero/sprint');

		$this->assertResponseCode(403);
	}

	public function testSprintStartsTheSprintRunFromZero()
	{
		new ContextPreparator([
			'user' => ['name' => 'kovarex', 'level' => 100],
			'achievement-conditions' => [['category' => AchievementCondition::CATEGORY_SPRINT, 'value' => 7]]]);

		$this->post('/hero/sprint');

		$this->assertResponseCode(200);
		$this->assertSame(0.0, AchievementCondition::value(Auth::getUserID(), AchievementCondition::CATEGORY_SPRINT));
	}

	public function testIntuitionRequiresLevel()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'level' => 1]]);

		$this->post('/hero/intuition');

		$this->assertResponseCode(403);
	}

	public function testRejuvenationRequiresLevel()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'level' => 1]]);

		$this->post('/hero/rejuvenation');

		$this->assertResponseCode(403);
	}

	public function testRevelationRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->post('/hero/revelation/1');

		$this->assertResponseCode(401);
	}

	public function testRevelationRespectsUseLimit()
	{
		$context = new ContextPreparator(['tsumego' => 1, 'user' => ['level' => 1]]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->post('/hero/revelation/' . $tsumegoId);
		$this->assertSame(1, (int) $context->reloadUser()['used_revelation']);

		$this->post('/hero/revelation/' . $tsumegoId);
		$this->assertResponseCode(403);
	}

	public function testRefinementRequiresLevelOrPremium()
	{
		new ContextPreparator(['user' => ['name' => 'lowlevel', 'level' => 1, 'premium' => 0]]);

		$this->get('/hero/refinement');

		$this->assertResponseCode(403);
	}

	public function testRefinementMakesProblemNearRatingGolden()
	{
		$context = new ContextPreparator([
			'user' => ['level' => HeroPowers::$REFINEMENT_MINIMUM_LEVEL, 'rating' => 1500],
			'tsumegos' => [
				['rating' => 3000, 'sets' => [['name' => 'set 1', 'num' => 1]]],
				['rating' => 1550, 'status' => 'S', 'sets' => [['name' => 'set 1', 'num' => 2]]],
				['rating' => 100, 'sets' => [['name' => 'set 1', 'num' => 3]]]]]);

		$this->get('/hero/refinement');

		$this->assertRedirect('/' . $context->tsumegos[1]['set-connections'][0]['id']);
		$this->assertSame([['tsumego_id' => $context->tsumegos[1]['id'], 'status' => 'G']], $this->statuses($context->user['id']));
		$this->assertSame(1, (int) $context->reloadUser()['used_refinement']);
	}

	public function testRefinementTakesAnyOfficialProblemWhenNoneIsNearRating()
	{
		$context = new ContextPreparator([
			'user' => ['level' => HeroPowers::$REFINEMENT_MINIMUM_LEVEL, 'rating' => 1500],
			'tsumegos' => [
				['rating' => 3000, 'sets' => [['name' => 'private set', 'num' => 1, 'public' => 0]]],
				['rating' => 100, 'sets' => [['name' => 'set 1', 'num' => 1]]]]]);

		$this->get('/hero/refinement');

		$this->assertRedirect('/' . $context->tsumegos[1]['set-connections'][0]['id']);
		$this->assertSame([['tsumego_id' => $context->tsumegos[1]['id'], 'status' => 'G']], $this->statuses($context->user['id']));
	}

	public function testRejuvenationRestoresHealthAndUnlocksFailedProblems()
	{
		$context = new ContextPreparator([
			'user' => ['level' => HeroPowers::$REJUVENATION_MINIMUM_LEVEL, 'damage' => 7, 'used_intuition' => 1],
			'other-users' => [['name' => 'bob']],
			'tsumegos' => [
				['set_order' => 1, 'statuses' => [['name' => 'F'], ['name' => 'F', 'user' => 'bob']]],
				['set_order' => 2, 'status' => 'X'],
				['set_order' => 3, 'status' => 'S']]]);

		$this->post('/hero/rejuvenation');

		$this->assertResponseCode(200);
		$this->assertSame([
			['tsumego_id' => $context->tsumegos[0]['id'], 'status' => 'V'],
			['tsumego_id' => $context->tsumegos[1]['id'], 'status' => 'W'],
			['tsumego_id' => $context->tsumegos[2]['id'], 'status' => 'S']], $this->statuses($context->user['id']));
		$this->assertSame([['tsumego_id' => $context->tsumegos[0]['id'], 'status' => 'F']], $this->statuses($context->otherUsers[0]['id']));
		$user = $context->reloadUser();
		$this->assertSame(0, (int) $user['damage']);
		$this->assertSame(0, (int) $user['used_intuition']);
		$this->assertSame(1, (int) $user['used_rejuvenation']);
	}

	public function testRevelationSolvesProblem()
	{
		$context = new ContextPreparator([
			'user' => ['level' => HeroPowers::$REVELATION_MINIMUM_LEVEL],
			'tsumegos' => [['set_order' => 1, 'status' => 'V'], ['set_order' => 2]]]);

		$this->post('/hero/revelation/' . $context->tsumegos[0]['id']);
		$this->assertResponseCode(200);
		$this->post('/hero/revelation/' . $context->tsumegos[1]['id']);
		$this->assertResponseCode(200);

		$this->assertSame([
			['tsumego_id' => $context->tsumegos[0]['id'], 'status' => 'S'],
			['tsumego_id' => $context->tsumegos[1]['id'], 'status' => 'S']], $this->statuses($context->user['id']));
	}

	public function testRevelationOfMissingProblemKeepsTheUse()
	{
		$context = new ContextPreparator(['tsumego' => 1, 'user' => ['level' => 1]]);

		$this->post('/hero/revelation/' . ($context->tsumegos[0]['id'] + 1));

		$this->assertResponseCode(404);
		$this->assertSame(0, (int) $context->reloadUser()['used_revelation']);
	}

	//The statuses of a player, ordered by problem.
	private function statuses(int $userID): array
	{
		return Util::query('SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ? ORDER BY tsumego_id', [$userID]);
	}
}

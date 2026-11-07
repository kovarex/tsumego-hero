<?php

//Which problem rating mode picks. Solved problems give no rating, so the unsolved ones in range go first.
//The pick is random, so each test asks several times.
class RatingModeControllerTest extends IntegrationTestCase
{
	private const PICKS = 10;

	public function testUnsolvedProblemsGoFirst(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'rating' => 1000],
			'tsumegos' => [
				['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]],
				['rating' => 1000, 'status' => 'S', 'sets' => [['name' => 'set 2', 'num' => 1]]],
				['rating' => 1000, 'status' => 'C', 'sets' => [['name' => 'set 3', 'num' => 1]]],
				['rating' => 1000, 'status' => 'F', 'sets' => [['name' => 'set 4', 'num' => 1]]],
				['rating' => 1000, 'status' => 'X', 'sets' => [['name' => 'set 5', 'num' => 1]]]]]);
		$this->login('alice');

		$this->assertSame([$context->tsumegos[0]['id']], $this->pickedTsumegoIDs());
	}

	public function testReviewProblemsCountAsUnsolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'rating' => 1000],
			'tsumegos' => [
				['rating' => 1000, 'status' => 'W', 'sets' => [['name' => 'set 1', 'num' => 1]]],
				['rating' => 1000, 'status' => 'S', 'sets' => [['name' => 'set 2', 'num' => 1]]]]]);
		$this->login('alice');

		$this->assertSame([$context->tsumegos[0]['id']], $this->pickedTsumegoIDs());
	}

	public function testSolvesOfOtherPlayersDoNotMatter(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'rating' => 1000],
			'other-users' => [['name' => 'bob']],
			'tsumegos' => [
				['rating' => 1000, 'statuses' => [['name' => 'S', 'user' => 'bob']], 'sets' => [['name' => 'set 1', 'num' => 1]]],
				['rating' => 1000, 'status' => 'S', 'sets' => [['name' => 'set 2', 'num' => 1]]]]]);
		$this->login('alice');

		$this->assertSame([$context->tsumegos[0]['id']], $this->pickedTsumegoIDs());
	}

	public function testSolvedProblemsComeBeforeLockedOnes(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'rating' => 1000],
			'tsumegos' => [
				['rating' => 1000, 'status' => 'S', 'sets' => [['name' => 'set 1', 'num' => 1]]],
				['rating' => 1000, 'status' => 'F', 'sets' => [['name' => 'set 2', 'num' => 1]]]]]);
		$this->login('alice');

		$this->assertSame([$context->tsumegos[0]['id']], $this->pickedTsumegoIDs());
	}

	public function testLockedProblemIsPickedWhenNothingElseIsInRange(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'rating' => 1000],
			'tsumegos' => [
				['rating' => 1000, 'status' => 'F', 'sets' => [['name' => 'set 1', 'num' => 1]]],
				['rating' => 2000, 'sets' => [['name' => 'set 2', 'num' => 1]]]]]);
		$this->login('alice');

		$this->assertSame([$context->tsumegos[0]['id']], $this->pickedTsumegoIDs());
	}

	public function testAllUnsolvedProblemsInRangeCanBePicked(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'rating' => 1000],
			'tsumegos' => [
				['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]],
				['rating' => 1000, 'status' => 'V', 'sets' => [['name' => 'set 2', 'num' => 1]]]]]);
		$this->login('alice');

		// both have a 1 in 2 chance each time, so missing one in 30 picks happens once in a billion runs
		$this->assertSame(
			[$context->tsumegos[0]['id'], $context->tsumegos[1]['id']],
			$this->pickedTsumegoIDs(30));
	}

	//With every problem in range solved, an unsolved one a bit out of range is better: a solved problem
	//gives no rating or XP. The narrowest wider range that has one is taken.
	public function testAnUnsolvedProblemOfAWiderRangeComesBeforeASolvedOneInRange(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'rating' => 1000],
			'tsumegos' => [
				['rating' => 1000, 'status' => 'S', 'sets' => [['name' => 'set 1', 'num' => 1]]],
				['rating' => 1400, 'sets' => [['name' => 'set 2', 'num' => 1]]],
				['rating' => 1800, 'sets' => [['name' => 'set 3', 'num' => 1]]]]]);
		$this->login('alice');

		$this->assertSame([$context->tsumegos[1]['id']], $this->pickedTsumegoIDs());
	}

	//When even the widest range has only solved problems, they come before an unsolved one far away
	public function testSolvedProblemsComeOnceTheWidestRangeHasNothingUnsolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'rating' => 1000],
			'tsumegos' => [
				['rating' => 1000, 'status' => 'S', 'sets' => [['name' => 'set 1', 'num' => 1]]],
				['rating' => 2200, 'sets' => [['name' => 'set 2', 'num' => 1]]]]]);
		$this->login('alice');

		$this->assertSame([$context->tsumegos[0]['id']], $this->pickedTsumegoIDs());
	}

	//A player far from every problem at the difficulty picked gets the closest problem, so they keep
	//playing and keep the slider to change the difficulty on (it was a redirect to the collections,
	//on every visit, as the difficulty can only be changed on the page of rating mode).
	public function testTheClosestProblemIsPickedWhenNoneIsInRangeOfTheDifficulty(): void
	{
		foreach ([[100, 1, 0], [2700, 7, 2]] as [$rating, $difficulty, $closest])
		{
			$context = new ContextPreparator([
				'user' => ['name' => 'alice', 'rating' => $rating],
				'tsumegos' => [
					['rating' => 300, 'sets' => [['name' => 'set 1', 'num' => 1]]],
					['rating' => 1500, 'sets' => [['name' => 'set 2', 'num' => 1]]],
					['rating' => 2600, 'sets' => [['name' => 'set 3', 'num' => 1]]]]]);
			$this->login('alice');
			// the slider of rating mode sets the cookie
			$_COOKIE['difficulty'] = (string) $difficulty;

			$this->assertSame([$context->tsumegos[$closest]['id']], $this->pickedTsumegoIDs(), "rating $rating, difficulty $difficulty");
			$this->assertSame($difficulty, (int) $this->viewVariable('difficulty'));
		}
	}

	//The distinct problems rating mode opened in the given number of visits, sorted by id.
	private function pickedTsumegoIDs(int $picks = self::PICKS): array
	{
		$tsumegoIDs = [];
		for ($i = 0; $i < $picks; $i++)
		{
			$this->get('/ratingMode');
			$this->assertResponseOk();
			$tsumegoIDs[] = (int) $this->viewVariable('tsumego')->id;
		}
		$tsumegoIDs = array_values(array_unique($tsumegoIDs));
		sort($tsumegoIDs);
		return $tsumegoIDs;
	}
}

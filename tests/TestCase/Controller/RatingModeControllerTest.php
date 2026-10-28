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

	//The distinct problems rating mode opened in the given number of visits, sorted by id.
	private function pickedTsumegoIDs(int $picks = self::PICKS): array
	{
		$tsumegoIDs = [];
		for ($i = 0; $i < $picks; $i++)
		{
			$this->get('/ratingMode');
			$this->assertResponseOk();
			$tsumegoIDs[] = (int) $this->viewVariable('t')->id;
		}
		$tsumegoIDs = array_values(array_unique($tsumegoIDs));
		sort($tsumegoIDs);
		return $tsumegoIDs;
	}
}

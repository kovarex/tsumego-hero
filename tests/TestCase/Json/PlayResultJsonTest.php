<?php

use App\Utility\PlayOutcome;

//The JSON the play page gets back for the result of a play: the new rating, the xp, the status and
//the achievements it unlocked, which the popups show.
class PlayResultJsonTest extends JsonGoldenTestCase
{
	private function postOutcome(ContextPreparator $context, PlayOutcome $outcome): void
	{
		$this->post('/tsumegos/result', [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'set_connection_id' => $context->tsumegos[0]['set-connections'][0]['id'],
			'seconds' => 12,
			'outcome' => $outcome->value]);
	}

	public function testSolved(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 1500], 'tsumego' => ['set_order' => 1, 'rating' => 1500]]);
		$this->postOutcome($context, PlayOutcome::SolvedClean);
		$this->assertJsonGolden('solved');
	}

	public function testFailed(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 1500], 'tsumego' => ['set_order' => 1, 'rating' => 1500]]);
		$this->postOutcome($context, PlayOutcome::Failed);
		$this->assertJsonGolden('failed');
	}

	public function testSolvedAfterAMistake(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 1500], 'tsumego' => ['set_order' => 1, 'rating' => 1500, 'status' => 'F']]);
		$this->postOutcome($context, PlayOutcome::SolvedAfterMistake);
		$this->assertJsonGolden('solved after a mistake');
	}

	//The 1000th problem solved unlocks an achievement, which comes with the result for its popup
	public function testSolvedUnlockingAnAchievement(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 1500, 'solved' => 999], 'tsumego' => ['set_order' => 1, 'rating' => 1500]]);
		$this->postOutcome($context, PlayOutcome::SolvedClean);
		$this->assertJsonGolden('solved unlocking an achievement');
	}
}

<?php

use App\Model\Table\CapturingRaceTypeTable;

//A capturing race quiz is a problem with a row in tsumego_capturing_race, which the play page loads
//with the problem
class CapturingRaceTest extends IntegrationTestCase
{
	public function testPlayPageShowsTheQuizOfACapturingRace(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'set_order' => 1,
			'sgf' => '(;GM[1]FF[4]SZ[19]TR[aa][ab]SQ[ba][bb];B[cc])',
			'capturing_race' => [
				'capturing_race_type_id' => CapturingRaceTypeTable::TYPE_NO_EYES_INSIDE_LIBERTIES,
				'liberty_count' => 6,
				'minimum_liberties' => 1,
				'maximum_liberties' => 3,
				'variance' => 1,
				'inside_liberties' => 3]]]);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->assertResponseOk();
		$this->assertResponseContains('options.multipleChoice = true;');
		$this->assertResponseContains('let multipleChoiceSemeaiType = ' . CapturingRaceTypeTable::TYPE_NO_EYES_INSIDE_LIBERTIES . '+"";');
		$this->assertResponseContains('let multipleChoiceInsideLiberties = 3+"";');
		$this->assertResponseContains('multipleChoiceLibertyCount = 6;');
		$this->assertResponseContains('id="multipleChoiceAlerts"');
		// the quiz always shows the player black
		$this->assertResponseContains('options.playerColor = "black"');
	}

	public function testPlayPageShowsNoQuizForAnyOtherProblem(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19];B[cc])']]);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->assertResponseOk();
		$this->assertResponseNotContains('options.multipleChoice = true;');
		$this->assertResponseNotContains('id="multipleChoiceAlerts"');
		$this->assertResponseContains('let multipleChoiceSemeaiType = +"";');
	}

	//The quiz values belong to their problem and go with it
	public function testTheQuizGoesWithItsProblem(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'set_order' => 1,
			'capturing_race' => ['capturing_race_type_id' => CapturingRaceTypeTable::TYPE_EYE_VS_NO_EYE, 'liberty_count' => 4, 'variance' => 0]]]);
		$tsumegoID = $context->tsumegos[0]['id'];
		$this->assertTrue($this->fetchTable('TsumegoCapturingRace')->exists(['tsumego_id' => $tsumegoID]));

		$this->fetchTable('Tsumego')->deleteAll(['id' => $tsumegoID]);

		$this->assertFalse($this->fetchTable('TsumegoCapturingRace')->exists(['tsumego_id' => $tsumegoID]));
	}
}

<?php

use App\Model\Table\SemeaiTypeTable;
use App\Utility\Util;

//A semeai problem is a problem with a row in tsumego_semeai, which the play page loads
//with the problem
class SemeaiTest extends IntegrationTestCase
{
	public function testPlayPageShowsTheQuestionOfASemeaiProblem(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'set_order' => 1,
			'sgf' => '(;GM[1]FF[4]SZ[19]TR[aa][ab]SQ[ba][bb];B[cc])',
			'semeai' => [
				'semeai_type_id' => SemeaiTypeTable::TYPE_NO_EYES_INSIDE_LIBERTIES,
				'liberty_count' => 6,
				'minimum_liberties' => 1,
				'maximum_liberties' => 3,
				'variance' => 1,
				'inside_liberties' => 3]]]);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->assertResponseOk();
		$problem = $this->playPage()['problem'];
		$this->assertSame(SemeaiTypeTable::TYPE_NO_EYES_INSIDE_LIBERTIES, $problem['semeai']['semeaiTypeID']);
		$this->assertSame(3, $problem['semeai']['insideLiberties']);
		$this->assertSame(6, $problem['semeai']['libertyCount']);
		$this->assertNotNull($problem['semeai']['question']);
		$this->assertResponseContains('id="multipleChoiceAlerts"');
		// a semeai problem always shows the player black
		$this->assertSame('black', $problem['playerColor']);
	}

	//The marks are counted as written, also when the editor wrapped their list
	public function testTheQuestionCountsTheMarksOfTheProblem(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'set_order' => 1,
			'sgf' => "(;GM[1]FF[4]SZ[19]TR[aa]\n[ab][ac]SQ[ba][bb][bc];B[cc])",
			'semeai' => [
				'semeai_type_id' => SemeaiTypeTable::TYPE_BIG_EYE_VS_SMALLER_EYE,
				'liberty_count' => 6,
				'minimum_liberties' => 1,
				'maximum_liberties' => 6,
				'variance' => 2,
				'inside_liberties' => 0]]]);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->assertSame(3, $this->playPage()['problem']['semeai']['question']['variance']);
	}

	public function testPlayPageShowsNoSemeaiQuestionForAnyOtherProblem(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19];B[cc])']]);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->assertResponseOk();
		$this->assertResponseNotContains('id="multipleChoiceAlerts"');
		$this->assertNull($this->playPage()['problem']['semeai']);
	}

	//The values the old columns of tsumego could leave empty: without a variance (like 0) the groups can
	//be as far apart as the minimum and maximum allow; without a liberty count no question can be
	//made, so the problem shows without one (it was a server error before); a type the explanation
	//doesn't know is shown with its id, as before
	public function testPlayPageOfSemeaiProblemsWithMissingValues(): void
	{
		foreach ([
			'no variance' => [['semeai_type_id' => SemeaiTypeTable::TYPE_EYE_VS_NO_EYE, 'liberty_count' => 5], true],
			'no liberty count' => [['semeai_type_id' => SemeaiTypeTable::TYPE_NO_EYES_FEW_INSIDE_LIBERTIES], false],
			'unknown type' => [['semeai_type_id' => 9, 'liberty_count' => 4, 'variance' => 1], true]] as $case => [$semeai, $hasQuestion])
		{
			if ($semeai['semeai_type_id'] === 9)
				Util::execute("INSERT IGNORE INTO semeai_type (id, name) VALUES (9, 'unknown 9')");
			$context = new ContextPreparator(['tsumego' => [
				'set_order' => 1,
				'sgf' => '(;GM[1]FF[4]SZ[19]TR[aa][ab]SQ[ba][bb];B[cc])',
				'semeai' => $semeai]]);

			$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

			$this->assertResponseOk($case);
			$problemSemeai = $this->playPage()['problem']['semeai'];
			$this->assertSame($semeai['semeai_type_id'], $problemSemeai['semeaiTypeID'], $case);
			$this->assertSame($hasQuestion, $problemSemeai['question'] !== null, $case);
		}
	}

	//The semeai values belong to their problem and go with it
	public function testTheSemeaiValuesGoWithTheirProblem(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'set_order' => 1,
			'semeai' => ['semeai_type_id' => SemeaiTypeTable::TYPE_EYE_VS_NO_EYE, 'liberty_count' => 4, 'variance' => 0]]]);
		$tsumegoID = $context->tsumegos[0]['id'];
		$this->assertTrue($this->fetchTable('TsumegoSemeai')->exists(['tsumego_id' => $tsumegoID]));

		$this->fetchTable('Tsumego')->deleteAll(['id' => $tsumegoID]);

		$this->assertFalse($this->fetchTable('TsumegoSemeai')->exists(['tsumego_id' => $tsumegoID]));
	}

	//The answer is checked against the liberties the page left each side, and the race is explained
	public function testTheAnswerIsCheckedAndExplained(): void
	{
		$context = new ContextPreparator(['tsumego' => [
			'set_order' => 1,
			'sgf' => '(;GM[1]FF[4]SZ[19]TR[aa][ab][ac]SQ[ba][bb][bc];B[cc])',
			'semeai' => [
				'semeai_type_id' => SemeaiTypeTable::TYPE_BIG_EYE_VS_SMALLER_EYE,
				'liberty_count' => 6,
				'minimum_liberties' => 1,
				'maximum_liberties' => 6,
				'variance' => 2,
				'inside_liberties' => 0]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// the liberties each side keeps: its count less those taken by the other color's marks
		$question = $browser->driver->executeScript('return window.eval("playPage.problem.semeai.question");');
		$black = 6 - $question['libertiesW'];
		$white = 6 - $question['libertiesB'];
		$correct = $black < $white ? 1 : ($black > $white ? 2 : 3);
		$browser->clickId('besogo-multipleChoice' . $correct);

		$this->assertTrue($browser->find('#multipleChoiceAlerts')->isDisplayed());
		$explanation = $browser->find('#multipleChoiceText')->getText();
		$this->assertStringContainsString('Semeai Type 5: Big eye vs smaller eye.', $explanation);
		$this->assertStringContainsString("Black has $black liberties.", $explanation);
		$this->assertStringContainsString("White has $white liberties.", $explanation);
		foreach ([1, 2, 3, 4] as $answer)
			$this->assertSame(
				$answer === $correct ? 'rgb(62, 207, 120)' : 'rgb(224, 116, 127)',
				$browser->find('#besogo-multipleChoice' . $answer)->getCssValue('background-color'),
				"answer $answer");
	}
}

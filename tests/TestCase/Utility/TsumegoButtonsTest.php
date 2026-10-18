<?php

use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use App\Utility\TsumegoButton;

class TsumegoButtonsTest extends TestCaseWithAuth
{
	public function testMistakeTrainingOneButtonPerTsumegoInMultipleSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'sets' => [
					['name' => 'Set A', 'num' => 1],
					['name' => 'Set B', 'num' => 2],
				],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);

		$this->assertCount(1, $buttons, 'A tsumego in multiple sets should appear once in the training queue');
		$this->assertSame($scId, $buttons[0]->setConnectionID, 'Button should point at the current set connection');
	}

	public function testMistakeTrainingPrefersCurrentSetConnection(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'sets' => [
					['name' => 'Set A', 'num' => 1],
					['name' => 'Set B', 'num' => 2],
				],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$secondScId = (int) $context->tsumegos[0]['set-connections'][1]['id'];

		$buttons = MistakeTraining::buildQueueButtons($secondScId);

		$this->assertCount(1, $buttons);
		$this->assertSame($secondScId, $buttons[0]->setConnectionID, 'Queue should prefer the set connection the user is on');
	}

	public function testMistakeTrainingQueueKeepsTheOrderTheProblemsCameIn(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-2 days'))],
				],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);

		$this->assertCount(2, $buttons);
		$this->assertSame($context->tsumegos[0]['id'], $buttons[0]->tsumegoID,
			'The problem that came into the training first comes first, however overdue the others are');
		$this->assertSame(1, $buttons->currentOrder);
	}

	public function testMistakeTrainingQueueExcludesNotDueAndDeleted(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+5 days'))],
				],
				[
					'set_order' => 3,
					'deleted' => date('Y-m-d H:i:s'),
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
				],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);

		$this->assertCount(1, $buttons, 'Not-yet-due and soft-deleted problems should be excluded from the queue');
	}

	public function testMistakeTrainingEdgeLinksPointToQueueLanding(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
				],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);
		$links = [];
		$setFunction = function ($name, $value) use (&$links) {
			$links[$name] = $value;
		};
		$buttons->exportCurrentAndPreviousLink($setFunction, null, $scId, null, '/mistake-training');

		$this->assertSame('/mistake-training', $links['previousLink'], 'Previous at the start of the queue should go to the queue landing');
		$this->assertSame('/mistake-training/play/' . $context->tsumegos[1]['set-connections'][0]['id'], $links['nextLink'],
			'Queue navigation must stay on the training route, so the route can re-assert the mode');
	}

	public function testMistakeTrainingQueueButtonsStayOnTheTrainingRoute(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);
		ob_start();
		$buttons[0]->render(0, false);
		$html = ob_get_clean();

		$this->assertStringContainsString('href="/mistake-training/play/' . $scId . '"', $html,
			'Challenge buttons in the training queue must link back to the training route');
	}

	public function testMistakeTrainingButtonsShowTheRungBadge(): void
	{
		$tsumegos = [];
		foreach (MistakeTraining::ladder() as $rung => $days)
			$tsumegos[] = [
				'set_order' => $rung + 1,
				'status' => [
					'name' => 'S',
					'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')),
					'mistake_training_rung' => $rung,
				],
			];
		$context = new ContextPreparator(['user' => ['name' => 'testuser'], 'tsumegos' => $tsumegos]);

		$buttons = MistakeTraining::buildQueueButtons((int) $context->tsumegos[0]['set-connections'][0]['id']);

		foreach (MistakeTraining::ladder() as $rung => $days)
		{
			$html = $this->renderButton($buttons[$rung]);
			$this->assertStringContainsString('<div class="problem-nav__rung problem-nav__rung--' . $rung . '">' . $days . 'd</div>', $html,
				"The button of a problem on rung $rung shows its own badge with the wait of the rung");
			$this->assertStringNotContainsString('problem-nav__ratio', $html, 'The training has no ratio to show');
			$this->assertStringContainsString('problem-nav__item--N', $html, 'A problem due today is grey, whatever its status');
		}
	}

	public function testMistakeTrainingButtonOfAGraduationShowsItsOwnBadge(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 5],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];
		MistakeTraining::recordResult($context->user['id'], (int) $context->tsumegos[0]['id'], PlayOutcome::SolvedClean, null, true);

		$html = $this->renderButton(MistakeTraining::buildQueueButtons($scId)[0]);

		$this->assertStringContainsString('problem-nav__rung--graduated', $html);
		$this->assertStringContainsString('problem-nav__item--S', $html, 'A graduation is solved today, so it is green');
		$this->assertStringContainsString('Graduated today', $html);
	}

	public function testButtonsOutsideTheTrainingHaveNoRungBadge(): void
	{
		$button = new TsumegoButton(1, 2, 3, 'S', 1000.0, '');
		$button->performance = '';

		$html = $this->renderButton($button);

		$this->assertStringNotContainsString('problem-nav__rung', $html, 'Normal play renders the button as it always did');
		$this->assertStringContainsString('<div class="problem-nav__ratio">-</div>', $html);
		$this->assertStringContainsString('<div class="problem-nav__time">-</div>', $html);
		$this->assertStringContainsString(TsumegoStatus::label('S'), $html, 'Outside the training the tooltip names the status');
	}

	private function renderButton(TsumegoButton $button): string
	{
		ob_start();
		$button->render();
		return ob_get_clean();
	}
}

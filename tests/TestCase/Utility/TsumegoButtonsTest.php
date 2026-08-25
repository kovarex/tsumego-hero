<?php

App::uses('TsumegoButtons', 'Utility');
App::uses('MistakeTraining', 'Utility');

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
				'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
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
				'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$secondScId = (int) $context->tsumegos[0]['set-connections'][1]['id'];

		$buttons = MistakeTraining::buildQueueButtons($secondScId);

		$this->assertCount(1, $buttons);
		$this->assertSame($secondScId, $buttons[0]->setConnectionID, 'Queue should prefer the set connection the user is on');
	}

	public function testMistakeTrainingQueueOrdersByDueDate(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-2 days'))],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
				],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);

		$this->assertCount(2, $buttons);
		$this->assertSame($context->tsumegos[0]['id'], $buttons[0]->tsumegoID, 'Most overdue problem should come first');
		$this->assertSame(1, $buttons->currentOrder);
	}

	public function testMistakeTrainingQueueExcludesNotDueAndDeleted(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('+5 days'))],
				],
				[
					'set_order' => 3,
					'deleted' => date('Y-m-d H:i:s'),
					'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
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
				'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'status' => ['name' => 'V', 'mt_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
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
		$this->assertSame('/' . $context->tsumegos[1]['set-connections'][0]['id'], $links['nextLink']);
	}
}

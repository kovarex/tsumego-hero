<?php

class TimeModeControllerTest extends ControllerTestCase
{
	public function testStartTimeModeWithoutSpecifyingCategoryIDThrowsException()
	{
		$context = new ContextPreparator(['tsumego' => 1, 'time-mode-ranks' => ['5k']]);
		$this->assertTrue(Auth::isInLevelMode());
		$this->expectException(BadRequestException::class);
		$this->expectExceptionMessage('Time mode category not specified.');
		$this->testAction('/timeMode/start?rankID=' . $context->timeModeRanks[0]['id'], ['return' => 'view']);
	}

	public function testStartTimeModeWithoutSpecifyingRankIDThrowsException()
	{
		new ContextPreparator([]);
		$this->assertTrue(Auth::isInLevelMode());
		$this->expectException(BadRequestException::class);
		$this->expectExceptionMessage('Time mode rank not specified.');
		$this->testAction('/timeMode/start?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED);
	}

	public function testTimeModePlayWithoutBeingLoggedInRedirectsToLogin()
	{
		foreach (['/timeMode/play', '/timeMode/overview', '/timeMode/result'] as $page)
		{
			new ContextPreparator(['user' => null]);
			$this->testAction($page);
			$this->assertSame(Util::getInternalAddress() . '/users/login', $this->headers['Location']);
		}
	}

	public function testTimeModePlayWithoutSessionBeingInProgress()
	{
		new ContextPreparator();
		$this->testAction('/timeMode/play');
		$this->assertSame(Util::getInternalAddress() . '/timeMode/overview', $this->headers['Location']);
	}

	public function testTimeModePlayWithSessionToBeFinished()
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]]]]);

		// session in progress with just one attempt which is solved
		$this->testAction('/timeMode/play');
		$this->assertSame(Util::getInternalAddress() . '/timeMode/result/' . $context->timeModeSessions[0]['id'], $this->headers['Location']);
	}

	public function testTimeModePlaySwitchesToTimeMode()
	{
		new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]]]]);

		$this->testAction('/timeMode/play');
		$this->assertTrue(Auth::isInTimeMode());
	}

	public function testTimeModePlayOfTsumegoWithoutSetConnection()
	{
		$contextParameters = [];
		$contextParameters['tsumego'] = ['rating' => 1000];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['time-mode-sessions'] [] = [
			'category' => TimeModeUtil::$CATEGORY_BLITZ,
			'rank' => '5k',
			'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]];
		$context = new ContextPreparator($contextParameters);

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Time mode session contains tsumego without a set connection.');
		$this->testAction('/timeMode/play');
	}

	public function testOpeningTimeModeResultWihoutSpcificSessionUnlocked()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		new ContextPreparator($contextParameters);

		$this->testAction('/timeMode/result');
		// no redirect
		$this->assertSame(null, $this->headers['Location']);
	}

	public function testOpeningTimeModeResultWithInvalidTimeSessionID()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		new ContextPreparator($contextParameters);

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('Time Mode Session not found.');
		$this->testAction('/timeMode/result/56465487');
	}

	public function testResultPageShowsSetTitlesAndAttempts()
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 42],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]],
			]],
		]);

		$this->testAction('/timeMode/result/' . $context->timeModeSessions[0]['id'], ['return' => 'view']);

		// Set title and order appear in the attempt link
		$this->assertStringContainsString('test set - 42', $this->view);
	}

	public function testResultPageShowsSessionHeader(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]],
			]],
		]);

		$this->testAction('/timeMode/result/' . $context->timeModeSessions[0]['id'], ['return' => 'view']);

		$this->assertStringContainsString('Blitz', $this->view);
		$this->assertStringContainsString('5k', $this->view);
		$this->assertStringContainsString('passed', $this->view);
	}

	public function testResultPageShowsFailedStatus(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_FAILED,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_FAILED]],
			]],
		]);

		$this->testAction('/timeMode/result/' . $context->timeModeSessions[0]['id'], ['return' => 'view']);

		$this->assertStringContainsString('failed', $this->view);
	}

	public function testResultPageShowsSolvedCountInHeader(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
					['order' => 3, 'status' => TimeModeUtil::$ATTEMPT_RESULT_FAILED],
				],
			]],
		]);

		$this->testAction('/timeMode/result/' . $context->timeModeSessions[0]['id'], ['return' => 'view']);

		$this->assertStringContainsString('passed(2/10)', $this->view);
	}

	public function testResultPageShowsAttemptStatusLabels(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_FAILED],
					['order' => 3, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED],
				],
			]],
		]);

		$this->testAction('/timeMode/result/' . $context->timeModeSessions[0]['id'], ['return' => 'view']);

		$this->assertStringContainsString('solved', $this->view);
		$this->assertStringContainsString('failed', $this->view);
		$this->assertStringContainsString('queued', $this->view);
	}

	public function testResultPageShowsAttemptOrderNumber(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
				],
			]],
		]);

		$this->testAction('/timeMode/result/' . $context->timeModeSessions[0]['id'], ['return' => 'view']);

		$this->assertStringContainsString('#1', $this->view);
		$this->assertStringContainsString('#2', $this->view);
	}

	public function testResultPageShowsMultipleCategories(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k', '1d'],
			'time-mode-sessions' => [
				[
					'category' => TimeModeUtil::$CATEGORY_BLITZ,
					'rank' => '5k',
					'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]],
				],
				[
					'category' => TimeModeUtil::$CATEGORY_SLOW_SPEED,
					'rank' => '1d',
					'status' => TimeModeUtil::$SESSION_STATUS_FAILED,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_FAILED]],
				],
			],
		]);

		$this->testAction('/timeMode/result/' . $context->timeModeSessions[0]['id'], ['return' => 'view']);

		$this->assertStringContainsString('Blitz', $this->view);
		$this->assertStringContainsString('Slow', $this->view);
		$this->assertStringContainsString('5k', $this->view);
		$this->assertStringContainsString('1d', $this->view);
	}

	public function testResultPageShowsBestNotCurrentWhenDifferent(): void
	{
		// Two sessions for same category x rank: one with 30pt (best), one with 10pt (current)
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [
				[
					'category' => TimeModeUtil::$CATEGORY_BLITZ,
					'rank' => '5k',
					'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]],
				],
				[
					'category' => TimeModeUtil::$CATEGORY_BLITZ,
					'rank' => '5k',
					'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]],
				],
			],
		]);

		Util::execute('UPDATE time_mode_session SET points = 30 WHERE id = ?', [$context->timeModeSessions[0]['id']]);
		Util::execute('UPDATE time_mode_session SET points = 10 WHERE id = ?', [$context->timeModeSessions[1]['id']]);

		// Navigate to the 10pt (worse) session
		$this->testAction('/timeMode/result/' . $context->timeModeSessions[1]['id'], ['return' => 'view']);

		$this->assertStringContainsString('Best: passed(1/10)', $this->view);
		$this->assertStringContainsString('Result: passed(1/10)- 10 points', $this->view);
	}

	public function testResultPageWithoutAttemptsStillRenders(): void
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
			]],
		]);

		$this->testAction('/timeMode/result/' . $context->timeModeSessions[0]['id'], ['return' => 'view']);

		$this->assertStringContainsString('Blitz', $this->view);
		$this->assertStringContainsString('5k', $this->view);
		$this->assertStringContainsString('passed(0/10)', $this->view);
	}
}

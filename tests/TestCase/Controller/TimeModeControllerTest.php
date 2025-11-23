<?php

require_once(__DIR__ . '/../../ContextPreparator.php');

class TimeModeControllerTest extends ControllerTestCase
{
	public function testStartTimeModeWithoutSpecifyingCategoryIDThrowsException()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => 1]]],
			'time-mode-ranks' => ['5k']]);
		$this->assertTrue(Auth::isInLevelMode());
		$this->expectException(AppException::class);
		$this->expectExceptionMessage('Time mode category not specified.');
		$this->testAction('/timeMode/start?rankID=' . $context->timeModeRanks[0]['id'], ['return' => 'view']);
	}

	public function testStartTimeModeWithoutSpecifyingRankIDThrowsException()
	{
		$this->assertTrue(Auth::isInLevelMode());
		$this->expectException(AppException::class);
		$this->expectExceptionMessage('Time mode rank not specified.');
		$this->testAction('/timeMode/start?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED);
	}

	public function testTimeModePlayWithoutBeingLoggedInRedirectsToLogin()
	{
		foreach (['/timeMode/play', '/timeMode/overview', '/timeMode/result'] as $page)
		{
			new ContextPreparator();
			$this->testAction($page);
			$this->assertSame(Util::getInternalAddress() . '/users/login', $this->headers['Location']);
		}
	}

	public function testTimeModePlayWithoutSessionBeingInProgress()
	{
		new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE]]);
		$this->testAction('/timeMode/play');
		$this->assertSame(Util::getInternalAddress() . '/timeMode/overview', $this->headers['Location']);
	}

	public function testTimeModePlayWithSessionToBeFinished()
	{
		$contextParameters = [];
		$contextParameters['tsumego'] = [];
		$contextParameters['user'] = ['mode' => Constants::$TIME_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['time-mode-sessions'] [] = [
			'category' => TimeModeUtil::$CATEGORY_BLITZ,
			'rank' => '5k',
			'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]];
		$context = new ContextPreparator($contextParameters);
		// session in progress with just one attempt which is solved

		$this->testAction('/timeMode/play');
		$this->assertSame(Util::getInternalAddress() . '/timeMode/result/' . $context->timeModeSessions[0]['id'], $this->headers['Location']);
	}

	public function testTimeModePlaySwitchesToTimeMode()
	{
		$contextParameters = [];
		$contextParameters['tsumego'] = ['sets' => [['name' => 'tsumego set 1', 'num' => 1]]];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['time-mode-sessions'] [] = [
			'category' => TimeModeUtil::$CATEGORY_BLITZ,
			'rank' => '5k',
			'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]];
		$context = new ContextPreparator($contextParameters);

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

		$this->expectException(AppException::class);
		$this->expectExceptionMessage('Time Mode Session not found.');
		$this->testAction('/timeMode/result/56465487');
	}
}

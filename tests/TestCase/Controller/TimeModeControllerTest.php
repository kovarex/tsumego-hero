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

	public function testOpeningTimeModeResultWithoutSpecificSessionUnlocked()
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

	public function testSolvingAProblemInTimeModeRecordsTheTimeItTook()
	{
		// the time the client reports is kept between a hundredth of a second and the time the problem is given
		foreach ([
			['category' => TimeModeUtil::$CATEGORY_BLITZ, 'seconds' => 5, 'recordedSeconds' => 5],
			['category' => TimeModeUtil::$CATEGORY_BLITZ, 'seconds' => -3, 'recordedSeconds' => 0.01],
			['category' => TimeModeUtil::$CATEGORY_SLOW_SPEED,
				'seconds' => TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS * 5,
				'recordedSeconds' => TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS]] as $reported)
		{
			$context = new ContextPreparator([
				'tsumego' => 1,
				'time-mode-ranks' => ['5k'],
				'time-mode-sessions' => [[
					'category' => $reported['category'],
					'rank' => '5k',
					'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]]]]);

			// the player opens the play page, which is what puts the account into time mode
			$this->testAction('/timeMode/play');

			Auth::init();
			$this->testAction('/tsumegos/result', [
				'method' => 'POST',
				'data' => [
					'tsumego_id' => $context->tsumegos[0]['id'],
					'seconds' => $reported['seconds'],
					'solved' => true]]);

			$attempt = ClassRegistry::init('TimeModeAttempt')->find('first', [
				'conditions' => ['time_mode_session_id' => $context->timeModeSessions[0]['id']]]);
			$this->assertSame(
				TimeModeUtil::$ATTEMPT_RESULT_SOLVED,
				(int) $attempt['TimeModeAttempt']['time_mode_attempt_status_id']);
			$this->assertSame(
				(float) $reported['recordedSeconds'],
				(float) $attempt['TimeModeAttempt']['seconds']);
		}
	}

	public function testAProblemThatWasGivenUpOnStaysGivenUpOn()
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'tsumegos' => [2],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [
					// given up on in one tab, solved in another one at the same time
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_STATUS_SKIPPED, 'started-seconds-ago' => 0],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);

		$this->testAction('/timeMode/play');

		Auth::init();
		$this->testAction('/tsumegos/result', [
			'method' => 'POST',
			'data' => [
				'tsumego_id' => $context->tsumegos[0]['id'],
				'seconds' => 5,
				'solved' => true]]);

		$attempt = ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => ['time_mode_session_id' => $context->timeModeSessions[0]['id'], 'order' => 1]]);
		$this->assertSame(
			TimeModeUtil::$ATTEMPT_STATUS_SKIPPED,
			(int) $attempt['TimeModeAttempt']['time_mode_attempt_status_id']);

		// the problem the player is on is not disturbed by the ignored result
		$this->testAction('/timeMode/play');
		$this->assertSame(null, $this->headers['Location']);
	}

	public function testTimeModeProblemLeftBehindIsRecordedAsTimedOutWithItsFullTime()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$TIME_MODE],
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_SLOW_SPEED,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED,
					// the problem was presented and left behind, so its time ran out
					'started-seconds-ago' => TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS + 1]]]]]);

		$this->testAction('/timeMode/play');

		$attempt = ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => ['time_mode_session_id' => $context->timeModeSessions[0]['id']]]);
		$this->assertSame(
			TimeModeUtil::$ATTEMPT_STATUS_TIMEOUT,
			(int) $attempt['TimeModeAttempt']['time_mode_attempt_status_id']);
		$this->assertSame(TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS, (int) $attempt['TimeModeAttempt']['seconds']);
		$this->assertSame(0, (int) $attempt['TimeModeAttempt']['points']);
	}

	public function testTimeModeServesTheProblemAtTheRequestedPosition()
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'tsumegos' => [2],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);

		$html = $this->testAction('/timeMode/play/2', ['return' => 'contents']);

		preg_match('/var tsumegoID = (\d+);/', $html, $matches);
		$this->assertSame((int) $context->tsumegos[1]['id'], (int) ($matches[1] ?? 0));
	}

	public function testTimeModeNextLinkLeadsToTheNextProblemOrToTheResult()
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'tsumegos' => [2],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);

		foreach ([1 => '/timeMode/play/2',
			2 => '/timeMode/result/' . $context->timeModeSessions[0]['id']] as $position => $expectedLink)
		{
			$html = $this->testAction('/timeMode/play/' . $position, ['return' => 'contents']);

			preg_match('/var noSkipNextButtonLink = "([^"]*)";/', $html, $matches);
			$this->assertSame($expectedLink, $matches[1] ?? 'not rendered');
		}
	}

	public function testTimeModeSessionDoesNotRepeatAProblemThatIsInSeveralSets()
	{
		$context = new ContextPreparator([
			'tsumego' => ['rating' => 1600, 'sets' => [
				['name' => 'time mode set one', 'num' => 1],
				['name' => 'time mode set two', 'num' => 2]]],
			'time-mode-ranks' => ['5k']]);

		$this->testAction('/timeMode/start?categoryID=' . TimeModeUtil::$CATEGORY_BLITZ
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		$session = ClassRegistry::init('TimeModeSession')->find('first', [
			'conditions' => ['user_id' => $context->user['id']]]);
		$attempts = ClassRegistry::init('TimeModeAttempt')->find('all', [
			'conditions' => ['time_mode_session_id' => $session['TimeModeSession']['id']],
			'order' => 'id ASC']);

		$tsumegoIDs = array_map(fn($attempt) => (int) $attempt['TimeModeAttempt']['tsumego_id'], $attempts);
		$this->assertSame([(int) $context->tsumegos[0]['id']], $tsumegoIDs);
	}

	public function testTimeModeResultWaitsWhileAProblemIsStillUnrecorded()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$TIME_MODE],
			'tsumego' => 1,
			'tsumegos' => [2],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);

		$html = $this->testAction(
			'/timeMode/result/' . $context->timeModeSessions[0]['id'],
			['return' => 'contents']);

		$this->assertStringContainsString('Finishing your session', $html);
		$session = ClassRegistry::init('TimeModeSession')->findById($context->timeModeSessions[0]['id']);
		$this->assertSame(
			TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			(int) $session['TimeModeSession']['time_mode_session_status_id']);
	}

	public function testTimeModeResultShowsTheScoreOutOfTheProblemsItHad()
	{
		// the count comes from the session itself, which can have fewer problems than a full one
		foreach ([1, 2, 7] as $problemCount)
		{
			$tsumegos = [];
			$attempts = [];
			for ($order = 1; $order <= $problemCount; $order++)
			{
				$attempts[] = ['order' => $order, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]
					+ ($order == 1 ? [] : ['tsumego_id' => 'other:' . ($order - 1)]);
				if ($order > 1)
					$tsumegos[] = $order;
			}

			$context = new ContextPreparator([
				'user' => ['mode' => Constants::$TIME_MODE],
				'tsumego' => 1,
				'tsumegos' => $tsumegos,
				'time-mode-ranks' => ['5k'],
				'time-mode-sessions' => [[
					'category' => TimeModeUtil::$CATEGORY_BLITZ,
					'rank' => '5k',
					'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
					'attempts' => $attempts]]]);

			$html = $this->testAction(
				'/timeMode/result/' . $context->timeModeSessions[0]['id'],
				['return' => 'contents']);

			$this->assertStringContainsString('passed(' . $problemCount . '/' . $problemCount . ')', $html);
		}
	}

	public function testTimeModeSkipGivesUpOnTheProblemBeingShown()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$TIME_MODE],
			'tsumego' => 1,
			'tsumegos' => [2, 3],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [
					// the first problem was solved and its result is still on its way, the second
					// one is the one being shown
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'started-seconds-ago' => 0],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'started-seconds-ago' => 0, 'tsumego_id' => 'other:1'],
					['order' => 3, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:2']]]]]);
		$sessionID = $context->timeModeSessions[0]['id'];

		$html = $this->testAction('/timeMode/play/2', ['return' => 'contents']);
		preg_match('/var nextButtonLink = "([^"]*)";/', $html, $matches);
		$this->assertSame('/timeMode/skip/2', $matches[1] ?? 'not rendered');

		$this->testAction('/timeMode/skip/2');
		$this->assertSame(Util::getInternalAddress() . '/timeMode/play/3', $this->headers['Location']);

		$attempt = ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => ['time_mode_session_id' => $sessionID, 'order' => 2]]);
		$this->assertSame(
			TimeModeUtil::$ATTEMPT_STATUS_SKIPPED,
			(int) $attempt['TimeModeAttempt']['time_mode_attempt_status_id']);

		$html = $this->testAction('/timeMode/play/3', ['return' => 'contents']);

		preg_match('/var tsumegoID = (\d+);/', $html, $matches);
		$this->assertSame((int) $context->tsumegos[2]['id'], (int) ($matches[1] ?? 0));
	}

	public function testTimeModeSkipOfAProblemThatIsNotOpenSkipsNothing()
	{
		foreach ([null, 1] as $position)
		{
			$context = new ContextPreparator([
				'user' => ['mode' => Constants::$TIME_MODE],
				'tsumego' => 1,
				'tsumegos' => [2],
				'time-mode-ranks' => ['5k'],
				'time-mode-sessions' => [[
					'category' => TimeModeUtil::$CATEGORY_BLITZ,
					'rank' => '5k',
					'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
					'attempts' => [
						['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
						// the second problem is the one being played, the first one is recorded already
						['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'started-seconds-ago' => 0, 'tsumego_id' => 'other:1']]]]]);
			$sessionID = $context->timeModeSessions[0]['id'];

			$this->testAction('/timeMode/skip' . ($position === null ? '' : '/' . $position));
			$this->assertSame(
				Util::getInternalAddress() . '/timeMode/play' . ($position === null ? '' : '/2'),
				$this->headers['Location']);

			$attempts = ClassRegistry::init('TimeModeAttempt')->find('all', [
				'conditions' => ['time_mode_session_id' => $sessionID],
				'order' => 'TimeModeAttempt.order ASC']);
			$this->assertSame(
				TimeModeUtil::$ATTEMPT_RESULT_SOLVED,
				(int) $attempts[0]['TimeModeAttempt']['time_mode_attempt_status_id']);
			$this->assertSame(
				TimeModeUtil::$ATTEMPT_RESULT_QUEUED,
				(int) $attempts[1]['TimeModeAttempt']['time_mode_attempt_status_id']);
		}
	}

	public function testTimeModePlayOfAProblemThatIsAlreadyDoneServesTheNextOne()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$TIME_MODE],
			'tsumego' => 1,
			'tsumegos' => [2],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [
					// the solved problem was opened long enough ago that its time would be up
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED, 'started-seconds-ago' => 3600],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);
		$sessionID = $context->timeModeSessions[0]['id'];

		$html = $this->testAction('/timeMode/play/1', ['return' => 'contents']);

		preg_match('/var tsumegoID = (\d+);/', $html, $matches);
		$this->assertSame((int) $context->tsumegos[1]['id'], (int) ($matches[1] ?? 0));

		$attempt = ClassRegistry::init('TimeModeAttempt')->find('first', [
			'conditions' => ['time_mode_session_id' => $sessionID, 'order' => 1]]);
		$this->assertSame(
			TimeModeUtil::$ATTEMPT_RESULT_SOLVED,
			(int) $attempt['TimeModeAttempt']['time_mode_attempt_status_id']);
	}

	public function testTimeModeServesTheNextUnplayedProblemAfterThePositionAskedFor()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$TIME_MODE],
			'tsumego' => 1,
			'tsumegos' => [2, 3],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [
					// the first problem is being played, its result has not been recorded yet
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'started-seconds-ago' => 0],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED, 'tsumego_id' => 'other:1'],
					['order' => 3, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:2']]]]]);

		$html = $this->testAction('/timeMode/play/2', ['return' => 'contents']);

		preg_match('/var tsumegoID = (\d+);/', $html, $matches);
		$this->assertSame((int) $context->tsumegos[2]['id'], (int) ($matches[1] ?? 0));
	}

	public function testTimeModePlayPastTheEndServesTheNextUnplayedProblem()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$TIME_MODE],
			'tsumego' => 1,
			'tsumegos' => [2, 3],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1'],
					['order' => 3, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:2']]]]]);

		$html = $this->testAction('/timeMode/play/9', ['return' => 'contents']);

		preg_match('/var tsumegoID = (\d+);/', $html, $matches);
		$this->assertSame((int) $context->tsumegos[1]['id'], (int) ($matches[1] ?? 0));
	}

	public function testTimeModePlayPastTheEndWaitsWhileAResultIsOnItsWay()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$TIME_MODE],
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED,
					// the only problem was played, its result has not reached the database yet
					'started-seconds-ago' => 0]]]]]);

		$html = $this->testAction('/timeMode/play/2', ['return' => 'contents']);

		$this->assertStringContainsString('Finishing your session', $html);
	}
}

<?php

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use App\Model\Table\AchievementConditionTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\PlayOutcome;
use App\Utility\TimeModeUtil;
use App\Utility\Util;

class TimeModeControllerTest extends IntegrationTestCase
{
	public function testStartTimeModeWithoutSpecifyingCategoryIDThrowsException()
	{
		$context = new ContextPreparator(['tsumego' => 1, 'time-mode-ranks' => ['5k']]);
		$this->assertTrue(Auth::isInLevelMode());
		$this->disableErrorHandlerMiddleware();
		$this->expectException(BadRequestException::class);
		$this->expectExceptionMessage('Time mode category not specified.');

		$this->get('/timeMode/start?rankID=' . $context->timeModeRanks[0]['id']);
	}

	public function testStartTimeModeWithoutSpecifyingRankIDThrowsException()
	{
		new ContextPreparator([]);
		$this->assertTrue(Auth::isInLevelMode());
		$this->disableErrorHandlerMiddleware();
		$this->expectException(BadRequestException::class);
		$this->expectExceptionMessage('Time mode rank not specified.');

		$this->get('/timeMode/start?categoryID=' . TimeModeCategoryTable::SLOW);
	}

	public function testStartTimeModeRequiresLogin()
	{
		$context = new ContextPreparator(['user' => null, 'time-mode-ranks' => ['5k']]);

		$this->get('/timeMode/start?categoryID=' . TimeModeCategoryTable::SLOW . '&rankID=' . $context->timeModeRanks[0]['id']);

		$this->assertResponseCode(401);
	}

	public function testTimeModePlayWithoutBeingLoggedInRedirectsToLogin()
	{
		foreach (['/timeMode/play', '/timeMode/overview', '/timeMode/result'] as $page)
		{
			new ContextPreparator(['user' => null]);
			$this->get($page);
			$this->assertSame(Util::getInternalAddress() . '/users/login', $this->headerValue('Location'));
		}
	}

	public function testTimeModePlayWithoutSessionBeingInProgress()
	{
		new ContextPreparator();
		$this->post('/timeMode/play');
		$this->assertSame(Util::getInternalAddress() . '/timeMode/overview', $this->headerValue('Location'));
	}

	public function testTimeModePlayWithSessionToBeFinished()
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]]]]);

		// session in progress with just one attempt which is solved
		$this->post('/timeMode/play');
		$this->assertSame(Util::getInternalAddress() . '/timeMode/result/' . $context->timeModeSessions[0]->id, $this->headerValue('Location'));
	}

	public function testTimeModePlaySwitchesToTimeMode()
	{
		new ContextPreparator([
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]]]]);

		$this->post('/timeMode/play');
		$this->assertTrue(Auth::isInTimeMode());
	}

	public function testTimeModePlayOfTsumegoWithoutSetConnection()
	{
		$contextParameters = [];
		$contextParameters['tsumego'] = ['rating' => 1000];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['time-mode-sessions'] [] = [
			'category' => TimeModeCategoryTable::BLITZ,
			'rank' => '5k',
			'status' => TimeModeSessionStatusTable::IN_PROGRESS,
			'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]];
		$context = new ContextPreparator($contextParameters);

		$this->disableErrorHandlerMiddleware();
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Time mode session contains tsumego without a set connection.');

		$this->post('/timeMode/play');
	}

	public function testOpeningTimeModeResultWithoutSpecificSessionUnlocked()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		new ContextPreparator($contextParameters);

		$this->get('/timeMode/result');
		// no redirect
		$this->assertSame(null, $this->headerValue('Location'));
	}

	public function testOpeningTimeModeResultWithInvalidTimeSessionID()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		new ContextPreparator($contextParameters);

		$this->disableErrorHandlerMiddleware();
		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('Time Mode Session not found.');

		$this->get('/timeMode/result/56465487');
	}

	public function testSolvingAProblemInTimeModeRecordsTheTimeItTook()
	{
		// the time the client reports is kept between a hundredth of a second and the time the problem is given
		foreach ([
			['category' => TimeModeCategoryTable::BLITZ, 'seconds' => 5, 'recordedSeconds' => 5],
			['category' => TimeModeCategoryTable::BLITZ, 'seconds' => -3, 'recordedSeconds' => 0.01],
			['category' => TimeModeCategoryTable::SLOW,
				'seconds' => TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS * 5,
				'recordedSeconds' => TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS]] as $reported)
		{
			$context = new ContextPreparator([
				'tsumego' => 1,
				'time-mode-ranks' => ['5k'],
				'time-mode-sessions' => [[
					'category' => $reported['category'],
					'rank' => '5k',
					'status' => TimeModeSessionStatusTable::IN_PROGRESS,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]]]]);

			// the player opens the play page, which is what puts the account into time mode
			$this->post('/timeMode/play');

			Auth::init();
			$this->post('/tsumegos/result', [
				'tsumego_id' => $context->tsumegos[0]['id'],
				'seconds' => $reported['seconds'],
				'outcome' => PlayOutcome::SolvedClean->value]);

			$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $context->timeModeSessions[0]->id])->first();
			$this->assertSame(
				TimeModeUtil::$ATTEMPT_RESULT_SOLVED,
				(int) $attempt->time_mode_attempt_status_id);
			$this->assertSame(
				(float) $reported['recordedSeconds'],
				(float) $attempt->seconds);
		}
	}

	//Time mode is not part of the no-error streak: playing there neither breaks it nor extends it.
	public function testTimeModeLeavesTheNoErrorStreakAlone()
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'achievement-conditions' => [['category' => AchievementConditionTable::CATEGORY_NO_ERROR_STREAK, 'value' => 7]],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]]]]);

		// the player opens the play page, which is what puts the account into time mode
		$this->post('/timeMode/play');

		Auth::init();
		$this->post('/tsumegos/result', [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 5,
			'outcome' => PlayOutcome::Failed->value]);

		$errCondition = $this->fetchTable('AchievementCondition')->find()->where(['user_id' => $context->user['id'], 'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_NO_ERROR_STREAK])->first();
		$this->assertSame(7, (int) $errCondition->value,
			'Time mode does not move the streak');
	}

	public function testAProblemThatWasGivenUpOnStaysGivenUpOn()
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'tsumegos' => [2],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					// given up on in one tab, solved in another one at the same time
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_STATUS_SKIPPED, 'started-seconds-ago' => 0],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);

		$this->post('/timeMode/play');

		Auth::init();
		$this->post('/tsumegos/result', [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 5,
			'outcome' => PlayOutcome::SolvedClean->value]);

		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $context->timeModeSessions[0]->id, 'order' => 1])->first();
		$this->assertSame(
			TimeModeUtil::$ATTEMPT_STATUS_SKIPPED,
			(int) $attempt->time_mode_attempt_status_id);

		// the problem the player is on is not disturbed by the ignored result
		$this->post('/timeMode/play');
		$this->assertSame(null, $this->headerValue('Location'));
	}

	public function testTimeModeProblemLeftBehindIsRecordedAsTimedOutWithItsFullTime()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$TIME_MODE],
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::SLOW,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED,
					// the problem was presented and left behind, so its time ran out
					'started-seconds-ago' => TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS + 1]]]]]);

		$this->post('/timeMode/play');

		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $context->timeModeSessions[0]->id])->first();
		$this->assertSame(
			TimeModeUtil::$ATTEMPT_STATUS_TIMEOUT,
			(int) $attempt->time_mode_attempt_status_id);
		$this->assertSame(TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS, (int) $attempt->seconds);
		$this->assertSame(0, (int) $attempt->points);
	}

	//Running out of time in time mode is recorded as a timeout with the full time of the category.
	public function testTimeModeSubmittingATimeoutIsRecordedWithTheFullTime()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$TIME_MODE],
			'tsumego' => 1,
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::SLOW,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]]]]);

		$this->post('/timeMode/play');

		Auth::init();
		$this->post('/tsumegos/result', [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 5,
			'outcome' => PlayOutcome::TimedOut->value]);

		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $context->timeModeSessions[0]->id, 'order' => 1])->first();
		$this->assertSame(
			TimeModeUtil::$ATTEMPT_STATUS_TIMEOUT,
			(int) $attempt->time_mode_attempt_status_id);
		$this->assertSame(
			TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS,
			(int) $attempt->seconds,
			'Running out of time costs the full time of the category');
	}

	public function testTimeModeServesTheProblemAtTheRequestedPosition()
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'tsumegos' => [2],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);

		$this->post('/timeMode/play/2');
		$html = $this->view;

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
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);

		foreach ([1 => '/timeMode/play/2',
			2 => '/timeMode/result/' . $context->timeModeSessions[0]->id] as $position => $expectedLink)
		{
			$this->post('/timeMode/play/' . $position);
			$html = $this->view;

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

		$this->get('/timeMode/start?categoryID=' . TimeModeCategoryTable::BLITZ
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		$sessionID = $this->fetchTable('TimeModeSession')->find()->where(['user_id' => $context->user['id']])->firstOrFail()->id;
		$attempts = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $sessionID])->orderBy('id')->toArray();

		$tsumegoIDs = array_map(fn($attempt) => (int) $attempt->tsumego_id, $attempts);
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
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);

		$this->get('/timeMode/result/' . $context->timeModeSessions[0]->id);
		$html = $this->view;

		$this->assertStringContainsString('Finishing your session', $html);
		$this->assertSame(
			TimeModeSessionStatusTable::IN_PROGRESS,
			$this->fetchTable('TimeModeSession')->get($context->timeModeSessions[0]->id)->time_mode_session_status_id);
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
					'category' => TimeModeCategoryTable::BLITZ,
					'rank' => '5k',
					'status' => TimeModeSessionStatusTable::IN_PROGRESS,
					'attempts' => $attempts]]]);

			$this->get('/timeMode/result/' . $context->timeModeSessions[0]->id);
			$html = $this->view;

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
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					// the first problem was solved and its result is still on its way, the second
					// one is the one being shown
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'started-seconds-ago' => 0],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'started-seconds-ago' => 0, 'tsumego_id' => 'other:1'],
					['order' => 3, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:2']]]]]);
		$sessionID = $context->timeModeSessions[0]->id;

		$this->post('/timeMode/play/2');
		$html = $this->view;
		preg_match('/var nextButtonLink = "([^"]*)";/', $html, $matches);
		$this->assertSame('/timeMode/skip/2', $matches[1] ?? 'not rendered');

		$this->get('/timeMode/skip/2');
		$this->assertSame(Util::getInternalAddress() . '/timeMode/play/3', $this->headerValue('Location'));

		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $sessionID, 'order' => 2])->first();
		$this->assertSame(
			TimeModeUtil::$ATTEMPT_STATUS_SKIPPED,
			(int) $attempt->time_mode_attempt_status_id);

		$this->post('/timeMode/play/3');
		$html = $this->view;

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
					'category' => TimeModeCategoryTable::BLITZ,
					'rank' => '5k',
					'status' => TimeModeSessionStatusTable::IN_PROGRESS,
					'attempts' => [
						['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
						// the second problem is the one being played, the first one is recorded already
						['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'started-seconds-ago' => 0, 'tsumego_id' => 'other:1']]]]]);
			$sessionID = $context->timeModeSessions[0]->id;

			$this->get('/timeMode/skip' . ($position === null ? '' : '/' . $position));
			$this->assertSame(
				Util::getInternalAddress() . '/timeMode/play' . ($position === null ? '' : '/2'),
				$this->headerValue('Location'));

			$attempts = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $sessionID])->orderBy('order')->toArray();
			$this->assertSame(
				TimeModeUtil::$ATTEMPT_RESULT_SOLVED,
				(int) $attempts[0]->time_mode_attempt_status_id);
			$this->assertSame(
				TimeModeUtil::$ATTEMPT_RESULT_QUEUED,
				(int) $attempts[1]->time_mode_attempt_status_id);
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
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					// the solved problem was opened long enough ago that its time would be up
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED, 'started-seconds-ago' => 3600],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);
		$sessionID = $context->timeModeSessions[0]->id;

		$this->post('/timeMode/play/1');
		$html = $this->view;

		preg_match('/var tsumegoID = (\d+);/', $html, $matches);
		$this->assertSame((int) $context->tsumegos[1]['id'], (int) ($matches[1] ?? 0));

		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $sessionID, 'order' => 1])->first();
		$this->assertSame(
			TimeModeUtil::$ATTEMPT_RESULT_SOLVED,
			(int) $attempt->time_mode_attempt_status_id);
	}

	public function testTimeModeServesTheNextUnplayedProblemAfterThePositionAskedFor()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$TIME_MODE],
			'tsumego' => 1,
			'tsumegos' => [2, 3],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					// the first problem is being played, its result has not been recorded yet
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'started-seconds-ago' => 0],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED, 'tsumego_id' => 'other:1'],
					['order' => 3, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:2']]]]]);

		$this->post('/timeMode/play/2');
		$html = $this->view;

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
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1'],
					['order' => 3, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:2']]]]]);

		$this->post('/timeMode/play/9');
		$html = $this->view;

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
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED,
					// the only problem was played, its result has not reached the database yet
					'started-seconds-ago' => 0]]]]]);

		$this->post('/timeMode/play/2');
		$html = $this->view;

		$this->assertStringContainsString('Finishing your session', $html);
	}

	public function testTimeModeResultListsAllTheRunsOfTheRank()
	{
		$solved = ['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED];
		$failedRun = [
			['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
			['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED, 'tsumego_id' => 'other:1'],
			['order' => 3, 'status' => TimeModeUtil::$ATTEMPT_RESULT_FAILED, 'tsumego_id' => 'other:2']];
		$context = new ContextPreparator([
			'tsumego' => 1,
			'tsumegos' => [2, 3],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [
				['category' => TimeModeCategoryTable::BLITZ, 'rank' => '5k', 'status' => TimeModeSessionStatusTable::SOLVED, 'attempts' => [$solved]],
				['category' => TimeModeCategoryTable::BLITZ, 'rank' => '5k', 'status' => TimeModeSessionStatusTable::FAILED, 'attempts' => $failedRun],
				['category' => TimeModeCategoryTable::BLITZ, 'rank' => '5k', 'status' => TimeModeSessionStatusTable::SOLVED, 'attempts' => [$solved]],
				// still being played, so it is not a finished run yet
				['category' => TimeModeCategoryTable::BLITZ, 'rank' => '5k', 'status' => TimeModeSessionStatusTable::IN_PROGRESS,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]],
				// the only run of its rank and category, so there is no history to show for it
				['category' => TimeModeCategoryTable::SLOW, 'rank' => '5k', 'status' => TimeModeSessionStatusTable::SOLVED, 'attempts' => [$solved]]]]);

		$this->get('/timeMode/result');
		$html = $this->view;

		$this->assertSame(1, substr_count($html, 'History:'));
		$this->assertStringContainsString('History: 3 runs', $html);
		$this->assertStringContainsString('>2/3</td>', $html);
		$this->assertSame(2, substr_count($html, '>1/1</td>'));
		// the newest run first
		$positions = array_map(
			fn($index) => strpos($html, 'href="/timeMode/result/' . $context->timeModeSessions[$index]->id . '?history=1"'),
			[2, 1, 0]);
		$this->assertNotContains(false, $positions);
		$sortedPositions = $positions;
		sort($sortedPositions);
		$this->assertSame($sortedPositions, $positions);
		foreach ([3, 4] as $index)
			$this->assertStringNotContainsString('href="/timeMode/result/' . $context->timeModeSessions[$index]->id . '?history=1"', $html);
	}

	public function testTimeModeRunOpenedFromTheHistoryDoesNotShowTheUnlockAgain()
	{
		foreach ([['query' => '', 'unlockShown' => true], ['query' => '?history=1', 'unlockShown' => false]] as $testCase)
		{
			$context = new ContextPreparator([
				'tsumego' => 1,
				'time-mode-ranks' => ['5k', '1d'],
				'time-mode-sessions' => [[
					'category' => TimeModeCategoryTable::BLITZ,
					'rank' => '5k',
					'status' => TimeModeSessionStatusTable::SOLVED,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]]]]);

			$this->get('/timeMode/result/' . $context->timeModeSessions[0]->id . $testCase['query']);
			$html = $this->view;

			$this->assertSame($testCase['unlockShown'], str_contains($html, 'time-rank-unlock-alert'));
		}
	}

	public function testTimeModeResultShowsAProblemInSeveralSetsOnceInItsDisplaySet()
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [
				['name' => 'time mode set one', 'num' => 1],
				['name' => 'time mode set two', 'num' => 2, 'order' => 1]]],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::SOLVED,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]]]]);

		$this->get('/timeMode/result/' . $context->timeModeSessions[0]->id);
		$html = $this->view;

		$this->assertSame(1, substr_count($html, 'href="/tsumegos/play/' . $context->tsumegos[0]['id'] . '"'));
		// the set with an order is shown before the one without
		$this->assertStringContainsString('time mode set two', $html);
		$this->assertStringNotContainsString('time mode set one', $html);
		$this->assertStringContainsString('passed(1/1)', $html);
	}

	public function testTimeModeResultShowsTheBestRunNextToTheResult()
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'tsumegos' => [2],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [
				['category' => TimeModeCategoryTable::BLITZ, 'rank' => '5k', 'status' => TimeModeSessionStatusTable::SOLVED,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]],
				['category' => TimeModeCategoryTable::BLITZ, 'rank' => '5k', 'status' => TimeModeSessionStatusTable::FAILED,
					'attempts' => [
						['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
						['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_FAILED, 'tsumego_id' => 'other:1']]]]]);
		Util::execute('UPDATE time_mode_session SET points = 30 WHERE id = ?', [$context->timeModeSessions[0]->id]);
		Util::execute('UPDATE time_mode_session SET points = 10 WHERE id = ?', [$context->timeModeSessions[1]->id]);

		$this->get('/timeMode/result/' . $context->timeModeSessions[1]->id);
		$html = $this->view;
		$this->assertStringContainsString('Best: passed(1/1)', $html);
		$this->assertStringContainsString('Result: failed(1/2)- 10.00 points', $html);
		$this->assertSame(1, substr_count($html, 'href="/tsumegos/play/' . $context->tsumegos[1]['id'] . '"'));

		// the best run itself is shown only once, as the result
		$this->get('/timeMode/result/' . $context->timeModeSessions[0]->id);
		$html = $this->view;
		$this->assertStringNotContainsString('Best:', $html);
		$this->assertStringContainsString('Result: passed(1/1)- 30.00 points', $html);
	}

	public function testTimeModeRunStillBeingPlayedIsNotTheBestRun()
	{
		$context = new ContextPreparator([
			'tsumego' => 1,
			'tsumegos' => [2],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [
				['category' => TimeModeCategoryTable::BLITZ, 'rank' => '5k', 'status' => TimeModeSessionStatusTable::SOLVED,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]],
				['category' => TimeModeCategoryTable::BLITZ, 'rank' => '5k', 'status' => TimeModeSessionStatusTable::IN_PROGRESS,
					'attempts' => [
						['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED],
						['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);
		Util::execute('UPDATE time_mode_session SET points = 10 WHERE id = ?', [$context->timeModeSessions[0]->id]);
		Util::execute('UPDATE time_mode_session SET points = 50 WHERE id = ?', [$context->timeModeSessions[1]->id]);

		$this->get('/timeMode/result');
		$html = $this->view;

		$this->assertStringContainsString('Best: passed(1/1)', $html);
		$this->assertStringContainsString('10.00 points', $html);
		$this->assertStringNotContainsString('50.00 points', $html);
	}
}

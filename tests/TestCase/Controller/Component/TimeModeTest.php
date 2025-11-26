<?php

App::uses('Auth', 'Utility');
App::uses('TimeModeUtil', 'Utility');
App::uses('RatingBounds', 'Utility');
App::uses('TimeMode', 'Utility');

use Facebook\WebDriver\WebDriverBy;

class TimeModeTest extends TestCaseWithAuth
{
	public function testPointsCalculation()
	{
		$this->assertSame(TimeMode::calculatePoints(30, 30), 100 * TimeModeUtil::$POINTS_RATIO_FOR_FINISHING);
		$this->assertSame(TimeMode::calculatePoints(50, 100), 100 * (1 + TimeModeUtil::$POINTS_RATIO_FOR_FINISHING) / 2);
		$this->assertSame(TimeMode::calculatePoints(0, 30), 100.0);
	}

	public function testTimeModeRankContentsIntegrity()
	{
		$context = new ContextPreparator(['time-mode-ranks' => ['1k', '1d', '2d']]);
		// The ranks in the time_mode_rank table should be always ascending when ordered by id.
		// This fact is used to conveniently deduce the rating range of the current rank
		$allTimeModeRanks = ClassRegistry::init('TimeModeRank')->find('all', ['order' => 'id']) ?: [];
		$this->assertNotEmpty($allTimeModeRanks);

		$previousRank = null;
		foreach ($allTimeModeRanks as $timeModeRank)
		{
			if ($previousRank)
			{
				$previousRank = Rating::getRankMinimalRatingFromReadableRank($previousRank['TimeModeRank']['name']);
				$currentRank = Rating::getRankMinimalRatingFromReadableRank($timeModeRank['TimeModeRank']['name']);
				$this->assertTrue($previousRank < $currentRank);
			}
			$previousRank = $timeModeRank;
		}
	}

	public function testRatingBoundsOneRank()
	{
		$context = new ContextPreparator(['time-mode-ranks' => ['1k']]);
		$ratingBounds = TimeMode::getRatingBounds($context->timeModeRanks[0]['id']);

		// with just one rank, everything belongs to it
		$this->assertTrue(is_null($ratingBounds->min));
		$this->assertTrue(is_null($ratingBounds->max));
	}

	public function testRatingBoundsTwoRanks()
	{
		$context = new ContextPreparator(['time-mode-ranks' => ['1k', '1d']]);

		$ratingBounds1k = TimeMode::getRatingBounds($context->timeModeRanks[0]['id']);
		// with just one rank, everything belongs to it
		$this->assertTrue(is_null($ratingBounds1k->min));
		$this->assertSame($ratingBounds1k->max, Rating::getRankMinimalRatingFromReadableRank('1d'));

		$ratingBounds1d = TimeMode::getRatingBounds($context->timeModeRanks[1]['id']);
		// with just one rank, everything belongs to it
		$this->assertSame($ratingBounds1d->min, $ratingBounds1k->max);
		$this->assertNull($ratingBounds1d->max);
	}

	public function testRatingBoundsThreeRanks()
	{
		$context = new ContextPreparator(['time-mode-ranks' => ['10k', '1k', '1d']]);

		$ratingBounds10k = TimeMode::getRatingBounds($context->timeModeRanks[0]['id']);
		// with just one rank, everything belongs to it
		$this->assertTrue(is_null($ratingBounds10k->min));
		$this->assertSame($ratingBounds10k->max, Rating::getRankMinimalRatingFromReadableRank('9k'));

		$ratingBounds1k = TimeMode::getRatingBounds($context->timeModeRanks[1]['id']);
		// with just one rank, everything belongs to it
		$this->assertSame($ratingBounds1k->min, $ratingBounds10k->max);
		$this->assertSame($ratingBounds1k->max, Rating::getRankMinimalRatingFromReadableRank('1d'));

		$ratingBounds1d = TimeMode::getRatingBounds($context->timeModeRanks[2]['id']);
		// with just one rank, everything belongs to it
		$this->assertSame($ratingBounds1d->min, $ratingBounds1k->max);
		$this->assertNull($ratingBounds1d->max);
	}

	public function testStartTimeMode()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['sets' => [['name' => 'tsumego set 1', 'num' => 1]]],
			'time-mode-ranks' => ['5k']]);

		$this->assertTrue(Auth::isInLevelMode());
		$this->testAction('/timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id']);
		$this->assertTrue(Auth::isInTimeMode());
		$sessions = ClassRegistry::init('TimeModeSession')->find('all', [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]) ?: [];
		$this->assertSame(count($sessions), 1);
		$this->assertSame($sessions[0]['TimeModeSession']['user_id'], Auth::getUserID());
		$this->assertSame($sessions[0]['TimeModeSession']['time_mode_category_id'], TimeModeUtil::$CATEGORY_SLOW_SPEED);
		$this->assertSame($sessions[0]['TimeModeSession']['time_mode_rank_id'], $context->timeModeRanks[0]['id']);

		$attempts = ClassRegistry::init('TimeModeAttempt')->find('all', [
			'user_id' => Auth::getUserID(),
			'time_mode_session_id' => $sessions[0]['TimeModeSession']['id']]) ?: [];
		$this->assertTrue(count($attempts) > 0);
		$this->assertSame($attempts[0]['TimeModeAttempt']['time_mode_attempt_status_id'], TimeModeUtil::$ATTEMPT_RESULT_QUEUED);
	}

	public function getTimeModeReportedTime($browser): array
	{
		$countdown = $browser->driver->findElement(WebDriverBy::cssSelector('#time-mode-countdown'))->getText();
		$x = [];
		$this->assertSame(preg_match('/(\d+):([0-5]\d)\.(\d)/', $countdown, $x), 1, 'The status should contain time in format m:s.d, but it wasn\'t found in the string: "' . $countdown . "'");
		return ['minutes' => intval($x[1]), 'seconds' => intval($x[2]), 'decimals' => intval($x[3])];
	}

	public function testTimeModeFullProcess()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['other-tsumegos'] = [];

		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];

		$context = new ContextPreparator($contextParameters);

		$this->assertEmpty(ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]));

		$this->assertTrue(Auth::isInLevelMode());
		$browser = Browser::instance();

		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		$session = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]);

		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());

		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; $i++)
		{
			if ($i < TimeModeUtil::$PROBLEM_COUNT)
			{
				$result = $this->getTimeModeReportedTime($browser);
				$this->assertWithinMargin($result['minutes'], 1, TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS / 60);
				$this->assertWithinMargin($result['seconds'], 2, 60);
			}
			$solvedAttempts = ClassRegistry::init('TimeModeAttempt')->find('all', [
				'conditions' => [
					'time_mode_session_id' => $session['TimeModeSession']['id'],
					'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]) ?: [];
			$failedAttempts = ClassRegistry::init('TimeModeAttempt')->find('all', [
				'conditions' => [
					'time_mode_session_id' => $session['TimeModeSession']['id'],
					'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_FAILED]]) ?: [];
			$this->assertSame(count($failedAttempts), 0);
			$this->assertSame(count($solvedAttempts), $i);

			$queuedAttempts = ClassRegistry::init('TimeModeAttempt')->find('all', [
				'conditions' => [
					'time_mode_session_id' => $session['TimeModeSession']['id'],
					'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]) ?: [];
			$this->assertSame(count($queuedAttempts), TimeModeUtil::$PROBLEM_COUNT - $i);
			foreach ($solvedAttempts as $solvedAttempt)
				$this->assertTrue($solvedAttempt['TimeModeAttempt']['points'] > 20);
			if ($i == TimeModeUtil::$PROBLEM_COUNT)
				break;
			usleep(1000 * 100);
			$browser->driver->executeScript("displayResult('S')"); // mark the problem solved
			$nextButton = $browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'));
			$this->assertTrue($nextButton->isEnabled());
			$this->assertTrue($nextButton->isDisplayed());
			$this->assertSame($nextButton->getTagName(), "input");
			$this->assertSame($nextButton->getAttribute('value'), 'Next');
			$nextButton->click();
		}

		$this->assertEmpty(ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]));
		Auth::init();
		$this->assertFalse(Auth::isInTimeMode());
		$session = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => ['id' => $session['TimeModeSession']['id']]]);
		$this->assertTrue($session['TimeModeSession']['time_mode_session_status_id'] == TimeModeUtil::$SESSION_STATUS_SOLVED);
	}

	public function testTimeModeRefreshDoesntRefreshTime()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());

		usleep(200 * 1000);
		$result = $this->getTimeModeReportedTime($browser);
		$this->assertSame($result['minutes'], TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS / 60 - 1);
		$this->assertWithinMargin($result['seconds'], 3, 60);

		// refresh
		$browser->get('timeMode/play');
		usleep(200 * 1000);
		$timeModeSessionID = ClassRegistry::init('TimeModeSession')->find('first')['TimeModeSession']['id'];
		$queuedAttempts = ClassRegistry::init('TimeModeAttempt')->find('all', ['conditions' => [
			'time_mode_session_id' => $timeModeSessionID,
			'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]);
		$this->assertSame(count($queuedAttempts), TimeModeUtil::$PROBLEM_COUNT);

		$startedQueuedAttempts = ClassRegistry::init('TimeModeAttempt')->find('all', ['conditions' => [
			'time_mode_session_id' => $timeModeSessionID,
			'time_mode_attempt_status_id' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED,
			'started not' => null]]);
		$this->assertSame(count($startedQueuedAttempts), 1);

		$newResult = $this->getTimeModeReportedTime($browser);
		$this->assertSame($newResult['minutes'], TimeModeUtil::$CATEGORY_SLOW_SPEED_SECONDS / 60 - 1);
		$this->assertTrue($newResult['seconds'] + $newResult['decimals'] * 0.1 < $result['seconds'] + $result['decimals'] * 0.1,
			"Started attempt time: " . $startedQueuedAttempts[0]['TimeModeAttempt']['started']
			. " Reported time: " . $newResult['seconds'] . '.' . $newResult['decimals']
			. " Old time: " . $result['seconds'] . '.' . $result['decimals']);
	}

	public function testTimeModeTimeout()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());

		usleep(100 * 1000);
		$browser->driver->executeScript("window['tcount'] = 0.1;");
		usleep(200 * 1000);
		$browser->assertNoJsErrors();
		$newResult = $this->getTimeModeReportedTime($browser);
		$this->assertSame($newResult['minutes'], 0);
		$this->assertSame($newResult['seconds'], 0);
		$this->assertSame($newResult['decimals'], 0);
		$browser->get('timeMode/play');

		$session = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]])['TimeModeSession'];
		$attempt = ClassRegistry::init('TimeModeAttempt')->find('first', ['conditions' => ['time_mode_session_id' => $session['id']], 'order' => ['id' => 'ASC']])['TimeModeAttempt'];
		$this->assertSame($attempt['time_mode_attempt_status_id'], TimeModeUtil::$ATTEMPT_STATUS_TIMEOUT);
	}

	public function testNextButtonTitleFromSkipToNextInTheTimeMode()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->getAttribute("value"), "Skip");
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // mark the problem solved
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->getAttribute("value"), "Next");
	}

	public function testTimeModeSkip()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();
		$session = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]])['TimeModeSession'];
		$attempt = ClassRegistry::init('TimeModeAttempt')->find('first', ['conditions' => ['time_mode_session_id' => $session['id']], 'order' => ['id' => 'ASC']])['TimeModeAttempt'];
		$this->assertSame($attempt['time_mode_attempt_status_id'], TimeModeUtil::$ATTEMPT_STATUS_SKIPPED);
	}

	public function testTimeTitleWithDifferentTsumegoFilters()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE, 'query' => 'favorites'];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());
		$this->assertSame(Util::getMyAddress() . '/timeMode/play', $browser->driver->getCurrentURL());
		$this->assertSame($browser->getCssSelect("#playTitle")[0]->getText(), "1 of 10");
	}

	public function checkUnlockWhen($conditions)
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$TIME_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		if ($conditions['alreadySolved'])
		{
			$contextParameters['time-mode-sessions'] [] = [
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]];
		}
		if ($conditions['higherRankPresent'])
			$contextParameters['time-mode-ranks'] [] = '1d';
		$contextParameters['tsumego'] = ['sets' => [['name' => 'set 1', 'num' => 1]]];
		$contextParameters['time-mode-sessions'] [] = [
			'category' => TimeModeUtil::$CATEGORY_BLITZ,
			'rank' => '5k',
			'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]];
		if ($conditions['failedSessionOnRankToBeUnlocked'])
		{
			$contextParameters['time-mode-sessions'] [] = [
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '1d',
				'status' => TimeModeUtil::$SESSION_STATUS_FAILED];
		}

		$context = new ContextPreparator($contextParameters);
		$this->assertTrue(Auth::isInTimeMode());

		$browser = Browser::instance();
		$browser->get('timeMode/play');

		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('" . ($conditions['actuallySolvedSession'] ? 'S' : 'F') . "')"); // mark the problem solved
		$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();

		$this->assertEmpty(ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]));
		Auth::init();
		$this->assertFalse(Auth::isInTimeMode());
		$session = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => ['id' => $context->timeModeSessions[0]['id']]]);
		$this->assertSame($session['TimeModeSession']['time_mode_session_status_id'],
			$conditions['actuallySolvedSession'] ? TimeModeUtil::$SESSION_STATUS_SOLVED : TimeModeUtil::$SESSION_STATUS_FAILED);
		$alerts = $browser->driver->findElements(WebDriverBy::cssSelector('#time-rank-unlock-alert'));
		if (count($alerts) == 1)
		{
			$this->assertTrue($alerts[0]->isDisplayed());
			return true;
		}
		return false;
	}

	public function testTimeModeUnlockMessage()
	{
		$this->assertTrue($this->checkUnlockWhen([
			'higherRankPresent' => true,
			'alreadySolved' => false,
			'failedSessionOnRankToBeUnlocked' => false,
			'actuallySolvedSession' => true]));
		$this->assertTrue($this->checkUnlockWhen([
			'higherRankPresent' => true,
			'alreadySolved' => false,
			'failedSessionOnRankToBeUnlocked' => true,
			'actuallySolvedSession' => true]));
		$this->assertFalse($this->checkUnlockWhen([
			'higherRankPresent' => false,
			'alreadySolved' => false,
			'failedSessionOnRankToBeUnlocked' => false,
			'actuallySolvedSession' => true]));
		$this->assertFalse($this->checkUnlockWhen([
			'higherRankPresent' => true,
			'alreadySolved' => true,
			'failedSessionOnRankToBeUnlocked' => true,
			'actuallySolvedSession' => true]));
		$this->assertFalse($this->checkUnlockWhen([
			'higherRankPresent' => true,
			'alreadySolved' => false,
			'failedSessionOnRankToBeUnlocked' => true,
			'actuallySolvedSession' => false]));
	}

	public function testTimeModeResultShowsSpecifiedResult(): void
	{
		$browser = Browser::instance();
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k', '1d'];
		foreach ($contextParameters['time-mode-ranks'] as $rank)
			$contextParameters['time-mode-sessions'] [] = ['category' => TimeModeUtil::$CATEGORY_BLITZ, 'rank' => $rank, 'status' => TimeModeUtil::$SESSION_STATUS_SOLVED];
		$context = new ContextPreparator($contextParameters);

		foreach ([0, 1] as $indexToShow)
		{
			$browser->get('timeMode/result/' . $context->timeModeSessions[$indexToShow]['id']);
			foreach ($contextParameters['time-mode-ranks'] as $rank)
			{
				$relatedDiv = $browser->driver->findElement(WebDriverBy::cssSelector('#results_Blitz_' . $rank));
				$this->assertSame($relatedDiv->isDisplayed(), $rank == $context->timeModeRanks[$indexToShow]['name']);
			}
		}
	}

	public function testTimeModeOverviewShowsUnlockedStatusesCorrectly(): void
	{
		$browser = Browser::instance();

		foreach (['solve-nothing', 'solve-5k', 'solve-all'] as $testCase)
		{
			$contextParameters = [];
			$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
			$contextParameters['time-mode-ranks'] = ['5k', '1d'];
			if ($testCase === 'solve-all' || $testCase === 'solve-5k')
				$contextParameters['time-mode-sessions'] [] = ['category' => TimeModeUtil::$CATEGORY_BLITZ, 'rank' => '5k', 'status' => TimeModeUtil::$SESSION_STATUS_SOLVED];
			if ($testCase === 'solve-all')
				$contextParameters['time-mode-sessions'] [] = ['category' => TimeModeUtil::$CATEGORY_BLITZ, 'rank' => '1d', 'status' => TimeModeUtil::$SESSION_STATUS_SOLVED];
			$context = new ContextPreparator($contextParameters);
			$browser->get('timeMode/overview');

			$div5k = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . $context->timeModeRanks[0]['id']));
			$links5k = $div5k->findElements(WebDriverBy::tagName('a'));

			$div1d = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . $context->timeModeRanks[1]['id']));
			$links1d = $div1d->findElements(WebDriverBy::tagName('a'));

			// lowest rank is always unlocked
			$this->assertTrue(count($links5k) == 1);
			// 1d rank is unlocked when the previous rank (5k in this case) is solved
			$this->assertSame(count($links1d) == 1, $testCase === 'solve-all' || $testCase === 'solve-5k');
		}
	}

	public function testTimeModeOverviewTsumegoCountCalculation(): void
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['10k', '5k', '1d', '5d'];
		// empty 10k
		// one tsumego in 5k category
		$contextParameters['other-tsumegos'] [] = ['rating' => Rating::getRankMiddleRatingFromReadableRank('5k'), 'sets' => [['name' => 'set 1', 'num' => 1]]];

		// two in 1d category
		for ($i = 1; $i <= 2; $i++)
			$contextParameters['other-tsumegos'] [] = ['rating' => Rating::getRankMiddleRatingFromReadableRank('1d'), 'sets' => [['name' => 'set 1', 'num' => 1]]];

		// 3 in 5d category
		for ($i = 1; $i <= 3; $i++)
			$contextParameters['other-tsumegos'] [] = ['rating' => Rating::getRankMiddleRatingFromReadableRank('5d'), 'sets' => [['name' => 'set 1', 'num' => 1]]];
		// one in 5d category, but in a set not included in time mode
		$contextParameters['other-tsumegos'] [] = ['rating' => Rating::getRankMiddleRatingFromReadableRank('5d'), 'sets' => [['name' => 'set weird', 'num' => 1, 'included_in_time_mode' => false]]];

		$context = new ContextPreparator($contextParameters);
		$browser = Browser::instance();
		$browser->get('timeMode/overview');
		$renderedCounts = $browser->driver->findElements(WebDriverBy::cssSelector(".imageContainerText2"));
		$visibleCounts = array_values(array_filter($renderedCounts, function ($el) { return $el->isDisplayed(); }));

		$this->assertSame(count($visibleCounts), count($contextParameters['time-mode-ranks']));
		$this->assertSame($visibleCounts[0]->getText(), "0");
		$this->assertSame($visibleCounts[1]->getText(), "1");
		$this->assertSame($visibleCounts[2]->getText(), "2");
		$this->assertSame($visibleCounts[3]->getText(), "3");
	}

	public function testTimeModeButtonsHover(): void
	{
		$browser = Browser::instance();
		$contextParameters = [];
		$contextParameters['user'] = [
			'mode' => Constants::$LEVEL_MODE,
			'last-time-mode-category-id' => TimeModeUtil::$CATEGORY_BLITZ];
		$contextParameters['time-mode-ranks'] = ['5k', '1d'];
		$context = new ContextPreparator($contextParameters);
		$browser->get('timeMode/overview');

		$div5k = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . $context->timeModeRanks[0]['id']));
		$links5k = $div5k->findElements(WebDriverBy::tagName('a'));
		$imgs5k = $div5k->findElements(WebDriverBy::tagName('img'));

		$div1d = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . $context->timeModeRanks[1]['id']));
		$links1d = $div1d->findElements(WebDriverBy::tagName('a'));
		$imgs1d = $div1d->findElements(WebDriverBy::tagName('img'));

		// lowest rank is always unlocked
		$this->assertSame(count($links5k), 1);

		$this->assertSame(count($imgs5k), 2); // one of the rank, one of the storage icon
		$this->assertSame($imgs5k[0]->getAttribute('src'), "/img/rankButton5k.png");

		// hovering shows hovered image
		$browser->hover($div5k);
		$browser->driver->wait(10, 50)->until(function () use ($imgs5k) { return $imgs5k[0]->getAttribute('src') == "/img/rankButton5khover.png"; });

		// hovering something else shows unhovered image
		$browser->hover($div1d);
		$browser->driver->wait(10, 50)->until(function () use ($imgs5k) { return $imgs5k[0]->getAttribute('src') == "/img/rankButton5k.png"; });

		// locked rank shows inactive image
		$this->assertSame(count($links1d), 0);
		$this->assertSame(count($imgs1d), 2);
		$this->assertSame($imgs1d[0]->getAttribute('src'), "/img/rankButton1dinactive.png");
	}

	public function testTimeModeSwitchCategory(): void
	{
		$browser = Browser::instance();
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE, 'last-time-mode-category-id' => TimeModeUtil::$CATEGORY_BLITZ];
		$contextParameters['time-mode-ranks'] = ['5k', '1d'];
		$context = new ContextPreparator($contextParameters);
		$browser->get('timeMode/overview');

		$div5kBlitz = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . $context->timeModeRanks[0]['id']));
		$div1dBlitz = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . $context->timeModeRanks[1]['id']));
		$div5kFast = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_FAST_SPEED . '-' . $context->timeModeRanks[0]['id']));
		$div1dFast = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_FAST_SPEED . '-' . $context->timeModeRanks[1]['id']));

		// blirz buttons are visible at start
		$this->assertTrue($div5kBlitz->isDisplayed());
		$this->assertTrue($div1dBlitz->isDisplayed());
		$this->assertFalse($div5kFast->isDisplayed());
		$this->assertFalse($div1dFast->isDisplayed());

		// we switch to fast speed, and blitz buttons get hidden, instead we see fast buttons
		$browser->clickId('timeMode' . TimeModeUtil::$CATEGORY_FAST_SPEED);
		$this->assertFalse($div5kBlitz->isDisplayed());
		$this->assertFalse($div1dBlitz->isDisplayed());
		$this->assertTrue($div5kFast->isDisplayed());
		$this->assertTrue($div1dFast->isDisplayed());
	}
}

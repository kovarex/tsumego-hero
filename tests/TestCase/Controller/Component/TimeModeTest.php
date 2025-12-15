<?php

App::uses('Auth', 'Utility');
App::uses('TimeModeUtil', 'Utility');
App::uses('RatingBounds', 'Utility');
App::uses('TimeMode', 'Utility');
App::uses('TimeModeRank', 'Model');
App::uses('Rating', 'Utility');

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
		new ContextPreparator();  // Ensures all ranks exist
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
		new ContextPreparator();  // Ensures all ranks exist
		$ratingBounds = TimeMode::getRatingBounds(TimeModeRank::RANK_5D);
		// 5d is the highest rank, so it has a min bound (4d max) but no max
		$this->assertSame($ratingBounds->min, Rating::getRankMinimalRatingFromReadableRank('5d'));
		$this->assertTrue(is_null($ratingBounds->max));
	}

	public function testRatingBoundsTwoRanks()
	{
		new ContextPreparator();  // Ensures all ranks exist

		// Test two adjacent ranks (1k and 1d)
		$ratingBounds1k = TimeMode::getRatingBounds(TimeModeRank::RANK_1K);
		// 1k (id=15) has 2k below it, so it has a min
		$this->assertSame($ratingBounds1k->min, Rating::getRankMinimalRatingFromReadableRank('1k'));
		// 1k has 1d above it, so it has a max at 1d
		$this->assertSame($ratingBounds1k->max, Rating::getRankMinimalRatingFromReadableRank('1d'));

		$ratingBounds1d = TimeMode::getRatingBounds(TimeModeRank::RANK_1D);
		// 1d (id=16) has 1k below it
		$this->assertSame($ratingBounds1d->min, $ratingBounds1k->max);
		// 1d has 2d above it, so it has a max at 2d
		$this->assertSame($ratingBounds1d->max, Rating::getRankMinimalRatingFromReadableRank('2d'));
	}

	public function testRatingBoundsThreeRanks()
	{
		new ContextPreparator();  // Ensures all ranks exist

		// Test three non-adjacent ranks (10k, 1k, 1d) - all 20 ranks exist now
		$ratingBounds10k = TimeMode::getRatingBounds(TimeModeRank::RANK_10K);
		// 10k (id=6) has 11k below it (id=5)
		$this->assertSame($ratingBounds10k->min, Rating::getRankMinimalRatingFromReadableRank('10k'));
		// 10k has 9k above it (id=7)
		$this->assertSame($ratingBounds10k->max, Rating::getRankMinimalRatingFromReadableRank('9k'));

		$ratingBounds1k = TimeMode::getRatingBounds(TimeModeRank::RANK_1K);
		// 1k (id=15) has 2k below it (id=14)
		$this->assertSame($ratingBounds1k->min, Rating::getRankMinimalRatingFromReadableRank('1k'));
		// 1k has 1d above it (id=16)
		$this->assertSame($ratingBounds1k->max, Rating::getRankMinimalRatingFromReadableRank('1d'));

		$ratingBounds1d = TimeMode::getRatingBounds(TimeModeRank::RANK_1D);
		// 1d (id=16) has 1k below it (id=15)
		$this->assertSame($ratingBounds1d->min, Rating::getRankMinimalRatingFromReadableRank('1d'));
		// 1d has 2d above it (id=17)
		$this->assertSame($ratingBounds1d->max, Rating::getRankMinimalRatingFromReadableRank('2d'));
	}

	public function testStartTimeMode()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['rating' => Rating::getRankMiddleRatingFromReadableRank('5k'), 'sets' => [['name' => 'tsumego set 1', 'num' => 1]]]]);

		$this->assertTrue(Auth::isInLevelMode());
		$this->testAction('/timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . TimeModeRank::RANK_5K);
		$this->assertTrue(Auth::isInTimeMode());
		$sessions = ClassRegistry::init('TimeModeSession')->find('all', [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]) ?: [];
		$this->assertSame(count($sessions), 1);
		$this->assertSame($sessions[0]['TimeModeSession']['user_id'], Auth::getUserID());
		$this->assertSame($sessions[0]['TimeModeSession']['time_mode_category_id'], TimeModeUtil::$CATEGORY_SLOW_SPEED);
		$this->assertSame($sessions[0]['TimeModeSession']['time_mode_rank_id'], TimeModeRank::RANK_5K);

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
		$contextParameters['other-tsumegos'] = [];

		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['rating' => Rating::getRankMiddleRatingFromReadableRank('5k'), 'sets' => [['name' => 'tsumego set 1', 'num' => $i]]];

		$context = new ContextPreparator($contextParameters);

		$this->assertEmpty(ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]));

		$this->assertTrue(Auth::isInLevelMode());
		$browser = Browser::instance();

		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . TimeModeRank::RANK_5K);

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
			$browser->playWithResult('S');
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
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . TimeModeRank::RANK_5K);

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
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . TimeModeRank::RANK_5K);

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
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . TimeModeRank::RANK_5K);

		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->getAttribute("value"), "Skip");
		$browser->playWithResult('S'); // mark the problem solved
		$this->assertSame($browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->getAttribute("value"), "Next");
	}

	public function testTimeModeSkip()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . TimeModeRank::RANK_5K);

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
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . TimeModeRank::RANK_5K);

		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());
		$this->assertSame(Util::getMyAddress() . '/timeMode/play', $browser->driver->getCurrentURL());
		$this->assertSame($browser->getCssSelect("#playTitle")[0]->getText(), "1 of 10");
	}

	public function checkUnlockWhen($conditions)
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$TIME_MODE];
		if ($conditions['alreadySolved'])
		{
			$contextParameters['time-mode-sessions'] [] = [
				'category' => TimeModeUtil::$CATEGORY_BLITZ,
				'rank' => '5k',
				'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
				'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_SOLVED]]];
		}
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

		$browser->playWithResult($conditions['actuallySolvedSession'] ? 'S' : 'F'); // mark the problem solved
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
		$rankNames = ['5k', '1d'];
		foreach ($rankNames as $rank)
			$contextParameters['time-mode-sessions'] [] = ['category' => TimeModeUtil::$CATEGORY_BLITZ, 'rank' => $rank, 'status' => TimeModeUtil::$SESSION_STATUS_SOLVED];
		$context = new ContextPreparator($contextParameters);

		foreach ([0, 1] as $indexToShow)
		{
			$browser->get('timeMode/result/' . $context->timeModeSessions[$indexToShow]['id']);
			foreach ($rankNames as $rank)
			{
				$relatedDiv = $browser->driver->findElement(WebDriverBy::cssSelector('#results_Blitz_' . $rank));
				// Get the rank name from the session
				$sessionRankId = $context->timeModeSessions[$indexToShow]['time_mode_rank_id'];
				$sessionRank = ClassRegistry::init('TimeModeRank')->findById($sessionRankId)['TimeModeRank']['name'];
				$this->assertSame($relatedDiv->isDisplayed(), $rank == $sessionRank);
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
			if ($testCase === 'solve-all' || $testCase === 'solve-5k')
				$contextParameters['time-mode-sessions'] [] = ['category' => TimeModeUtil::$CATEGORY_BLITZ, 'rank' => '5k', 'status' => TimeModeUtil::$SESSION_STATUS_SOLVED];
			if ($testCase === 'solve-all')
				$contextParameters['time-mode-sessions'] [] = ['category' => TimeModeUtil::$CATEGORY_BLITZ, 'rank' => '1d', 'status' => TimeModeUtil::$SESSION_STATUS_SOLVED];
			$context = new ContextPreparator($contextParameters);
			$browser->get('timeMode/overview');

			$div5k = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . TimeModeRank::RANK_5K));
			$links5k = $div5k->findElements(WebDriverBy::tagName('a'));

			$div1d = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . TimeModeRank::RANK_1D));
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

		// All 20 ranks exist now, so check specific rank counts instead
		$this->assertSame(count($visibleCounts), 20);  // All ranks shown
		// Find specific rank counts - indices depend on rank order (15k=0, ..., 10k=5, 5k=10, 1d=15, 5d=19)
		$this->assertSame($visibleCounts[5]->getText(), "0");  // 10k: empty
		$this->assertSame($visibleCounts[10]->getText(), "1"); // 5k: one tsumego
		$this->assertSame($visibleCounts[15]->getText(), "2"); // 1d: two tsumegos
		$this->assertSame($visibleCounts[19]->getText(), "3"); // 5d: three tsumegos
	}

	public function testTimeModeButtonsHover(): void
	{
		$browser = Browser::instance();
		$contextParameters = [];
		$contextParameters['user'] = [
			'mode' => Constants::$LEVEL_MODE,
			'last-time-mode-category-id' => TimeModeUtil::$CATEGORY_BLITZ];
		$context = new ContextPreparator($contextParameters);
		$browser->get('timeMode/overview');

		$div5k = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . TimeModeRank::RANK_5K));
		$links5k = $div5k->findElements(WebDriverBy::tagName('a'));
		$imgs5k = $div5k->findElements(WebDriverBy::tagName('img'));

		$div1d = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . TimeModeRank::RANK_1D));
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
		$context = new ContextPreparator($contextParameters);
		$browser->get('timeMode/overview');

		$div5kBlitz = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . TimeModeRank::RANK_5K));
		$div1dBlitz = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_BLITZ . '-' . TimeModeRank::RANK_1D));
		$div5kFast = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_FAST_SPEED . '-' . TimeModeRank::RANK_5K));
		$div1dFast = $browser->driver->findElement(WebDriverBy::cssSelector('#rank-selector-' . TimeModeUtil::$CATEGORY_FAST_SPEED . '-' . TimeModeRank::RANK_1D));

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

	public function testTimeModeSessionWithNothingQueued(): void
	{
		$contextParameters = [
			'user' => ['mode' => Constants::$LEVEL_MODE]];

		// I prepare tsumegos in a way that one will be always left from the time mode selection
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'][] = ['rating' => Rating::getRankMiddleRatingFromReadableRank('5k'), 'sets' => [['name' => 'tsumego set 1', 'num' => 1]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/overview');
		$browser->get('/timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . TimeModeRank::RANK_5K);
		$this->assertSame(Util::getMyAddress() . '/timeMode/play', $browser->driver->getCurrentURL());

		$tsumegosInTimeMode = [];
		$attempts = ClassRegistry::init('TimeModeAttempt')->find('all');
		foreach ($attempts as $attempt)
			$tsumegosInTimeMode[$attempt['TimeModeAttempt']['tsumego_id']] = true;

		$tsumegoIDNotInTimeMode = array_find($context->otherTsumegos, fn($t) => !$tsumegosInTimeMode[$t['id']])['id'];
		$setConnectionIDNotInTimeMode = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $tsumegoIDNotInTimeMode]])['SetConnection']['id'];
		$browser->playWithResult('S'); // mark the problem solved
		usleep(1000 * 50);

		// we found the only tsumego NOT in the time mode, and we open it in no-time mode
		// first time, we are processing the previous time mode attempt
		$browser->get('/' . $setConnectionIDNotInTimeMode);

		// now se solve the only tsumego not in time mode
		$browser->playWithResult('S'); // mark the problem solved
		usleep(1000 * 50);

		Auth::getUser()['mode'] = Constants::$TIME_MODE; // I force the time mode to be active
		Auth::saveUser();

		// I'm processing result of the play which wasn't time mode related, while time mode is also activated
		$browser->get('/sets');
		$statusOfNoTimeProblemPlay = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $tsumegoIDNotInTimeMode]]);
		$this->assertSame($statusOfNoTimeProblemPlay['TsumegoStatus']['status'], 'S');
	}

	public function testTimeModeTimeAddedForEachMovePlayed()
	{
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['other-tsumegos'] = [];
		$sgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i)
			$contextParameters['other-tsumegos'] [] = ['rating' => Rating::getRankMiddleRatingFromReadableRank('5k'), 'sgf' => $sgf, 'sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		$context = new ContextPreparator($contextParameters);

		$browser = Browser::instance();
		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . TimeModeRank::RANK_5K);

		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());

		usleep(100 * 1000);
		$browser->assertNoJsErrors();
		$beforePlayResult = $this->getTimeModeReportedTime($browser);
		$beforePlayResultSeconds = $beforePlayResult['seconds'] + $beforePlayResult['minutes'] * 60;
		$browser->clickBoard(4, 4);
		$afterPlayResult = $this->getTimeModeReportedTime($browser);
		$afterPlayResultSeconds = $afterPlayResult['seconds'] + $afterPlayResult['minutes'];
		$this->assertGreaterThan($afterPlayResultSeconds, $beforePlayResultSeconds);
	}
}

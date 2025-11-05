<?php

require_once(__DIR__ . '/../TestCaseWithAuth.php');
App::uses('Auth', 'Utility');
App::uses('TimeModeUtil', 'Utility');
App::uses('RatingBounds', 'Utility');
use Facebook\WebDriver\WebDriverBy;

class TimeModeComponentTest extends TestCaseWithAuth {
	public function testPointsCalculation() {
		$this->assertSame(TimeModeComponent::calculatePoints(30, 30), 100 * TimeModeUtil::$POINTS_RATIO_FOR_FINISHING);
		$this->assertSame(TimeModeComponent::calculatePoints(50, 100), 100 * (1 + TimeModeUtil::$POINTS_RATIO_FOR_FINISHING) / 2);
		$this->assertSame(TimeModeComponent::calculatePoints(0, 30), 100.0);
	}
	public function testTimeModeRankContentsIntegrity() {
		$context = new ContextPreparator(['time-mode-ranks' => ['1k', '1d', '2d']]);
		// The ranks in the time_mode_rank table should be always ascending when ordered by id.
		// This fact is used to conveniently deduce the rating range of the current rank
		$allTimeModeRanks = ClassRegistry::init('TimeModeRank')->find('all', ['order' => 'id']) ?: [];
		$this->assertNotEmpty($allTimeModeRanks);

		$previousRank = null;
		foreach ($allTimeModeRanks as $timeModeRank) {
			if ($previousRank) {
				$previousRank = Rating::getRankMinimalRatingFromReadableRank($previousRank['TimeModeRank']['name']);
				$currentRank = Rating::getRankMinimalRatingFromReadableRank($timeModeRank['TimeModeRank']['name']);
				$this->assertTrue($previousRank < $currentRank);
			}
			$previousRank = $timeModeRank;
		}
	}

	public function testRatingBoundsOneRank() {
		$context = new ContextPreparator(['time-mode-ranks' => ['1k']]);
		$ratingBounds = TimeModeComponent::getRatingBounds($context->timeModeRanks[0]['id']);

		// with just one rank, everything belongs to it
		$this->assertTrue(is_null($ratingBounds->min));
		$this->assertTrue(is_null($ratingBounds->max));
	}

	public function testRatingBoundsTwoRanks() {
		$context = new ContextPreparator(['time-mode-ranks' => ['1k', '1d']]);

		$ratingBounds1k = TimeModeComponent::getRatingBounds($context->timeModeRanks[0]['id']);
		// with just one rank, everything belongs to it
		$this->assertTrue(is_null($ratingBounds1k->min));
		$this->assertSame($ratingBounds1k->max, Rating::getRankMinimalRatingFromReadableRank('1d'));

		$ratingBounds1d = TimeModeComponent::getRatingBounds($context->timeModeRanks[1]['id']);
		// with just one rank, everything belongs to it
		$this->assertSame($ratingBounds1d->min, $ratingBounds1k->max);
		$this->assertNull($ratingBounds1d->max);
	}

	public function testRatingBoundsThreeRanks() {
		$context = new ContextPreparator(['time-mode-ranks' => ['10k', '1k', '1d']]);

		$ratingBounds10k = TimeModeComponent::getRatingBounds($context->timeModeRanks[0]['id']);
		// with just one rank, everything belongs to it
		$this->assertTrue(is_null($ratingBounds10k->min));
		$this->assertSame($ratingBounds10k->max, Rating::getRankMinimalRatingFromReadableRank('9k'));

		$ratingBounds1k = TimeModeComponent::getRatingBounds($context->timeModeRanks[1]['id']);
		// with just one rank, everything belongs to it
		$this->assertSame($ratingBounds1k->min, $ratingBounds10k->max);
		$this->assertSame($ratingBounds1k->max, Rating::getRankMinimalRatingFromReadableRank('1d'));

		$ratingBounds1d = TimeModeComponent::getRatingBounds($context->timeModeRanks[2]['id']);
		// with just one rank, everything belongs to it
		$this->assertSame($ratingBounds1d->min, $ratingBounds1k->max);
		$this->assertNull($ratingBounds1d->max);
	}

	public function testStartTimeMode() {
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

	public function testTimeModeFullProcess() {
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k'];
		$contextParameters['other-tsumegos'] = [];

		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i) {
			$contextParameters['other-tsumegos'] [] = ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		}

		$context = new ContextPreparator($contextParameters);

		$this->assertEmpty(ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]));

		$this->assertTrue(Auth::isInLevelMode());
		$browser = new Browser();

		$browser->get('timeMode/start'
			. '?categoryID=' . TimeModeUtil::$CATEGORY_SLOW_SPEED
			. '&rankID=' . $context->timeModeRanks[0]['id']);

		$session = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]);

		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());

		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; $i++) {

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
			foreach ($solvedAttempts as $solvedAttempt) {
				$this->assertTrue($solvedAttempt['TimeModeAttempt']['points'] > 20);
			}
			if ($i == TimeModeUtil::$PROBLEM_COUNT) {
				break;
			}
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

	public function testTimeModeUnlockMessage() {
		$browser = new Browser();
		foreach ([false, true] as $higherRankPresent) {
			foreach ($higherRankPresent ? [false, true] : [false] as $failedSessionOnRankToBeUnlocked) {
				$contextParameters = [];
				$contextParameters['user'] = ['mode' => Constants::$TIME_MODE];
				$contextParameters['time-mode-ranks'] = ['5k'];
				if ($higherRankPresent) {
					$contextParameters['time-mode-ranks'] [] = '1d';
				}
				$contextParameters['tsumego'] = ['sets' => [['name' => 'set 1', 'num' => 1]]];
				$contextParameters['time-mode-sessions'] [] = [
					'category' => TimeModeUtil::$CATEGORY_BLITZ,
					'rank' => '5k',
					'status' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
					'attempts' => [['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED]]];
				if ($failedSessionOnRankToBeUnlocked) {
					$contextParameters['time-mode-sessions'] [] = [
						'category' => TimeModeUtil::$CATEGORY_BLITZ,
						'rank' => '1d',
						'status' => TimeModeUtil::$SESSION_STATUS_FAILED];
				}

				$context = new ContextPreparator($contextParameters);
				$this->assertTrue(Auth::isInTimeMode());

				$browser->get('tsumegos/play');

				usleep(1000 * 100);
				$browser->driver->executeScript("displayResult('S')"); // mark the problem solved
				$browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'))->click();

				$this->assertEmpty(ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
					'user_id' => Auth::getUserID(),
					'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS]]));
				Auth::init();
				$this->assertFalse(Auth::isInTimeMode());
				$session = ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => ['id' => $context->timeModeSessions[0]['id']]]);
				$this->assertSame($session['TimeModeSession']['time_mode_session_status_id'], TimeModeUtil::$SESSION_STATUS_SOLVED);
				$alerts = $browser->driver->findElements(WebDriverBy::cssSelector('#time-rank-unlock-alert'));
				$this->assertSame(count($alerts) == 1, $higherRankPresent);
				if ($higherRankPresent) {
					$this->assertTrue($alerts[0]->isDisplayed());
				}
			}
		}
	}

	public function testTimeModeResultShowsSpecifiedResult(): void {
		$browser = new Browser();
		$contextParameters = [];
		$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
		$contextParameters['time-mode-ranks'] = ['5k', '1d'];
		foreach ($contextParameters['time-mode-ranks'] as $rank) {
			$contextParameters['time-mode-sessions'] [] = ['category' => TimeModeUtil::$CATEGORY_BLITZ, 'rank' => $rank, 'status' => TimeModeUtil::$SESSION_STATUS_SOLVED];
		}
		$context = new ContextPreparator($contextParameters);

		foreach ([0, 1] as $indexToShow) {
			$browser->get('timeMode/result/' . $context->timeModeSessions[$indexToShow]['id']);
			foreach ($contextParameters['time-mode-ranks'] as $rank) {
				$relatedDiv = $browser->driver->findElement(WebDriverBy::cssSelector('#results_Blitz_' . $rank));
				$this->assertSame($relatedDiv->isDisplayed(), $rank == $context->timeModeRanks[$indexToShow]['name']);
			}
		}
	}

	public function testTimeModeOverviewShowsUnlockedStatusesCorrectly(): void {
		$browser = new Browser();

		foreach (['solve-nothing', 'solve-5k', 'solve-all'] as $testCase) {
			$contextParameters = [];
			$contextParameters['user'] = ['mode' => Constants::$LEVEL_MODE];
			$contextParameters['time-mode-ranks'] = ['5k', '1d'];
			if ($testCase === 'solve-all' || $testCase === 'solve-5k') {
				$contextParameters['time-mode-sessions'] [] = ['category' => TimeModeUtil::$CATEGORY_BLITZ, 'rank' => '5k', 'status' => TimeModeUtil::$SESSION_STATUS_SOLVED];
			}
			if ($testCase === 'solve-all') {
				$contextParameters['time-mode-sessions'] [] = ['category' => TimeModeUtil::$CATEGORY_BLITZ, 'rank' => '1d', 'status' => TimeModeUtil::$SESSION_STATUS_SOLVED];
			}
			$context = new ContextPreparator($contextParameters);
			$browser->get('timeMode/overview');

			$page = $browser->driver->getPageSource();
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
}

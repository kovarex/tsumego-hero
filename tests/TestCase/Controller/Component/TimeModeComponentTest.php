<?php

require_once(__DIR__ . '/../TestCaseWithAuth.php');
App::uses('Auth', 'Utility');
App::uses('TimeModeUtil', 'Utility');
App::uses('RatingBounds', 'Utility');

class TimeModeComponentTest extends TestCaseWithAuth {
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
		$contextParameters = ['time-mode-ranks' => ['5k']];
		$contextParameters['other-tsumegos'] = [];
		for ($i = 0; $i < TimeModeUtil::$PROBLEM_COUNT + 1; ++$i) {
			$contextParameters['other-tsumegos'] []= ['sets' => [['name' => 'tsumego set 1', 'num' => $i]]];
		}

		$context = new ContextPreparator($contextParameters);

		$this->assertTrue(Auth::isInLevelMode());
		$browser = new Browser();
		$browser->get('/timeMode/start'
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

		$attempts = ClassRegistry::init('TimeModeAttempt')->find('all', ['time_mode_session_id' => $sessions[0]['TimeModeSession']['id']]) ?: [];
		$this->assertSame(count($attempts), TimeModeUtil::$PROBLEM_COUNT);
		foreach ($attempts as $attempt) {
			$this->assertSame($attempt['TimeModeAttempt']['time_mode_attempt_status_id'], TimeModeUtil::$ATTEMPT_RESULT_QUEUED);
		}

		$_COOKIE['score'] = Util::wierdEncrypt('1');
		$nextButton = $browser->driver->findElement(WebDriverBy::cssSelector('#besogo-next-button'));
		$this->assertNotNull($nextButton);
		$browser->driver->action()->click($nextButton);


	}
}

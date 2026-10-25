<?php

use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use App\Utility\Rating;
use Facebook\WebDriver\WebDriverBy;

class MistakeTrainingControllerTest extends TestCaseWithAuth
{
	public function testRequiresLogin()
	{
		new ContextPreparator(['user' => null]);
		$this->testAction('/mistake-training', ['method' => 'get']);
		$this->assertNotNull($this->headers['Location'] ?? null,
			'Should redirect to login');
	}

	public function testNothingToReviewYetForAPlayerWhoNeverTrained()
	{
		new ContextPreparator(['user' => ['name' => 'testuser']]);
		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('Nothing to review yet', $this->view);
		$this->assertStringNotContainsString('All caught up', $this->view);
	}

	public function testTheTrainingShowsTheRankOfTheProblemButNoXP()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'rating' => 1500,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$this->assertMatchesRegularExpression('/<div id="status2" align="center">\s*<font size="4">\s*' . preg_quote(Rating::getReadableRankFromRating(1500), '/') . ' /',
			$this->view, 'The rank shows as in the level mode');
		$this->assertStringNotContainsString('new XPStatus(', $this->view, 'The training gives no XP');
	}

	public function testAllCaughtUpWhenNoDueProblems()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'status' => ['name' => 'S', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+3 days'))]],
		]);
		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);
		$this->assertStringContainsString('All caught up', $this->view);
	}

	public function testRendersNextDueProblem()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);
		// Renders the next due problem's play page directly (no redirect), and
		// puts the user into mistake-training mode.
		$this->assertStringContainsString('var tsumegoID = ' . $context->tsumegos[0]['id'] . ';', $this->view,
			'The play page of the due problem is rendered');
		$this->assertStringContainsString('problem-nav__rung', $this->view, 'With the training row under the board');
		$this->assertTrue(Auth::isInMistakeTrainingMode());
	}

	public function testAllCaughtUpShowsUpcomingDayCounts()
	{
		// Tomorrow of the training day, which is still yesterday's day before the day
		// boundary, so the test does not depend on the hour it runs at.
		$dueTomorrow = date('Y-m-d', strtotime(MistakeTraining::today() . ' +1 day')) . ' 12:00:00';
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => $dueTomorrow]],
				['set_order' => 2, 'status' => ['name' => 'V', 'mistake_training_due' => $dueTomorrow]],
			],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$text = preg_replace('/\s+/', ' ', $this->view);
		$this->assertStringContainsString('All caught up', $text);
		$this->assertStringContainsString('2 problems in training.', $text);
		$this->assertStringContainsString('Coming up', $text);
		$this->assertStringContainsString('2 reviews tomorrow', $text, 'The day count must cover every problem due that day');
	}

	public function testQueueLinksStayOnTheTrainingRoute()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('href="/mistake-training/play/' . $scId . '"', $this->view,
			'Queue navigation must go through the training route, which owns the mode');
	}

	public function testTrainingRouteKeepsTrainingMode()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];
		Auth::saveUserField('mode', Constants::$LEVEL_MODE);

		$this->testAction('/mistake-training/play/' . $scId, ['method' => 'get', 'return' => 'contents']);

		$this->assertTrue(Auth::isInMistakeTrainingMode(), 'The training route asserts the training mode');
		$this->assertStringContainsString('Mistake Training', $this->view);
	}

	public function testTrainingRouteRedirectsForAProblemOutsideThePool()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'status' => 'V'],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$this->testAction('/mistake-training/play/' . $scId, ['method' => 'get']);

		$this->assertStringEndsWith('/mistake-training', $this->headers['Location'] ?? '',
			'A problem outside the pool falls back to the queue');
	}

	public function testTrainingRouteRedirectsForAProblemThatIsNotDueYet()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+3 days'))],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$this->testAction('/mistake-training/play/' . $scId, ['method' => 'get']);

		$this->assertStringEndsWith('/mistake-training', $this->headers['Location'] ?? '',
			'A problem that is scheduled for later is not part of today\'s queue');
	}

	/**
	 * Problems in the training, in the order they came in. Each entry is added to the
	 * status fixture of its problem; by default a problem is due, every one at the same
	 * moment the way an import brings them, and untried today.
	 */
	private function prepareTraining(array ...$problems): ContextPreparator
	{
		$due = date('Y-m-d H:i:s', strtotime('-1 hour'));
		$tsumegos = [];
		foreach ($problems as $index => $problem)
			$tsumegos[] = ['set_order' => $index + 1, 'status' => $problem + ['name' => 'V', 'mistake_training_due' => $due]];
		return new ContextPreparator(['user' => ['name' => 'testuser'], 'tsumegos' => $tsumegos]);
	}

	private static function reviewedToday(string $result): array
	{
		return [
			'mistake_training_due' => $result === MistakeTraining::REVIEW_MISSED
				? date('Y-m-d H:i:s', strtotime('-1 hour'))
				: date('Y-m-d H:i:s', strtotime('+3 days')),
			'mistake_training_review_day' => MistakeTraining::today(),
			'mistake_training_review_result' => $result,
		];
	}

	private static function setConnectionId(ContextPreparator $context, int $index): int
	{
		return (int) $context->tsumegos[$index]['set-connections'][0]['id'];
	}

	/**
	 * Twenty problems imported at once are due at the same moment, and they come in
	 * newest mistake first, not in the order of their set. The training must still
	 * open at the first of them, not wherever an unordered query lands.
	 */
	public function testEnteringTheTrainingOpensTheFirstProblemOfTheDay()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [['set_order' => 1], ['set_order' => 2], ['set_order' => 3]],
		]);
		$due = date('Y-m-d H:i:s', strtotime('-1 hour'));
		foreach ([1, 2, 0] as $index)
			MistakeTraining::addProblem($context->user['id'], (int) $context->tsumegos[$index]['id'], $due);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('var tsumegoID = ' . $context->tsumegos[1]['id'] . ';', $this->view,
			'The training opens the problem that came in first');
		$this->assertStringContainsString('Mistake Training 1/3', $this->view);
	}

	public function testEnteringTheTrainingOpensTheFirstProblemNotTriedToday()
	{
		$this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_SOLVED), self::reviewedToday(MistakeTraining::REVIEW_MISSED), []);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('Mistake Training 3/3', $this->view,
			'A problem solved today keeps its place in the row, a problem missed today waits for the untried ones');
	}

	public function testAProblemMissedTodayCanBeTriedAgain()
	{
		$context = $this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_MISSED));

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 0), ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('Mistake Training 1/1', $this->view, 'A miss stays in today\'s training');
		$this->assertStringNotContainsString('This try does not count', $this->view, 'Trying a missed problem again is its review');
		$this->assertStringContainsString('problem-nav__item--N', $this->view, 'It stays grey until it is solved');
	}

	public function testAProblemSolvedTodayOpensAsAReplay()
	{
		$context = $this->prepareTraining([], self::reviewedToday(MistakeTraining::REVIEW_SOLVED));

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 1), ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('Mistake Training 2/2', $this->view, 'A problem solved today keeps its place in the row');
		$this->assertStringContainsString('Already solved today. This try does not count.', $this->view);
	}

	/**
	 * The colour of the row comes from what today did, so a problem missed and then
	 * solved on a retry is green when the page is drawn again, whatever its status.
	 */
	public function testAProblemSolvedOnARetryIsGreenInTheRow()
	{
		$context = $this->prepareTraining(['name' => 'F', 'mistake_training_rung' => 3]);
		$userId = (int) $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null, true);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null, true);

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 0), ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('problem-nav__item problem-nav__item--S problem-nav__item--current', $this->view);
		$this->assertStringContainsString('<div class="problem-nav__rung problem-nav__rung--chevron" data-rung="0" role="img" aria-label="Rung 1 of 8">', $this->view,
			'The retry earned no rung back');
		$this->assertStringContainsString('Already solved today. This try does not count.', $this->view);
	}

	public function testTrainingRouteRedirectsForAProblemSolvedOnAnEarlierDay()
	{
		$context = $this->prepareTraining([
			'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+2 days')),
			'mistake_training_review_day' => date('Y-m-d', strtotime(MistakeTraining::today() . ' -1 day')),
			'mistake_training_review_result' => MistakeTraining::REVIEW_SOLVED,
		]);

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 0), ['method' => 'get']);

		$this->assertStringEndsWith('/mistake-training', $this->headers['Location'] ?? '',
			'Yesterday\'s solve is not part of today\'s training');
	}

	public function testTheNextLinkSkipsWhatTodaySolved()
	{
		$context = $this->prepareTraining([], self::reviewedToday(MistakeTraining::REVIEW_SOLVED), []);

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 0), ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString(
			'var nextButtonLink = "' . MistakeTraining::$PLAY_LINK_PREFIX . self::setConnectionId($context, 2) . '"', $this->view);
		$this->assertStringContainsString('var previousButtonLink = ""', $this->view,
			'Back at the start of the row has nowhere to go, so the page turns it off');
	}

	public function testTheLastDueProblemHasNoNextUntilItIsSolved()
	{
		$context = $this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_SOLVED), []);

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 1), ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('var nextButtonLink = ""', $this->view,
			'The queue page would only serve the same problem again; the solve turns Next on');
		$this->assertStringContainsString('var previousButtonLink = "' . MistakeTraining::$PLAY_LINK_PREFIX . self::setConnectionId($context, 0) . '"', $this->view);
	}

	public function testAllCaughtUpSaysWhatTodaySolved()
	{
		$context = $this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_SOLVED), self::reviewedToday(MistakeTraining::REVIEW_SOLVED));

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$text = preg_replace('/\s+/', ' ', $this->view);
		$this->assertStringContainsString('All caught up', $text);
		$this->assertStringContainsString('Today you solved 2 problems.', $text);
		$this->assertStringContainsString('href="' . MistakeTraining::$PLAY_LINK_PREFIX . self::setConnectionId($context, 0) . '"', $text,
			'The page offers to look at today\'s training again, from its first problem');
	}

	public function testAllCaughtUpSaysNothingAboutTodayBeforeAnythingWasSolved()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+2 days'))]],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringNotContainsString('Today you solved', $this->view);
	}

	public function testPlainProblemLinkLeavesTrainingMode()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];
		Auth::saveUserField('mode', Constants::$MISTAKE_TRAINING_MODE);

		$this->testAction('/' . $scId, ['return' => 'contents']);

		$this->assertFalse(Auth::isInMistakeTrainingMode(),
			'A plain problem link means normal play, like it does for the other modes');
	}

	public function testSolvingAlreadySolvedProblemClimbsTheLadder()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'S', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		$rungBefore = (int) MistakeTraining::getPoolRow($context->user['id'], $tsumegoId)['rung'];

		$browser = Browser::instance();
		$browser->get('mistake-training');

		// A problem the player already solved is still a fresh challenge here, so
		// it must not open in review mode.
		$this->assertSame(0, count($browser->driver->findElements(WebDriverBy::cssSelector('#besogo-review-button'))),
			'Training must not open an already solved problem in review mode.');

		$browser->playWithResult('S');

		// Solving must reach the server, otherwise the ladder never advances and
		// the same problem is served forever.
		$this->assertGreaterThan($rungBefore, (int) MistakeTraining::getPoolRow($context->user['id'], $tsumegoId)['rung'],
			'Solving in training must record the result and climb the ladder.');
	}

	/**
	 * A mistake locks the board, but the problem stays in today's training, so Reset
	 * must open a fresh try of it. The solve that follows is the retry: it earns no
	 * rung, and the button turns green because today is done with the problem. The
	 * page is not reloaded after a result, so the result itself redraws the button.
	 */
	public function testAMistakeInTheTrainingCanBeTriedAgainWithReset()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'S', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3],
			],
		]);
		$userId = (int) $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$browser = Browser::instance();
		$browser->get('mistake-training');
		$browser->playWithResult('F');

		$this->assertStringContainsString('Press Reset to try again', $browser->find('#status')->getText());
		$this->assertSame(0, (int) MistakeTraining::getPoolRow($userId, $tsumegoId)['rung'], 'A mistake goes back to the bottom');
		$this->assertTrue(MistakeTraining::isDue($userId, $tsumegoId), 'A mistake keeps the problem due today');
		$this->assertSame('Rung 1 of 8, missed today',
			$browser->find('.problem-nav__item--current .problem-nav__rung')->getAttribute('aria-label'),
			'The button shows the bottom rung the mistake took the problem to, ringed as missed');

		$status = $browser->find('#status');
		$browser->clickId('besogo-reset-button');
		$browser->waitUntilStale($status);
		$this->assertStringEndsWith(MistakeTraining::$PLAY_LINK_PREFIX . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL(),
			'Reset opens the problem again on the training route');
		$this->assertStringNotContainsString('Incorrect', $browser->find('#status')->getText(), 'Reset opens a fresh try');
		$this->assertCount(1, $browser->driver->findElements(WebDriverBy::id('besogo-back-button-inactive')),
			'The only problem of the day has nothing before it');
		$this->assertCount(1, $browser->driver->findElements(WebDriverBy::id('besogo-next-button-inactive')),
			'Nor anything after it until it is solved');

		$browser->playWithResult('S');
		$browser->driver->wait(5)->until(fn() => count($browser->driver->findElements(WebDriverBy::id('besogo-next-button'))) === 1);
		$this->assertStringEndsWith(MistakeTraining::$QUEUE_LINK, (string) $browser->find('#besogo-next-button')->getAttribute('href'),
			'The solve turns Next on, to the page that says the day is done');
		$this->assertNotEmpty($browser->find('#status .mistake-training-outcome')->getText(), 'And says what it did to the problem');

		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'The solve after a mistake of the same day earns no rung');
		$this->assertSame(MistakeTraining::REVIEW_SOLVED, $row['last_review_result']);
		$this->assertFalse(MistakeTraining::isDue($userId, $tsumegoId), 'The problem is done for today');

		// The colour the server draws, not the one the page set on the solve.
		$browser->get(MistakeTraining::$PLAY_LINK_PREFIX . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertStringContainsString('problem-nav__item--S', $browser->find('.problem-nav__item--current')->getAttribute('class'),
			'Solved today shows green');
		$this->assertStringContainsString('Already solved today', $browser->pageText(), 'Opened again, it is only a replay');
	}

	public function testSkipsDeletedTsumegos()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		ClassRegistry::init('Tsumego')->delete($context->tsumegos[0]['id']);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);
		// The row went with the problem, so the training is empty
		$this->assertStringContainsString('Nothing to review yet', $this->view);
	}

	public function testSkipsSoftDeletedTsumegos()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
				'deleted' => date('Y-m-d H:i:s'),
			],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);
		// Soft-deleted tsumego should be skipped, and with nothing else due it
		// shows "All caught up".
		$this->assertStringContainsString('All caught up', $this->view);

		// It keeps its row, so a problem that is restored comes back with its history
		$this->assertNotNull(MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']));
	}

	public function testLeavesOutAProblemOfACollectionInTheTrash()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'sets' => [['name' => 'trashed collection', 'num' => 1, 'deleted' => date('Y-m-d H:i:s')]],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);
		$this->assertStringContainsString('All caught up', $this->view,
			'A collection in the trash cannot be opened, so its problems are not served');

		// The row stays, so a restored collection brings the problem back with what it has learned
		$this->assertNotNull(MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']));
	}

	public function testServesAProblemOfATrashedCollectionThroughItsOtherCollection()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'sets' => [
					['name' => 'trashed collection', 'num' => 1, 'deleted' => date('Y-m-d H:i:s')],
					['name' => 'live collection', 'num' => 1],
				],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);
		$openConnection = (int) $context->tsumegos[0]['set-connections'][1]['id'];
		$this->assertStringContainsString('var tsumegoID = ' . $context->tsumegos[0]['id'] . ';', $this->view);
		$this->assertStringContainsString('set_connection_id: ' . $openConnection . ',', $this->view,
			'The problem is played in the collection that is still there');
	}

	public function testALinkToACollectionInTheTrashLeadsBackToTheQueue()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'sets' => [
					['name' => 'trashed collection', 'num' => 1, 'deleted' => date('Y-m-d H:i:s')],
					['name' => 'live collection', 'num' => 1],
				],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$trashedConnection = (int) $context->tsumegos[0]['set-connections'][0]['id'];
		$this->testAction(MistakeTraining::$PLAY_LINK_PREFIX . $trashedConnection, ['method' => 'get']);

		$this->assertStringEndsWith('/mistake-training', $this->headers['Location'] ?? '',
			'A link the play page would refuse leads back to the queue');
	}

	public function testTheOverviewNamesTheCollectionTheProblemOpensIn()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'sets' => [
					['name' => 'trashed collection', 'num' => 1, 'deleted' => date('Y-m-d H:i:s')],
					['name' => 'live collection', 'num' => 1],
				],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d', strtotime('+3 days')) . ' 00:00:00'],
			],
		]);

		$this->testAction(MistakeTraining::$OVERVIEW_LINK, ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('live collection', $this->view, 'The list names the collection the problem opens in');
		$this->assertStringNotContainsString('trashed collection', $this->view, 'Not the one in the trash');
	}

	public function testServesASandboxProblemOnlyToAPlayerWithSandbox()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'sets' => [['name' => 'sandbox collection', 'num' => 1, 'public' => 0]],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);
		$this->assertStringContainsString('All caught up', $this->view,
			'A player without sandbox access is not sent into a sandbox collection');

		$context = new ContextPreparator([
			'user' => ['name' => 'testuser2', 'premium' => 2],
			'tsumego' => [
				'sets' => [['name' => 'sandbox collection', 'num' => 1, 'public' => 0]],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);
		$this->assertStringContainsString('var tsumegoID = ' . $context->tsumegos[0]['id'] . ';', $this->view,
			'Premium players may play sandbox collections, so the training opens the problem');
	}

	public function testTheHomepageShowsWhatTheTrainingHasToday()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 hour'))]],
				['set_order' => 2, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 hour'))]],
			],
		]);

		$html = (string) $this->testAction('/sites/index', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('class="mistake-training-card" href="' . MistakeTraining::$OVERVIEW_LINK . '"', $html,
			'The homepage opens the overview, which leads into the training');
		$this->assertStringContainsString('class="mistake-training-card__count" role="img" title="2 problems to review today" aria-label="2 problems to review today">2</span>', $html);
		$this->assertStringNotContainsString('Mistake<br>Training', $html, 'The training is on the homepage, not in the Play menu');
	}

	public function testTheOverviewShowsWhatIsDueAndLeadsIntoTheTraining()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 hour'))]],
				['set_order' => 2, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 hour'))]],
			],
		]);

		$html = (string) $this->testAction(MistakeTraining::$OVERVIEW_LINK, ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('id="mistake-training-due">2 problems to review today</p>', $html);
		$this->assertStringContainsString('id="mistake-training-start" href="' . MistakeTraining::$QUEUE_LINK . '">Start training</a>', $html);
		$this->assertStringContainsString('id="mistake-training-overview-title">Your progress</h2>', $html, 'With the calendar and streak of the profile');
		$this->assertStringNotContainsString('All caught up', $html);
		$this->assertStringNotContainsString('id="board"', $html, 'The overview opens no problem');
	}

	public function testTheOverviewListsTheProblemsInTraining()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['set_order' => 7, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 hour'))]],
				['set_order' => 3, 'status' => ['name' => 'V', 'mistake_training_rung' => 4, 'mistake_training_due' => date('Y-m-d', strtotime(MistakeTraining::today() . ' +10 days')) . ' 00:00:00']],
			],
		]);

		$html = (string) $this->testAction(MistakeTraining::$OVERVIEW_LINK, ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('<summary>Problems in training (2)</summary>', $html);
		$this->assertMatchesRegularExpression('~#7</td>\s*<td><span class="training-overview__insignia training-overview__insignia--chevron" role="img" aria-label="' . MistakeTraining::rungName(0) . '"~', $html);
		$this->assertMatchesRegularExpression('~title="' . MistakeTraining::rungName(4) . '">.*?</span></td>\s*<td>'
			. date('M j', strtotime(MistakeTraining::today() . ' +10 days')) . '</td>~s', $html, 'With the rung as insignia and the day of the next review');
		$this->assertLessThan(strpos($html, '#3</td>'), strpos($html, '#7</td>'), 'Soonest review first');
	}

	public function testTheOverviewIsAllCaughtUpOnceNothingIsDue()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+2 days'))]],
		]);

		$html = (string) $this->testAction(MistakeTraining::$OVERVIEW_LINK, ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('All caught up!', $html);
		$this->assertStringNotContainsString('mistake-training-start', $html);
	}

	public function testTheTitleOfATrainingProblemLinksToTheOverview()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 hour'))]],
		]);

		$html = (string) $this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('<a id="playTitleA" href="' . MistakeTraining::$OVERVIEW_LINK . '">Mistake Training 1/1</a>', $html);
	}

	public function testTheHomepageOffersPastMistakesEvenWithNothingToImport()
	{
		new ContextPreparator(['user' => ['name' => 'testuser']]);

		$html = (string) $this->testAction('/sites/index', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('Nothing to review today. Add past mistakes to training.', $html);
	}

	public function testAGuestSeesNoTrainingOnTheHomepage()
	{
		new ContextPreparator(['user' => null]);

		$html = (string) $this->testAction('/sites/index', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringNotContainsString('mistake-training-card', $html);
	}

	public function testHelpPageDescribesTheTraining()
	{
		$html = (string) $this->testAction('/sites/websitefunctions', ['method' => 'get', 'return' => 'contents']);
		$this->assertStringContainsString('A problem goes to the mistake training', $html);
	}

	public function testHelpPageGivesTheXpOfTheTraining()
	{
		$html = (string) $this->testAction('/sites/websitefunctions', ['method' => 'get', 'return' => 'contents']);
		$text = preg_replace('/\s+/', ' ', strip_tags($html));

		$this->assertStringContainsString('earns ' . round(MistakeTraining::REVIEW_XP_SHARE * 100) . '% of the XP the problem gives', $text);
		$this->assertStringContainsString('graduating it another ' . round(MistakeTraining::GRADUATION_XP_SHARE * 100) . '%', $text);
		$this->assertStringContainsString('at least ' . MistakeTraining::CLEAR_DAY_MIN_PROBLEMS . ' problems', $text);
		$this->assertStringContainsString('a bonus of ' . round(MistakeTraining::CLEAR_DAY_XP_SHARE * 100) . '%', $text);
	}

	public function testAnEmptyTrainingOffersToImportPastMistakes()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString(MistakeTraining::$IMPORT_LINK, (string) $this->view);
		$this->assertStringContainsString('Add past mistakes to training', (string) $this->view);
	}

	public function testATrainingThatHasWorkTodayDoesNotOfferAnImport()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'attempt' => ['solved' => true, 'misplays' => 1],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
			],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringNotContainsString(MistakeTraining::$IMPORT_LINK, (string) $this->view);
	}

	public function testTheImportIsOfferedWithoutACount()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]],
		]);

		$this->testAction('/mistake-training', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringNotContainsString('Before the training existed', (string) $this->view, 'The page does not count what is left to import');
	}

	public function testTheImportSaysHowManyProblemsItAdded()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]],
		]);

		$this->testAction(MistakeTraining::$IMPORT_LINK, ['method' => 'post']);

		$this->assertSame('Added 1 past mistake to the training.', CookieFlash::get());
	}

	public function testTheImportSaysWhenThereIsNothingLeftToAdd()
	{
		new ContextPreparator(['user' => ['name' => 'testuser']]);

		$this->testAction(MistakeTraining::$IMPORT_LINK, ['method' => 'post']);

		$this->assertSame('Nothing left to add, every past mistake is already in the training.', CookieFlash::get());
	}

	public function testImportFillsTheTrainingAndReturnsToTheQueue()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]],
		]);

		$this->testAction(MistakeTraining::$IMPORT_LINK, ['method' => 'post']);

		$this->assertNotNull(
			MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']),
			'The import brings the problem into the training'
		);
		$this->assertStringContainsString(MistakeTraining::$QUEUE_LINK, (string) ($this->headers['Location'] ?? ''));
	}

	/**
	 * The way the training was tried on the test server: of three imported problems one is solved and one
	 * missed today, the third is left alone. The next day the untouched one and the
	 * missed one are still to do, and the solved one comes back after its two days.
	 */
	public function testANextDayKeepsWhatWasNotSolvedAndBringsBackTheSolvedOneOnTime()
	{
		$now = date('Y-m-d H:i:s');
		$today = MistakeTraining::today();
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'S', 'mistake_training_due' => $now]],
				['set_order' => 2, 'status' => ['name' => 'S', 'mistake_training_due' => $now, 'mistake_training_review_day' => $today, 'mistake_training_review_result' => 'F']],
				['set_order' => 3, 'status' => ['name' => 'S', 'mistake_training_rung' => 1, 'mistake_training_due' => date('Y-m-d', strtotime($today . ' +2 days')) . ' 00:00:00', 'mistake_training_review_day' => $today, 'mistake_training_review_result' => 'S']],
			],
		]);
		$userId = $context->user['id'];
		$inTraining = fn() => array_map(fn($row) => (int) $row['tsumego_id'], MistakeTraining::todaysTraining($userId));
		$untouched = (int) $context->tsumegos[0]['id'];
		$missed = (int) $context->tsumegos[1]['id'];
		$solved = (int) $context->tsumegos[2]['id'];
		$this->assertSame([$untouched, $missed, $solved], $inTraining());

		$nextDay = fn() => MistakeTraining::shiftReviews($userId, -1);
		$nextDay();
		$this->assertSame([$untouched, $missed], $inTraining(), 'The next day the problems not solved are still there');
		$nextDay();
		$this->assertSame([$untouched, $missed, $solved], $inTraining(), 'Two days later the solved one is back');
	}

}

<?php

use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Feature;
use App\Utility\FeatureKey;
use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use Facebook\WebDriver\WebDriverBy;

class MistakeTrainingControllerTest extends TestCaseWithAuth
{
	public function setUp(): void
	{
		parent::setUp();
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::EVERYONE);
	}

	public function tearDown(): void
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::ADMINS);
		parent::tearDown();
	}
	public function testRequiresLogin()
	{
		new ContextPreparator(['user' => null]);
		$this->testAction('/mistake-training');
		$this->assertNotNull($this->headers['Location'] ?? null,
			'Should redirect to login');
	}

	public function testAllCaughtUpWhenNoDueProblems()
	{
		new ContextPreparator(['user' => ['name' => 'testuser']]);
		$this->testAction('/mistake-training', ['return' => 'contents']);
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

		$this->testAction('/mistake-training', ['return' => 'contents']);
		// Renders the next due problem's play page directly (no redirect), and
		// puts the user into mistake-training mode.
		$this->assertStringContainsString('Mistake Training', $this->view);
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

		$this->testAction('/mistake-training', ['return' => 'contents']);

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

		$this->testAction('/mistake-training', ['return' => 'contents']);

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

		$this->testAction('/mistake-training/play/' . $scId, ['return' => 'contents']);

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

		$this->testAction('/mistake-training/play/' . $scId);

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

		$this->testAction('/mistake-training/play/' . $scId);

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

		$this->testAction('/mistake-training', ['return' => 'contents']);

		$this->assertStringContainsString('var tsumegoID = ' . $context->tsumegos[1]['id'] . ';', $this->view,
			'The training opens the problem that came in first');
		$this->assertStringContainsString('Mistake Training 1/3', $this->view);
	}

	public function testEnteringTheTrainingOpensTheFirstProblemNotTriedToday()
	{
		$this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_SOLVED), self::reviewedToday(MistakeTraining::REVIEW_MISSED), []);

		$this->testAction('/mistake-training', ['return' => 'contents']);

		$this->assertStringContainsString('Mistake Training 3/3', $this->view,
			'A problem solved today keeps its place in the row, a problem missed today waits for the untried ones');
	}

	public function testAProblemMissedTodayCanBeTriedAgain()
	{
		$context = $this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_MISSED));

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 0), ['return' => 'contents']);

		$this->assertStringContainsString('Mistake Training 1/1', $this->view, 'A miss stays in today\'s training');
		$this->assertStringNotContainsString('This try does not count', $this->view, 'Trying a missed problem again is its review');
		$this->assertStringContainsString('problem-nav__item--N', $this->view, 'It stays grey until it is solved');
	}

	public function testAProblemSolvedTodayOpensAsAReplay()
	{
		$context = $this->prepareTraining([], self::reviewedToday(MistakeTraining::REVIEW_SOLVED));

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 1), ['return' => 'contents']);

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

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 0), ['return' => 'contents']);

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

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 0));

		$this->assertStringEndsWith('/mistake-training', $this->headers['Location'] ?? '',
			'Yesterday\'s solve is not part of today\'s training');
	}

	public function testTheNextLinkSkipsWhatTodaySolved()
	{
		$context = $this->prepareTraining([], self::reviewedToday(MistakeTraining::REVIEW_SOLVED), []);

		$this->testAction('/mistake-training/play/' . self::setConnectionId($context, 0), ['return' => 'contents']);

		$this->assertStringContainsString(
			'var nextButtonLink = "' . MistakeTraining::$PLAY_LINK_PREFIX . self::setConnectionId($context, 2) . '"', $this->view);
		$this->assertStringContainsString('var previousButtonLink = "' . MistakeTraining::$QUEUE_LINK . '"', $this->view,
			'Previous at the start of the row goes to the queue page');
	}

	public function testAllCaughtUpSaysWhatTodaySolved()
	{
		$context = $this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_SOLVED), self::reviewedToday(MistakeTraining::REVIEW_SOLVED));

		$this->testAction('/mistake-training', ['return' => 'contents']);

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

		$this->testAction('/mistake-training', ['return' => 'contents']);

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
		$this->assertSame('Rung 1 of 8',
			$browser->find('.problem-nav__item--current .problem-nav__rung')->getAttribute('aria-label'),
			'The button shows the bottom rung the mistake took the problem to');

		$status = $browser->find('#status');
		$browser->clickId('besogo-reset-button');
		$browser->waitUntilStale($status);
		$this->assertStringEndsWith(MistakeTraining::$PLAY_LINK_PREFIX . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL(),
			'Reset opens the problem again on the training route');
		$this->assertStringNotContainsString('Incorrect', $browser->find('#status')->getText(), 'Reset opens a fresh try');

		$browser->playWithResult('S');

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

		$this->testAction('/mistake-training', ['return' => 'contents']);
		// Should show "all caught up" since the tsumego was deleted
		$this->assertStringContainsString('All caught up', $this->view);
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

		$this->testAction('/mistake-training', ['return' => 'contents']);
		// Soft-deleted tsumego should be skipped, and with nothing else due it
		// shows "All caught up".
		$this->assertStringContainsString('All caught up', $this->view);

		// And it should be removed from the pool so it drops out of the queue
		$this->assertNull(MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']));
	}

	public function testTrainingIsNotFoundWhenTheFlagIsOff()
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::OFF);
		new ContextPreparator(['user' => ['name' => 'testuser']]);

		$this->expectException(NotFoundException::class);

		$this->testAction('/mistake-training');
	}

	public function testTrainingIsNotFoundForAPlayerWhileItIsOpenToAdmins()
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::ADMINS);
		new ContextPreparator(['user' => ['name' => 'testuser']]);

		$this->expectException(NotFoundException::class);

		$this->testAction('/mistake-training');
	}

	public function testAGuestIsNotFoundWhileTheFlagIsOff()
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::OFF);

		$this->expectException(NotFoundException::class);

		$this->testAction('/mistake-training');
	}

	public function testTrainingIsAvailableToAnAdminWhileItIsOpenToAdmins()
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::ADMINS);
		new ContextPreparator(['user' => ['admin' => true]]);

		$this->testAction('/mistake-training', ['return' => 'contents']);

		$this->assertStringContainsString('All caught up', $this->view);
	}

	public function testMenuHidesTheTrainingWhenTheFlagIsOff()
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::OFF);
		new ContextPreparator(['user' => ['name' => 'testuser']]);

		$html = $this->testAction('/sites/index', ['return' => 'contents']);

		$this->assertStringNotContainsString('/mistake-training', (string) $html);
	}

	public function testMenuShowsTheTrainingWhenItIsOpenToPlayers()
	{
		new ContextPreparator(['user' => ['name' => 'testuser']]);

		$html = $this->testAction('/sites/index', ['return' => 'contents']);

		$this->assertStringContainsString('/mistake-training', (string) $html);
	}

	public function testHelpPageDescribesTheTrainingOnlyWhileItIsOpen()
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::OFF);

		$html = (string) $this->testAction('/sites/websitefunctions', ['return' => 'contents']);
		$this->assertStringNotContainsString('A problem goes to the mistake training', $html);

		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::EVERYONE);

		$html = (string) $this->testAction('/sites/websitefunctions', ['return' => 'contents']);
		$this->assertStringContainsString('A problem goes to the mistake training', $html);
	}

	public function testAStoredTrainingModeFallsBackToLevelModeWhenTheFlagIsOff()
	{
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);
		Auth::saveUserField('mode', Constants::$MISTAKE_TRAINING_MODE);
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::OFF);
		$this->login($context->user['name']);

		$this->assertFalse(Auth::isInMistakeTrainingMode(),
			'A player left in training mode plays normally once the feature is off');
		$this->assertTrue(Auth::isInLevelMode());
	}

	public function testAnEmptyTrainingOffersToImportPastMistakes()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]],
		]);

		$this->testAction('/mistake-training', ['return' => 'contents']);

		$this->assertStringContainsString(MistakeTraining::$IMPORT_LINK, (string) $this->view);
	}

	public function testATrainingWithProblemsDoesNotOfferAnImport()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'attempt' => ['solved' => true, 'misplays' => 1],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+2 days'))],
			],
		]);

		$this->testAction('/mistake-training', ['return' => 'contents']);

		$this->assertStringNotContainsString(MistakeTraining::$IMPORT_LINK, (string) $this->view);
	}

	public function testATrainingWithoutCandidatesDoesNotOfferAnImport()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 0]],
		]);

		$this->testAction('/mistake-training', ['return' => 'contents']);

		$this->assertStringNotContainsString(MistakeTraining::$IMPORT_LINK, (string) $this->view);
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

	public function testImportIsNotAvailableWhileTheFeatureIsClosed()
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::OFF);
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]],
		]);

		$this->expectException(NotFoundException::class);
		$this->testAction(MistakeTraining::$IMPORT_LINK, ['method' => 'post']);
	}

	public function testTheToolboxIsClosedToPlayers()
	{
		new ContextPreparator(['user' => ['name' => 'testuser']]);

		$this->expectException(NotFoundException::class);
		$this->testAction(MistakeTraining::$DEBUG_LINK);
	}

	public function testTheToolboxIsNotServedOutsideDebugMode()
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$debug = Configure::read('debug');
		Configure::write('debug', 0);

		try
		{
			$this->expectException(NotFoundException::class);
			$this->testAction(MistakeTraining::$DEBUG_LINK);
		}
		finally
		{
			Configure::write('debug', $debug);
		}
	}

	/**
	 * Switching the training off must not take away the page that switches it back
	 * on, so the toolbox ignores the flag it controls.
	 */
	public function testTheToolboxStaysOpenWhileTheTrainingIsOff()
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::OFF);

		$this->testAction(MistakeTraining::$DEBUG_LINK, ['return' => 'contents']);

		$this->assertStringContainsString('Mistake Training toolbox', (string) $this->view);
	}

	public function testTheToolboxSwitchesTheFlag()
	{
		$context = new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::EVERYONE);
		$this->login($context->user['name']);

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'flag', 'state' => Feature::OFF],
		]);

		$this->assertSame(Feature::OFF, Feature::state(FeatureKey::MistakeTraining),
			'The toolbox turns the training off');
	}

	public function testTheToolboxAcceptsAPastedProblemAddress()
	{
		$context = new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true], 'tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		$connectionID = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'add', 'problem' => 'https://tsumego.ddev.site:33003/' . $connectionID . '?sid=7'],
		]);

		$this->assertNotNull(MistakeTraining::getPoolRow($userId, $tsumegoId),
			'The port and the query are not mistaken for the problem');
	}

	public function testTheToolboxSimulatesASolveAndAMistake()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'solve', 'tsumego_id' => $tsumegoId],
		]);
		$this->assertSame(1, (int) MistakeTraining::getPoolRow($userId, $tsumegoId)['rung'],
			'A simulated clean solve climbs the ladder');

		$this->login($context->user['name']);
		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'fail', 'tsumego_id' => $tsumegoId],
		]);

		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'A simulated mistake takes the problem back to the bottom');
		$this->assertSame(
			date('Y-m-d', strtotime(MistakeTraining::today() . ' +1 day')),
			date('Y-m-d', strtotime($row['next_due']))
		);
	}

	public function testTheToolboxMistakeOnADueProblemKeepsItDue()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'fail', 'tsumego_id' => $tsumegoId],
		]);

		$this->assertSame(0, (int) MistakeTraining::getPoolRow($userId, $tsumegoId)['rung']);
		$this->assertTrue(MistakeTraining::isDue($userId, $tsumegoId), 'A mistake on a due problem keeps it due today');
		$this->assertStringContainsString('stays due today until it is solved', urldecode((string) ($this->headers['Location'] ?? '')));
	}

	public function testTheToolboxGraduatesAProblem()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'graduate', 'tsumego_id' => $tsumegoId],
		]);

		$this->assertSame(0, MistakeTraining::totalInTraining($userId), 'A graduated problem leaves the training');
		$this->assertNull(MistakeTraining::getPoolRow($userId, $tsumegoId)['next_due']);
	}

	public function testTheToolboxMakesAProblemDueNow()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+5 days'))],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		$connectionID = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$this->assertFalse(MistakeTraining::isDue($userId, $tsumegoId));

		$this->testAction(MistakeTraining::$DEBUG_LINK . '/play/' . $connectionID);

		$this->assertTrue(MistakeTraining::isDue($userId, $tsumegoId),
			'Opening it schedules the problem for today');
		$this->assertSame(0, (int) MistakeTraining::getPoolRow($userId, $tsumegoId)['rung'],
			'And it does not change how well the problem is known');
		$this->assertStringContainsString(
			MistakeTraining::$PLAY_LINK_PREFIX . $connectionID,
			(string) ($this->headers['Location'] ?? '')
		);
	}

	public function testTheToolboxProblemLinkIsClosedToPlayers()
	{
		$context = new ContextPreparator(['user' => ['name' => 'testuser'], 'tsumego' => ['set_order' => 1]]);
		$connectionID = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$this->expectException(NotFoundException::class);
		$this->testAction(MistakeTraining::$DEBUG_LINK . '/play/' . $connectionID);
	}

	public function testTheToolboxIgnoresAProblemThatDoesNotExist()
	{
		$context = new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true], 'tsumego' => 1]);
		$userId = $context->user['id'];

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'add', 'problem' => 'https://tsumego.com/999999'],
		]);

		$this->assertSame(0, MistakeTraining::totalInTraining($userId));
		$this->assertStringContainsString('No problem for', urldecode((string) ($this->headers['Location'] ?? '')));
	}

	public function testTheToolboxIgnoresAnUnknownFlagState()
	{
		$context = new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::ADMINS);
		$this->login($context->user['name']);

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'flag', 'state' => 'both'],
		]);

		$this->assertSame(Feature::ADMINS, Feature::state(FeatureKey::MistakeTraining));
	}

	public function testTheToolboxShowsThePool()
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => [
					'name' => 'V',
					'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+2 days')),
					'mistake_training_rung' => 3,
					'mistake_training_lapses' => 2,
				],
			],
		]);

		$this->testAction(MistakeTraining::$DEBUG_LINK, ['return' => 'contents']);

		$this->assertStringContainsString('Mistake Training toolbox', (string) $this->view);
		$this->assertStringContainsString('<th>Rung</th><th>Lapses</th>', (string) $this->view);
		$this->assertMatchesRegularExpression('~<td>3</td>\s*<td>2</td>~', (string) $this->view, 'The row shows the rung and the lapses');
		$this->assertStringContainsString('<td>1, 2, 3, 6, 11, 23, 45, 90</td>', (string) $this->view, 'The waits after one lapse');
		$this->assertMatchesRegularExpression('~<td>3 or more</td>\s*<td>1, 1, 2, 4, 8, 15, 30, 60</td>~', (string) $this->view,
			'The last row is the floor, which every further lapse keeps');
	}

	/**
	 * The toolbox jumps through time, it does not rewrite the history of a problem, so
	 * the lapses stay whatever it does with the rung.
	 */
	public function testTheToolboxKeepsTheLapses()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+2 days')), 'mistake_training_lapses' => 2],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		$lapses = fn() => (int) MistakeTraining::getPoolRow($userId, $tsumegoId)['lapses'];

		foreach ([['command' => 'rung', 'rung' => 5], ['command' => 'add'], ['command' => 'graduate']] as $data)
		{
			$this->login($context->user['name']);
			$this->testAction(MistakeTraining::$DEBUG_LINK, [
				'method' => 'post',
				'data' => $data + ['tsumego_id' => $tsumegoId, 'problem' => $context->tsumegos[0]['set-connections'][0]['id']],
			]);
			$this->assertSame(2, $lapses(), 'The ' . $data['command'] . ' command keeps the lapses');
		}
	}

	public function testTheToolboxMovesTheReviewsByDays()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+3 days'))],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'shift', 'days' => 4],
		]);

		$this->assertSame(
			date('Y-m-d', strtotime('+7 days')),
			date('Y-m-d', strtotime(MistakeTraining::getPoolRow($userId, $tsumegoId)['next_due'])),
			'A shift moves the date and nothing else'
		);
	}

	public function testTheToolboxAddsAProblemAndJumpsToARung()
	{
		$context = new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true], 'tsumego' => 1]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'add', 'problem' => $context->tsumegos[0]['set-connections'][0]['id']],
		]);
		$this->assertTrue(MistakeTraining::isDue($userId, $tsumegoId), 'An added problem is due at once');

		$this->login($context->user['name']);
		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'rung', 'problem' => $context->tsumegos[0]['set-connections'][0]['id'], 'rung' => 5],
		]);

		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertSame(5, (int) $row['rung']);
		$this->assertTrue(MistakeTraining::isDue($userId, $tsumegoId), 'A jumped problem is due at once');
	}

	public function testTheToolboxEmptiesTheTraining()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$userId = $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'reset'],
		]);

		$this->assertNull(MistakeTraining::getPoolRow($userId, $tsumegoId));
	}

	public function testTheToolboxImportsAsManyAsAsked()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'attempt' => ['solved' => true, 'misplays' => 1, 'created' => date('Y-m-d H:i:s', strtotime('-1 year'))],
			],
			'tsumegos' => [
				['set_order' => 2, 'attempt' => ['solved' => true, 'misplays' => 1]],
			],
		]);
		$userId = $context->user['id'];

		$this->testAction(MistakeTraining::$DEBUG_LINK, [
			'method' => 'post',
			'data' => ['command' => 'import', 'limit' => 1],
		]);

		$this->assertSame(1, MistakeTraining::totalInTraining($userId));
		$this->assertNotNull(MistakeTraining::getPoolRow($userId, (int) $context->tsumegos[1]['id']),
			'The newest mistake comes first');
	}
}

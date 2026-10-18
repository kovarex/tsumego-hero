<?php

use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Feature;
use App\Utility\FeatureKey;
use App\Utility\MistakeTraining;
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
		$dueTomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));
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
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+2 days'))],
			],
		]);

		$this->testAction(MistakeTraining::$DEBUG_LINK, ['return' => 'contents']);

		$this->assertStringContainsString('Mistake Training toolbox', (string) $this->view);
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

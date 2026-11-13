<?php

use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use App\Utility\Rating;
use Facebook\WebDriver\WebDriverBy;

class MistakeTrainingControllerTest extends IntegrationTestCase
{
	private const SGF = '(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]SZ[19]KM[0.00]AW[cc][cd][dc][ed]AB[cb][db][eb][fc])';

	public function testRequiresLogin()
	{
		new ContextPreparator(['user' => null]);
		$this->get('/mistake-training');
		$this->assertNotNull($this->headerValue('Location') ?? null,
			'Should redirect to login');
	}

	public function testNothingToReviewYetForAPlayerWhoNeverTrained()
	{
		new ContextPreparator(['user' => ['name' => 'testuser']]);
		$this->get('/mistake-training');

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

		$this->get('/mistake-training');

		$this->assertMatchesRegularExpression('/<div id="status2"[^>]*>\s*<span class="status-rating"[^>]*>\s*' . preg_quote(Rating::getReadableRankFromRating(1500), '/') . ' /',
			$this->view, 'The rank shows as in the level mode');
		$this->assertNull($this->playPage()['problem']['xpStatus'], 'The training gives no XP');
	}

	public function testAllCaughtUpWhenNoDueProblems()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'status' => ['name' => 'S', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+3 days'))]],
		]);
		$this->get('/mistake-training');
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

		$this->get('/mistake-training');
		// Renders the next due problem's play page directly (no redirect), and
		// puts the user into mistake-training mode.
		$this->assertSame((int) $context->tsumegos[0]['id'], $this->playPage()['problem']['tsumegoID'],
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

		$this->get('/mistake-training');

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

		$this->get('/mistake-training');

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
		Auth::saveUserField('mode_id', Constants::$LEVEL_MODE);

		$this->get('/mistake-training/play/' . $scId);

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

		$this->get('/mistake-training/play/' . $scId);

		$this->assertStringEndsWith('/mistake-training', $this->headerValue('Location') ?? '',
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

		$this->get('/mistake-training/play/' . $scId);

		$this->assertStringEndsWith('/mistake-training', $this->headerValue('Location') ?? '',
			'A problem that is scheduled for later is not part of today\'s queue');
	}

	//Problems in the training, in the order they came in. Each entry is added to the
	//status fixture of its problem; by default a problem is due, every one at the same
	//moment the way an import brings them, and untried today.
	private function prepareTraining(array ...$problems): ContextPreparator
	{
		$due = MistakeTraining::today() . ' 00:00:00';
		$tsumegos = [];
		foreach ($problems as $index => $problem)
			$tsumegos[] = ['set_order' => $index + 1, 'status' => $problem + ['name' => 'V', 'mistake_training_due' => $due]];
		return new ContextPreparator(['user' => ['name' => 'testuser'], 'tsumegos' => $tsumegos]);
	}

	private static function reviewedToday(int $result): array
	{
		return [
			'mistake_training_due' => $result === MistakeTraining::REVIEW_MISSED
				? MistakeTraining::today() . ' 00:00:00'
				: date('Y-m-d H:i:s', strtotime('+3 days')),
			'mistake_training_review_day' => MistakeTraining::today(),
			'mistake_training_review_result' => $result,
		];
	}

	private static function setConnectionId(ContextPreparator $context, int $index): int
	{
		return (int) $context->tsumegos[$index]['set-connections'][0]['id'];
	}

	//Twenty problems imported at once are due at the same moment, and they come in
	//newest mistake first, not in the order of their set. The training must still
	//open at the first of them, not wherever an unordered query lands.
	public function testEnteringTheTrainingOpensTheFirstProblemOfTheDay()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [['set_order' => 1], ['set_order' => 2], ['set_order' => 3]],
		]);
		$due = MistakeTraining::today() . ' 00:00:00';
		foreach ([1, 2, 0] as $index)
			MistakeTraining::addProblem($context->user['id'], (int) $context->tsumegos[$index]['id'], $due);

		$this->get('/mistake-training');

		$this->assertSame((int) $context->tsumegos[1]['id'], $this->playPage()['problem']['tsumegoID'],
			'The training opens the problem that came in first');
		$this->assertStringContainsString('Mistake Training 1/3', $this->view);
	}

	public function testEnteringTheTrainingOpensTheFirstProblemNotTriedToday()
	{
		$this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_SOLVED), self::reviewedToday(MistakeTraining::REVIEW_MISSED), []);

		$this->get('/mistake-training');

		$this->assertStringContainsString('Mistake Training 3/3', $this->view,
			'A problem solved today keeps its place in the row, a problem missed today waits for the untried ones');
	}

	public function testAProblemMissedTodayCanBeTriedAgain()
	{
		$context = $this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_MISSED));

		$this->get('/mistake-training/play/' . self::setConnectionId($context, 0));

		$this->assertStringContainsString('Mistake Training 1/1', $this->view, 'A miss stays in today\'s training');
		$this->assertStringNotContainsString('This try does not count', $this->view, 'Trying a missed problem again is its review');
		$this->assertStringContainsString('problem-nav__item--N', $this->view, 'It stays grey until it is solved');
	}

	public function testAProblemSolvedTodayOpensAsAReplay()
	{
		$context = $this->prepareTraining([], self::reviewedToday(MistakeTraining::REVIEW_SOLVED));

		$this->get('/mistake-training/play/' . self::setConnectionId($context, 1));

		$this->assertStringContainsString('Mistake Training 2/2', $this->view, 'A problem solved today keeps its place in the row');
		$this->assertStringContainsString('Already solved today. This try does not count.', $this->view);
	}

	//The replay of a problem solved today offers its normal page, where its review shows the answer; a problem that
	//is due, missed today included, offers nothing of the kind.
	public function testOnlyAProblemSolvedTodayOffersItsPageOutsideTheTraining()
	{
		$context = $this->prepareTraining([], self::reviewedToday(MistakeTraining::REVIEW_SOLVED), self::reviewedToday(MistakeTraining::REVIEW_MISSED));

		$this->get(MistakeTraining::$PLAY_LINK_PREFIX . self::setConnectionId($context, 1));
		$this->assertStringContainsString('<a class="btn btn--small btn--navigation" id="mistake-training-normal-view" href="/'
			. self::setConnectionId($context, 1) . '">Open outside the training</a>', $this->view);

		foreach ([0 => 'due today', 2 => 'missed today'] as $index => $state)
		{
			$this->get(MistakeTraining::$PLAY_LINK_PREFIX . self::setConnectionId($context, $index));
			$this->assertStringContainsString('Mistake Training', $this->view);
			$this->assertStringNotContainsString('mistake-training-normal-view', $this->view, "A problem $state opens only in the training");
		}
	}

	//The normal page of a problem the training has due shows it unsolved, whatever its status says: no review, which
	//shows the answer, no comments, which may tell it, until it is solved, which is its review. Due today, missed
	//today and overdue since an earlier day are all due.
	public function testTheNormalPageOfADueProblemKeepsItsAnswerClosed()
	{
		$context = $this->prepareTraining(
			['name' => 'S'],
			['name' => 'S'] + self::reviewedToday(MistakeTraining::REVIEW_MISSED),
			['name' => 'S', 'mistake_training_due' => date('Y-m-d', strtotime(MistakeTraining::today() . ' -2 days')) . ' 00:00:00']);

		foreach ([0 => 'due today', 1 => 'missed today', 2 => 'overdue'] as $index => $state)
		{
			$this->get('/' . self::setConnectionId($context, $index));
			$this->assertFalse($this->playPage()['problem']['solved'], "A problem $state opens unsolved, its review closed");
			$this->assertStringContainsString('title="The comments open once the problem is solved"', $this->view, "And its comments closed ($state)");
		}
	}

	//Once the training solved a problem today, or while it waits for a later day, its normal page shows it as any
	//solved problem: its review and its comments are open.
	public function testTheNormalPageOfAProblemNotDueShowsItSolved()
	{
		$context = $this->prepareTraining(
			['name' => 'S'] + self::reviewedToday(MistakeTraining::REVIEW_SOLVED),
			['name' => 'S', 'mistake_training_due' => date('Y-m-d', strtotime(MistakeTraining::today() . ' +1 day')) . ' 00:00:00']);

		foreach ([0 => 'solved today', 1 => 'due tomorrow'] as $index => $state)
		{
			$this->get('/' . self::setConnectionId($context, $index));
			$this->assertTrue($this->playPage()['problem']['solved'], "A problem $state opens solved, with its review");
			$this->assertStringNotContainsString('The comments open once the problem is solved', $this->view, "And its comments ($state)");
		}
	}

	//The way out of the training, in the browser: the overview opens the problem solved today on its normal page,
	//with its review. The one still due opens there with its review closed, and solving it there is its review,
	//which opens the review.
	public function testTheOverviewOpensOutsideTheTrainingOnlyWhatIsNotDue()
	{
		$context = $this->prepareTraining(['name' => 'S'], ['name' => 'S'] + self::reviewedToday(MistakeTraining::REVIEW_SOLVED));
		$userId = (int) $context->user['id'];
		$dueTsumegoId = (int) $context->tsumegos[0]['id'];
		$reviewState = fn(Browser $browser) => (string) $browser->driver->executeScript(
			'return document.getElementById("besogo-review-button") ? "active" : (document.getElementById("besogo-review-button-inactive") ? "inactive" : "none");');

		$browser = Browser::instance();
		$browser->get(substr(MistakeTraining::$OVERVIEW_LINK, 1));
		$buttons = $browser->getElements('.mistake-training-pool__table a[data-tsumego-id]');
		$this->assertCount(1, $buttons, 'Only the problem solved today opens outside the training');
		$this->assertCount(1, $browser->getElements('.mistake-training-pool__table .problem-nav__item--locked'), 'The one due today is locked');
		$buttons[0]->click();
		$browser->waitUntil(fn() => str_ends_with($browser->driver->getCurrentURL(), '/' . self::setConnectionId($context, 1)), context: 'the normal page of the problem');
		$browser->waitForBoard();
		$this->assertSame('active', $reviewState($browser), 'Its review is open');

		$browser->get((string) self::setConnectionId($context, 0));
		$browser->waitForBoard();
		$this->assertSame('inactive', $reviewState($browser), 'The review of the problem that is due stays closed');
		$browser->playWithResult('S');
		$browser->waitUntil(fn() => $reviewState($browser) === 'active', context: 'the review opened by the solve');
		$row = MistakeTraining::getPoolRow($userId, $dueTsumegoId);
		$this->assertFalse(MistakeTraining::isDue($userId, $dueTsumegoId), 'The solve on the normal page was its review');
		$this->assertSame(MistakeTraining::REVIEW_SOLVED, $row['last_mistake_training_result_id']);
		$this->assertSame(1, (int) $row['rung'], 'A clean review climbs a rung');
	}

	//The colour of the row comes from what today did, so a problem missed and then
	//solved on a retry is green when the page is drawn again, whatever its status.
	public function testAProblemSolvedOnARetryIsGreenInTheRow()
	{
		$context = $this->prepareTraining(['name' => 'F', 'mistake_training_rung' => 3]);
		$userId = (int) $context->user['id'];
		$tsumegoId = (int) $context->tsumegos[0]['id'];
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::Failed, null, true);
		MistakeTraining::recordResult($userId, $tsumegoId, PlayOutcome::SolvedClean, null, true);

		$this->get('/mistake-training/play/' . self::setConnectionId($context, 0));

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

		$this->get('/mistake-training/play/' . self::setConnectionId($context, 0));

		$this->assertStringEndsWith('/mistake-training', $this->headerValue('Location') ?? '',
			'Yesterday\'s solve is not part of today\'s training');
	}

	public function testTheNextLinkSkipsWhatTodaySolved()
	{
		$context = $this->prepareTraining([], self::reviewedToday(MistakeTraining::REVIEW_SOLVED), []);

		$this->get('/mistake-training/play/' . self::setConnectionId($context, 0));

		$this->assertSame(MistakeTraining::$PLAY_LINK_PREFIX . self::setConnectionId($context, 2), $this->playPage()['problem']['nextLink']);
		$this->assertSame('', $this->playPage()['problem']['previousLink'],
			'Back at the start of the row has nowhere to go, so the page turns it off');
	}

	public function testTheLastDueProblemHasNoNextUntilItIsSolved()
	{
		$context = $this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_SOLVED), []);

		$this->get('/mistake-training/play/' . self::setConnectionId($context, 1));

		$this->assertSame('', $this->playPage()['problem']['nextLink'],
			'The queue page would only serve the same problem again; the solve turns Next on');
		$this->assertSame(MistakeTraining::$PLAY_LINK_PREFIX . self::setConnectionId($context, 0), $this->playPage()['problem']['previousLink']);
	}

	public function testAllCaughtUpSaysWhatTodaySolved()
	{
		$context = $this->prepareTraining(self::reviewedToday(MistakeTraining::REVIEW_SOLVED), self::reviewedToday(MistakeTraining::REVIEW_SOLVED));

		$this->get('/mistake-training');

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

		$this->get('/mistake-training');

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
		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		$this->get('/' . $scId);

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

	//A mistake locks the board, but the problem stays in today's training, so Reset
	//must start a fresh try of it, on the board as it is, without loading the page again.
	//The solve that follows is the retry: it earns no rung, and the button turns green
	//because today is done with the problem. The page is not reloaded after a result, so
	//the result itself redraws the button.
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

		$this->assertSame('Incorrect!', $browser->find('#status')->getText(), 'The row of the messages says only Incorrect!');
		$this->assertTrue((bool) $browser->driver->executeScript('return document.activeElement?.id === "besogo-reset-button";'), 'Reset has the focus, to try again');
		$this->assertSame(0, (int) MistakeTraining::getPoolRow($userId, $tsumegoId)['rung'], 'A mistake goes back to the bottom');
		$this->assertTrue(MistakeTraining::isDue($userId, $tsumegoId), 'A mistake keeps the problem due today');
		$this->assertSame('Rung 1 of 8, missed today',
			$browser->find('.problem-nav__item--current .problem-nav__rung')->getAttribute('aria-label'),
			'The button shows the bottom rung the mistake took the problem to, ringed as missed');

		$browser->driver->executeScript('window.pageBeforeReset = true;');
		$browser->clickId('besogo-reset-button');
		$this->assertTrue((bool) $browser->driver->executeScript('return window.pageBeforeReset === true;'), 'Reset does not load the page again');
		$this->assertStringEndsWith(MistakeTraining::$PLAY_LINK_PREFIX . $context->tsumegos[0]['set-connections'][0]['id'], $browser->driver->getCurrentURL(),
			'The problem stays on the training route');
		$this->assertStringNotContainsString('Incorrect', $browser->find('#status')->getText(), 'Reset opens a fresh try');
		$this->assertCount(0, $browser->driver->findElements(WebDriverBy::id('besogo-back-button-inactive')),
			'The training has no Back, the problem buttons lead back');
		$this->assertCount(1, $browser->driver->findElements(WebDriverBy::id('besogo-next-button-inactive')),
			'Nor anything after it until it is solved');

		$browser->playWithResult('S');
		$browser->driver->wait(5)->until(fn() => count($browser->driver->findElements(WebDriverBy::id('besogo-next-button'))) === 1);
		$this->assertStringEndsWith(MistakeTraining::$QUEUE_LINK, (string) $browser->find('#besogo-next-button')->getAttribute('href'),
			'The solve turns Next on, to the page that says the day is done');
		$this->assertSame('Correct!', $browser->find('#status')->getText(), 'The row of the messages says only Correct!');
		$this->assertNotEmpty($browser->find('#xpDisplay')->getAttribute('title'), 'And the reward says what it did to the problem');

		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'The solve after a mistake of the same day earns no rung');
		$this->assertSame(MistakeTraining::REVIEW_SOLVED, $row['last_mistake_training_result_id']);
		$this->assertFalse(MistakeTraining::isDue($userId, $tsumegoId), 'The problem is done for today');

		// The colour the server draws, not the one the page set on the solve.
		$browser->get(MistakeTraining::$PLAY_LINK_PREFIX . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertStringContainsString('problem-nav__item--S', $browser->find('.problem-nav__item--current')->getAttribute('class'),
			'Solved today shows green');
		$this->assertStringContainsString('Already solved today', $browser->pageText(), 'Opened again, it is only a replay');
	}

	//Reset in the training rewinds the board where it is, as in the level mode, so with the random colours and corner
	//the problem keeps the ones it was shown with, try after try; only another problem picks them again.
	public function testResetInTheTrainingKeepsTheColoursAndTheCorner()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser', 'player_color_setting' => UserTable::PLAYER_COLOR_SETTING_RANDOM, 'board_orientation_setting' => UserTable::BOARD_ORIENTATION_SETTING_RANDOM],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'S', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3],
			],
		]);

		$browser = Browser::instance();
		$browser->get('mistake-training');
		$browser->waitForBoard();
		$shown = 'return [besogo.playerColor, besogo.boardInverted, besogo.boardParameters.corner].join("|");';
		$before = $browser->driver->executeScript($shown);
		$browser->driver->executeScript('window.pageBeforeReset = true;');
		for ($try = 0; $try < 5; $try++)
		{
			$browser->playWithResult('F');
			$browser->clickId('besogo-reset-button');
			$this->assertSame($before, $browser->driver->executeScript($shown), 'The colours and the corner stay after reset ' . ($try + 1));
		}
		$this->assertTrue((bool) $browser->driver->executeScript('return window.pageBeforeReset === true;'), 'No reset loaded the page again');
	}

	//The tries after Reset count as the tries of a reloaded page did: every fail is sent (the first miss of the day
	//is the one the ladder counts), a reset with nothing lost sends nothing, and the solve after the
	//mistakes is sent as a clean one of its own try.
	public function testEachTryAfterResetInTheTrainingIsAFreshOne()
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
		//the outcomes the page sends, in order
		$browser->driver->executeScript(<<<'JS'
			window.sentOutcomes = [];
			let send = window.fetch;
			window.fetch = function (input, init) {
				if (String(input).includes('/tsumegos/result'))
					window.sentOutcomes.push(JSON.parse(init.body).outcome);
				return send.apply(window, arguments);
			};
			JS);
		$browser->playWithResult('F');
		$browser->clickId('besogo-reset-button');
		$browser->playWithResult('F');
		$browser->clickId('besogo-reset-button');
		//a reset with nothing played sends nothing
		$browser->clickId('besogo-reset-button');
		$browser->playWithResult('S');

		$this->assertSame([PlayOutcome::Failed->value, PlayOutcome::Failed->value, PlayOutcome::SolvedClean->value],
			$browser->driver->executeScript('return window.sentOutcomes;'), 'Each fail is sent, and the solve is a clean one of its own try');
		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertSame(0, (int) $row['rung'], 'The mistakes of the day took the problem to the bottom');
		$this->assertSame(MistakeTraining::REVIEW_SOLVED, $row['last_mistake_training_result_id']);
		$this->assertFalse(MistakeTraining::isDue($userId, $tsumegoId), 'The solve finishes the problem for today');
	}

	//A problem solved on the page is done for today, so Reset after the solve starts a replay: the page says so, as it
	//does when the problem is opened again, offers the problem outside the training, and the review closes until the
	//replay is solved. The replay changes nothing in the training.
	public function testResetAfterASolveInTheTrainingIsAReplay()
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
		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];

		$browser = Browser::instance();
		$browser->get('mistake-training');
		$this->assertFalse($browser->find('.mistake-training-replay')->isDisplayed(), 'A problem that is due is no replay');
		$browser->playWithResult('S');
		$rowAfterSolve = MistakeTraining::getPoolRow($userId, $tsumegoId);
		$this->assertCount(1, $browser->driver->findElements(WebDriverBy::id('besogo-review-button')), 'The solve opens the review');

		$browser->clickId('besogo-reset-button');
		$this->assertTrue($browser->find('.mistake-training-replay')->isDisplayed(), 'The next try is a replay, and the page says so');
		$this->assertStringContainsString('Already solved today. This try does not count.', $browser->find('.mistake-training-replay')->getText());
		$this->assertStringEndsWith('/' . $setConnectionId, (string) $browser->find('#mistake-training-normal-view')->getAttribute('href'),
			'It offers the problem outside the training');
		$this->assertCount(1, $browser->driver->findElements(WebDriverBy::id('besogo-review-button-inactive')), 'The review closes for the replay');

		$browser->playWithResult('F');
		$browser->clickId('besogo-reset-button');
		$browser->playWithResult('S');
		$this->assertCount(1, $browser->driver->findElements(WebDriverBy::cssSelector('#mistake-training-normal-view')), 'The link is offered once');
		$this->assertEquals($rowAfterSolve, MistakeTraining::getPoolRow($userId, $tsumegoId), 'The replay changes nothing in the training');
		$browser->assertNoJsErrors();
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

		$this->fetchTable('Tsumego')->deleteAll(['id' => $context->tsumegos[0]['id']]);

		$this->get('/mistake-training');
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

		$this->get('/mistake-training');
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

		$this->get('/mistake-training');
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

		$this->get('/mistake-training');
		$openConnection = (int) $context->tsumegos[0]['set-connections'][1]['id'];
		$this->assertSame((int) $context->tsumegos[0]['id'], $this->playPage()['problem']['tsumegoID']);
		$this->assertSame((int) $openConnection, $this->playPage()['problem']['setConnectionID'],
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
		$this->get(MistakeTraining::$PLAY_LINK_PREFIX . $trashedConnection);

		$this->assertStringEndsWith('/mistake-training', $this->headerValue('Location') ?? '',
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

		$this->get(MistakeTraining::$OVERVIEW_LINK);

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

		$this->get('/mistake-training');
		$this->assertStringContainsString('All caught up', $this->view,
			'A player without sandbox access is not sent into a sandbox collection');

		$context = new ContextPreparator([
			'user' => ['name' => 'testuser2', 'premium' => 2],
			'tsumego' => [
				'sets' => [['name' => 'sandbox collection', 'num' => 1, 'public' => 0]],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$this->get('/mistake-training');
		$this->assertSame((int) $context->tsumegos[0]['id'], $this->playPage()['problem']['tsumegoID'],
			'Premium players may play sandbox collections, so the training opens the problem');
	}

	//The training is in the top bar of every page, so the homepage has no card of its own
	public function testTheHomepageShowsWhatTheTrainingHasTodayInTheTopBar()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => MistakeTraining::today() . ' 00:00:00']],
				['set_order' => 2, 'status' => ['name' => 'V', 'mistake_training_due' => MistakeTraining::today() . ' 00:00:00']],
			],
		]);

		$this->get('/sites/index');
		$html = $this->view;

		$this->assertMatchesRegularExpression('#<a id="header-mistake-training"[^>]*href="' . preg_quote(MistakeTraining::$OVERVIEW_LINK, '#') . '"[^>]*>.*?<span class="header-icons__badge" aria-hidden="true">2</span>#s', $html,
			'The top bar opens the overview, with what is due today');
		$this->assertStringNotContainsString('mistake-training-card', $html, 'The homepage has no card of the training');
	}

	public function testTheOverviewShowsWhatIsDueAndLeadsIntoTheTraining()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => MistakeTraining::today() . ' 00:00:00']],
				['set_order' => 2, 'status' => ['name' => 'V', 'mistake_training_due' => MistakeTraining::today() . ' 00:00:00']],
			],
		]);

		$this->get(MistakeTraining::$OVERVIEW_LINK);
		$html = $this->view;

		$this->assertStringContainsString('id="mistake-training-due">2 problems to review today</p>', $html);
		$this->assertStringContainsString('id="mistake-training-start" href="' . MistakeTraining::$QUEUE_LINK . '">Start training</a>', $html);
		$this->assertStringContainsString('id="mistake-training-overview-title">Your progress</h2>', $html, 'With the calendar of the profile');
		$this->assertStringNotContainsString('All caught up', $html);
		$this->assertStringNotContainsString('id="board"', $html, 'The overview opens no problem');
	}

	public function testTheOverviewListsTheProblemsInTraining()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['set_order' => 7, 'status' => ['name' => 'V', 'mistake_training_due' => MistakeTraining::today() . ' 00:00:00']],
				['set_order' => 3, 'status' => ['name' => 'V', 'mistake_training_rung' => 4, 'mistake_training_due' => date('Y-m-d', strtotime(MistakeTraining::today() . ' +10 days')) . ' 00:00:00']],
			],
		]);

		$this->get(MistakeTraining::$OVERVIEW_LINK);
		$html = $this->view;
		$rows = self::overviewRows($html);

		$this->assertStringContainsString('<summary>Problems in training (2)</summary>', $html);
		$this->assertCount(2, $rows);
		$this->assertStringContainsString('problem-nav__item--locked', $rows[0], 'Soonest review first: the one due today');
		$this->assertMatchesRegularExpression('~<td><span class="training-overview__insignia training-overview__insignia--chevron" role="img" aria-label="' . MistakeTraining::rungName(0) . '"~', $rows[0]);
		$this->assertStringContainsString('href="/' . self::setConnectionId($context, 1) . '"', $rows[1]);
		$this->assertMatchesRegularExpression('~title="' . MistakeTraining::rungName(4) . '">.*?</span></td>\s*<td class="mistake-training-pool__next">'
			. preg_quote(MistakeTraining::dayHtml(MistakeTraining::today() . ' +10 days')) . '</td>~s', $rows[1], 'With the rung as insignia and the day of the next review');
	}

	//The rows of the list of problems on the overview, in their order
	private static function overviewRows(string $html): array
	{
		preg_match_all('~<tr class="mistake-training-pool__row">.*?</tr>~s', $html, $matches);
		return $matches[0];
	}

	//A problem opens from the overview in the normal play page, where the review shows the solution, so
	//one still to be solved in today's training is locked: due today and untried, or missed today. One
	//solved today and one due another day open.
	public function testTheOverviewOpensOnlyWhatTodaysTrainingIsDone()
	{
		$today = MistakeTraining::today() . ' 00:00:00';
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['sgf' => self::SGF, 'sets' => [['name' => 'Due Collection', 'num' => 41]], 'rating' => 1500, 'status' => ['name' => 'V', 'mistake_training_due' => $today]],
				['sgf' => self::SGF, 'sets' => [['name' => 'Missed Collection', 'num' => 42]], 'rating' => 1500, 'status' => ['name' => 'V'] + self::reviewedToday(MistakeTraining::REVIEW_MISSED)],
				['sgf' => self::SGF, 'sets' => [['name' => 'Solved Collection', 'num' => 43]], 'rating' => 1500, 'status' => ['name' => 'V'] + self::reviewedToday(MistakeTraining::REVIEW_SOLVED)],
				['sgf' => self::SGF, 'sets' => [['name' => 'Later Collection', 'num' => 44]], 'rating' => 1500,
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d', strtotime(MistakeTraining::today() . ' +5 days')) . ' 00:00:00']],
			],
		]);

		$this->get(MistakeTraining::$OVERVIEW_LINK);
		$rows = self::overviewRows($this->view);

		$this->assertCount(4, $rows);
		foreach ([0 => 'Due', 1 => 'Missed'] as $index => $name)
		{
			$row = $rows[$index];
			$this->assertStringContainsString('<li class="problem-nav__item problem-nav__item--N problem-nav__item--locked">', $row, "$name today: locked");
			$this->assertMatchesRegularExpression('~<span class="problem-nav__number">\?</span>~', $row, "$name today: shows only ?");
			foreach (['Due Collection', 'Missed Collection', '#4', '>41<', '>42<', 'href=', 'data-', 'tooltip', '/sets/view/'] as $leak)
				$this->assertStringNotContainsString($leak, $row, "$name today: the row doesn't give away $leak");
			foreach ([0, 1] as $hidden)
			{
				$this->assertStringNotContainsString('/' . self::setConnectionId($context, $hidden) . '"', $this->view, 'No link to a locked problem anywhere');
				$this->assertStringNotContainsString('data-tsumego-id="' . $context->tsumegos[$hidden]['id'] . '"', $this->view, 'No id of a locked problem anywhere');
			}
		}
		$this->assertStringNotContainsString('Due Collection', $this->view, 'The collection of a locked problem is nowhere on the page');
		$this->assertStringNotContainsString('Missed Collection', $this->view, 'The collection of a locked problem is nowhere on the page');

		foreach ([2 => 'Solved', 3 => 'Later'] as $index => $name)
		{
			$row = $rows[$index];
			$this->assertStringNotContainsString('problem-nav__item--locked', $row, "$name: opens");
			$this->assertStringContainsString('<a class="tooltip" href="/' . self::setConnectionId($context, $index) . '" data-tsumego-id="' . $context->tsumegos[$index]['id'] . '"', $row,
				"$name: the normal problem button, leading to the normal play page");
			$this->assertStringContainsString('<div class="problem-nav__number">' . (41 + $index) . '</div>', $row, "$name: with its number in the collection");
			$this->assertStringContainsString('>' . $name . ' Collection</a>', $row, "$name: with its collection");
			$this->assertStringContainsString(' data-sgf-preview=', $row, "$name: with the board in its tooltip");
		}
	}

	//The list, with its buttons, its collections and the rank, fits a narrow phone without a sideways scroll.
	public function testTheOverviewFitsANarrowPhone(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['sets' => [['name' => 'A collection with a rather long name for a narrow phone', 'num' => 128]], 'rating' => 2300,
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d', strtotime(MistakeTraining::today() . ' +3 days')) . ' 00:00:00']],
				['sets' => [['name' => 'Gokyo Shumyo', 'num' => 23]], 'rating' => 2100,
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d', strtotime(MistakeTraining::today() . ' +4 days')) . ' 00:00:00']],
				['sets' => [['name' => 'Capturing Race', 'num' => 4]], 'rating' => 2500,
					'status' => ['name' => 'V', 'mistake_training_rung' => 6, 'mistake_training_due' => date('Y-m-d', strtotime(MistakeTraining::today() . ' +20 days')) . ' 00:00:00']],
				['set_order' => 2, 'status' => ['name' => 'V', 'mistake_training_due' => MistakeTraining::today() . ' 00:00:00']],
			],
		]);
		// The browser window doesn't get narrower than about 490 pixels, so the page is measured in a frame of 320.
		$browser = Browser::instance();
		$browser->get(substr(MistakeTraining::$OVERVIEW_LINK, 1));
		$layout = $browser->driver->executeAsyncScript(<<<'JS'
			const done = arguments[arguments.length - 1];
			const frame = document.createElement('iframe');
			frame.style.cssText = 'width: 320px; height: 700px; border: 0';
			frame.onload = () =>
			{
				const page = frame.contentDocument;
				// a phone's scrollbar lies over the page
				page.documentElement.style.scrollbarWidth = 'none';
				const width = page.documentElement.clientWidth;
				const parts = [...page.querySelectorAll('.mistake-training-pool *')];
				done({
					width,
					rows: page.querySelectorAll('.mistake-training-pool__row').length,
					squashedSets: [...page.querySelectorAll('.mistake-training-pool__set')].filter(set => set.getBoundingClientRect().width < 50).length,
					overflow: page.documentElement.scrollWidth - width,
					outside: parts.filter(part => part.getBoundingClientRect().right > width + 0.5).length,
				});
			};
			frame.src = location.href;
			document.body.prepend(frame);
			JS);
		$this->assertEquals(['width' => 320, 'rows' => 4, 'squashedSets' => 0, 'outside' => 0, 'overflow' => 0], $layout,
			'The collection wraps between its words, under the button if need be');
		$browser->assertNoJsErrors();
	}

	//The rank of each problem, as the problem page shows it, also of a locked one, and the way to its normal page for
	//the ones that are not due today
	public function testTheOverviewShowsTheRankAndOpensWhatIsNotDue()
	{
		$today = MistakeTraining::today();
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumegos' => [
				['set_order' => 1, 'rating' => 1500, 'status' => ['name' => 'S', 'mistake_training_due' => $today . ' 00:00:00']],
				['set_order' => 2, 'rating' => 2200, 'status' => ['name' => 'S'] + self::reviewedToday(MistakeTraining::REVIEW_SOLVED)],
				['set_order' => 3, 'rating' => 600, 'status' => ['name' => 'S', 'mistake_training_due' => date('Y-m-d', strtotime($today . ' +5 days')) . ' 00:00:00']],
			],
		]);

		$this->get(MistakeTraining::$OVERVIEW_LINK);

		$this->assertStringContainsString('<tr><th>Problem</th><th>Rank</th><th>Rung</th><th>Next review</th></tr>', $this->view);
		// soonest review first: due today, then solved today (due in 3 days), then due in 5 days
		$rows = self::overviewRows($this->view);
		$this->assertCount(3, $rows);
		foreach ([0 => '6k', 1 => '2d', 2 => '15k'] as $index => $rank)
		{
			$this->assertSame($rank, Rating::getReadableRankFromRating([1500, 2200, 600][$index]), 'The rank as the rest of the site writes it');
			$this->assertStringContainsString('<td class="mistake-training-pool__rank">' . $rank . '</td>', $rows[$index]);
		}
		$this->assertStringContainsString('problem-nav__item--locked', $rows[0], 'Due today, it opens only in the training');
		foreach ([1 => 'solved today', 2 => 'due on a later day'] as $index => $state)
			$this->assertStringContainsString('<a class="tooltip" href="/' . self::setConnectionId($context, $index) . '"', $rows[$index],
				"A problem $state opens on its normal page");
	}

	public function testTheOverviewIsAllCaughtUpOnceNothingIsDue()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+2 days'))]],
		]);

		$this->get(MistakeTraining::$OVERVIEW_LINK);
		$html = $this->view;

		$this->assertStringContainsString('All caught up!', $html);
		$this->assertStringNotContainsString('mistake-training-start', $html);
	}

	public function testTheTitleOfATrainingProblemLinksToTheOverview()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'status' => ['name' => 'V', 'mistake_training_due' => MistakeTraining::today() . ' 00:00:00']],
		]);

		$this->get('/mistake-training');
		$html = $this->view;

		$this->assertStringContainsString('<a id="playTitleA" href="' . MistakeTraining::$OVERVIEW_LINK . '">Mistake Training 1/1</a>', $html);
	}

	public function testAGuestSeesNoTrainingOnTheHomepage()
	{
		new ContextPreparator(['user' => null]);

		$this->get('/sites/index');
		$html = $this->view;

		$this->assertStringNotContainsString('header-mistake-training', $html);
	}

	public function testHelpPageDescribesTheTraining()
	{
		$this->get('/sites/websitefunctions');
		$html = $this->view;
		$this->assertStringContainsString('A problem goes to the mistake training', $html);
	}

	public function testHelpPageGivesTheXpOfTheTraining()
	{
		$this->get('/sites/websitefunctions');
		$html = $this->view;
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

		$this->get('/mistake-training');

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
				'status' => ['name' => 'V', 'mistake_training_due' => MistakeTraining::today() . ' 00:00:00'],
			],
		]);

		$this->get('/mistake-training');

		$this->assertStringNotContainsString(MistakeTraining::$IMPORT_LINK, (string) $this->view);
	}

	public function testTheImportIsOfferedWithoutACount()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]],
		]);

		$this->get('/mistake-training');

		$this->assertStringNotContainsString('Before the training existed', (string) $this->view, 'The page does not count what is left to import');
	}

	public function testTheImportSaysHowManyProblemsItAdded()
	{
		new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]],
		]);

		$this->post(MistakeTraining::$IMPORT_LINK);

		$this->assertSame('Added 1 past mistake to the training.', CookieFlash::get());
	}

	public function testTheImportSaysWhenThereIsNothingLeftToAdd()
	{
		new ContextPreparator(['user' => ['name' => 'testuser']]);

		$this->post(MistakeTraining::$IMPORT_LINK);

		$this->assertSame('Nothing left to add, every past mistake is already in the training.', CookieFlash::get());
	}

	public function testImportFillsTheTrainingAndReturnsToTheQueue()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => ['set_order' => 1, 'attempt' => ['solved' => true, 'misplays' => 1]],
		]);

		$this->post(MistakeTraining::$IMPORT_LINK);

		$this->assertNotNull(
			MistakeTraining::getPoolRow($context->user['id'], (int) $context->tsumegos[0]['id']),
			'The import brings the problem into the training'
		);
		$this->assertStringContainsString(MistakeTraining::$QUEUE_LINK, (string) ($this->headerValue('Location') ?? ''));
	}

	//The way the training was tried on the test server: of three imported problems one is solved and one
	//missed today, the third is left alone. The next day the untouched one and the
	//missed one are still to do, and the solved one comes back after its two days.
	public function testANextDayKeepsWhatWasNotSolvedAndBringsBackTheSolvedOneOnTime()
	{
		$today = MistakeTraining::today();
		// due now: at the start of the player's day
		$now = $today . ' 00:00:00';
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'S', 'mistake_training_due' => $now]],
				['set_order' => 2, 'status' => ['name' => 'S', 'mistake_training_due' => $now, 'mistake_training_review_day' => $today, 'mistake_training_review_result' => MistakeTraining::REVIEW_MISSED]],
				['set_order' => 3, 'status' => ['name' => 'S', 'mistake_training_rung' => 1, 'mistake_training_due' => date('Y-m-d', strtotime($today . ' +2 days')) . ' 00:00:00', 'mistake_training_review_day' => $today, 'mistake_training_review_result' => MistakeTraining::REVIEW_SOLVED]],
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

	//The day of a next review and the days of the calendar are written in the date format of the player
	public function testTheOverviewWritesItsDaysInTheDateFormatOfThePlayer()
	{
		$today = MistakeTraining::today();
		$far = date('Y-m-d', strtotime($today . ' +10 days'));
		$near = date('Y-m-d', strtotime($today . ' +3 days'));
		$czech = fn(string $day) => date('j. n. Y', strtotime($day));
		foreach ([
			UserTable::DATE_FORMAT_ISO => fn(string $day) => $day,
			UserTable::DATE_FORMAT_DAY_MONTH_YEAR => $czech] as $dateFormat => $format)
		{
			new ContextPreparator([
				'user' => ['name' => 'testuser', 'date_format_id' => $dateFormat],
				'tsumegos' => [
					['set_order' => 1, 'status' => ['name' => 'S', 'mistake_training_due' => $far . ' 00:00:00']],
					['set_order' => 2, 'status' => ['name' => 'S', 'mistake_training_due' => $near . ' 00:00:00']],
				],
			]);
			$browser = Browser::instance();
			$browser->get(substr(MistakeTraining::$OVERVIEW_LINK, 1));
			$rows = $browser->getElements('.mistake-training-pool__row td:last-child');

			$this->assertSame(date('l', strtotime($near)) . ' (' . $format($near) . ')', $rows[0]->getText(),
				"A review within the week is named and dated, in format $dateFormat");
			$this->assertSame($format($far), $rows[1]->getText(), "A later review is dated, in format $dateFormat");
			$this->assertStringStartsWith($format($today) . ': ', (string) $browser->find('[data-day="' . $today . '"]')->getAttribute('title'),
				"The calendar day in format $dateFormat");
		}
	}

	//The next review a solve says under "Correct!" is written by the page in the date format of the player
	public function testTheNextReviewAfterASolveIsInTheDateFormatOfThePlayer()
	{
		foreach ([
			UserTable::DATE_FORMAT_ISO => fn(string $day) => date('Y-m-d', strtotime($day)),
			UserTable::DATE_FORMAT_DAY_MONTH_YEAR => fn(string $day) => date('j. n. Y', strtotime($day))] as $dateFormat => $format)
		{
			$context = new ContextPreparator([
				'user' => ['name' => 'testuser', 'date_format_id' => $dateFormat],
				'tsumego' => [
					'set_order' => 1,
					'status' => ['name' => 'S', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 3],
				],
			]);
			$browser = Browser::instance();
			$browser->get('mistake-training');
			$browser->playWithResult('S');

			// a clean solve on the fourth rung brings the problem back after 15 days
			$nextDue = MistakeTraining::getPoolRow((int) $context->user['id'], (int) $context->tsumegos[0]['id'])['next_due'];
			$browser->waitUntil(fn() => str_contains((string) $browser->find('#xpDisplay')->getAttribute('title'), 'next review'), context: 'the outcome of the solve');
			$this->assertStringContainsString('next review ' . $format($nextDue) . '.', (string) $browser->find('#xpDisplay')->getAttribute('title'),
				"The title of the reward in format $dateFormat");
			$this->assertMatchesRegularExpression('/^\+\d+ XP$/', trim((string) $browser->driver->executeScript('return document.getElementById("xpDisplayText").textContent;')), 'The XP of the solve shows where the XP of a problem shows');
			$this->assertStringContainsString($format($nextDue), (string) $browser->driver->executeScript(
				'return document.querySelector(".problem-nav__item--current .tooltip-desc").textContent;'), "And the tooltip of the button, in format $dateFormat");
		}
	}
}

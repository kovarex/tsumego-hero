<?php

use App\Model\Table\SetConnectionTable;
use App\Model\Table\ChallengeTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeAttemptStatusTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\PlayOutcome;
use App\Utility\Rating;
use App\Utility\TimeModeUtil;
use App\Utility\Util;
use Facebook\WebDriver\WebDriverKeys;

//Challenges between players: everyone in a challenge plays the same problems like a time mode
//run, and the page of the challenge ranks them by their points.
class ChallengesControllerTest extends IntegrationTestCase
{
	private ContextPreparator $context;

	//The player 'challenger' is signed in, 'friend' and 'stranger' can join, and there are
	//twelve problems of the one rank of the time mode.
	private function prepare(int $problemCount = 12): void
	{
		Auth::logout();
		$this->context = new ContextPreparator([
			'user' => ['name' => 'challenger', 'mode' => Constants::$LEVEL_MODE],
			'other-users' => [['name' => 'friend'], ['name' => 'stranger']],
			'time-mode-ranks' => ['5k'],
			'tsumegos' => range(1, $problemCount),
		]);
		$this->login('challenger');
	}

	private function userId(string $name): int
	{
		return (int) ContextPreparator::getUserIdFromName($name);
	}

	private function rankId(string $name): int
	{
		return (int) array_find($this->context->timeModeRanks, fn(array $rank) => $rank['name'] === $name)['id'];
	}

	/** @param array<string, mixed> $data What the form sends, over the defaults */
	private function create(array $data = []): ?int
	{
		$this->post('/challenges/create', $data + [
			'minimum_rank' => $this->rankId('5k'),
			'maximum_rank' => $this->rankId('5k'),
			'seconds_per_problem' => 60,
			'problem_count' => 10,
			'players' => '',
		]);
		return $this->fetchTable('Challenge')->find()->orderByDesc('id')->first()?->id;
	}

	/** @return array<int> The users in the challenge */
	private function participants(int $challengeId): array
	{
		return array_map(fn(array $row) => (int) $row['user_id'],
			Util::query('SELECT user_id FROM challenge_participant WHERE challenge_id = ? ORDER BY id', [$challengeId]));
	}

	/** @return array<array<string, mixed>> The attempts of the run of the player, in their order */
	private function runAttempts(int $challengeId, string $name): array
	{
		return Util::query('SELECT a.* FROM time_mode_attempt a
			JOIN time_mode_session s ON s.id = a.time_mode_session_id
			WHERE s.challenge_id = ? AND s.user_id = ?
			ORDER BY a.`order`', [$challengeId, $this->userId($name)]);
	}

	//Plays the run of the signed-in player through the result requests the board sends.
	/** @param list<array{0: PlayOutcome, 1: float}> $results The outcome and seconds of each problem */
	private function playRun(int $challengeId, array $results): void
	{
		$this->post('/challenges/play/' . $challengeId);
		$problemIDs = ChallengeTable::problemIDs($challengeId);
		foreach ($results as $index => [$outcome, $seconds])
		{
			$this->post('/timeMode/play/' . ($index + 1));
			Auth::init();
			$this->post('/tsumegos/result', [
				'tsumego_id' => $problemIDs[$index],
				'seconds' => $seconds,
				'outcome' => $outcome->value]);
		}
		$this->post('/timeMode/play');
		Auth::init();
	}

	public function testCreatingAChallengeDrawsItsProblemsAndInvitesThePlayers(): void
	{
		$this->prepare();

		$challengeId = $this->create(['seconds_per_problem' => 30, 'players' => ' DN_friend , DN_friend']);

		$challenge = ChallengeTable::withDetails($challengeId);
		$this->assertSame(30, (int) $challenge['seconds_per_problem']);
		$this->assertSame(10, (int) $challenge['problem_count']);
		$this->assertCount(10, array_unique(ChallengeTable::problemIDs($challengeId)));
		$this->assertSame([$this->userId('challenger'), $this->userId('friend')], $this->participants($challengeId));
		$this->assertStringEndsWith('/challenges/' . $challengeId, $this->headerValue('Location'));

		// the invitation is the challenge in the friend's challenges, not a message
		$this->assertEmpty(Util::query('SELECT * FROM message'));
		$this->assertSame(1, ChallengeTable::countWaitingFor($this->userId('friend')));
		$this->assertStringContainsString('10 problems, 5k, 30 seconds each', ChallengeTable::describe($challenge));
	}

	public function testTheTimeForAProblemCanBeLong(): void
	{
		$this->prepare();
		$challengeId = $this->create(['seconds_per_problem' => 1800]);
		$this->assertSame(1800, (int) ChallengeTable::withDetails($challengeId)['seconds_per_problem']);
		$this->assertStringContainsString('10 problems, 5k, 30 minutes each', ChallengeTable::describe(ChallengeTable::withDetails($challengeId)));
	}

	public function testAChallengeDrawsOnlyProblemsOfItsRanks(): void
	{
		Auth::logout();
		$easy = Rating::getRankMinimalRatingFromReadableRank('10k') + 1;
		$middle = Rating::getRankMinimalRatingFromReadableRank('5k') + 1;
		$hard = Rating::getRankMinimalRatingFromReadableRank('1d') + 1;
		$this->context = new ContextPreparator([
			'user' => ['name' => 'challenger'],
			'time-mode-ranks' => ['10k', '5k', '1d'],
			'tsumegos' => [
				['rating' => $easy, 'set_order' => 1],
				['rating' => $middle, 'set_order' => 2],
				['rating' => $middle, 'set_order' => 3],
				['rating' => $hard, 'set_order' => 4]],
		]);
		$this->login('challenger');

		// picked the other way round, the range is the same
		$challengeId = $this->create(['minimum_rank' => $this->rankId('1d'), 'maximum_rank' => $this->rankId('5k')]);

		$challenge = ChallengeTable::withDetails($challengeId);
		$this->assertSame('5k', $challenge['minimum_rank']);
		$this->assertSame('1d', $challenge['maximum_rank']);
		$problemIDs = ChallengeTable::problemIDs($challengeId);
		sort($problemIDs);
		$this->assertSame(array_map(fn(int $index) => (int) $this->context->tsumegos[$index]['id'], [1, 2, 3]), $problemIDs);
	}

	public function testAChallengeWithoutProblemsOfItsRanksIsNotCreated(): void
	{
		Auth::logout();
		$this->context = new ContextPreparator([
			'user' => ['name' => 'challenger'],
			'time-mode-ranks' => ['10k', '5k', '1d'],
			'tsumegos' => [['rating' => Rating::getRankMinimalRatingFromReadableRank('10k') + 1, 'set_order' => 1]],
		]);
		$this->login('challenger');

		$this->assertNull($this->create(['minimum_rank' => $this->rankId('1d'), 'maximum_rank' => $this->rankId('1d')]));
		$this->assertStringEndsWith('/challenges', $this->headerValue('Location'));
	}

	public function testAnUnknownTimeForAProblemIsRefused(): void
	{
		$this->prepare();

		$this->create(['seconds_per_problem' => 7]);

		$this->assertResponseCode(400);
	}

	public function testCreatingAChallengeNeedsAnAccount(): void
	{
		new ContextPreparator(['user' => null, 'time-mode-ranks' => ['5k']]);
		$this->post('/challenges/create');

		$this->assertResponseCode(401);
	}

	public function testInvitingAnUnknownNameInvitesNobody(): void
	{
		$this->prepare();
		$challengeId = $this->create();

		$this->post('/challenges/invite/' . $challengeId, ['players' => 'Nobody']);

		$this->assertSame([$this->userId('challenger')], $this->participants($challengeId));
		$this->assertEmpty(Util::query('SELECT * FROM message'));
	}

	//The invitations of one request fill the challenge up to its limit, the names after that are told it is full.
	public function testInvitingStopsWhenTheChallengeIsFull(): void
	{
		$others = [['name' => 'friend'], ['name' => 'stranger']];
		for ($index = 0; $index < ChallengeTable::MAXIMUM_PARTICIPANTS - 2; $index++)
			$others[] = ['name' => 'filler player ' . $index];
		Auth::logout();
		$this->context = new ContextPreparator([
			'user' => ['name' => 'challenger', 'mode' => Constants::$LEVEL_MODE],
			'other-users' => $others,
			'time-mode-ranks' => ['5k'],
			'tsumegos' => range(1, 12),
		]);
		$this->login('challenger');
		$challengeId = $this->create();
		foreach (array_slice($this->context->otherUsers, 2) as $filler)
			ChallengeTable::addParticipant($challengeId, (int) $filler['id'], null);

		$this->post('/challenges/invite/' . $challengeId, ['players' => 'DN_friend, DN_stranger']);

		$this->assertSame(ChallengeTable::MAXIMUM_PARTICIPANTS, ChallengeTable::participantCount($challengeId));
		$this->assertTrue(ChallengeTable::isParticipant($challengeId, $this->userId('friend')));
		$this->assertFalse(ChallengeTable::isParticipant($challengeId, $this->userId('stranger')));
		$this->assertSame('Invited DN_friend. The challenge waits for them in their challenges. The challenge is full, DN_stranger can\'t be invited.', CookieFlash::get());
	}

	public function testOnlyThePlayersOfAChallengeInvite(): void
	{
		$this->prepare();
		$challengeId = $this->create();
		$this->login('stranger');
		$this->post('/challenges/invite/' . $challengeId, ['players' => 'DN_friend']);

		$this->assertResponseCode(403);
	}

	public function testAnyoneWithTheLinkCanJoin(): void
	{
		$this->prepare();
		$challengeId = $this->create();
		$this->login('stranger');

		$this->get('/challenges/' . $challengeId);
		$page = $this->view;
		$this->assertTextContains('id="challenge-join"', $page);

		$this->post('/challenges/join/' . $challengeId);
		$this->assertSame([$this->userId('challenger'), $this->userId('stranger')], $this->participants($challengeId));
	}

	public function testEveryoneGetsTheSameProblemsWithTheTimeOfTheChallenge(): void
	{
		$this->prepare();
		$challengeId = $this->create(['seconds_per_problem' => 30, 'problem_count' => 5]);

		$this->post('/challenges/play/' . $challengeId);
		$this->assertStringEndsWith('/timeMode/play', $this->headerValue('Location'));
		Auth::init();
		$this->assertTrue(Auth::isInTimeMode());

		// playing joins the challenge too
		$this->login('stranger');
		$this->post('/challenges/play/' . $challengeId);
		$this->assertContains($this->userId('stranger'), $this->participants($challengeId));

		foreach (['challenger', 'stranger'] as $name)
		{
			$attempts = $this->runAttempts($challengeId, $name);
			$this->assertSame(ChallengeTable::problemIDs($challengeId), array_map(fn(array $attempt) => (int) $attempt['tsumego_id'], $attempts));
			$this->assertSame([1, 2, 3, 4, 5], array_map(fn(array $attempt) => (int) $attempt['order'], $attempts));
		}

		// the time the client reports is capped by the time of the challenge, and the points are counted from it
		$this->post('/timeMode/play');
		Auth::init();
		$this->post('/tsumegos/result', [
			'tsumego_id' => ChallengeTable::problemIDs($challengeId)[0],
			'seconds' => 500,
			'outcome' => PlayOutcome::SolvedClean->value]);
		$attempt = $this->runAttempts($challengeId, 'stranger')[0];
		$this->assertSame(30.0, (float) $attempt['seconds']);
		$this->assertSame(100 * TimeModeUtil::POINTS_RATIO_FOR_FINISHING, (float) $attempt['points']);
	}

	public function testAPlayerPlaysTheirRunOnlyOnce(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5]);
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$this->assertFalse(Auth::isInTimeMode());

		$this->post('/challenges/play/' . $challengeId);

		$this->assertStringEndsWith('/challenges/' . $challengeId, $this->headerValue('Location'));
		$this->assertSame(1, $this->fetchTable('TimeModeSession')->find()->where(['challenge_id' => $challengeId])->count());
	}

	public function testTheEndOfARunLeadsToTheTableOfTheChallenge(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5]);
		$this->post('/challenges/play/' . $challengeId);
		$problemIDs = ChallengeTable::problemIDs($challengeId);
		foreach ($problemIDs as $index => $problemID)
		{
			$this->post('/timeMode/play/' . ($index + 1));
			Auth::init();
			$this->post('/tsumegos/result', [
				'tsumego_id' => $problemID, 'seconds' => 10, 'outcome' => PlayOutcome::SolvedClean->value]);
		}

		$this->post('/timeMode/play');
		$session = ChallengeTable::runOf($challengeId, $this->userId('challenger'));
		$this->assertSame(Util::getInternalAddress() . '/timeMode/result/' . $session->id, $this->headerValue('Location'));

		$this->get('/timeMode/result/' . $session->id);
		$this->assertSame(Util::getInternalAddress() . '/challenges/' . $challengeId, $this->headerValue('Location'));
	}

	//Until every player still in it finished, a challenge is in progress: its table and the list show
	//the points of the finished runs and how many finished, no places and no medals. The last run brings them.
	public function testAChallengeInProgressHasNoPlaces(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend, DN_stranger']);
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$this->login('friend');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 20]));

		$this->assertSame([null, null, null], array_column(ChallengeTable::standings($challengeId), 'place'));
		$this->get('/challenges/' . $challengeId);
		$this->assertTextNotContains('challenge-place', $this->view);
		$this->assertTextNotContains('<th>#</th>', $this->view);
		$this->assertTextContains('The challenge is in progress, 2 of 3 players finished.', $this->view);
		$this->get('/challenges');
		$row = $this->listRow($challengeId);
		$this->assertStringNotContainsString('challenge-place', $row);
		$this->assertMatchesRegularExpression('/>2<\/button>.*<\/span>\s*of 3 finished/s', $row);
		$this->assertStringContainsString('challenge-standings-popup--unranked', $row, 'the standings without places');
		$this->assertMatchesRegularExpression('/points<\/strong> \(\d+%\) in <a href="\/challenges\/' . $challengeId . '">/', $row);
		$created = (string) Util::query('SELECT created FROM challenge WHERE id = ?', [$challengeId])[0]['created'];
		$this->assertStringContainsString('started <time datetime="' . Util::toIso8601($created) . '"', $row);

		$this->login('stranger');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::Failed, 10]));
		$this->assertSame([1, 2, 3], array_column(ChallengeTable::standings($challengeId), 'place'));
		$this->get('/challenges/' . $challengeId);
		$this->assertTextContains('aria-label="3rd place"', $this->view);
		$this->get('/challenges');
		$row = $this->listRow($challengeId);
		$this->assertStringContainsString('aria-label="1st place"', $row);
		$this->assertStringNotContainsString('started', $row);
	}

	//The creator removes a player who hasn't started, right away: the challenge finishes without
	//them, the table says they were removed, and they can't play it, join it again or be invited.
	public function testTheCreatorRemovesAPlayerWhoHasNotStarted(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend, DN_stranger']);
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$this->login('friend');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 20]));
		$this->get('/challenges/' . $challengeId);
		$this->assertTextNotContains('action="/challenges/remove/', $this->view, 'only the creator removes');
		$this->login('challenger');
		$this->get('/challenges/' . $challengeId);
		$this->assertTextContains('action="/challenges/remove/' . $challengeId . '"', $this->view, 'right away, though invited just now');
		$this->post('/challenges/remove/' . $challengeId, ['user_id' => $this->userId('stranger')]);

		$this->assertFalse(ChallengeTable::isParticipant($challengeId, $this->userId('stranger')));
		$this->assertSame([1, 2, null], array_column(ChallengeTable::standings($challengeId), 'place'));
		$this->get('/challenges/' . $challengeId);
		$this->assertTextContains('removed by the creator', $this->view);
		$this->assertTextContains('aria-label="1st place"', $this->view);
		$this->post('/challenges/invite/' . $challengeId, ['players' => 'DN_stranger']);
		$this->assertSame('DN_stranger was removed from the challenge.', \App\Utility\CookieFlash::get());

		$this->login('stranger');
		$this->assertSame(0, ChallengeTable::countWaitingFor($this->userId('stranger')));
		$this->get('/challenges/' . $challengeId);
		$this->assertTextContains('id="challenge-removed"', $this->view);
		$this->post('/challenges/play/' . $challengeId);
		$this->assertNull(ChallengeTable::runOf($challengeId, $this->userId('stranger')));
	}

	//A started run can be removed once it stays on a problem for twice the time for a problem, counted from the start of that problem.
	public function testTheCreatorRemovesARunStuckOnAProblem(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend', 'seconds_per_problem' => 60]);
		$this->login('friend');
		$this->post('/challenges/play/' . $challengeId);
		$this->post('/timeMode/play/1');
		$this->login('challenger');
		$this->get('/challenges/' . $challengeId);
		$this->assertTextNotContains('action="/challenges/remove/', $this->view);

		$runId = ChallengeTable::runOf($challengeId, $this->userId('friend'))->id;
		$problemStarted = fn(int $secondsAgo) => Util::execute('UPDATE time_mode_attempt SET started = NOW() - INTERVAL ? SECOND
			WHERE time_mode_session_id = ? AND started IS NOT NULL', [$secondsAgo, $runId]);
		$problemStarted(110);
		$this->get('/challenges/' . $challengeId);
		$this->assertTextNotContains('action="/challenges/remove/', $this->view, 'not yet twice the minute');
		$this->post('/challenges/remove/' . $challengeId, ['user_id' => $this->userId('friend')]);
		$this->assertResponseCode(422);

		$problemStarted(ChallengeTable::REMOVABLE_AFTER_TIMES_FOR_A_PROBLEM * 60 + 1);
		$this->get('/challenges/' . $challengeId);
		$this->assertTextContains('action="/challenges/remove/' . $challengeId . '"', $this->view);
		$this->assertTextContains('title="No new problem for 2 minutes, twice the time for a problem"', $this->view);
		$this->post('/challenges/remove/' . $challengeId, ['user_id' => $this->userId('friend')]);
		$this->assertSame([ChallengeTable::RUN_REMOVED], array_values(array_column(array_filter(ChallengeTable::standings($challengeId),
			fn(array $row) => (int) $row['user_id'] === $this->userId('friend')), 'run')));
	}

	//The table of the players as JSON, for the page that keeps it up to date while the challenge is in progress.
	public function testTheStandingsAsJson(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend']);
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));

		$this->get('/challenges/standings/' . $challengeId);
		$body = json_decode((string) $this->_response->getBody(), true);
		$this->assertFalse($body['finished']);
		$this->assertStringContainsString('not played yet', $body['html']);
		$this->assertStringNotContainsString('challenge-place', $body['html']);

		$this->login('friend');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 20]));
		$this->logout();
		$this->get('/challenges/standings/' . $challengeId);
		$body = json_decode((string) $this->_response->getBody(), true);
		$this->assertTrue($body['finished'], 'anyone with the link sees the table');
		$this->assertStringContainsString('aria-label="2nd place"', $body['html']);
	}

	//The page of a challenge in progress takes the new runs into its table by itself, and reloads
	//with the places once the last player finished.
	public function testThePageOfAChallengeInProgressFollowsTheRuns(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend, DN_stranger']);
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$browser = Browser::instance();
		$browser->get('/challenges/' . $challengeId);
		$browser->driver->executeScript('window.notReloaded = true;');
		$friendRow = '#challenge-standings tr[data-user-id="' . $this->userId('friend') . '"]';
		$this->assertStringContainsString('not played yet', $browser->find($friendRow)->getText());

		$this->login('friend');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 20]));
		$browser->waitUntil(fn() => !str_contains($browser->find($friendRow)->getText(), 'not played yet'), 25, 'the run of the friend in the table');
		$this->assertTrue($browser->driver->executeScript('return window.notReloaded === true;'), 'the table changed without a reload');
		$this->assertCount(0, $browser->getElements('#challenge-standings .challenge-place'));

		$this->login('stranger');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::Failed, 10]));
		$browser->waitUntil(fn() => count($browser->getElements('#challenge-standings .challenge-place')) === 3, 25, 'the places after the last run');
		$browser->assertNoJsErrors();
	}

	public function testOnlyTheCreatorRemovesPlayers(): void
	{
		$this->prepare();
		$challengeId = $this->create(['players' => 'DN_friend, DN_stranger']);
		$this->login('friend');

		$this->post('/challenges/remove/' . $challengeId, ['user_id' => $this->userId('stranger')]);

		$this->assertResponseCode(403);
		$this->assertTrue(ChallengeTable::isParticipant($challengeId, $this->userId('stranger')));
	}

	//A player can be removed at any time before they start, and once started when the start of their current problem
	//is twice the time for a problem ago; a finished or declined run never.
	public function testWhenAPlayerCanBeRemoved(): void
	{
		$now = strtotime('2026-11-06 12:00:00');
		$secondsAgo = fn(float $seconds) => date('Y-m-d H:i:s', (int) ($now - $seconds));
		$this->assertSame(2, ChallengeTable::REMOVABLE_AFTER_TIMES_FOR_A_PROBLEM);
		$this->assertTrue(ChallengeTable::canRemove(['run' => ChallengeTable::RUN_NOT_STARTED, 'joined' => $secondsAgo(0)], 60, $now));
		$this->assertFalse(ChallengeTable::canRemove(['run' => ChallengeTable::RUN_PLAYING, 'last_activity' => $secondsAgo(119)], 60, $now));
		$this->assertTrue(ChallengeTable::canRemove(['run' => ChallengeTable::RUN_PLAYING, 'last_activity' => $secondsAgo(120)], 60, $now));
		$this->assertFalse(ChallengeTable::canRemove(['run' => ChallengeTable::RUN_PLAYING, 'last_activity' => $secondsAgo(120)], 90, $now));
		$this->assertTrue(ChallengeTable::canRemove(['run' => ChallengeTable::RUN_PLAYING, 'last_activity' => $secondsAgo(180)], 90, $now));
		$this->assertFalse(ChallengeTable::canRemove(['run' => ChallengeTable::RUN_FINISHED, 'last_activity' => $secondsAgo(100000)], 60, $now));
		$this->assertFalse(ChallengeTable::canRemove(['run' => ChallengeTable::RUN_DECLINED, 'joined' => $secondsAgo(100000)], 60, $now));
	}

	public function testTheTableRanksThePlayersByTheirPoints(): void
	{
		$this->prepare();
		$challengeId = $this->create([
			'problem_count' => 5,
			'players' => 'DN_friend, DN_stranger']);

		// the challenger solves three problems, the friend all five, the stranger declines, which finishes the challenge
		$this->login('stranger');
		$this->post('/challenges/decline/' . $challengeId);
		$this->login('challenger');
		$this->playRun($challengeId, [
			[PlayOutcome::SolvedClean, 10],
			[PlayOutcome::Failed, 10],
			[PlayOutcome::SolvedClean, 10],
			[PlayOutcome::Failed, 10],
			[PlayOutcome::SolvedClean, 10]]);
		$this->login('friend');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 20]));

		$standings = ChallengeTable::standings($challengeId);
		$this->assertSame(['DN_friend', 'DN_challenger', 'DN_stranger'], array_column($standings, 'user_display_name'));
		$this->assertSame([1, 2, null], array_column($standings, 'place'));
		$this->assertSame([5, 3, 0], array_column($standings, 'solved_count'));
		$this->assertSame([ChallengeTable::RUN_FINISHED, ChallengeTable::RUN_FINISHED, ChallengeTable::RUN_DECLINED], array_column($standings, 'run'));

		$this->get('/challenges/' . $challengeId);
		$page = $this->view;
		$this->assertTextContains('You are <strong>1st</strong> of 2', $page);
		$this->assertTextContains('challenges__cell--failed', $page);
		$this->assertTextContains('declined', $page);

		// the problem buttons are coloured by how the player looking did in their run, not by their status of the problem
		preg_match_all('/class="problem-nav__item problem-nav__item--(\w)/', $page, $matches);
		$this->assertSame(['S', 'S', 'S', 'S', 'S'], $matches[1]);
		$this->login('challenger');
		$this->get('/challenges/' . $challengeId);
		$page = $this->view;
		preg_match_all('/class="problem-nav__item problem-nav__item--(\w)/', $page, $matches);
		$this->assertSame(['S', 'F', 'S', 'F', 'S'], $matches[1]);
		$this->assertTextContains('You got it wrong.', $page);
	}

	//Runs with the same points in the same time share their place and its medal, the next run skips the shared places.
	public function testTiedRunsShareTheirPlaceAndMedal(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend, DN_stranger']);
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$this->login('friend');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$this->login('stranger');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::Failed, 10]));

		$this->assertSame([1, 1, 3], array_column(ChallengeTable::standings($challengeId), 'place'));
		$this->get('/challenges/' . $challengeId);
		$standings = substr($this->view, strpos($this->view, 'id="challenge-standings"'));
		$this->assertSame(2, substr_count($standings, 'class="challenge-place challenge-place--gold" role="img" aria-label="1st place"'));
		$this->assertSame(1, substr_count($standings, 'aria-label="3rd place"'));
		$this->assertStringNotContainsString('challenge-place--silver', $standings);
	}

	//The list of the player's challenges says "2nd of 2 with X points (Y%) in Challenge #N", the 2 opening the
	//standings of everyone who finished, and when the last of them finished.
	public function testTheListShowsThePlaceOfThePlayerAndTheStandingsOfTheChallenge(): void
	{
		$this->prepare();
		$wonByFriend = $this->create(['problem_count' => 5, 'players' => 'DN_friend, DN_stranger']);
		$this->playRun($wonByFriend, array_fill(0, 5, [PlayOutcome::Failed, 10]));
		$this->login('friend');
		$this->playRun($wonByFriend, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$this->login('challenger');
		// the stranger never starts, so the challenge finishes without them
		$this->post('/challenges/remove/' . $wonByFriend, ['user_id' => $this->userId('stranger')]);

		$this->get('/challenges');
		$row = $this->listRow($wonByFriend);
		$phrase = ['aria-label="2nd place"', '2nd</span>', 'of <span class="challenge-standings-popup">', 'with <strong>0.0 points</strong> (0%) in',
			'<a href="/challenges/' . $wonByFriend . '">', 'data-format="relative"', 'class="hint challenges__row-details"'];
		$positions = array_map(fn(string $part) => strpos($row, $part), $phrase);
		$this->assertNotContains(false, $positions, 'the row has all the parts of the phrase');
		$sorted = $positions;
		sort($sorted);
		$this->assertSame($sorted, $positions, 'in the order of the phrase');
		$this->assertStringNotContainsString('played', $row);

		// the standings, the best first, each with its place, player and points
		$this->assertStringContainsString('aria-controls="challenge-standings-' . $wonByFriend . '" aria-describedby="challenge-standings-' . $wonByFriend . '">2</button>', $row);
		$popup = substr($row, strpos($row, 'class="challenge-standings-popup__panel"'));
		$friendPoints = (float) ChallengeTable::runOf($wonByFriend, $this->userId('friend'))->points;
		$this->assertMatchesRegularExpression('/aria-label="1st place".*DN_friend.*' . preg_quote(ChallengeTable::formatPoints($friendPoints)) . ' points'
			. '.*aria-label="2nd place".*DN_challenger.*0\.0 points/s', $popup);
		$this->assertStringNotContainsString('DN_stranger', $popup, 'who has not finished');

		// the time of the last run, the friend's
		$friendFinished = array_find(ChallengeTable::placesOfPlayer($this->userId('challenger'))[$wonByFriend]['standings'],
			fn(array $run) => (int) $run['user_id'] === $this->userId('friend'))['finished'];
		$this->assertStringContainsString('<span class="hint challenges__when"><time datetime="' . Util::toIso8601($friendFinished) . '"', $row);

		$this->login('friend');
		$this->get('/challenges');
		$row = $this->listRow($wonByFriend);
		$this->assertStringContainsString('aria-label="1st place"', $row);
		$this->assertStringContainsString('<strong>' . ChallengeTable::formatPoints($friendPoints) . ' points</strong> ('
			. (int) round($friendPoints / 500 * 100) . '%)', $row, 'of the most five problems give');

		// the removed player sees how many finished
		$this->login('stranger');
		$this->get('/challenges');
		$row = $this->listRow($wonByFriend);
		$this->assertStringContainsString('Removed by the creator: <a href="/challenges/' . $wonByFriend . '">', $row);
		$this->assertMatchesRegularExpression('/· <span class="challenge-standings-popup">.*>2<\/button>.*<\/span>\s*finished/s', $row);
	}

	public function testTheStandingsOpenFromTheListOfChallengesInTheBrowser(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend']);
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$this->login('friend');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::Failed, 10]));
		$this->login('challenger');

		$browser = Browser::instance();
		$browser->get('challenges');
		$time = $browser->find('.challenges__when time');
		$this->assertContains($time->getText(), ['just now', 'a minute ago']);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $time->getAttribute('title'));

		$toggle = $browser->find('.challenge-standings-popup__toggle');
		$panel = $browser->find('#challenge-standings-' . $challengeId);
		$this->assertFalse($panel->isDisplayed());
		$toggle->click();
		$this->assertTrue($panel->isDisplayed());
		$this->assertSame('true', $toggle->getAttribute('aria-expanded'));
		$this->assertStringContainsString('DN_friend', $panel->getText());

		$browser->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
		$this->assertFalse($panel->isDisplayed());
		$this->assertSame('false', $toggle->getAttribute('aria-expanded'));

		$toggle->click();
		$this->assertTrue($panel->isDisplayed());
		$browser->find('.challenges__title')->click();
		$this->assertFalse($panel->isDisplayed());
		$browser->assertNoJsErrors();
	}

	//The places and the standings of every challenge of the list come from one query, whatever their number.
	public function testTheListTakesAsManyQueriesForManyChallengesAsForOne(): void
	{
		$this->prepare();
		$queriesOfTheList = function (): int {
			$questions = fn() => (int) Util::query("SHOW SESSION STATUS LIKE 'Questions'")[0]['Value'];
			$before = $questions();
			$this->get('/challenges');
			return $questions() - $before;
		};
		$playOne = function (): void {
			$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend']);
			$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
			$this->login('friend');
			$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::Failed, 10]));
			$this->login('challenger');
		};

		$playOne();
		// the first request fills the caches of the request, like UserTable's list of deleted accounts
		$queriesOfTheList();
		$queriesOfOne = $queriesOfTheList();
		$playOne();
		$playOne();
		$this->assertSame($queriesOfOne, $queriesOfTheList());
		$this->assertSame(3, substr_count($this->view, 'class="challenge-standings-popup__panel"'));
		$this->assertSame(6, substr_count($this->view, 'class="challenge-standings-popup__run"'), 'two players in each');
	}

	//The row of the list of challenges of the challenge.
	private function listRow(int $challengeId): string
	{
		$link = strpos($this->view, '<a href="/challenges/' . $challengeId . '">');
		$start = strrpos(substr($this->view, 0, $link), 'class="challenges__row"');
		$end = strpos($this->view, 'class="challenges__row"', $link);
		return substr($this->view, $start, $end === false ? null : $end - $start);
	}

	public function testTheProblemsShowOnlyToThePlayersWhoFinishedTheirRun(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend']);
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$firstProblemLink = 'href="/' . SetConnectionTable::findDisplaySetConnection(ChallengeTable::problemIDs($challengeId)[0])->id . '"';

		$this->get('/challenges/' . $challengeId);
		$page = $this->view;
		$this->assertTextContains($firstProblemLink, $page);
		$this->assertSame(5, substr_count($page, 'class="problem-nav__item '));
		$this->assertTextContains('challenges__cell--solved', $page);

		$this->login('friend');
		$this->get('/challenges/' . $challengeId);
		$page = $this->view;
		$this->assertTextNotContains($firstProblemLink, $page);
		$this->assertTextNotContains('problem-nav__item', $page);
		$this->assertTextNotContains('challenges__cell--solved', $page);
		$this->assertTextContains('shows once you finish your run', $page);
	}

	public function testStartingATimeModeRunEndsTheRunOfAChallenge(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5]);
		$this->post('/challenges/play/' . $challengeId);
		$this->post('/timeMode/play/1');
		Auth::init();
		$this->post('/tsumegos/result', [
			'tsumego_id' => ChallengeTable::problemIDs($challengeId)[0], 'seconds' => 10, 'outcome' => PlayOutcome::SolvedClean->value]);

		$this->get('/timeMode/overview');
		$page = $this->view;
		$this->assertTextContains('id="challenge-in-progress"', $page);

		$this->get('/timeMode/start?categoryID=' . TimeModeCategoryTable::BLITZ . '&rankID=' . $this->rankId('5k'));

		// the problems of the challenge are known now, so its run ends instead of being thrown away
		$session = ChallengeTable::runOf($challengeId, $this->userId('challenger'));
		$this->assertNotSame(TimeModeSessionStatusTable::IN_PROGRESS, $session->time_mode_session_status_id);
		$this->assertSame(
			[TimeModeAttemptStatusTable::SOLVED, TimeModeAttemptStatusTable::SKIPPED, TimeModeAttemptStatusTable::SKIPPED, TimeModeAttemptStatusTable::SKIPPED, TimeModeAttemptStatusTable::SKIPPED],
			array_map(fn(array $attempt) => (int) $attempt['time_mode_attempt_status_id'], $this->runAttempts($challengeId, 'challenger')));

		// and the new time mode run is the one being played
		$timeMode = new App\Utility\TimeMode();
		$this->assertNull($timeMode->challenge);
		$this->assertSame(TimeModeCategoryTable::BLITZ, $timeMode->currentSession->time_mode_category_id);
	}

	public function testPlayingAnotherChallengeEndsTheRunOfTheFirst(): void
	{
		$this->prepare();
		$firstId = $this->create(['problem_count' => 5]);
		$secondId = $this->create(['problem_count' => 5]);
		$this->post('/challenges/play/' . $firstId);

		$this->get('/challenges/' . $secondId);
		$page = $this->view;
		$this->assertTextContains('id="challenge-other-in-progress"', $page);
		$this->get('/challenges/' . $firstId);
		$page = $this->view;
		$this->assertTextNotContains('id="challenge-other-in-progress"', $page);

		$this->post('/challenges/play/' . $secondId);

		$this->assertSame([ChallengeTable::RUN_FINISHED], array_column(ChallengeTable::standings($firstId), 'run'));
		$this->assertSame([ChallengeTable::RUN_PLAYING], array_column(ChallengeTable::standings($secondId), 'run'));
	}

	//A player who finished their run starts a rematch: a new challenge with the same settings and
	//new problems, the other players invited, the one who declined left out.
	public function testARematchRepeatsTheChallengeWithNewProblems(): void
	{
		$this->prepare(12);
		$challengeId = $this->create(['problem_count' => 5, 'seconds_per_problem' => 30, 'players' => 'DN_friend, DN_stranger']);
		$this->login('stranger');
		$this->post('/challenges/decline/' . $challengeId);
		$this->login('challenger');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$this->get('/challenges/' . $challengeId);
		$this->assertTextNotContains('action="/challenges/rematch/', $this->view, 'not while the friend still plays');
		$this->login('friend');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 20]));
		$this->get('/challenges/' . $challengeId);
		$this->assertTextContains('action="/challenges/rematch/' . $challengeId . '"', $this->view);

		$this->post('/challenges/rematch/' . $challengeId);

		$rematchId = $this->fetchTable('Challenge')->find()->orderByDesc('id')->firstOrFail()->id;
		$this->assertNotSame($challengeId, $rematchId);
		$this->assertSame(Util::getInternalAddress() . '/challenges/' . $rematchId, $this->headerValue('Location'));
		$original = ChallengeTable::withDetails($challengeId);
		$rematch = ChallengeTable::withDetails($rematchId);
		foreach (['minimum_time_mode_rank_id', 'maximum_time_mode_rank_id', 'seconds_per_problem', 'problem_count'] as $setting)
			$this->assertEquals($original[$setting], $rematch[$setting], $setting);
		$this->assertSame((int) $this->userId('friend'), (int) $rematch['created_by_user_id']);
		$this->assertSame([], array_intersect(ChallengeTable::problemIDs($challengeId), ChallengeTable::problemIDs($rematchId)), 'twelve problems leave room for five new ones');
		$this->assertSame([$this->userId('friend'), $this->userId('challenger')], $this->participants($rematchId));
		$this->assertSame(1, ChallengeTable::countWaitingFor($this->userId('challenger')));
	}

	//With too few problems of the ranks for new ones, the rematch takes some of the old ones too.
	public function testARematchOfFewProblemsTakesOldOnesToo(): void
	{
		$this->prepare(5);
		$challengeId = $this->create(['problem_count' => 5]);

		$this->post('/challenges/rematch/' . $challengeId);

		$rematchId = $this->fetchTable('Challenge')->find()->orderByDesc('id')->firstOrFail()->id;
		$this->assertCount(5, ChallengeTable::problemIDs($rematchId));
		$this->assertSame([$this->userId('challenger')], $this->participants($rematchId));
	}

	public function testOnlyThePlayersOfAChallengeStartARematch(): void
	{
		$this->prepare();
		$challengeId = $this->create();
		$this->login('stranger');

		$this->post('/challenges/rematch/' . $challengeId);

		$this->assertResponseCode(403);
		$this->assertSame(1, $this->fetchTable('Challenge')->find()->count());
	}

	public function testAnInvitedPlayerDeclinesAndCanStillChangeTheirMind(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend']);
		$this->login('friend');
		$this->get('/challenges');
		$page = $this->view;
		$this->assertTextContains('action="/challenges/decline/' . $challengeId . '"', $page);

		$this->post('/challenges/decline/' . $challengeId);

		$this->assertFalse(ChallengeTable::isParticipant($challengeId, $this->userId('friend')));
		$this->assertSame(0, ChallengeTable::countWaitingFor($this->userId('friend')));
		$this->assertSame(['DN_challenger', 'DN_friend'], array_column(ChallengeTable::standings($challengeId), 'user_display_name'));
		$this->assertSame([ChallengeTable::RUN_NOT_STARTED, ChallengeTable::RUN_DECLINED], array_column(ChallengeTable::standings($challengeId), 'run'));
		$this->get('/challenges/' . $challengeId);
		$page = $this->view;
		$this->assertTextContains('id="challenge-declined"', $page);

		// the challenger sees it and can't invite the friend again
		$this->login('challenger');
		$this->get('/challenges/' . $challengeId);
		$page = $this->view;
		$this->assertTextContains('>declined</td>', $page);
		$this->post('/challenges/invite/' . $challengeId, ['players' => 'DN_friend']);
		$this->assertSame(ChallengeTable::RUN_DECLINED, ChallengeTable::standings($challengeId)[1]['run']);

		// playing it after all puts the friend back in
		$this->login('friend');
		$this->post('/challenges/play/' . $challengeId);
		$this->assertTrue(ChallengeTable::isParticipant($challengeId, $this->userId('friend')));
		$this->assertNotNull(ChallengeTable::runOf($challengeId, $this->userId('friend')));
	}

	public function testAStartedRunCannotBeDeclined(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5]);
		$this->post('/challenges/play/' . $challengeId);

		$this->post('/challenges/decline/' . $challengeId);

		$this->assertTrue(ChallengeTable::isParticipant($challengeId, $this->userId('challenger')));
		$this->assertSame([ChallengeTable::RUN_PLAYING], array_column(ChallengeTable::standings($challengeId), 'run'));
	}

	public function testARunOfAChallengeIsNotARunOfTheTimeMode(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5]);
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));

		$this->get('/timeMode/overview');
		$page = $this->view;

		// with no run of the time mode there are no results of it to open
		$this->assertTextContains('btn btn--inactive" href="#">Results', $page);
	}

	public function testTheProfileOffersToChallengeThePlayer(): void
	{
		$this->prepare();

		$this->get('/challenges?opponent=' . $this->userId('friend'));
		$page = $this->view;

		$this->assertTextContains('id="challenge-players" name="players" value="DN_friend"', $page);
	}

	public function testTheMenuCountsTheChallengesNotPlayedYet(): void
	{
		$this->prepare();
		$this->create(['players' => 'DN_friend']);
		$this->create(['players' => 'DN_friend']);

		$this->assertSame(2, ChallengeTable::countWaitingFor($this->userId('friend')));
		$this->assertSame(2, ChallengeTable::countWaitingFor($this->userId('challenger')));
		$this->assertSame(0, ChallengeTable::countWaitingFor($this->userId('stranger')));
	}

	//The whole way through the board: the player starts their run on the page of the challenge,
	//solves the problems one after another and ends on the table of the challenge.
	public function testPlayingAChallengeInTheBrowser(): void
	{
		$this->prepare(3);
		$challengeId = ChallengeTable::start($this->userId('challenger'), $this->rankId('5k'), $this->rankId('5k'), 60, 5);
		$this->assertNotNull($challengeId);

		$browser = Browser::instance();
		$browser->get('challenges/' . $challengeId);
		$playButton = $browser->find('#challenge-play');
		$playButton->click();
		$browser->waitUntilStale($playButton);

		for ($i = 0; $i < 3; $i++)
		{
			$this->assertStringContainsString('Challenge', $browser->find('#account-bar-xp')->getText());
			$browser->playWithResult('S');
			$nextButton = $browser->find('#besogo-next-button');
			$nextButton->click();
			$browser->waitUntilStale($nextButton);
		}

		$this->assertStringEndsWith('/challenges/' . $challengeId, $browser->driver->getCurrentURL());
		$this->assertStringContainsString('You are 1st of 1', $browser->find('#challenge-my-result')->getText());
		$this->assertCount(3, $browser->getElements('.challenges__standing .challenges__cell--solved'));
		$browser->assertNoJsErrors();
	}
}

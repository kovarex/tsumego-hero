<?php

use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\PlayOutcome;
use App\Utility\Rating;
use App\Utility\TimeModeUtil;
use App\Utility\Util;

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
		$challenge = ClassRegistry::init('Challenge')->find('first', ['order' => 'id DESC']);
		return $challenge ? (int) $challenge['Challenge']['id'] : null;
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
		$problemIDs = Challenge::problemIDs($challengeId);
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

		$challenge = Challenge::withDetails($challengeId);
		$this->assertSame(30, (int) $challenge['seconds_per_problem']);
		$this->assertSame(10, (int) $challenge['problem_count']);
		$this->assertCount(10, array_unique(Challenge::problemIDs($challengeId)));
		$this->assertSame([$this->userId('challenger'), $this->userId('friend')], $this->participants($challengeId));
		$this->assertStringEndsWith('/challenges/' . $challengeId, $this->headerValue('Location'));

		// the invitation is the challenge in the friend's challenges, not a message
		$this->assertEmpty(Util::query('SELECT * FROM message'));
		$this->assertSame(1, Challenge::countWaitingFor($this->userId('friend')));
		$this->assertStringContainsString('10 problems, 5k, 30 seconds each', Challenge::describe($challenge));
	}

	public function testTheTimeForAProblemCanBeLong(): void
	{
		$this->prepare();
		$challengeId = $this->create(['seconds_per_problem' => 1800]);
		$this->assertSame(1800, (int) Challenge::withDetails($challengeId)['seconds_per_problem']);
		$this->assertStringContainsString('10 problems, 5k, 30 minutes each', Challenge::describe(Challenge::withDetails($challengeId)));
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

		$challenge = Challenge::withDetails($challengeId);
		$this->assertSame('5k', $challenge['minimum_rank']);
		$this->assertSame('1d', $challenge['maximum_rank']);
		$problemIDs = Challenge::problemIDs($challengeId);
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
			$this->assertSame(Challenge::problemIDs($challengeId), array_map(fn(array $attempt) => (int) $attempt['tsumego_id'], $attempts));
			$this->assertSame([1, 2, 3, 4, 5], array_map(fn(array $attempt) => (int) $attempt['order'], $attempts));
		}

		// the time the client reports is capped by the time of the challenge, and the points are counted from it
		$this->post('/timeMode/play');
		Auth::init();
		$this->post('/tsumegos/result', [
			'tsumego_id' => Challenge::problemIDs($challengeId)[0],
			'seconds' => 500,
			'outcome' => PlayOutcome::SolvedClean->value]);
		$attempt = $this->runAttempts($challengeId, 'stranger')[0];
		$this->assertSame(30.0, (float) $attempt['seconds']);
		$this->assertSame(100 * TimeModeUtil::$POINTS_RATIO_FOR_FINISHING, (float) $attempt['points']);
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
		$problemIDs = Challenge::problemIDs($challengeId);
		foreach ($problemIDs as $index => $problemID)
		{
			$this->post('/timeMode/play/' . ($index + 1));
			Auth::init();
			$this->post('/tsumegos/result', [
				'tsumego_id' => $problemID, 'seconds' => 10, 'outcome' => PlayOutcome::SolvedClean->value]);
		}

		$this->post('/timeMode/play');
		$session = Challenge::runOf($challengeId, $this->userId('challenger'));
		$this->assertSame(Util::getInternalAddress() . '/timeMode/result/' . $session->id, $this->headerValue('Location'));

		$this->get('/timeMode/result/' . $session->id);
		$this->assertSame(Util::getInternalAddress() . '/challenges/' . $challengeId, $this->headerValue('Location'));
	}

	public function testTheTableRanksThePlayersByTheirPoints(): void
	{
		$this->prepare();
		$challengeId = $this->create([
			'problem_count' => 5,
			'players' => 'DN_friend, DN_stranger']);

		// the challenger solves three problems, the friend all five, the stranger hasn't played yet
		$this->playRun($challengeId, [
			[PlayOutcome::SolvedClean, 10],
			[PlayOutcome::Failed, 10],
			[PlayOutcome::SolvedClean, 10],
			[PlayOutcome::Failed, 10],
			[PlayOutcome::SolvedClean, 10]]);
		$this->login('friend');
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 20]));

		$standings = Challenge::standings($challengeId);
		$this->assertSame(['DN_friend', 'DN_challenger', 'DN_stranger'], array_column($standings, 'user_display_name'));
		$this->assertSame([1, 2, null], array_column($standings, 'place'));
		$this->assertSame([5, 3, 0], array_column($standings, 'solved_count'));
		$this->assertSame([Challenge::RUN_FINISHED, Challenge::RUN_FINISHED, Challenge::RUN_NOT_STARTED], array_column($standings, 'run'));

		$this->get('/challenges/' . $challengeId);
		$page = $this->view;
		$this->assertTextContains('You are <strong>1st</strong> of 2', $page);
		$this->assertTextContains('challenges__cell--failed', $page);
		$this->assertTextContains('not played yet', $page);

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

	public function testTheProblemsShowOnlyToThePlayersWhoFinishedTheirRun(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend']);
		$this->playRun($challengeId, array_fill(0, 5, [PlayOutcome::SolvedClean, 10]));
		$firstProblemLink = 'href="/' . ClassRegistry::init('SetConnection')->findDisplaySetConnection(Challenge::problemIDs($challengeId)[0])['SetConnection']['id'] . '"';

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
			'tsumego_id' => Challenge::problemIDs($challengeId)[0], 'seconds' => 10, 'outcome' => PlayOutcome::SolvedClean->value]);

		$this->get('/timeMode/overview');
		$page = $this->view;
		$this->assertTextContains('id="challenge-in-progress"', $page);

		$this->get('/timeMode/start?categoryID=' . TimeModeUtil::$CATEGORY_BLITZ . '&rankID=' . $this->rankId('5k'));

		// the problems of the challenge are known now, so its run ends instead of being thrown away
		$session = Challenge::runOf($challengeId, $this->userId('challenger'));
		$this->assertNotSame(TimeModeUtil::$SESSION_STATUS_IN_PROGRESS, $session->time_mode_session_status_id);
		$this->assertSame(
			[TimeModeUtil::$ATTEMPT_RESULT_SOLVED, TimeModeUtil::$ATTEMPT_STATUS_SKIPPED, TimeModeUtil::$ATTEMPT_STATUS_SKIPPED, TimeModeUtil::$ATTEMPT_STATUS_SKIPPED, TimeModeUtil::$ATTEMPT_STATUS_SKIPPED],
			array_map(fn(array $attempt) => (int) $attempt['time_mode_attempt_status_id'], $this->runAttempts($challengeId, 'challenger')));

		// and the new time mode run is the one being played
		$timeMode = new App\Utility\TimeMode();
		$this->assertNull($timeMode->challenge);
		$this->assertSame(TimeModeUtil::$CATEGORY_BLITZ, $timeMode->currentSession->time_mode_category_id);
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

		$this->assertSame([Challenge::RUN_FINISHED], array_column(Challenge::standings($firstId), 'run'));
		$this->assertSame([Challenge::RUN_PLAYING], array_column(Challenge::standings($secondId), 'run'));
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

		$this->assertFalse(Challenge::isParticipant($challengeId, $this->userId('friend')));
		$this->assertSame(0, Challenge::countWaitingFor($this->userId('friend')));
		$this->assertSame(['DN_challenger', 'DN_friend'], array_column(Challenge::standings($challengeId), 'user_display_name'));
		$this->assertSame([Challenge::RUN_NOT_STARTED, Challenge::RUN_DECLINED], array_column(Challenge::standings($challengeId), 'run'));
		$this->get('/challenges/' . $challengeId);
		$page = $this->view;
		$this->assertTextContains('id="challenge-declined"', $page);

		// the challenger sees it and can't invite the friend again
		$this->login('challenger');
		$this->get('/challenges/' . $challengeId);
		$page = $this->view;
		$this->assertTextContains('>declined</td>', $page);
		$this->post('/challenges/invite/' . $challengeId, ['players' => 'DN_friend']);
		$this->assertSame(Challenge::RUN_DECLINED, Challenge::standings($challengeId)[1]['run']);

		// playing it after all puts the friend back in
		$this->login('friend');
		$this->post('/challenges/play/' . $challengeId);
		$this->assertTrue(Challenge::isParticipant($challengeId, $this->userId('friend')));
		$this->assertNotNull(Challenge::runOf($challengeId, $this->userId('friend')));
	}

	public function testAStartedRunCannotBeDeclined(): void
	{
		$this->prepare();
		$challengeId = $this->create(['problem_count' => 5]);
		$this->post('/challenges/play/' . $challengeId);

		$this->post('/challenges/decline/' . $challengeId);

		$this->assertTrue(Challenge::isParticipant($challengeId, $this->userId('challenger')));
		$this->assertSame([Challenge::RUN_PLAYING], array_column(Challenge::standings($challengeId), 'run'));
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

		$this->assertSame(2, Challenge::countWaitingFor($this->userId('friend')));
		$this->assertSame(2, Challenge::countWaitingFor($this->userId('challenger')));
		$this->assertSame(0, Challenge::countWaitingFor($this->userId('stranger')));
	}

	//The whole way through the board: the player starts their run on the page of the challenge,
	//solves the problems one after another and ends on the table of the challenge.
	public function testPlayingAChallengeInTheBrowser(): void
	{
		$this->prepare(3);
		$challengeId = Challenge::start($this->userId('challenger'), $this->rankId('5k'), $this->rankId('5k'), 60, 5);
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

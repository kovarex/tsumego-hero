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

//Challenges between players: everyone in a challenge plays the same problems like a time mode
//run, and the page of the challenge ranks them by their points.
class ChallengesControllerTest extends IntegrationTestCase
{
	private ContextPreparator $context;

	//Black (1,1) and then (2,1) after white's answer (1,2) solves it, anything else fails
	private const SGF = '(;GM[1]FF[4]SZ[19]AB[cc](;B[aa];W[ab];B[ba]C[+]))';

	//The player 'challenger' is signed in, 'friend' and 'stranger' can join, and there are
	//twelve problems of the one rank of the time mode.
	//$sgf: the SGF of every problem, none when null
	private function prepare(int $problemCount = 12, ?string $sgf = null): void
	{
		Auth::logout();
		$this->context = new ContextPreparator([
			'user' => ['name' => 'challenger', 'mode' => Constants::$LEVEL_MODE],
			'other-users' => [['name' => 'friend'], ['name' => 'stranger']],
			'time-mode-ranks' => ['5k'],
			'tsumegos' => $sgf === null ? range(1, $problemCount) : array_map(fn(int $order) => ['set_order' => $order, 'sgf' => $sgf], range(1, $problemCount)),
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
	/** @param list<array{0: PlayOutcome, 1: float, 2?: array<string, mixed>}> $results The outcome and seconds of each problem, and what else the request sends */
	private function playRun(int $challengeId, array $results): void
	{
		$this->post('/challenges/play/' . $challengeId);
		$problemIDs = ChallengeTable::problemIDs($challengeId);
		foreach ($results as $index => $result)
		{
			[$outcome, $seconds] = $result;
			$this->post('/timeMode/play/' . ($index + 1));
			Auth::init();
			$this->post('/tsumegos/result', ($result[2] ?? []) + [
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

		$standings = ChallengeTable::standings($challengeId);
		$this->assertSame(['DN_friend', 'DN_challenger', 'DN_stranger'], array_column($standings, 'user_display_name'));
		$this->assertSame([1, 2, null], array_column($standings, 'place'));
		$this->assertSame([5, 3, 0], array_column($standings, 'solved_count'));
		$this->assertSame([ChallengeTable::RUN_FINISHED, ChallengeTable::RUN_FINISHED, ChallengeTable::RUN_NOT_STARTED], array_column($standings, 'run'));

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

	private function sgfID(int $tsumegoID): int
	{
		return (int) Util::query('SELECT id FROM sgf WHERE tsumego_id = ? ORDER BY id DESC LIMIT 1', [$tsumegoID])[0]['id'];
	}

	//A fail and a time-out keep the line played, in the coordinates of the version played; a line that
	//isn't one, or one on a version of another problem, is left out and the result still counts
	public function testAFailedProblemKeepsTheLinePlayed(): void
	{
		$this->prepare(12, self::SGF);
		$challengeId = $this->create(['problem_count' => 5]);
		$problemIDs = ChallengeTable::problemIDs($challengeId);
		$this->playRun($challengeId, [
			[PlayOutcome::Failed, 10, ['failed_line' => 'aabbcc', 'sgf_id' => $this->sgfID($problemIDs[0])]],
			[PlayOutcome::TimedOut, 60, ['failed_line' => 'ddtt', 'sgf_id' => $this->sgfID($problemIDs[1])]],
			[PlayOutcome::Failed, 10, ['failed_line' => 'aazz', 'sgf_id' => $this->sgfID($problemIDs[2])]],
			[PlayOutcome::Failed, 10, ['failed_line' => 'aabb', 'sgf_id' => $this->sgfID($problemIDs[0])]],
			[PlayOutcome::SolvedClean, 10, ['failed_line' => 'aabb', 'sgf_id' => $this->sgfID($problemIDs[4])]]]);

		$attempts = $this->runAttempts($challengeId, 'challenger');
		$this->assertSame(['aabbcc', 'ddtt', null, null, null], array_column($attempts, 'failed_line'));
		$this->assertSame([$this->sgfID($problemIDs[0]), $this->sgfID($problemIDs[1]), null, null, null], array_map(fn($sgfID) => $sgfID === null ? null : (int) $sgfID, array_column($attempts, 'sgf_id')));
		$this->assertSame([TimeModeAttemptStatusTable::FAILED, TimeModeAttemptStatusTable::TIMEOUT, TimeModeAttemptStatusTable::FAILED, TimeModeAttemptStatusTable::FAILED, TimeModeAttemptStatusTable::SOLVED],
			array_map('intval', array_column($attempts, 'time_mode_attempt_status_id')));
	}

	//The line shows on the cell of the fail to the players who finished their run, as the results do
	public function testTheFailedLineShowsOnlyToThePlayersWhoFinishedTheirRun(): void
	{
		$this->prepare(12, self::SGF);
		$challengeId = $this->create(['problem_count' => 5, 'players' => 'DN_friend']);
		$problemIDs = ChallengeTable::problemIDs($challengeId);
		$this->playRun($challengeId, [[PlayOutcome::Failed, 10, ['failed_line' => 'aabb', 'sgf_id' => $this->sgfID($problemIDs[0])]]]
			+ array_fill(1, 4, [PlayOutcome::SolvedClean, 10]));

		$this->get('/challenges/' . $challengeId);
		preg_match('/data-failed-line="([^"]*)"/', $this->view, $match);
		$this->assertNotEmpty($match, 'the cell of the fail has its line');
		$line = json_decode(html_entity_decode($match[1]), true);
		$this->assertSame('B', $line['firstColor']);
		$this->assertSame(4, strlen($line['line']));
		$this->assertSame(19, $line['boardSize']);

		$this->login('friend');
		$this->get('/challenges/' . $challengeId);
		$this->assertTextNotContains('data-failed-line', $this->view);
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

	//The board sends the moves of a lost line in the coordinates of the SGF, however it turned the board,
	//and the cell of the fail shows them numbered on the problem
	public function testAFailedLineShowsOnTheCellOfTheFail(): void
	{
		$this->playAFailedLine(self::SGF, 'aaabea');
	}

	//The same problem in the bottom right corner, which the board shows in the top left
	public function testAFailedLineIsInTheCoordinatesOfTheSgf(): void
	{
		$this->playAFailedLine('(;GM[1]FF[4]SZ[19]AB[qq](;B[ss];W[sr];B[rs]C[+]))', 'sssros');
	}

	//Plays black (1,1), and after white's answer (5,1), on the board as shown, which fails the problem
	private function playAFailedLine(string $sgf, string $expectedLine): void
	{
		$this->prepare(1, $sgf);
		$challengeId = ChallengeTable::start($this->userId('challenger'), $this->rankId('5k'), $this->rankId('5k'), 60, 5);

		$browser = Browser::instance();
		$browser->get('challenges/' . $challengeId);
		$playButton = $browser->find('#challenge-play');
		$playButton->click();
		$browser->waitUntilStale($playButton);
		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber >= 2');
		$browser->clickBoard(5, 1);
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Incorrect")');
		$browser->waitForSubmitResult();

		$attempt = $this->runAttempts($challengeId, 'challenger')[0];
		$this->assertSame($expectedLine, $attempt['failed_line']);
		$this->assertSame($this->sgfID((int) $attempt['tsumego_id']), (int) $attempt['sgf_id']);

		$nextButton = $browser->find('#besogo-next-button');
		$nextButton->click();
		$browser->waitUntilStale($nextButton);
		$this->assertStringEndsWith('/challenges/' . $challengeId, $browser->driver->getCurrentURL());
		$browser->hover($browser->find('.challenges__cell--line'));
		$browser->waitUntilJs('document.querySelector(".challenges__line-board svg") !== null');
		$this->assertSame(['1', '2', '3'], $browser->driver->executeScript('return Array.from(document.querySelectorAll(".challenges__line-board svg text")).map(number => number.textContent);'));
		$this->assertSame('1', $browser->driver->executeScript('return getComputedStyle(document.querySelector(".challenges__line-board")).opacity;'));
		$browser->assertNoJsErrors();
	}
}

<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\Rating;
use App\Utility\TimeMode;
use App\Utility\TimeModeUtil;

/**
 * Challenges between players: one player picks a range of difficulty, the time for a problem and
 * how many problems, invites the others by their names or sends them the link, and everyone in the
 * challenge plays the same problems like a time mode run, whenever they like. The page of the
 * challenge is the table of who has how many points.
 */
class ChallengesController extends AppController
{
	/**
	 * The challenges of the player, and the form to start one. ?opponent=<user id> fills in the
	 * player to invite, which is where the button on a profile leads.
	 */
	#[HttpGet]
	public function index(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$this->set('_page', 'challenges');
		$this->set('_title', 'Tsumego Hero - Challenges');
		$this->set('challenges', Challenge::ofPlayer(Auth::getUserID()));

		$ranks = ClassRegistry::init('TimeModeRank')->find('all', ['order' => 'id']) ?: [];
		$this->set('ranks', array_map(fn(array $rank) => $rank['TimeModeRank'], $ranks));
		$this->set('defaultRankID', self::rankOfPlayer($ranks));

		$opponent = null;
		if ($opponentID = (int) ($this->params['url']['opponent'] ?? 0))
			if ($opponentRow = ClassRegistry::init('User')->findById($opponentID))
				if (!User::isDeleted($opponentRow['User']) && $opponentID !== Auth::getUserID())
					$opponent = $opponentRow['User']['display_name'];
		$this->set('opponent', $opponent);
		return null;
	}

	#[HttpPost]
	public function create(): mixed
	{
		$this->Authorization->authorize('Challenge', 'create');

		$data = $this->request->data;
		$minimumRankID = (int) ($data['minimum_rank'] ?? 0);
		$maximumRankID = (int) ($data['maximum_rank'] ?? 0);
		$secondsPerProblem = (int) ($data['seconds_per_problem'] ?? 0);
		$problemCount = (int) ($data['problem_count'] ?? 0);
		$rankModel = ClassRegistry::init('TimeModeRank');
		if (!$rankModel->findById($minimumRankID) || !$rankModel->findById($maximumRankID))
			throw new BadRequestException('Unknown rank.');
		if (!in_array($secondsPerProblem, Challenge::SECONDS_PER_PROBLEM_CHOICES, true))
			throw new BadRequestException('Unknown time for a problem.');
		if (!in_array($problemCount, Challenge::PROBLEM_COUNT_CHOICES, true))
			throw new BadRequestException('Unknown number of problems.');
		// the ranks go up with their ids, so a range picked the other way round is the same range
		if ($minimumRankID > $maximumRankID)
			[$minimumRankID, $maximumRankID] = [$maximumRankID, $minimumRankID];

		$challengeID = Challenge::start(Auth::getUserID(), $minimumRankID, $maximumRankID, $secondsPerProblem, $problemCount);
		if ($challengeID === null)
		{
			CookieFlash::set('There are no problems of these ranks, pick other ones.', 'error');
			return $this->redirect('/challenges');
		}
		$this->inviteByNames($challengeID, (string) ($data['players'] ?? ''));
		return $this->redirect(self::challengeUrl($challengeID));
	}

	/**
	 * The page of a challenge: what is played, the table of the players, and a way in for the
	 * ones who aren't in yet. How each player did with each problem is shown to the players who
	 * finished their own run, so nobody sees the problems before they play them.
	 */
	#[HttpGet]
	public function view(string $id): mixed
	{
		$challenge = $this->findChallenge($id);
		$challengeID = (int) $challenge['id'];
		$this->set('_page', 'challenges');
		$this->set('_title', 'Tsumego Hero - ' . Challenge::title($challenge));
		$this->set('challenge', $challenge);

		$standings = Challenge::standings($challengeID);
		$this->set('standings', $standings);

		$isParticipant = Auth::isLoggedIn() && Challenge::isParticipant($challengeID, Auth::getUserID());
		$myRun = $isParticipant ? array_find($standings, fn(array $row) => (int) $row['user_id'] === Auth::getUserID()) : null;
		$this->set('isParticipant', $isParticipant);
		$this->set('hasDeclined', Auth::isLoggedIn() && !$isParticipant && Challenge::participation($challengeID, Auth::getUserID()) !== null);
		$this->set('myRun', $myRun);
		$this->set('isFull', !$isParticipant && Challenge::participantCount($challengeID) >= Challenge::MAXIMUM_PARTICIPANTS);
		$this->set('joinUrl', Router::url(self::challengeUrl($challengeID), true));

		// playing this one ends the run of another challenge, so the player is told before they start
		$otherRun = Auth::isLoggedIn() ? ClassRegistry::init('TimeModeSession')->find('first', ['conditions' => [
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeUtil::$SESSION_STATUS_IN_PROGRESS,
			'challenge_id NOT' => null,
			'challenge_id !=' => $challengeID]]) : null;
		$this->set('otherChallengeInProgressID', $otherRun ? (int) $otherRun['TimeModeSession']['challenge_id'] : null);

		$showResults = $myRun !== null && $myRun['run'] === Challenge::RUN_FINISHED;
		$this->set('showResults', $showResults);
		$attemptsByRun = $showResults ? Challenge::attemptsByRun($challengeID) : [];
		$this->set('attemptsByRun', $attemptsByRun);
		$this->set('problemButtons', $showResults ? Challenge::problemButtons($challengeID, $attemptsByRun[(int) $myRun['session_id']] ?? []) : []);
		return null;
	}

	#[HttpPost]
	public function join(string $id): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$challenge = $this->findChallenge($id);
		$this->joinChallenge($challenge);
		return $this->redirect(self::challengeUrl((int) $challenge['id']));
	}

	/**
	 * The player turns the challenge down, before they start their run.
	 */
	#[HttpPost]
	public function decline(string $id): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$challenge = $this->findChallenge($id);
		if (Challenge::decline((int) $challenge['id'], Auth::getUserID()))
			CookieFlash::set('You declined ' . Challenge::title($challenge) . '.', 'success');
		else
			CookieFlash::set('Only a challenge you haven\'t started can be declined.', 'error');
		return $this->redirect('/challenges');
	}

	/**
	 * A player in the challenge invites others by their names, separated by commas. Each gets a
	 * message with the link.
	 */
	#[HttpPost]
	public function invite(string $id): mixed
	{
		$challenge = $this->findChallenge($id);
		if (!Auth::isLoggedIn() || !Challenge::isParticipant((int) $challenge['id'], Auth::getUserID()))
			throw new ForbiddenException('Only the players of the challenge can invite others.');
		$this->inviteByNames((int) $challenge['id'], (string) ($this->request->data['players'] ?? ''));
		return $this->redirect(self::challengeUrl((int) $challenge['id']));
	}

	/**
	 * Starts the run of the player, joining the challenge first when they aren't in yet, or gets
	 * them back to a run they left in the middle.
	 */
	#[HttpPost]
	public function play(string $id): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$challenge = $this->findChallenge($id);
		$challengeID = (int) $challenge['id'];
		if (!$this->joinChallenge($challenge))
			return $this->redirect(self::challengeUrl($challengeID));

		$run = Challenge::runOf($challengeID, Auth::getUserID());
		if ($run && (int) $run['TimeModeSession']['time_mode_session_status_id'] !== TimeModeUtil::$SESSION_STATUS_IN_PROGRESS)
			return $this->redirect(self::challengeUrl($challengeID));
		if (!$run)
			new TimeMode()->startChallenge($challenge);
		return $this->redirect('/timeMode/play');
	}

	/**
	 * Puts the player into the challenge when they aren't in yet, or declined it before, and there is room.
	 *
	 * @param array<string, mixed> $challenge
	 * @return bool Whether the player is in the challenge
	 */
	private function joinChallenge(array $challenge): bool
	{
		$this->Authorization->authorize('Challenge', 'join');
		$challengeID = (int) $challenge['id'];
		if (Challenge::isParticipant($challengeID, Auth::getUserID()))
			return true;
		if (Challenge::participantCount($challengeID) >= Challenge::MAXIMUM_PARTICIPANTS)
		{
			CookieFlash::set('The challenge is full, ' . Challenge::MAXIMUM_PARTICIPANTS . ' players are in it already.', 'error');
			return false;
		}
		if (Challenge::participation($challengeID, Auth::getUserID()) !== null)
			Challenge::rejoin($challengeID, Auth::getUserID());
		else
			Challenge::addParticipant($challengeID, Auth::getUserID(), null);
		return true;
	}

	/**
	 * Adds the players of the names to the challenge and sends each of them the link, then says
	 * what came of it.
	 *
	 * @param string $names Display names separated by commas
	 */
	private function inviteByNames(int $challengeID, string $names): void
	{
		$names = array_values(array_unique(array_filter(array_map('trim', explode(',', $names)), fn(string $name) => $name !== '')));
		if (empty($names))
			return;

		$challenge = Challenge::withDetails($challengeID);
		$invited = [];
		$problems = [];
		foreach ($names as $name)
		{
			$player = ClassRegistry::init('User')->find('first', ['conditions' => ['display_name' => $name, 'deleted' => null]]);
			if (!$player)
			{
				$problems[] = 'There is no player named ' . $name . '.';
				continue;
			}
			$playerID = (int) $player['User']['id'];
			if ($playerID === Auth::getUserID())
				continue;
			if (Challenge::participantCount($challengeID) >= Challenge::MAXIMUM_PARTICIPANTS)
			{
				$problems[] = 'The challenge is full, ' . $name . ' can\'t be invited.';
				continue;
			}
			if (!Challenge::addParticipant($challengeID, $playerID, Auth::getUserID()))
			{
				$problems[] = Challenge::isParticipant($challengeID, $playerID)
					? $name . ' is in the challenge already.'
					: $name . ' declined the challenge.';
				continue;
			}
			Message::send(Auth::getUserID(), $playerID, self::invitationText($challenge), $this->_getEmailer());
			$invited[] = $name;
		}

		if (!empty($invited))
			CookieFlash::set('Invited ' . implode(', ', $invited) . '. They got a message with the link.'
				. (empty($problems) ? '' : ' ' . implode(' ', $problems)), empty($problems) ? 'success' : 'info');
		elseif (!empty($problems))
			CookieFlash::set(implode(' ', $problems), 'error');
	}

	/**
	 * @param array<string, mixed> $challenge As Challenge::withDetails() returns it
	 */
	private static function invitationText(array $challenge): string
	{
		return 'I challenge you to a tsumego duel: ' . Challenge::describe($challenge) . '. '
			. 'We all get the same problems, the most points win. Play it whenever you like: '
			. Router::url(self::challengeUrl((int) $challenge['id']), true);
	}

	/**
	 * The time mode rank of the player's rating, to start the form with, or the easiest one.
	 *
	 * @param array<array{TimeModeRank: array<string, mixed>}> $ranks Ordered by id
	 */
	private static function rankOfPlayer(array $ranks): ?int
	{
		if (empty($ranks))
			return null;
		$playerRank = Rating::getReadableRankFromRating((float) Auth::getUser()['rating']);
		foreach ($ranks as $rank)
			if ($rank['TimeModeRank']['name'] === $playerRank)
				return (int) $rank['TimeModeRank']['id'];
		return (int) $ranks[0]['TimeModeRank']['id'];
	}

	private static function challengeUrl(int $challengeID): string
	{
		return '/challenges/' . $challengeID;
	}

	/**
	 * @return array<string, mixed> As Challenge::withDetails() returns it
	 */
	private function findChallenge(string $id): array
	{
		$challenge = Challenge::withDetails((int) $id);
		if (!$challenge)
			throw new NotFoundException('Challenge not found');
		return $challenge;
	}
}

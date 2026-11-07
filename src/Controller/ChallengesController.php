<?php

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Exception\UnprocessableContentException;
use Cake\Http\Response;
use Cake\Routing\Router;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Model\Entity\TimeModeRank;
use App\Model\Table\ChallengeTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\PlayerNameSuggestions;
use App\Utility\Rating;
use App\Utility\TimeMode;

//Challenges between players: one player picks a range of difficulty, the time for a problem and
//how many problems, invites the others by their names or sends them the link, and everyone in the
//challenge plays the same problems like a time mode run, whenever they like. The page of the
//challenge is the table of who has how many points.
class ChallengesController extends AppController
{
	//The challenges of the player, and the form to start one. ?opponent=<user id> fills in the
	//player to invite, which is where the button on a profile leads.
	#[HttpGet]
	public function index(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$this->set('_page', 'challenges');
		$this->set('_title', 'Tsumego Hero - Challenges');
		$this->set('challenges', ChallengeTable::ofPlayer(Auth::getUserID()));
		$this->set('places', ChallengeTable::placesOfPlayer(Auth::getUserID()));

		$ranks = $this->fetchTable('TimeModeRank')->find()->orderBy('id')->toArray();
		$this->set('ranks', $ranks);
		$this->set('defaultRankID', self::rankOfPlayer($ranks));

		$opponent = null;
		if ($opponentID = (int) ($this->query('opponent') ?? 0))
			if ($opponentRow = $this->fetchTable('User')->find()->select(['display_name', 'deleted'])->where(['id' => $opponentID])->first())
				if ($opponentRow->deleted === null && $opponentID !== Auth::getUserID())
					$opponent = $opponentRow->display_name;
		$this->set('opponent', $opponent);
		return null;
	}

	#[HttpPost]
	public function create(): mixed
	{
		$this->Authorization->authorize('Challenge', 'create');

		$data = $this->inputs();
		$minimumRankID = (int) ($data['minimum_rank'] ?? 0);
		$maximumRankID = (int) ($data['maximum_rank'] ?? 0);
		$secondsPerProblem = (int) ($data['seconds_per_problem'] ?? 0);
		$problemCount = (int) ($data['problem_count'] ?? 0);
		$rankTable = $this->fetchTable('TimeModeRank');
		if (!$rankTable->exists(['id' => $minimumRankID]) || !$rankTable->exists(['id' => $maximumRankID]))
			throw new BadRequestException('Unknown rank.');
		if (!in_array($secondsPerProblem, ChallengeTable::SECONDS_PER_PROBLEM_CHOICES, true))
			throw new BadRequestException('Unknown time for a problem.');
		if (!in_array($problemCount, ChallengeTable::PROBLEM_COUNT_CHOICES, true))
			throw new BadRequestException('Unknown number of problems.');
		// the ranks go up with their ids, so a range picked the other way round is the same range
		if ($minimumRankID > $maximumRankID)
			[$minimumRankID, $maximumRankID] = [$maximumRankID, $minimumRankID];

		$challengeID = ChallengeTable::start(Auth::getUserID(), $minimumRankID, $maximumRankID, $secondsPerProblem, $problemCount);
		if ($challengeID === null)
		{
			CookieFlash::set('There are no problems of these ranks, pick other ones.', 'error');
			return $this->redirect('/challenges');
		}
		$this->inviteByNames($challengeID, (string) ($data['players'] ?? ''));
		return $this->redirect(self::challengeUrl($challengeID));
	}

	//The page of a challenge: what is played, the table of the players, and a way in for the
	//ones who aren't in yet. How each player did with each problem is shown to the players who
	//finished their own run, so nobody sees the problems before they play them.
	#[HttpGet]
	public function view(string $id): mixed
	{
		$challenge = $this->findChallenge($id);
		$challengeID = (int) $challenge['id'];
		$this->set('_page', 'challenges');
		$this->set('_title', 'Tsumego Hero - ' . ChallengeTable::title($challenge));
		$this->set('challenge', $challenge);

		$participation = Auth::isLoggedIn() ? ChallengeTable::participation($challengeID, Auth::getUserID()) : null;
		$wasRemoved = $participation !== null && $participation['removed'] !== null;
		$isParticipant = $participation !== null && $participation['declined'] === null && !$wasRemoved;
		$this->set($this->standingsVariables($challenge, $isParticipant));
		$this->set('isParticipant', $isParticipant);
		$this->set('wasRemoved', $wasRemoved);
		$this->set('hasDeclined', $participation !== null && !$isParticipant && !$wasRemoved);
		$rematch = ChallengeTable::rematchOf($challengeID);
		$this->set('rematch', $rematch);
		$this->set('isInRematch', $rematch !== null && Auth::isLoggedIn() && ChallengeTable::isParticipant((int) $rematch['id'], Auth::getUserID()));
		$this->set('isFull', !$isParticipant && ChallengeTable::participantCount($challengeID) >= ChallengeTable::MAXIMUM_PARTICIPANTS);
		$this->set('joinUrl', Router::url(self::challengeUrl($challengeID), true));

		// playing this one ends the run of another challenge, so the player is told before they start
		$otherRun = Auth::isLoggedIn() ? $this->fetchTable('TimeModeSession')->find()->where([
			'user_id' => Auth::getUserID(),
			'time_mode_session_status_id' => TimeModeSessionStatusTable::IN_PROGRESS,
			'challenge_id IS NOT' => null,
			'challenge_id !=' => $challengeID])->first() : null;
		$this->set('otherChallengeInProgressID', $otherRun?->challenge_id);
		return null;
	}

	//The table of the players of a challenge as the page draws it, for the page that keeps it up to
	//date while the challenge is in progress (webroot/js/ChallengePage.js).
	#[HttpGet]
	public function standings(string $id): Response
	{
		$challenge = $this->findChallenge($id);
		$isParticipant = Auth::isLoggedIn() && ChallengeTable::isParticipant((int) $challenge['id'], Auth::getUserID());
		$variables = $this->standingsVariables($challenge, $isParticipant);
		// never kept by a cache on the way, which would show the page a table it has outgrown
		return $this->respondJson([
			'finished' => $variables['isFinished'],
			'html' => $this->createView()->element('Challenges/standings', ['challenge' => $challenge] + $variables),
		])->withHeader('Cache-Control', 'private, no-store');
	}

	//What the table of the players needs, the same for the page and for standings(). How each
	//player did with each problem shows to the players who finished their own run.
	/**
	 * @param array<string, mixed> $challenge As withDetails() returns it
	 * @return array<string, mixed>
	 */
	private function standingsVariables(array $challenge, bool $isParticipant): array
	{
		$challengeID = (int) $challenge['id'];
		$standings = ChallengeTable::standings($challengeID);
		$myRun = $isParticipant ? array_find($standings, fn(array $row) => (int) $row['user_id'] === Auth::getUserID()) : null;
		$isCreator = Auth::isLoggedIn() && (int) $challenge['created_by_user_id'] === Auth::getUserID();
		$showResults = $myRun !== null && $myRun['run'] === ChallengeTable::RUN_FINISHED;
		$attemptsByRun = $showResults ? ChallengeTable::attemptsByRun($challengeID) : [];
		return [
			'standings' => $standings,
			'myRun' => $myRun,
			'isFinished' => ChallengeTable::everyoneFinished($standings),
			'removableUserIDs' => $isCreator ? array_map(fn(array $row) => (int) $row['user_id'],
				array_values(array_filter($standings, fn(array $row) => (int) $row['user_id'] !== Auth::getUserID()
					&& ChallengeTable::canRemove($row, (int) $challenge['seconds_per_problem'], time())))) : [],
			'showResults' => $showResults,
			'attemptsByRun' => $attemptsByRun,
			'problemButtons' => $showResults ? ChallengeTable::problemButtons($challengeID, $attemptsByRun[(int) $myRun['session_id']] ?? []) : [],
		];
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

	//The player turns the challenge down, before they start their run.
	#[HttpPost]
	public function decline(string $id): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$challenge = $this->findChallenge($id);
		if (ChallengeTable::decline((int) $challenge['id'], Auth::getUserID()))
			CookieFlash::set('You declined ' . ChallengeTable::title($challenge) . '.', 'success');
		else
			CookieFlash::set('Only a challenge you haven\'t started can be declined.', 'error');
		return $this->redirect('/challenges');
	}

	//The players the name being typed into an invitation can mean, friends first (PlayerNameSuggestions),
	//for the list under the field: ?query=<what is typed>
	#[HttpGet]
	public function players(): Response
	{
		if (!Auth::isLoggedIn())
			throw new UnauthorizedException('Sign in to invite players.');
		return $this->respondJson(['players' => PlayerNameSuggestions::for(Auth::getUserID(), (string) $this->query('query'))]);
	}

	//A player in the challenge invites others by their names, separated by commas.
	#[HttpPost]
	public function invite(string $id): mixed
	{
		$challenge = $this->findChallenge($id);
		if (!Auth::isLoggedIn() || !ChallengeTable::isParticipant((int) $challenge['id'], Auth::getUserID()))
			throw new ForbiddenException('Only the players of the challenge can invite others.');
		$this->inviteByNames((int) $challenge['id'], (string) ($this->input('players') ?? ''));
		return $this->redirect(self::challengeUrl((int) $challenge['id']));
	}

	//The creator removes a player who hasn't started, or whose run is stuck on a problem
	//(ChallengeTable::canRemove()), so the challenge can finish without them.
	//user_id: The player to remove
	#[HttpPost]
	public function remove(string $id): mixed
	{
		$challenge = $this->findChallenge($id);
		$challengeID = (int) $challenge['id'];
		if (!Auth::isLoggedIn() || (int) $challenge['created_by_user_id'] !== Auth::getUserID())
			throw new ForbiddenException('Only the creator of the challenge can remove its players.');
		$userID = (int) ($this->input('user_id') ?? 0);
		$standing = array_find(ChallengeTable::standings($challengeID), fn(array $row) => (int) $row['user_id'] === $userID);
		if ($standing === null || $userID === Auth::getUserID() || !ChallengeTable::canRemove($standing, (int) $challenge['seconds_per_problem'], time()))
			throw new UnprocessableContentException('Only a player who hasn\'t started, or is stuck on a problem, can be removed.');

		ChallengeTable::remove($challengeID, $userID);
		CookieFlash::set('Removed ' . $standing['user_display_name'] . ' from the challenge.', 'success');
		return $this->redirect(self::challengeUrl($challengeID));
	}

	//A player of the challenge starts a new one with its settings and its players invited, where
	//they can play again; the problems are new ones. A challenge has one rematch: once a player started
	//it, Rematch takes the others to it, where the usual rules of joining apply.
	#[HttpPost]
	public function rematch(string $id): mixed
	{
		$challenge = $this->findChallenge($id);
		$challengeID = (int) $challenge['id'];
		if (!Auth::isLoggedIn() || !ChallengeTable::isParticipant($challengeID, Auth::getUserID()))
			throw new ForbiddenException('Only the players of the challenge can start a rematch.');

		$existing = ChallengeTable::rematchOf($challengeID);
		if ($existing !== null)
			return $this->toStartedRematch($existing);

		$this->Authorization->authorize('Challenge', 'create');
		$rematch = ChallengeTable::rematch($challenge, Auth::getUserID());
		if ($rematch === null)
		{
			CookieFlash::set('There are no problems of these ranks anymore, start a challenge of other ones.', 'error');
			return $this->redirect(self::challengeUrl($challengeID));
		}
		// another player's click started it at the same moment
		if (!$rematch['started'])
			return $this->toStartedRematch(ChallengeTable::rematchOf($challengeID) ?? ['id' => $rematch['id']]);
		$invited = $rematch['invited'];
		CookieFlash::set('A rematch of ' . ChallengeTable::title($challenge) . ' with new problems'
			. (empty($invited) ? '.' : ', ' . count($invited) . (count($invited) === 1 ? ' player is' : ' players are') . ' invited.'), 'success');
		return $this->redirect(self::challengeUrl($rematch['id']));
	}

	//To the rematch another player started
	/** @param array<string, mixed> $rematch As ChallengeTable::rematchOf() returns it */
	private function toStartedRematch(array $rematch): Response
	{
		if (isset($rematch['user_display_name']))
			CookieFlash::set($rematch['user_display_name'] . ' started the rematch already, here it is.', 'success');
		return $this->redirect(self::challengeUrl((int) $rematch['id']));
	}

	//Starts the run of the player, joining the challenge first when they aren't in yet, or gets
	//them back to a run they left in the middle.
	#[HttpPost]
	public function play(string $id): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$challenge = $this->findChallenge($id);
		$challengeID = (int) $challenge['id'];
		if (!$this->joinChallenge($challenge))
			return $this->redirect(self::challengeUrl($challengeID));

		$run = ChallengeTable::runOf($challengeID, Auth::getUserID());
		if ($run && $run->time_mode_session_status_id !== TimeModeSessionStatusTable::IN_PROGRESS)
			return $this->redirect(self::challengeUrl($challengeID));
		if (!$run)
			new TimeMode()->startChallenge($challenge);
		return $this->redirect('/timeMode/play');
	}

	//Puts the player into the challenge when they aren't in yet, or declined it before, and there is room.
	//Returns whether the player is in the challenge
	/** @param array<string, mixed> $challenge */
	private function joinChallenge(array $challenge): bool
	{
		$this->Authorization->authorize('Challenge', 'join');
		$challengeID = (int) $challenge['id'];
		$participation = ChallengeTable::participation($challengeID, Auth::getUserID());
		if ($participation !== null && $participation['removed'] !== null)
		{
			CookieFlash::set('The creator removed you from this challenge.', 'error');
			return false;
		}
		if ($participation !== null && $participation['declined'] === null)
			return true;
		if (ChallengeTable::participantCount($challengeID) >= ChallengeTable::MAXIMUM_PARTICIPANTS)
		{
			CookieFlash::set('The challenge is full, ' . ChallengeTable::MAXIMUM_PARTICIPANTS . ' players are in it already.', 'error');
			return false;
		}
		if ($participation !== null)
			ChallengeTable::rejoin($challengeID, Auth::getUserID());
		else
			ChallengeTable::addParticipant($challengeID, Auth::getUserID(), null);
		return true;
	}

	//Adds the players of the names to the challenge, where it shows in their challenges and on the
	//count of the menu, then says what came of it.
	//$names: Display names separated by commas
	private function inviteByNames(int $challengeID, string $names): void
	{
		$names = array_values(array_unique(array_filter(array_map('trim', explode(',', $names)), fn(string $name) => $name !== '')));
		if (empty($names))
			return;

		$invited = [];
		$problems = [];
		$participantCount = ChallengeTable::participantCount($challengeID);
		foreach ($names as $name)
		{
			$player = $this->fetchTable('User')->find()->select(['id'])->where(['display_name' => $name, 'deleted IS' => null])->first();
			if (!$player)
			{
				$problems[] = 'There is no player named ' . $name . '.';
				continue;
			}
			$playerID = $player->id;
			if ($playerID === Auth::getUserID())
				continue;
			if ($participantCount >= ChallengeTable::MAXIMUM_PARTICIPANTS)
			{
				$problems[] = 'The challenge is full, ' . $name . ' can\'t be invited.';
				continue;
			}
			if (!ChallengeTable::addParticipant($challengeID, $playerID, Auth::getUserID()))
			{
				$participation = ChallengeTable::participation($challengeID, $playerID);
				$problems[] = match (true)
				{
					$participation['removed'] !== null => $name . ' was removed from the challenge.',
					$participation['declined'] !== null => $name . ' declined the challenge.',
					default => $name . ' is in the challenge already.',
				};
				continue;
			}
			$participantCount++;
			$invited[] = $name;
		}

		if (!empty($invited))
			CookieFlash::set('Invited ' . implode(', ', $invited) . '. The challenge waits for them in their challenges.'
				. (empty($problems) ? '' : ' ' . implode(' ', $problems)), empty($problems) ? 'success' : 'info');
		elseif (!empty($problems))
			CookieFlash::set(implode(' ', $problems), 'error');
	}

	//The time mode rank of the player's rating, to start the form with, or the easiest one.
	/** @param array<TimeModeRank> $ranks Ordered by id */
	private static function rankOfPlayer(array $ranks): ?int
	{
		if (empty($ranks))
			return null;
		$playerRank = Rating::getReadableRankFromRating((float) Auth::getUser()['rating']);
		foreach ($ranks as $rank)
			if ($rank->name === $playerRank)
				return $rank->id;
		return $ranks[0]->id;
	}

	private static function challengeUrl(int $challengeID): string
	{
		return '/challenges/' . $challengeID;
	}

	/** @return array<string, mixed> As ChallengeTable::withDetails() returns it */
	private function findChallenge(string $id): array
	{
		$challenge = ChallengeTable::withDetails((int) $id);
		if (!$challenge)
			throw new NotFoundException('Challenge not found');
		return $challenge;
	}
}

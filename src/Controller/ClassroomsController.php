<?php

use App\Attribute\HttpPost;
use App\Utility\Auth;
use App\Utility\CookieFlash;

/**
 * Classes: a teacher gathers players into a class, gives them problems to solve by a day and
 * watches who solved what. A player gets into a class when both sides agree: the teacher invites
 * and the player accepts, or the player asks to join and the teacher accepts.
 */
class ClassroomsController extends AppController
{
	/**
	 * The classes the player teaches and the ones they are in, the invitations waiting for their
	 * answer and the requests to join waiting for a teacher's, and the form to start a class.
	 */
	public function index(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$userId = Auth::getUserID();
		$this->set('_page', 'classes');
		$this->set('_title', 'Tsumego Hero - Classes');
		$this->set('taught', Classroom::taughtBy($userId));
		$this->set('memberships', Classroom::ofPupil($userId));
		return null;
	}

	#[HttpPost]
	public function create(): mixed
	{
		$this->Authorization->authorize('Classroom', 'create');

		$name = Classroom::cleanName((string) ($this->request->data['name'] ?? ''));
		if ($name === null)
		{
			CookieFlash::set('A class needs a name.', 'error');
			return $this->redirect('/classes');
		}

		$this->Classroom->create();
		$this->Classroom->save(['name' => $name, 'teacher_id' => Auth::getUserID()]);
		return $this->redirect(self::classUrl((int) $this->Classroom->id));
	}

	/**
	 * The page of a class. The teacher manages it here: the players, the invitations and requests,
	 * the assignments with the table of who solved what. A pupil sees the assignments with their
	 * own progress, an invited player the invitation, and anyone else a way to ask to join, which
	 * is why the teacher sends this link around.
	 */
	public function view(string $id): mixed
	{
		$classroom = $this->findClassroom($id);
		$this->set('_page', 'classes');
		$this->set('_title', 'Tsumego Hero - ' . $classroom['name']);
		$this->set('classroom', $classroom);
		$this->set('teacher', Classroom::teacher($classroom));

		$isTeacher = $this->Authorization->can(['Classroom' => $classroom], 'manage');
		$membership = Auth::isLoggedIn() ? ClassroomMember::of((int) $classroom['id'], Auth::getUserID()) : null;
		$isPupil = $membership !== null && (int) $membership['classroom_member_status_id'] === ClassroomMemberStatus::ACCEPTED;
		$this->set('isTeacher', $isTeacher);
		$this->set('membership', $membership);
		$this->set('assignments', $isTeacher || $isPupil ? ClassroomAssignment::ofClassroom((int) $classroom['id']) : []);

		if ($isTeacher)
		{
			$this->set('members', ClassroomMember::ofClassroom((int) $classroom['id']));
			$this->set('assignableSets', ClassroomAssignment::assignableSets(Auth::getUserID()));
			$this->set('defaultDue', date('Y-m-d', strtotime('+7 days')));
			$this->set('joinUrl', Router::url(self::classUrl((int) $classroom['id']), true));
		}
		return null;
	}

	/**
	 * The teacher invites a player by their name. A player who already asked to join is in the
	 * class at once, since both sides want it.
	 */
	#[HttpPost]
	public function invite(string $id): mixed
	{
		$classroom = $this->findClassroom($id);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');
		$url = self::classUrl((int) $classroom['id']);

		$name = trim((string) ($this->request->data['name'] ?? ''));
		if ($name === '')
		{
			CookieFlash::set('Write the name of the player to invite.', 'error');
			return $this->redirect($url);
		}
		$player = ClassRegistry::init('User')->find('first', [
			'conditions' => ['display_name' => $name, 'deleted' => null],
		]);
		if (!$player)
		{
			CookieFlash::set('There is no player named ' . $name . '.', 'error');
			return $this->redirect($url);
		}

		$playerId = (int) $player['User']['id'];
		$playerName = $player['User']['display_name'];
		if ($playerId === Auth::getUserID())
		{
			CookieFlash::set('You teach this class, you can\'t be its pupil too.', 'error');
			return $this->redirect($url);
		}

		$membership = ClassroomMember::of((int) $classroom['id'], $playerId);
		$statusBefore = $membership === null ? null : (int) $membership['classroom_member_status_id'];
		if ($statusBefore === ClassroomMemberStatus::ACCEPTED)
			CookieFlash::set($playerName . ' is in the class already.', 'info');
		elseif ($statusBefore === ClassroomMemberStatus::INVITED)
			CookieFlash::set($playerName . ' is invited already.', 'info');
		elseif (ClassroomMember::offer((int) $classroom['id'], $playerId, ClassroomMemberStatus::INVITED) === ClassroomMemberStatus::ACCEPTED)
			CookieFlash::set($playerName . ' asked to join before, so they are in the class now.', 'success');
		else
			CookieFlash::set('Invited ' . $playerName . '. They join the class once they accept.', 'success');
		return $this->redirect($url);
	}

	/**
	 * A player asks to join the class. An invited player is in the class at once, since both
	 * sides want it.
	 */
	#[HttpPost]
	public function join(string $id): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$classroom = $this->findClassroom($id);
		$url = self::classUrl((int) $classroom['id']);
		if ((int) $classroom['teacher_id'] === Auth::getUserID())
			return $this->redirect($url);

		$status = ClassroomMember::offer((int) $classroom['id'], Auth::getUserID(), ClassroomMemberStatus::REQUESTED);
		CookieFlash::set($status === ClassroomMemberStatus::ACCEPTED
			? 'You are in the class.'
			: 'You asked to join. You are in once the teacher accepts.', 'success');
		return $this->redirect($url);
	}

	/**
	 * The side that did not start a membership accepts it: the invited player, or the teacher of
	 * a player who asked to join.
	 */
	#[HttpPost]
	public function acceptMember(string $membershipId): mixed
	{
		[$membership, $classroom] = $this->findMembership($membershipId);
		$statusId = (int) $membership['classroom_member_status_id'];
		$isMember = (int) $membership['user_id'] === Auth::getUserID();
		$isTeacher = $this->Authorization->can(['Classroom' => $classroom], 'manage');
		if (!($statusId === ClassroomMemberStatus::INVITED && $isMember)
			&& !($statusId === ClassroomMemberStatus::REQUESTED && $isTeacher))
				throw new ForbiddenException();

		ClassroomMember::accept((int) $membership['id']);
		return $this->redirect(self::classUrl((int) $classroom['id']));
	}

	/**
	 * Ends a membership in whatever state it is: the player declines an invitation, takes back a
	 * request or leaves, the teacher turns down a request, takes back an invitation or removes a
	 * pupil.
	 */
	#[HttpPost]
	public function removeMember(string $membershipId): mixed
	{
		[$membership, $classroom] = $this->findMembership($membershipId);
		$isMember = (int) $membership['user_id'] === Auth::getUserID();
		if (!$isMember)
			$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		ClassroomMember::remove((int) $membership['id']);
		return $this->redirect($isMember ? '/classes' : self::classUrl((int) $classroom['id']));
	}

	/**
	 * The teacher gives the class a collection, or the problems of it with the numbers from
	 * first_number to last_number, to solve by the due day.
	 */
	#[HttpPost]
	public function assign(string $id): mixed
	{
		$classroom = $this->findClassroom($id);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');
		$url = self::classUrl((int) $classroom['id']);

		$set = ClassRegistry::init('Set')->findById((int) ($this->request->data['set_id'] ?? 0));
		if (!$set || !ClassroomAssignment::canAssignSet($set['Set'], Auth::getUserID()))
		{
			CookieFlash::set('Pick one of your collections or a collection of the site.', 'error');
			return $this->redirect($url);
		}

		$due = DateTime::createFromFormat('!Y-m-d', (string) ($this->request->data['due'] ?? ''));
		if ($due === false || $due->format('Y-m-d') !== $this->request->data['due'])
		{
			CookieFlash::set('The due date is not a date.', 'error');
			return $this->redirect($url);
		}

		$firstNumber = self::optionalNumber($this->request->data['first_number'] ?? null);
		$lastNumber = self::optionalNumber($this->request->data['last_number'] ?? null);
		if ($firstNumber === false || $lastNumber === false
			|| ($firstNumber !== null && $lastNumber !== null && $firstNumber > $lastNumber))
		{
			CookieFlash::set('The problem numbers are a range from a smaller number to a bigger one.', 'error');
			return $this->redirect($url);
		}

		$assignment = [
			'classroom_id' => (int) $classroom['id'],
			'set_id' => (int) $set['Set']['id'],
			'first_number' => $firstNumber,
			'last_number' => $lastNumber,
			'due' => $due->format('Y-m-d'),
		];
		if (empty(ClassroomAssignment::problems($assignment)))
		{
			CookieFlash::set('The collection has no problems with those numbers.', 'error');
			return $this->redirect($url);
		}

		ClassRegistry::init('ClassroomAssignment')->create();
		ClassRegistry::init('ClassroomAssignment')->save($assignment);
		CookieFlash::set('Assigned ' . $set['Set']['title'] . '.', 'success');
		return $this->redirect($url);
	}

	#[HttpPost]
	public function deleteAssignment(string $assignmentId): mixed
	{
		$assignment = $this->findAssignment($assignmentId);
		$classroom = $this->findClassroom((string) $assignment['classroom_id']);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		ClassRegistry::init('ClassroomAssignment')->delete((int) $assignment['id']);
		return $this->redirect(self::classUrl((int) $classroom['id']));
	}

	/**
	 * Who solved which problem of an assignment, as JSON for the table that keeps itself up to
	 * date: every pupil for the teacher, only their own row for a pupil.
	 */
	public function progress(string $assignmentId): CakeResponse
	{
		$assignment = $this->findAssignment($assignmentId);
		$classroom = $this->findClassroom((string) $assignment['classroom_id']);
		if ($this->Authorization->can(['Classroom' => $classroom], 'manage'))
			$progress = ClassroomAssignment::progress($assignment);
		elseif (Auth::isLoggedIn() && ClassroomMember::isPupil((int) $classroom['id'], Auth::getUserID()))
			$progress = ClassroomAssignment::progress($assignment, Auth::getUserID());
		else
			throw Auth::isLoggedIn() ? new ForbiddenException() : new UnauthorizedException();

		$this->response->type('json');
		$this->response->body(json_encode($progress));
		return $this->response;
	}

	#[HttpPost]
	public function delete(string $id): mixed
	{
		$classroom = $this->findClassroom($id);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		$this->Classroom->delete((int) $classroom['id']);
		CookieFlash::set('Deleted the class ' . $classroom['name'] . '.', 'success');
		return $this->redirect('/classes');
	}

	public static function classUrl(int $classroomId): string
	{
		return '/classes/' . $classroomId;
	}

	/**
	 * A problem number the form may leave empty: null when empty, false when it is not a number.
	 */
	private static function optionalNumber(mixed $value): int|false|null
	{
		$value = trim((string) $value);
		if ($value === '')
			return null;
		if (!ctype_digit($value) || (int) $value < 1)
			return false;
		return (int) $value;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function findClassroom(string $id): array
	{
		$classroom = $this->Classroom->findById((int) $id);
		if (!$classroom)
			throw new NotFoundException('Class not found');
		return $classroom['Classroom'];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function findAssignment(string $id): array
	{
		$assignment = ClassRegistry::init('ClassroomAssignment')->findById((int) $id);
		if (!$assignment)
			throw new NotFoundException('Assignment not found');
		return $assignment['ClassroomAssignment'];
	}

	/**
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>} The membership and its class
	 */
	private function findMembership(string $id): array
	{
		if (!Auth::isLoggedIn())
			throw new UnauthorizedException();
		$membership = ClassRegistry::init('ClassroomMember')->findById((int) $id);
		if (!$membership)
			throw new NotFoundException('Membership not found');
		$membership = $membership['ClassroomMember'];
		return [$membership, $this->findClassroom((string) $membership['classroom_id'])];
	}
}

<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Lib\Error\UnprocessableEntityException;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\Util;

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
	#[HttpGet]
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
	#[HttpGet]
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
		$this->set('assignments', $isTeacher || $isPupil ? ClassroomAssignment::ofClassroom((int) $classroom['id'], $isTeacher) : []);

		if ($isTeacher)
		{
			$this->set('members', ClassroomMember::ofClassroom((int) $classroom['id']));
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
	 * The teacher starts a new assignment, a draft the pupils don't see until it is published,
	 * and builds it up on its own page.
	 */
	#[HttpPost]
	public function createAssignment(string $id): mixed
	{
		$classroom = $this->findClassroom($id);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		$assignmentModel = ClassRegistry::init('ClassroomAssignment');
		$assignmentModel->create();
		$assignmentModel->save([
			'classroom_id' => (int) $classroom['id'],
			'due' => date('Y-m-d', strtotime('+7 days')),
		]);
		return $this->redirect(self::assignmentUrl((int) $assignmentModel->id));
	}

	/**
	 * The page where the teacher builds an assignment: the picked problems, and the picker that
	 * adds more from the collections (app/classrooms/AssignmentEditor.tsx).
	 */
	#[HttpGet]
	public function assignment(string $assignmentId): mixed
	{
		$assignment = $this->findAssignment($assignmentId);
		$classroom = $this->findClassroom((string) $assignment['classroom_id']);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		$this->set('_page', 'classes');
		$this->set('_title', 'Tsumego Hero - ' . $classroom['name'] . ' - Assignment');
		$this->set('classroom', $classroom);
		$this->set('assignment', $assignment);
		$this->set('problems', ClassroomAssignment::problems($assignment, true));
		return null;
	}

	/**
	 * The collections the teacher can pick problems from, for the picker.
	 */
	#[HttpGet]
	public function collections(): CakeResponse
	{
		$this->Authorization->authorize('Classroom', 'create');
		return $this->json(ClassroomAssignment::collectionCards(Auth::getUserID()));
	}

	/**
	 * The problems of a collection with their previews, for the picker.
	 */
	#[HttpGet]
	public function collectionProblems(string $setId): CakeResponse
	{
		$this->Authorization->authorize('Classroom', 'create');
		$set = ClassRegistry::init('Set')->findById((int) $setId);
		if (!$set)
			throw new NotFoundException('Collection not found');
		if (!ClassroomAssignment::canAssignSet($set['Set'], Auth::getUserID()))
			throw new ForbiddenException();
		return $this->json([
			'collection' => ['id' => (int) $set['Set']['id'], 'title' => (string) $set['Set']['title']],
			'problems' => ClassroomAssignment::collectionProblems((int) $set['Set']['id']),
		]);
	}

	/**
	 * Saves what the teacher built: the title, the due day and the picked problems in their
	 * order. With `publish` a draft goes to the pupils. A published assignment can still change,
	 * but keeps some problems.
	 */
	#[HttpPost]
	public function saveAssignment(string $assignmentId): CakeResponse
	{
		$assignment = $this->findAssignment($assignmentId);
		$classroom = $this->findClassroom((string) $assignment['classroom_id']);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		$input = json_decode((string) $this->request->input(), true);
		if (!is_array($input))
			throw new BadRequestException('Expected JSON.');
		$due = DateTime::createFromFormat('!Y-m-d', (string) ($input['due'] ?? ''));
		if ($due === false || $due->format('Y-m-d') !== ($input['due'] ?? null))
			throw new UnprocessableEntityException('The due date is not a date.');
		$setConnectionIds = $input['setConnectionIds'] ?? null;
		if (!is_array($setConnectionIds) || !array_is_list($setConnectionIds))
			throw new BadRequestException('Expected a list of problems.');
		if (count($setConnectionIds) > ClassroomAssignment::MAXIMUM_PROBLEMS)
			throw new UnprocessableEntityException('An assignment has at most ' . ClassroomAssignment::MAXIMUM_PROBLEMS . ' problems.');
		$publish = !empty($input['publish']) && !ClassroomAssignment::isPublished($assignment);
		if (($publish || ClassroomAssignment::isPublished($assignment)) && empty($setConnectionIds))
			throw new UnprocessableEntityException('Pick the problems to give first.');

		$problemCount = ClassroomAssignment::replaceProblems(
			(int) $assignment['id'],
			array_map('intval', $setConnectionIds),
			Auth::getUserID());

		$published = $publish ? date('Y-m-d H:i:s') : $assignment['published'];
		Util::execute('UPDATE classroom_assignment SET title = ?, due = ?, published = ? WHERE id = ?', [
			ClassroomAssignment::cleanTitle((string) ($input['title'] ?? '')),
			$due->format('Y-m-d'),
			$published,
			(int) $assignment['id'],
		]);
		if ($publish)
			CookieFlash::set('Published. The pupils see the assignment now.', 'success');
		return $this->json([
			'problemCount' => $problemCount,
			'published' => $published === null ? null : Util::toIso8601($published),
			'classUrl' => self::classUrl((int) $classroom['id']),
		]);
	}

	/**
	 * A pupil plays the assignment as one row of problems, numbered from 1 under the board like a
	 * collection of its own, with the title saying where in the assignment they are and which
	 * problem of which collection it is. Without a problem it opens the first one they haven't
	 * solved. The teacher can play it too, to see what the pupils see.
	 */
	#[HttpGet]
	public function play(string $assignmentId, ?string $setConnectionId = null): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$assignment = $this->findAssignment($assignmentId);
		$classroom = $this->findClassroom((string) $assignment['classroom_id']);
		$isTeacher = $this->Authorization->can(['Classroom' => $classroom], 'manage');
		if (!$isTeacher && !ClassroomMember::isPupil((int) $classroom['id'], Auth::getUserID()))
			throw new ForbiddenException();
		if (!$isTeacher && !ClassroomAssignment::isPublished($assignment))
			throw new NotFoundException('Assignment not found');

		$problems = ClassroomAssignment::problems($assignment);
		if (empty($problems))
		{
			CookieFlash::set('The assignment has no problems.', 'info');
			return $this->redirect(self::classUrl((int) $classroom['id']));
		}
		$current = $setConnectionId === null
			? ClassroomAssignment::firstUnsolvedSetConnectionId($assignment, Auth::getUserID())
			: (int) $setConnectionId;
		if (!in_array($current, array_column($problems, 'setConnectionId'), true))
			return $this->redirect(ClassroomAssignment::playLink((int) $assignment['id']));

		// the problems of an assignment are played like the ones of a collection
		if (!Auth::isInLevelMode())
			Auth::saveUserField('mode_id', Constants::$LEVEL_MODE);

		$play = new Play(function ($name, $value) {
			$this->set($name, $value);
		});
		$play->classroomAssignment = ClassroomAssignment::withCollections([$assignment])[0];
		// Same problem, same address for search engines, whichever route got here.
		$this->set('canonicalPath', '/' . $current);
		$play->play($current, $this->request, $this->request->data);
		$this->set('_page', 'classes');
		$this->render('/Tsumegos/play');
		return null;
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
	#[HttpGet]
	public function progress(string $assignmentId): CakeResponse
	{
		$assignment = $this->findAssignment($assignmentId);
		$classroom = $this->findClassroom((string) $assignment['classroom_id']);
		if (!ClassroomAssignment::isPublished($assignment))
			throw new NotFoundException('Assignment not found');
		if ($this->Authorization->can(['Classroom' => $classroom], 'manage'))
			$progress = ClassroomAssignment::progress($assignment);
		elseif (Auth::isLoggedIn() && ClassroomMember::isPupil((int) $classroom['id'], Auth::getUserID()))
			$progress = ClassroomAssignment::progress($assignment, Auth::getUserID());
		else
			throw Auth::isLoggedIn() ? new ForbiddenException() : new UnauthorizedException();

		return $this->json($progress);
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

	private static function classUrl(int $classroomId): string
	{
		return '/classes/' . $classroomId;
	}

	private static function assignmentUrl(int $assignmentId): string
	{
		return '/classes/assignment/' . $assignmentId;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function json(array $data): CakeResponse
	{
		$this->autoRender = false;
		$this->response->type('json');
		$this->response->body(json_encode($data));
		return $this->response;
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

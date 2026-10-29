<?php

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response as CakeResponse;
use Cake\Routing\Router;
use DateTime;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Lib\Error\UnprocessableEntityException;
use App\Model\Entity\Classroom;
use App\Model\Entity\ClassroomMember;
use App\Model\Table\ClassroomAssignmentTable;
use App\Model\Table\ClassroomMemberStatusTable;
use App\Model\Table\ClassroomMemberTable;
use App\Model\Table\ClassroomTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\Play;
use App\Utility\Util;

//Classes: a teacher gathers players into a class, gives them problems to solve by a day and
//watches who solved what. A player gets into a class when both sides agree: the teacher invites
//and the player accepts, or the player asks to join and the teacher accepts.
class ClassroomsController extends AppController
{
	//The classes the player teaches and the ones they are in, the invitations waiting for their
	//answer and the requests to join waiting for a teacher's, and the form to start a class.
	#[HttpGet]
	public function index(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$userId = Auth::getUserID();
		$this->set('_page', 'classes');
		$this->set('_title', 'Tsumego Hero - Classes');
		$this->set('taught', ClassroomTable::taughtBy($userId));
		$this->set('memberships', ClassroomTable::ofPupil($userId));
		return null;
	}

	#[HttpPost]
	public function create(): mixed
	{
		$this->Authorization->authorize('Classroom', 'create');

		$name = ClassroomTable::cleanName((string) ($this->input('name') ?? ''));
		if ($name === null)
		{
			CookieFlash::set('A class needs a name.', 'error');
			return $this->redirect('/classes');
		}

		$classroomTable = $this->fetchTable('Classroom');
		$classroom = $classroomTable->saveOrFail($classroomTable->newEntity(['name' => $name, 'teacher_user_id' => Auth::getUserID()]));
		return $this->redirect(self::classUrl($classroom->id));
	}

	//The page of a class. The teacher manages it here: the players, the invitations and requests,
	//the assignments with the table of who solved what. A pupil sees the assignments with their
	//own progress, an invited player the invitation, and anyone else a way to ask to join, which
	//is why the teacher sends this link around.
	#[HttpGet]
	public function view(string $id): mixed
	{
		$classroom = $this->findClassroom($id);
		$this->set('_page', 'classes');
		$this->set('_title', 'Tsumego Hero - ' . $classroom->name);
		$this->set('classroom', $classroom);
		$this->set('teacher', ClassroomTable::teacher($classroom));

		$isTeacher = $this->Authorization->can(['Classroom' => $classroom], 'manage');
		$membership = Auth::isLoggedIn() ? ClassroomMemberTable::of($classroom->id, Auth::getUserID()) : null;
		$isPupil = $membership !== null && $membership->classroom_member_status_id === ClassroomMemberStatusTable::ACCEPTED;
		$this->set('isTeacher', $isTeacher);
		$this->set('membership', $membership);
		$this->set('assignments', $isTeacher || $isPupil ? ClassroomAssignmentTable::ofClassroom($classroom->id, $isTeacher) : []);

		if ($isTeacher)
		{
			$this->set('members', ClassroomMemberTable::ofClassroom($classroom->id));
			$this->set('joinUrl', Router::url(self::classUrl($classroom->id), true));
		}
		return null;
	}

	//The teacher invites a player by their name. A player who already asked to join is in the
	//class at once, since both sides want it.
	#[HttpPost]
	public function invite(string $id): mixed
	{
		$classroom = $this->findClassroom($id);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');
		$url = self::classUrl($classroom->id);

		$name = trim((string) ($this->input('name') ?? ''));
		if ($name === '')
		{
			CookieFlash::set('Write the name of the player to invite.', 'error');
			return $this->redirect($url);
		}
		$player = $this->fetchTable('User')->find()
			->select(['id', 'display_name'])
			->where(['display_name' => $name, 'deleted IS' => null])
			->first();
		if (!$player)
		{
			CookieFlash::set('There is no player named ' . $name . '.', 'error');
			return $this->redirect($url);
		}

		$playerId = $player->id;
		$playerName = $player->display_name;
		if ($playerId === Auth::getUserID())
		{
			CookieFlash::set('You teach this class, you can\'t be its pupil too.', 'error');
			return $this->redirect($url);
		}

		$membership = ClassroomMemberTable::of($classroom->id, $playerId);
		$statusBefore = $membership?->classroom_member_status_id;
		if ($statusBefore === ClassroomMemberStatusTable::ACCEPTED)
			CookieFlash::set($playerName . ' is in the class already.', 'info');
		elseif ($statusBefore === ClassroomMemberStatusTable::INVITED)
			CookieFlash::set($playerName . ' is invited already.', 'info');
		elseif (ClassroomMemberTable::offer($classroom->id, $playerId, ClassroomMemberStatusTable::INVITED) === ClassroomMemberStatusTable::ACCEPTED)
			CookieFlash::set($playerName . ' asked to join before, so they are in the class now.', 'success');
		else
			CookieFlash::set('Invited ' . $playerName . '. They join the class once they accept.', 'success');
		return $this->redirect($url);
	}

	//A player asks to join the class. An invited player is in the class at once, since both sides want it.
	#[HttpPost]
	public function join(string $id): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$classroom = $this->findClassroom($id);
		$url = self::classUrl($classroom->id);
		if ($classroom->teacher_user_id === Auth::getUserID())
			return $this->redirect($url);

		$status = ClassroomMemberTable::offer($classroom->id, Auth::getUserID(), ClassroomMemberStatusTable::REQUESTED);
		CookieFlash::set($status === ClassroomMemberStatusTable::ACCEPTED
			? 'You are in the class.'
			: 'You asked to join. You are in once the teacher accepts.', 'success');
		return $this->redirect($url);
	}

	//The side that did not start a membership accepts it: the invited player, or the teacher of
	//a player who asked to join.
	#[HttpPost]
	public function acceptMember(string $membershipId): mixed
	{
		[$membership, $classroom] = $this->findMembership($membershipId);
		$statusId = $membership->classroom_member_status_id;
		$isMember = $membership->user_id === Auth::getUserID();
		$isTeacher = $this->Authorization->can(['Classroom' => $classroom], 'manage');
		if (!($statusId === ClassroomMemberStatusTable::INVITED && $isMember)
			&& !($statusId === ClassroomMemberStatusTable::REQUESTED && $isTeacher))
				throw new ForbiddenException();

		ClassroomMemberTable::accept($membership->id);
		return $this->redirect(self::classUrl($classroom->id));
	}

	//Ends a membership in whatever state it is: the player declines an invitation, takes back a
	//request or leaves, the teacher turns down a request, takes back an invitation or removes a
	//pupil.
	#[HttpPost]
	public function removeMember(string $membershipId): mixed
	{
		[$membership, $classroom] = $this->findMembership($membershipId);
		$isMember = $membership->user_id === Auth::getUserID();
		if (!$isMember)
			$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		ClassroomMemberTable::remove($membership->id);
		return $this->redirect($isMember ? '/classes' : self::classUrl($classroom->id));
	}

	//The teacher starts a new assignment, a draft the pupils don't see until it is published,
	//and builds it up on its own page.
	#[HttpPost]
	public function createAssignment(string $id): mixed
	{
		$classroom = $this->findClassroom($id);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		$assignmentTable = $this->fetchTable('ClassroomAssignment');
		$assignment = $assignmentTable->saveOrFail($assignmentTable->newEntity([
			'classroom_id' => $classroom->id,
			'due' => date('Y-m-d', strtotime('+7 days')),
		]));
		return $this->redirect(self::assignmentUrl($assignment->id));
	}

	//The page where the teacher builds an assignment: the picked problems, and the picker that
	//adds more from the collections (app/classrooms/AssignmentEditor.tsx).
	#[HttpGet]
	public function assignment(string $assignmentId): mixed
	{
		$assignment = $this->findAssignment($assignmentId);
		$classroom = $this->findClassroom((string) $assignment['classroom_id']);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		$this->set('_page', 'classes');
		$this->set('_title', 'Tsumego Hero - ' . $classroom->name . ' - Assignment');
		$this->set('classroom', $classroom);
		$this->set('assignment', $assignment);
		$this->set('problems', ClassroomAssignmentTable::problems($assignment, true));
		return null;
	}

	//The collections the teacher can pick problems from, for the picker.
	#[HttpGet]
	public function collections(): CakeResponse
	{
		$this->Authorization->authorize('Classroom', 'create');
		return $this->respondJson(ClassroomAssignmentTable::collectionCards(Auth::getUserID()));
	}

	//The problems of a collection with their previews, for the picker.
	#[HttpGet]
	public function collectionProblems(string $setId): CakeResponse
	{
		$this->Authorization->authorize('Classroom', 'create');
		$set = $this->fetchTable('Set')->find()->where(['id' => (int) $setId])->first();
		if (!$set)
			throw new NotFoundException('Collection not found');
		if (!ClassroomAssignmentTable::canAssignSet($set, Auth::getUserID()))
			throw new ForbiddenException();
		return $this->respondJson([
			'collection' => ['id' => (int) $set->id, 'title' => (string) $set->title],
			'problems' => ClassroomAssignmentTable::collectionProblems((int) $set->id),
		]);
	}

	//Saves what the teacher built: the title, the due day and the picked problems in their
	//order. With `publish` a draft goes to the pupils. A published assignment can still change,
	//but keeps some problems.
	#[HttpPost]
	public function saveAssignment(string $assignmentId): CakeResponse
	{
		$assignment = $this->findAssignment($assignmentId);
		$classroom = $this->findClassroom((string) $assignment['classroom_id']);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		$input = json_decode($this->requestBody(), true);
		if (!is_array($input))
			throw new BadRequestException('Expected JSON.');
		$due = DateTime::createFromFormat('!Y-m-d', (string) ($input['due'] ?? ''));
		if ($due === false || $due->format('Y-m-d') !== ($input['due'] ?? null))
			throw new UnprocessableEntityException('The due date is not a date.');
		$setConnectionIds = $input['setConnectionIds'] ?? null;
		if (!is_array($setConnectionIds) || !array_is_list($setConnectionIds))
			throw new BadRequestException('Expected a list of problems.');
		if (count($setConnectionIds) > ClassroomAssignmentTable::MAXIMUM_PROBLEMS)
			throw new UnprocessableEntityException('An assignment has at most ' . ClassroomAssignmentTable::MAXIMUM_PROBLEMS . ' problems.');
		$publish = !empty($input['publish']) && !ClassroomAssignmentTable::isPublished($assignment);
		if (($publish || ClassroomAssignmentTable::isPublished($assignment)) && empty($setConnectionIds))
			throw new UnprocessableEntityException('Pick the problems to give first.');

		$problemCount = ClassroomAssignmentTable::replaceProblems(
			(int) $assignment['id'],
			array_map('intval', $setConnectionIds),
			Auth::getUserID());

		$published = $publish ? date('Y-m-d H:i:s') : $assignment['published'];
		Util::execute('UPDATE classroom_assignment SET title = ?, due = ?, published = ? WHERE id = ?', [
			ClassroomAssignmentTable::cleanTitle((string) ($input['title'] ?? '')),
			$due->format('Y-m-d'),
			$published,
			(int) $assignment['id'],
		]);
		if ($publish)
			CookieFlash::set('Published. The pupils see the assignment now.', 'success');
		return $this->respondJson([
			'problemCount' => $problemCount,
			'published' => $published === null ? null : Util::toIso8601($published),
			'classUrl' => self::classUrl($classroom->id),
		]);
	}

	//A pupil plays the assignment as one row of problems, numbered from 1 under the board like a
	//collection of its own, with the title saying where in the assignment they are and which
	//problem of which collection it is. Without a problem it opens the first one they haven't
	//solved. The teacher can play it too, to see what the pupils see.
	#[HttpGet]
	public function play(string $assignmentId, ?string $setConnectionId = null): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$assignment = $this->findAssignment($assignmentId);
		$classroom = $this->findClassroom((string) $assignment['classroom_id']);
		$isTeacher = $this->Authorization->can(['Classroom' => $classroom], 'manage');
		if (!$isTeacher && !ClassroomMemberTable::isPupil($classroom->id, Auth::getUserID()))
			throw new ForbiddenException();
		if (!$isTeacher && !ClassroomAssignmentTable::isPublished($assignment))
			throw new NotFoundException('Assignment not found');

		$problems = ClassroomAssignmentTable::problems($assignment);
		if (empty($problems))
		{
			CookieFlash::set('The assignment has no problems.', 'info');
			return $this->redirect(self::classUrl($classroom->id));
		}
		$current = $setConnectionId === null
			? ClassroomAssignmentTable::firstUnsolvedSetConnectionId($assignment, Auth::getUserID())
			: (int) $setConnectionId;
		if (!in_array($current, array_column($problems, 'setConnectionId'), true))
			return $this->redirect(ClassroomAssignmentTable::playLink((int) $assignment['id']));

		// the problems of an assignment are played like the ones of a collection
		if (!Auth::isInLevelMode())
			Auth::saveUserField('mode_id', Constants::$LEVEL_MODE);

		$play = new Play(function ($name, $value) {
			$this->set($name, $value);
		});
		$play->classroomAssignment = ClassroomAssignmentTable::withCollections([$assignment])[0];
		// Same problem, same address for search engines, whichever route got here.
		$this->set('canonicalPath', '/' . $current);
		$play->play($current, $this->request);
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

		$this->fetchTable('ClassroomAssignment')->deleteAll(['id' => (int) $assignment['id']]);
		return $this->redirect(self::classUrl($classroom->id));
	}

	//Who solved which problem of an assignment, as JSON for the table that keeps itself up to
	//date: every pupil for the teacher, only their own row for a pupil.
	#[HttpGet]
	public function progress(string $assignmentId): CakeResponse
	{
		$assignment = $this->findAssignment($assignmentId);
		$classroom = $this->findClassroom((string) $assignment['classroom_id']);
		if (!ClassroomAssignmentTable::isPublished($assignment))
			throw new NotFoundException('Assignment not found');
		if ($this->Authorization->can(['Classroom' => $classroom], 'manage'))
			$progress = ClassroomAssignmentTable::progress($assignment);
		elseif (Auth::isLoggedIn() && ClassroomMemberTable::isPupil($classroom->id, Auth::getUserID()))
			$progress = ClassroomAssignmentTable::progress($assignment, Auth::getUserID());
		else
			throw Auth::isLoggedIn() ? new ForbiddenException() : new UnauthorizedException();

		return $this->respondJson($progress);
	}

	#[HttpPost]
	public function delete(string $id): mixed
	{
		$classroom = $this->findClassroom($id);
		$this->Authorization->authorize(['Classroom' => $classroom], 'manage');

		$this->fetchTable('Classroom')->deleteAll(['id' => $classroom->id]);
		CookieFlash::set('Deleted the class ' . $classroom->name . '.', 'success');
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

	private function findClassroom(string $id): Classroom
	{
		$classroom = $this->fetchTable('Classroom')->find()->where(['id' => (int) $id])->first();
		if ($classroom === null)
			throw new NotFoundException('Class not found');
		return $classroom;
	}

	//The assignment as the plain row the helpers of ClassroomAssignmentTable read, like the rows of ofClassroom().
	/** @return array<string, mixed> */
	private function findAssignment(string $id): array
	{
		$assignment = $this->fetchTable('ClassroomAssignment')->find()->where(['id' => (int) $id])
			->disableHydration()->disableResultsCasting()->first();
		if (!$assignment)
			throw new NotFoundException('Assignment not found');
		return $assignment;
	}

	/** @return array{0: ClassroomMember, 1: Classroom} The membership and its class */
	private function findMembership(string $id): array
	{
		if (!Auth::isLoggedIn())
			throw new UnauthorizedException();
		$membership = $this->fetchTable('ClassroomMember')->find()->where(['id' => (int) $id])->first();
		if ($membership === null)
			throw new NotFoundException('Membership not found');
		return [$membership, $this->findClassroom((string) $membership->classroom_id)];
	}
}

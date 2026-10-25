<?php

use App\Utility\Util;

/**
 * Classes of a teacher: getting into one takes both sides, the teacher gives the class problems to
 * solve by a day and sees who solved which.
 */
class ClassroomsControllerTest extends TestCaseWithAuth
{
	private ContextPreparator $context;

	/**
	 * A teacher with a private collection of three problems, two players who can become pupils and
	 * one who stays out.
	 */
	private function prepare(): void
	{
		$homework = fn(int $num) => ['sets' => [['name' => 'Homework', 'num' => $num, 'public' => 0, 'user_id' => 'self']]];
		$this->context = new ContextPreparator([
			'user' => ['name' => 'teacher'],
			'other-users' => [['name' => 'pupil'], ['name' => 'pupil2'], ['name' => 'outsider']],
			'tsumegos' => [$homework(1), $homework(2), $homework(3)],
		]);
	}

	private function userId(string $name): int
	{
		return (int) ContextPreparator::getUserIdFromName($name);
	}

	private function createClassroom(string $name = 'Lukas, Thursday'): int
	{
		Util::execute('INSERT INTO classroom (name, teacher_id) VALUES (?, ?)', [$name, $this->userId('teacher')]);
		return (int) Util::connection()->lastInsertId();
	}

	private function addMember(int $classroomId, string $name, int $statusId = ClassroomMemberStatus::ACCEPTED): int
	{
		Util::execute('INSERT INTO classroom_member (classroom_id, user_id, classroom_member_status_id) VALUES (?, ?, ?)',
			[$classroomId, $this->userId($name), $statusId]);
		return (int) Util::connection()->lastInsertId();
	}

	private function homeworkSetId(): int
	{
		return (int) $this->context->tsumegos[0]['sets'][0]['id'];
	}

	private function createAssignment(int $classroomId, string $created, string $due, ?int $firstNumber = null, ?int $lastNumber = null): int
	{
		Util::execute('INSERT INTO classroom_assignment (classroom_id, set_id, first_number, last_number, due, created) VALUES (?, ?, ?, ?, ?, ?)',
			[$classroomId, $this->homeworkSetId(), $firstNumber, $lastNumber, $due, $created]);
		return (int) Util::connection()->lastInsertId();
	}

	private function addAttempt(string $name, int $tsumegoIndex, bool $solved, int $misplays, string $created): void
	{
		Util::execute('INSERT INTO tsumego_attempt (user_id, tsumego_id, gain, solved, seconds, misplays, tsumego_rating, created) VALUES (?, ?, 0, ?, 10, ?, 1000, ?)',
			[$this->userId($name), $this->context->tsumegos[$tsumegoIndex]['id'], $solved ? 1 : 0, $misplays, $created]);
	}

	private function membershipStatus(int $classroomId, string $name): ?int
	{
		$membership = ClassroomMember::of($classroomId, $this->userId($name));
		return $membership === null ? null : (int) $membership['classroom_member_status_id'];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function progress(int $assignmentId): array
	{
		$this->testAction('/classes/progress/' . $assignmentId, ['method' => 'get']);
		return json_decode($this->controller->response->body(), true);
	}

	public function testCreatingAClassMakesThePlayerItsTeacher(): void
	{
		$this->prepare();

		$this->testAction('/classes/create', ['method' => 'post', 'data' => ['name' => '  Lukas,   Thursday ']]);

		$classroom = ClassRegistry::init('Classroom')->find('first')['Classroom'];
		$this->assertSame('Lukas, Thursday', $classroom['name']);
		$this->assertSame($this->userId('teacher'), (int) $classroom['teacher_id']);
		$this->assertStringEndsWith('/classes/' . $classroom['id'], $this->headers['Location']);
	}

	public function testAClassNeedsAName(): void
	{
		$this->prepare();

		$this->testAction('/classes/create', ['method' => 'post', 'data' => ['name' => '   ']]);

		$this->assertSame(0, ClassRegistry::init('Classroom')->find('count'));
	}

	public function testCreatingAClassNeedsAnAccount(): void
	{
		new ContextPreparator(['user' => null]);
		$this->expectException(UnauthorizedException::class);

		$this->testAction('/classes/create', ['method' => 'post', 'data' => ['name' => 'Sneaky']]);
	}

	public function testAnInvitedPlayerIsInTheClassOnceTheyAccept(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();

		$this->testAction('/classes/invite/' . $classroomId, ['method' => 'post', 'data' => ['name' => 'DN_pupil']]);
		$this->assertSame(ClassroomMemberStatus::INVITED, $this->membershipStatus($classroomId, 'pupil'));

		$this->login('pupil');
		$page = $this->testAction('/classes', ['method' => 'get', 'return' => 'view']);
		$this->assertTextContains('invites you to', $page);

		$membership = ClassroomMember::of($classroomId, $this->userId('pupil'));
		$this->testAction('/classes/accept-member/' . $membership['id'], ['method' => 'post']);
		$this->assertSame(ClassroomMemberStatus::ACCEPTED, $this->membershipStatus($classroomId, 'pupil'));
	}

	public function testTheTeacherCannotAcceptTheirOwnInvitation(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$membershipId = $this->addMember($classroomId, 'pupil', ClassroomMemberStatus::INVITED);

		$this->expectException(ForbiddenException::class);
		$this->testAction('/classes/accept-member/' . $membershipId, ['method' => 'post']);
	}

	public function testAPlayerWhoAskedToJoinIsInOnceTheTeacherAccepts(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();

		$this->login('pupil');
		$page = $this->testAction('/classes/' . $classroomId, ['method' => 'get', 'return' => 'view']);
		$this->assertTextContains('Ask to join', $page);
		$this->testAction('/classes/join/' . $classroomId, ['method' => 'post']);
		$this->assertSame(ClassroomMemberStatus::REQUESTED, $this->membershipStatus($classroomId, 'pupil'));

		$this->login('teacher');
		$page = $this->testAction('/classes/' . $classroomId, ['method' => 'get', 'return' => 'view']);
		$this->assertTextContains('asks to join', $page);
		$membership = ClassroomMember::of($classroomId, $this->userId('pupil'));
		$this->testAction('/classes/accept-member/' . $membership['id'], ['method' => 'post']);
		$this->assertSame(ClassroomMemberStatus::ACCEPTED, $this->membershipStatus($classroomId, 'pupil'));
	}

	public function testAPlayerCannotAcceptTheirOwnRequest(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$membershipId = $this->addMember($classroomId, 'pupil', ClassroomMemberStatus::REQUESTED);
		$this->login('pupil');

		$this->expectException(ForbiddenException::class);
		$this->testAction('/classes/accept-member/' . $membershipId, ['method' => 'post']);
	}

	public function testInvitingAPlayerWhoAskedToJoinPutsThemInTheClass(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil', ClassroomMemberStatus::REQUESTED);

		$this->testAction('/classes/invite/' . $classroomId, ['method' => 'post', 'data' => ['name' => 'DN_pupil']]);

		$this->assertSame(ClassroomMemberStatus::ACCEPTED, $this->membershipStatus($classroomId, 'pupil'));
	}

	public function testAskingToJoinWhenInvitedPutsThePlayerInTheClass(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil', ClassroomMemberStatus::INVITED);
		$this->login('pupil');

		$this->testAction('/classes/join/' . $classroomId, ['method' => 'post']);

		$this->assertSame(ClassroomMemberStatus::ACCEPTED, $this->membershipStatus($classroomId, 'pupil'));
	}

	public function testInvitingAnUnknownNameInvitesNobody(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();

		$this->testAction('/classes/invite/' . $classroomId, ['method' => 'post', 'data' => ['name' => 'Nobody']]);

		$this->assertSame(0, ClassRegistry::init('ClassroomMember')->find('count'));
	}

	public function testOnlyTheTeacherInvites(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->login('outsider');

		$this->expectException(ForbiddenException::class);
		$this->testAction('/classes/invite/' . $classroomId, ['method' => 'post', 'data' => ['name' => 'DN_pupil']]);
	}

	public function testAPupilLeavesAndTheTeacherRemoves(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$leavingId = $this->addMember($classroomId, 'pupil');
		$removedId = $this->addMember($classroomId, 'pupil2');

		$this->testAction('/classes/remove-member/' . $removedId, ['method' => 'post']);
		$this->login('pupil');
		$this->testAction('/classes/remove-member/' . $leavingId, ['method' => 'post']);

		$this->assertNull($this->membershipStatus($classroomId, 'pupil'));
		$this->assertNull($this->membershipStatus($classroomId, 'pupil2'));
	}

	public function testAnotherPlayerCannotRemoveAPupil(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$membershipId = $this->addMember($classroomId, 'pupil');
		$this->login('outsider');

		$this->expectException(ForbiddenException::class);
		$this->testAction('/classes/remove-member/' . $membershipId, ['method' => 'post']);
	}

	public function testTheTeacherAssignsAPartOfACollection(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();

		$this->testAction('/classes/assign/' . $classroomId, ['method' => 'post', 'data' => [
			'set_id' => $this->homeworkSetId(),
			'first_number' => '2',
			'last_number' => '',
			'due' => '2026-11-05',
		]]);

		$assignment = ClassRegistry::init('ClassroomAssignment')->find('first')['ClassroomAssignment'];
		$this->assertSame($this->homeworkSetId(), (int) $assignment['set_id']);
		$this->assertSame(2, (int) $assignment['first_number']);
		$this->assertNull($assignment['last_number']);
		$this->assertSame('2026-11-05', $assignment['due']);
		$this->assertCount(2, ClassroomAssignment::problems($assignment));
	}

	public function testATeacherCannotAssignAPrivateCollectionOfAnotherPlayer(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		Util::execute('UPDATE `set` SET user_id = ? WHERE id = ?', [$this->userId('outsider'), $this->homeworkSetId()]);

		$this->testAction('/classes/assign/' . $classroomId, ['method' => 'post', 'data' => [
			'set_id' => $this->homeworkSetId(),
			'due' => '2026-11-05',
		]]);

		$this->assertSame(0, ClassRegistry::init('ClassroomAssignment')->find('count'));
	}

	public function testAnAssignmentNeedsADueDateAndProblems(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();

		foreach ([
			['due' => 'next week'],
			['due' => '2026-11-05', 'first_number' => '5'],
			['due' => '2026-11-05', 'first_number' => '3', 'last_number' => '2'],
		] as $data)
			$this->testAction('/classes/assign/' . $classroomId, ['method' => 'post', 'data' => ['set_id' => $this->homeworkSetId()] + $data]);

		$this->assertSame(0, ClassRegistry::init('ClassroomAssignment')->find('count'));
	}

	public function testTheTeacherSeesWhoSolvedWhich(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$this->addMember($classroomId, 'pupil2');
		$this->addMember($classroomId, 'outsider', ClassroomMemberStatus::INVITED);
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->addAttempt('pupil', 0, true, 1, '2026-10-02 10:00:00');
		$this->addAttempt('pupil', 1, true, 0, '2026-10-09 10:00:00');
		$this->addAttempt('pupil', 2, true, 0, '2026-09-01 10:00:00');
		Util::execute("INSERT INTO tsumego_status (user_id, tsumego_id, status) VALUES (?, ?, 'S')",
			[$this->userId('pupil'), $this->context->tsumegos[2]['id']]);
		$this->addAttempt('pupil2', 0, false, 2, '2026-10-03 10:00:00');
		$this->addAttempt('outsider', 0, true, 0, '2026-10-03 10:00:00');

		$progress = $this->progress($assignmentId);

		$this->assertSame([1, 2, 3], array_column($progress['problems'], 'number'));
		$this->assertSame([$this->userId('pupil'), $this->userId('pupil2')], array_column($progress['pupils'], 'userId'));
		[$pupil, $pupil2] = $progress['pupils'];
		$this->assertSame(['solved', 'late', 'earlier'], array_column($pupil['cells'], 'state'));
		$this->assertSame(1, $pupil['cells'][0]['misplays']);
		$this->assertSame(2, $pupil['solvedCount']);
		$this->assertSame(['state' => 'tried', 'misplays' => 2], $pupil2['cells'][0]);
		$this->assertNull($pupil2['cells'][1]);
		$this->assertSame(0, $pupil2['solvedCount']);
	}

	public function testTheProgressCoversOnlyTheAssignedNumbers(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08', 2, 2);
		$this->addAttempt('pupil', 0, true, 0, '2026-10-02 10:00:00');

		$progress = $this->progress($assignmentId);

		$this->assertSame([2], array_column($progress['problems'], 'number'));
		$this->assertSame([null], $progress['pupils'][0]['cells']);
	}

	public function testAPupilSeesOnlyTheirOwnProgress(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$this->addMember($classroomId, 'pupil2');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->login('pupil2');

		$progress = $this->progress($assignmentId);

		$this->assertSame([$this->userId('pupil2')], array_column($progress['pupils'], 'userId'));
	}

	public function testAPlayerOutsideTheClassSeesNoProgress(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'outsider', ClassroomMemberStatus::REQUESTED);
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->login('outsider');

		$this->expectException(ForbiddenException::class);
		$this->progress($assignmentId);
	}

	public function testAPupilOpensThePrivateCollectionGivenToTheClass(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$set = ['id' => $this->homeworkSetId(), 'public' => 0, 'user_id' => $this->userId('teacher')];

		$this->assertTrue(App\Policy\SetPolicy::canView(['id' => $this->userId('pupil')], $set));
		$this->assertFalse(App\Policy\SetPolicy::canView(['id' => $this->userId('outsider')], $set));

		$this->login('pupil');
		$page = $this->testAction('/sets/' . $this->homeworkSetId(), ['method' => 'get', 'return' => 'view']);
		$this->assertTextContains('Homework', $page);
	}

	public function testThePupilPageShowsTheAssignments(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->login('pupil');

		$page = $this->testAction('/classes/' . $classroomId, ['method' => 'get', 'return' => 'view']);

		$this->assertTextContains('data-assignment-id="' . $assignmentId . '"', $page);
		$this->assertTextContains('Leave the class', $page);
		$this->assertTextNotContains('Invite a player', $page);
	}

	public function testAPlayerOutsideTheClassSeesNoAssignments(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->login('outsider');

		$page = $this->testAction('/classes/' . $classroomId, ['method' => 'get', 'return' => 'view']);

		$this->assertTextNotContains('data-assignment-id="' . $assignmentId . '"', $page);
		$this->assertTextContains('Ask to join', $page);
	}

	public function testTheMenuCountsWhatWaitsForAnAnswer(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$otherClassroomId = $this->createClassroom('Friday');
		$this->addMember($classroomId, 'pupil', ClassroomMemberStatus::INVITED);
		$this->addMember($otherClassroomId, 'pupil2', ClassroomMemberStatus::REQUESTED);
		$this->addMember($classroomId, 'pupil2', ClassroomMemberStatus::REQUESTED);

		$this->assertSame(2, ClassroomMember::countAwaitingAnswer($this->userId('teacher')));
		$this->assertSame(1, ClassroomMember::countAwaitingAnswer($this->userId('pupil')));
		$this->assertSame(0, ClassroomMember::countAwaitingAnswer($this->userId('pupil2')));

		$this->login('pupil');
		$page = $this->testAction('/classes', ['method' => 'get', 'return' => 'contents']);
		$this->assertMatchesRegularExpression('#href="/classes">Classes<span class="nav__new-badge nav__new-badge--visible"[^>]*>1</span>#', $page);
	}

	/**
	 * The teacher keeps the page open while the class plays, and a solve shows up without a reload.
	 */
	public function testTheTableShowsASolveWhileThePageIsOpen(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$this->createAssignment($classroomId, date('Y-m-d H:i:s', strtotime('-1 hour')), date('Y-m-d', strtotime('+7 days')));
		$browser = Browser::instance();

		$browser->get('/classes/' . $classroomId);
		$browser->waitUntilCssSelectorExistsWithText('.classes__solved-count', '0/3');
		$this->assertCount(0, $browser->getElements('.classes__table .classes__cell--solved'));

		$this->addAttempt('pupil', 1, true, 0, date('Y-m-d H:i:s'));

		$browser->waitUntilCssSelectorExistsWithText('.classes__solved-count', '1/3', 30);
		$this->assertCount(1, $browser->getElements('.classes__table .classes__cell--solved'));
	}

	public function testDeletingTheClassTakesItsMembersAndAssignments(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');

		$this->testAction('/classes/delete/' . $classroomId, ['method' => 'post']);

		$this->assertSame(0, ClassRegistry::init('Classroom')->find('count'));
		$this->assertSame(0, ClassRegistry::init('ClassroomMember')->find('count'));
		$this->assertSame(0, ClassRegistry::init('ClassroomAssignment')->find('count'));
	}

	public function testOnlyTheTeacherDeletesTheClass(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$this->login('pupil');

		$this->expectException(ForbiddenException::class);
		$this->testAction('/classes/delete/' . $classroomId, ['method' => 'post']);
	}
}

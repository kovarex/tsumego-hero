<?php

use App\Lib\Error\UnprocessableEntityException;
use App\Utility\Util;

/**
 * Classes of a teacher: getting into one takes both sides, the teacher gives the class problems to
 * solve by a day and sees who solved which.
 */
class ClassroomsControllerTest extends TestCaseWithAuth
{
	private ContextPreparator $context;

	/**
	 * A teacher with a private collection of three problems (0-2) and a second one of one problem
	 * (4), a collection of the site with two problems (3, and 0 again as its number 2), two players
	 * who can become pupils and one who stays out.
	 */
	private function prepare(): void
	{
		$homework = fn(int $num) => ['name' => 'Homework', 'num' => $num, 'public' => 0, 'user_id' => 'self'];
		$site = fn(int $num) => ['name' => 'Site basics', 'num' => $num];
		$this->context = new ContextPreparator([
			'user' => ['name' => 'teacher'],
			'other-users' => [['name' => 'pupil'], ['name' => 'pupil2'], ['name' => 'outsider']],
			'tsumegos' => [
				['sets' => [$homework(1), $site(2)]],
				['sets' => [$homework(2)]],
				['sets' => [$homework(3)]],
				['sets' => [$site(1)]],
				['sets' => [['name' => 'Secret', 'num' => 1, 'public' => 0, 'user_id' => 'self']]],
			],
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

	/**
	 * The problem of the tsumego with the index in the collection with the index among its own.
	 */
	private function setConnectionId(int $tsumegoIndex, int $setIndex = 0): int
	{
		return (int) $this->context->tsumegos[$tsumegoIndex]['set-connections'][$setIndex]['id'];
	}

	/**
	 * @param string|null $published Null for a draft
	 * @param list<int> $tsumegoIndexes The problems, from the first collection of each tsumego
	 */
	private function createAssignment(int $classroomId, ?string $published, string $due, array $tsumegoIndexes = [0, 1, 2]): int
	{
		Util::execute('INSERT INTO classroom_assignment (classroom_id, due, published, created) VALUES (?, ?, ?, ?)',
			[$classroomId, $due, $published, $published ?? date('Y-m-d H:i:s')]);
		$assignmentId = (int) Util::connection()->lastInsertId();
		foreach ($tsumegoIndexes as $position => $tsumegoIndex)
			Util::execute('INSERT INTO classroom_assignment_problem (classroom_assignment_id, set_connection_id, position) VALUES (?, ?, ?)',
				[$assignmentId, $this->setConnectionId($tsumegoIndex), $position + 1]);
		return $assignmentId;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function assignment(int $assignmentId): array
	{
		return Util::query('SELECT * FROM classroom_assignment WHERE id = ?', [$assignmentId])[0];
	}

	/**
	 * @return list<int> The set connections of the problems of the assignment, in their order
	 */
	private function assignedSetConnectionIds(int $assignmentId): array
	{
		return array_map('intval', array_column(Util::query(
			'SELECT set_connection_id FROM classroom_assignment_problem WHERE classroom_assignment_id = ? ORDER BY position',
			[$assignmentId]), 'set_connection_id'));
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function saveAssignment(int $assignmentId, array $data): array
	{
		$this->testAction('/classes/save-assignment/' . $assignmentId, [
			'method' => 'post',
			'data' => json_encode($data + ['title' => '', 'due' => '2026-11-05', 'setConnectionIds' => []]),
			'return' => 'contents',
		]);
		return json_decode($this->controller->response->body(), true);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getJson(string $url): array
	{
		$this->testAction($url, ['method' => 'get', 'return' => 'contents']);
		return json_decode($this->controller->response->body(), true);
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

	private function assertForbidden(callable $request): void
	{
		try
		{
			$request();
		}
		catch (ForbiddenException)
		{
			return;
		}
		$this->fail('The request was not forbidden');
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

	public function testANewAssignmentIsADraftThePupilsDontSee(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');

		$this->testAction('/classes/create-assignment/' . $classroomId, ['method' => 'post']);

		$assignment = Util::query('SELECT * FROM classroom_assignment')[0];
		$this->assertStringEndsWith('/classes/assignment/' . $assignment['id'], $this->headers['Location']);
		$this->assertNull($assignment['published']);
		$this->assertSame(date('Y-m-d', strtotime('+7 days')), $assignment['due']);
		$page = $this->testAction('/classes/' . $classroomId, ['method' => 'get', 'return' => 'view']);
		$this->assertTextContains('data-assignment-id="' . $assignment['id'] . '"', $page);
		$this->assertTextContains('classes__badge">draft', $page);

		$this->login('pupil');
		$page = $this->testAction('/classes/' . $classroomId, ['method' => 'get', 'return' => 'view']);
		$this->assertTextNotContains('data-assignment-id="' . $assignment['id'] . '"', $page);
	}

	public function testTheTeacherBuildsAnAssignmentFromSeveralCollections(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$assignmentId = $this->createAssignment($classroomId, null, '2026-11-01', []);

		$saved = $this->saveAssignment($assignmentId, [
			'title' => '  Thursday   homework ',
			'due' => '2026-11-05',
			'setConnectionIds' => [$this->setConnectionId(1), $this->setConnectionId(3), $this->setConnectionId(0)],
		]);

		$this->assertSame(3, $saved['problemCount']);
		$this->assertNull($saved['published']);
		$this->assertSame([$this->setConnectionId(1), $this->setConnectionId(3), $this->setConnectionId(0)], $this->assignedSetConnectionIds($assignmentId));
		$assignment = $this->assignment($assignmentId);
		$this->assertSame('Thursday homework', $assignment['title']);
		$this->assertSame('2026-11-05', $assignment['due']);
		$this->assertNull($assignment['published']);

		$this->saveAssignment($assignmentId, ['setConnectionIds' => [$this->setConnectionId(3)]]);
		$this->assertSame([$this->setConnectionId(3)], $this->assignedSetConnectionIds($assignmentId));
		$this->assertNull($this->assignment($assignmentId)['title']);
	}

	public function testAProblemInTwoCollectionsIsPickedOnce(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$assignmentId = $this->createAssignment($classroomId, null, '2026-11-01', []);

		$saved = $this->saveAssignment($assignmentId, ['setConnectionIds' => [$this->setConnectionId(0, 1), $this->setConnectionId(0)]]);

		$this->assertSame(1, $saved['problemCount']);
		$this->assertSame([$this->setConnectionId(0, 1)], $this->assignedSetConnectionIds($assignmentId));
	}

	public function testATeacherCannotPickFromAPrivateCollectionOfAnotherPlayer(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$assignmentId = $this->createAssignment($classroomId, null, '2026-11-01', []);
		$secretSetId = (int) $this->context->tsumegos[4]['sets'][0]['id'];
		Util::execute('UPDATE `set` SET user_id = ? WHERE id = ?', [$this->userId('outsider'), $secretSetId]);

		$this->saveAssignment($assignmentId, ['setConnectionIds' => [$this->setConnectionId(4), $this->setConnectionId(1)]]);
		$this->assertSame([$this->setConnectionId(1)], $this->assignedSetConnectionIds($assignmentId));

		$collections = $this->getJson('/classes/collections');
		$this->assertNotContains($secretSetId, array_column($collections['own'], 'id'));

		$this->expectException(ForbiddenException::class);
		$this->getJson('/classes/collection-problems/' . $secretSetId);
	}

	public function testThePickerOffersTheOwnCollectionsAndTheOnesOfTheSite(): void
	{
		$this->prepare();

		$collections = $this->getJson('/classes/collections');

		$this->assertSame(['Homework', 'Secret'], array_column($collections['own'], 'title'));
		$this->assertSame(['Site basics'], array_column($collections['site'], 'title'));
		$this->assertSame(2, $collections['site'][0]['problemCount']);
	}

	public function testThePickerShowsTheProblemsOfACollection(): void
	{
		$this->prepare();

		$problems = $this->getJson('/classes/collection-problems/' . $this->homeworkSetId());

		$this->assertSame('Homework', $problems['collection']['title']);
		$this->assertSame([1, 2, 3], array_column($problems['problems'], 'number'));
		$this->assertSame($this->setConnectionId(0), $problems['problems'][0]['setConnectionId']);
		$this->assertSame((int) $this->context->tsumegos[0]['id'], $problems['problems'][0]['tsumegoId']);
		$this->assertArrayHasKey('preview', $problems['problems'][0]);
	}

	public function testPublishingGivesTheAssignmentToThePupils(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, null, '2026-11-01', []);

		$saved = $this->saveAssignment($assignmentId, [
			'setConnectionIds' => [$this->setConnectionId(0), $this->setConnectionId(3)],
			'publish' => true,
		]);

		$this->assertNotNull($saved['published']);
		$this->assertSame('/classes/' . $classroomId, $saved['classUrl']);
		$this->assertNotNull($this->assignment($assignmentId)['published']);

		$this->login('pupil');
		$page = $this->testAction('/classes/' . $classroomId, ['method' => 'get', 'return' => 'view']);
		$this->assertTextContains('data-assignment-id="' . $assignmentId . '"', $page);
		$this->assertTextContains('Homework', $page);
		$this->assertTextContains('Site basics', $page);
	}

	public function testAnEmptyAssignmentIsNotPublished(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$assignmentId = $this->createAssignment($classroomId, null, '2026-11-01', []);

		try
		{
			$this->saveAssignment($assignmentId, ['setConnectionIds' => [], 'publish' => true]);
			$this->fail('An empty assignment was published');
		}
		catch (UnprocessableEntityException $exception)
		{
			$this->assertSame('Pick the problems to give first.', $exception->getMessage());
		}
		$this->assertNull($this->assignment($assignmentId)['published']);
	}

	public function testAnAssignmentNeedsADueDate(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$assignmentId = $this->createAssignment($classroomId, null, '2026-11-01', []);

		$this->expectException(UnprocessableEntityException::class);
		$this->saveAssignment($assignmentId, ['due' => 'next week', 'setConnectionIds' => [$this->setConnectionId(0)]]);
	}

	public function testOnlyTheTeacherEditsAnAssignment(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->login('pupil');

		$this->assertForbidden(fn() => $this->testAction('/classes/assignment/' . $assignmentId, ['method' => 'get']));
		$this->assertForbidden(fn() => $this->saveAssignment($assignmentId, ['setConnectionIds' => []]));
		$this->assertForbidden(fn() => $this->testAction('/classes/create-assignment/' . $classroomId, ['method' => 'post']));
		$this->assertCount(3, $this->assignedSetConnectionIds($assignmentId));
		$this->assertSame(1, ClassRegistry::init('ClassroomAssignment')->find('count'));
	}

	public function testTheEditorPageShowsThePickedProblems(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$assignmentId = $this->createAssignment($classroomId, null, '2026-11-01', [3, 1]);

		$page = $this->testAction('/classes/assignment/' . $assignmentId, ['method' => 'get', 'return' => 'view']);

		$this->assertSame(1, preg_match('/data-assignment-editor-root data-props="([^"]+)"/', $page, $match));
		$props = json_decode(html_entity_decode($match[1]), true);
		$this->assertSame([$this->setConnectionId(3), $this->setConnectionId(1)], array_column($props['problems'], 'setConnectionId'));
		$this->assertFalse($props['isPublished']);
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
		$this->assertSame(['Homework', 'Homework', 'Homework'], array_column($progress['problems'], 'setTitle'));
		$this->assertSame([$this->userId('pupil'), $this->userId('pupil2')], array_column($progress['pupils'], 'userId'));
		[$pupil, $pupil2] = $progress['pupils'];
		$this->assertSame(['solved', 'late', 'earlier'], array_column($pupil['cells'], 'state'));
		$this->assertSame(1, $pupil['cells'][0]['misplays']);
		$this->assertSame(2, $pupil['solvedCount']);
		$this->assertSame(['state' => 'tried', 'misplays' => 2], $pupil2['cells'][0]);
		$this->assertNull($pupil2['cells'][1]);
		$this->assertSame(0, $pupil2['solvedCount']);
	}

	public function testTheProgressCoversOnlyTheAssignedProblems(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08', [1]);
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

	public function testAPupilGetsNoProgressOfADraft(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, null, '2026-10-08');
		$this->login('pupil');

		$this->expectException(NotFoundException::class);
		$this->progress($assignmentId);
	}

	/**
	 * The teacher picks problems of two collections in the popup, some one by one and some as a
	 * range of numbers, and publishes; the class page then shows the table of the new assignment.
	 */
	public function testTheTeacherPicksProblemsInThePopupAndPublishes(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, null, date('Y-m-d', strtotime('+7 days')), []);
		$siteSetId = (int) $this->context->tsumegos[3]['sets'][0]['id'];
		$browser = Browser::instance();

		$browser->get('/classes/assignment/' . $assignmentId);
		$browser->clickId('add-problems');
		$browser->clickCssSelect('.classes__picker-card[data-collection-id="' . $this->homeworkSetId() . '"]');
		$browser->clickCssSelect('.classes__picker a[data-tsumego-id="' . $this->context->tsumegos[2]['id'] . '"]');
		$browser->clickCssSelect('.classes__picker a[data-tsumego-id="' . $this->context->tsumegos[1]['id'] . '"]');
		$browser->waitUntilCssSelectorExistsWithText('.classes__picker [data-picked-count]', '2 of 3 picked');
		$browser->clickCssSelect('.classes__picker-header button');
		$browser->clickCssSelect('.classes__picker-card[data-collection-id="' . $siteSetId . '"]');
		$browser->find('.classes__picker-range input[name="last_number"]')->sendKeys('1');
		$browser->clickCssSelect('.classes__picker-range button[type="submit"]');
		$browser->waitUntilCssSelectorExistsWithText('.classes__picker [data-picked-count]', '1 of 2 picked');
		$browser->clickCssSelect('.classes__picker-footer button');
		$browser->waitUntilCssSelectorExistsWithText('#assignment-problems .classes__count', '3');

		$browser->clickId('publish-assignment');
		$browser->waitUntilCssSelectorExistsWithText('.classes__solved-count', '0/3');

		$this->assertSame([$this->setConnectionId(1), $this->setConnectionId(2), $this->setConnectionId(3)], $this->assignedSetConnectionIds($assignmentId));
		$this->assertNotNull($this->assignment($assignmentId)['published']);
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

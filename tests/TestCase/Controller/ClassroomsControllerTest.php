<?php

use App\Lib\Error\UnprocessableEntityException;
use App\Model\Table\ClassroomAssignmentTable;
use App\Model\Table\ClassroomMemberStatusTable;
use App\Model\Table\ClassroomMemberTable;
use App\Utility\PlayOutcome;
use App\Utility\Util;

//Classes of a teacher: getting into one takes both sides, the teacher gives the class problems to
//solve by a day and sees who solved which.
class ClassroomsControllerTest extends IntegrationTestCase
{
	private ContextPreparator $context;

	//A teacher with a private collection of three problems (0-2) and a second one of one problem
	//(4), a collection of the site with two problems (3, and 0 again as its number 2), two players
	//who can become pupils and one who stays out.
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
		Util::execute('INSERT INTO classroom (name, teacher_user_id) VALUES (?, ?)', [$name, $this->userId('teacher')]);
		return (int) Util::connection()->lastInsertId();
	}

	private function addMember(int $classroomId, string $name, int $statusId = ClassroomMemberStatusTable::ACCEPTED): int
	{
		Util::execute('INSERT INTO classroom_member (classroom_id, user_id, classroom_member_status_id) VALUES (?, ?, ?)',
			[$classroomId, $this->userId($name), $statusId]);
		return (int) Util::connection()->lastInsertId();
	}

	private function homeworkSetId(): int
	{
		return (int) $this->context->tsumegos[0]['sets'][0]['id'];
	}

	//The problem of the tsumego with the index in the collection with the index among its own.
	private function setConnectionId(int $tsumegoIndex, int $setIndex = 0): int
	{
		return (int) $this->context->tsumegos[$tsumegoIndex]['set-connections'][$setIndex]['id'];
	}

	//$published: Null for a draft
	/** @param list<int> $tsumegoIndexes The problems, from the first collection of each tsumego */
	private function createAssignment(int $classroomId, ?string $published, string $due, array $tsumegoIndexes = [0, 1, 2]): int
	{
		$assignmentTable = $this->fetchTable('ClassroomAssignment');
		$assignmentId = $assignmentTable->saveOrFail($assignmentTable->newEntity([
			'classroom_id' => $classroomId,
			'due' => $due,
			'published' => $published,
			'created' => $published ?? date('Y-m-d H:i:s')]))->id;
		foreach ($tsumegoIndexes as $position => $tsumegoIndex)
			Util::execute('INSERT INTO classroom_assignment_problem (classroom_assignment_id, set_connection_id, position) VALUES (?, ?, ?)',
				[$assignmentId, $this->setConnectionId($tsumegoIndex), $position + 1]);
		return $assignmentId;
	}

	/** @return array<string, mixed> */
	private function assignment(int $assignmentId): array
	{
		return $this->fetchTable('ClassroomAssignment')->find()->where(['id' => $assignmentId])
			->disableHydration()->disableResultsCasting()->firstOrFail();
	}

	/** @return list<int> The set connections of the problems of the assignment, in their order */
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
		$this->post('/classes/save-assignment/' . $assignmentId, json_encode($data + ['title' => '', 'due' => '2026-11-05', 'setConnectionIds' => []]));
		return json_decode($this->view, true);
	}

	/** @return array<string, mixed> */
	private function getJson(string $url): array
	{
		$this->get($url);
		return json_decode($this->view, true);
	}

	//$solved: When the problem was solved for the assignment, null for not yet
	private function addResult(int $assignmentId, string $name, int $tsumegoIndex, ?string $solved, int $misplays = 0): void
	{
		Util::execute('INSERT INTO classroom_assignment_result (classroom_assignment_id, user_id, tsumego_id, misplays, solved) VALUES (?, ?, ?, ?, ?)',
			[$assignmentId, $this->userId($name), $this->context->tsumegos[$tsumegoIndex]['id'], $misplays, $solved]);
	}

	private function addSolvedStatus(string $name, int $tsumegoIndex): void
	{
		Util::execute("INSERT INTO tsumego_status (user_id, tsumego_id, status) VALUES (?, ?, 'S')",
			[$this->userId($name), $this->context->tsumegos[$tsumegoIndex]['id']]);
	}

	/** @return array{misplays: int, solved: string|null}|null What the assignment counts of the player on the problem */
	private function assignmentResult(int $assignmentId, string $name, int $tsumegoIndex): ?array
	{
		$rows = Util::query('SELECT misplays, solved FROM classroom_assignment_result WHERE classroom_assignment_id = ? AND user_id = ? AND tsumego_id = ?',
			[$assignmentId, $this->userId($name), $this->context->tsumegos[$tsumegoIndex]['id']]);
		return empty($rows) ? null : ['misplays' => (int) $rows[0]['misplays'], 'solved' => $rows[0]['solved']];
	}

	private function problemStatus(string $name, int $tsumegoIndex): ?string
	{
		$rows = Util::query('SELECT status FROM tsumego_status WHERE user_id = ? AND tsumego_id = ?',
			[$this->userId($name), $this->context->tsumegos[$tsumegoIndex]['id']]);
		return empty($rows) ? null : $rows[0]['status'];
	}

	//The colour of the button of the problem under the board, as its status letter.
	private function buttonStatus(Browser $browser, int $tsumegoIndex): string
	{
		$class = $browser->driver->executeScript('return document.querySelector(\'.problem-nav__item > a[data-tsumego-id="\' + arguments[0] + \'"]\').parentElement.className;',
			[$this->context->tsumegos[$tsumegoIndex]['id']]);
		$this->assertSame(1, preg_match('/problem-nav__item--([A-Z])\b/', $class, $match), $class);
		return $match[1];
	}

	private function xp(string $name): int
	{
		return (int) Util::query('SELECT xp FROM user WHERE id = ?', [$this->userId($name)])[0]['xp'];
	}

	private function postResult(string $name, int $tsumegoIndex, PlayOutcome $outcome): void
	{
		$this->login($name);
		$this->post('/tsumegos/result', ['tsumego_id' => $this->context->tsumegos[$tsumegoIndex]['id'], 'seconds' => 10, 'outcome' => $outcome->value]);
	}

	private function membershipStatus(int $classroomId, string $name): ?int
	{
		return ClassroomMemberTable::of($classroomId, $this->userId($name))?->classroom_member_status_id;
	}

	/** @return array<string, mixed> */
	private function progress(int $assignmentId): array
	{
		$this->get('/classes/progress/' . $assignmentId);
		return json_decode($this->view, true);
	}

	private function assertForbidden(callable $request): void
	{
		$request();

		$this->assertResponseCode(403);
	}

	public function testCreatingAClassMakesThePlayerItsTeacher(): void
	{
		$this->prepare();

		$this->post('/classes/create', ['name' => '  Lukas,   Thursday ']);

		$classroom = $this->fetchTable('Classroom')->find()->firstOrFail();
		$this->assertSame('Lukas, Thursday', $classroom->name);
		$this->assertSame($this->userId('teacher'), $classroom->teacher_user_id);
		$this->assertStringEndsWith('/classes/' . $classroom->id, $this->headerValue('Location'));
	}

	public function testAClassNeedsAName(): void
	{
		$this->prepare();

		$this->post('/classes/create', ['name' => '   ']);

		$this->assertSame(0, $this->fetchTable('Classroom')->find()->count());
	}

	public function testCreatingAClassNeedsAnAccount(): void
	{
		new ContextPreparator(['user' => null]);
		$this->post('/classes/create', ['name' => 'Sneaky']);

		$this->assertResponseCode(401);
	}

	public function testAnInvitedPlayerIsInTheClassOnceTheyAccept(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();

		$this->post('/classes/invite/' . $classroomId, ['name' => 'DN_pupil']);
		$this->assertSame(ClassroomMemberStatusTable::INVITED, $this->membershipStatus($classroomId, 'pupil'));

		$this->login('pupil');
		$this->get('/classes');
		$page = $this->view;
		$this->assertTextContains('invites you to', $page);

		$membership = ClassroomMemberTable::of($classroomId, $this->userId('pupil'));
		$this->post('/classes/accept-member/' . $membership->id);
		$this->assertSame(ClassroomMemberStatusTable::ACCEPTED, $this->membershipStatus($classroomId, 'pupil'));
	}

	public function testTheTeacherCannotAcceptTheirOwnInvitation(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$membershipId = $this->addMember($classroomId, 'pupil', ClassroomMemberStatusTable::INVITED);

		$this->post('/classes/accept-member/' . $membershipId);

		$this->assertResponseCode(403);
	}

	public function testAPlayerWhoAskedToJoinIsInOnceTheTeacherAccepts(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();

		$this->login('pupil');
		$this->get('/classes/' . $classroomId);
		$page = $this->view;
		$this->assertTextContains('Ask to join', $page);
		$this->post('/classes/join/' . $classroomId);
		$this->assertSame(ClassroomMemberStatusTable::REQUESTED, $this->membershipStatus($classroomId, 'pupil'));

		$this->login('teacher');
		$this->get('/classes/' . $classroomId);
		$page = $this->view;
		$this->assertTextContains('asks to join', $page);
		$membership = ClassroomMemberTable::of($classroomId, $this->userId('pupil'));
		$this->post('/classes/accept-member/' . $membership->id);
		$this->assertSame(ClassroomMemberStatusTable::ACCEPTED, $this->membershipStatus($classroomId, 'pupil'));
	}

	public function testAPlayerCannotAcceptTheirOwnRequest(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$membershipId = $this->addMember($classroomId, 'pupil', ClassroomMemberStatusTable::REQUESTED);
		$this->login('pupil');

		$this->post('/classes/accept-member/' . $membershipId);

		$this->assertResponseCode(403);
	}

	public function testInvitingAPlayerWhoAskedToJoinPutsThemInTheClass(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil', ClassroomMemberStatusTable::REQUESTED);

		$this->post('/classes/invite/' . $classroomId, ['name' => 'DN_pupil']);

		$this->assertSame(ClassroomMemberStatusTable::ACCEPTED, $this->membershipStatus($classroomId, 'pupil'));
	}

	public function testAskingToJoinWhenInvitedPutsThePlayerInTheClass(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil', ClassroomMemberStatusTable::INVITED);
		$this->login('pupil');

		$this->post('/classes/join/' . $classroomId);

		$this->assertSame(ClassroomMemberStatusTable::ACCEPTED, $this->membershipStatus($classroomId, 'pupil'));
	}

	public function testInvitingAnUnknownNameInvitesNobody(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();

		$this->post('/classes/invite/' . $classroomId, ['name' => 'Nobody']);

		$this->assertSame(0, $this->fetchTable('ClassroomMember')->find()->count());
	}

	public function testOnlyTheTeacherInvites(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->login('outsider');

		$this->post('/classes/invite/' . $classroomId, ['name' => 'DN_pupil']);

		$this->assertResponseCode(403);
	}

	public function testAPupilLeavesAndTheTeacherRemoves(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$leavingId = $this->addMember($classroomId, 'pupil');
		$removedId = $this->addMember($classroomId, 'pupil2');

		$this->post('/classes/remove-member/' . $removedId);
		$this->login('pupil');
		$this->post('/classes/remove-member/' . $leavingId);

		$this->assertNull($this->membershipStatus($classroomId, 'pupil'));
		$this->assertNull($this->membershipStatus($classroomId, 'pupil2'));
	}

	public function testAnotherPlayerCannotRemoveAPupil(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$membershipId = $this->addMember($classroomId, 'pupil');
		$this->login('outsider');

		$this->post('/classes/remove-member/' . $membershipId);

		$this->assertResponseCode(403);
	}

	public function testANewAssignmentIsADraftThePupilsDontSee(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');

		$this->post('/classes/create-assignment/' . $classroomId);

		$assignment = $this->fetchTable('ClassroomAssignment')->find()->disableHydration()->disableResultsCasting()->firstOrFail();
		$this->assertStringEndsWith('/classes/assignment/' . $assignment['id'], $this->headerValue('Location'));
		$this->assertNull($assignment['published']);
		$this->assertSame(date('Y-m-d', strtotime('+7 days')), $assignment['due']);
		$this->get('/classes/' . $classroomId);
		$page = $this->view;
		$this->assertTextContains('data-assignment-id="' . $assignment['id'] . '"', $page);
		$this->assertTextContains('classes__badge">draft', $page);

		$this->login('pupil');
		$this->get('/classes/' . $classroomId);
		$page = $this->view;
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

		$this->get('/classes/collection-problems/' . $secretSetId);

		$this->assertResponseCode(403);
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
		$this->get('/classes/' . $classroomId);
		$page = $this->view;
		$this->assertTextContains('data-assignment-id="' . $assignmentId . '"', $page);
		$this->assertTextContains('Homework', $page);
		$this->assertTextContains('Site basics', $page);
	}

	public function testAnEmptyAssignmentIsNotPublished(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$assignmentId = $this->createAssignment($classroomId, null, '2026-11-01', []);

		$this->disableErrorHandlerMiddleware();
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

		$this->post('/classes/save-assignment/' . $assignmentId, json_encode([
			'title' => '',
			'due' => 'next week',
			'setConnectionIds' => [$this->setConnectionId(0)],
		]));

		$this->assertResponseCode(422);
	}

	public function testOnlyTheTeacherEditsAnAssignment(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->login('pupil');

		$this->assertForbidden(fn() => $this->get('/classes/assignment/' . $assignmentId));
		$this->assertForbidden(fn() => $this->post('/classes/save-assignment/' . $assignmentId, json_encode(['setConnectionIds' => []])));
		$this->assertForbidden(fn() => $this->post('/classes/create-assignment/' . $classroomId));
		$this->assertCount(3, $this->assignedSetConnectionIds($assignmentId));
		$this->assertSame(1, $this->fetchTable('ClassroomAssignment')->find()->count());
	}

	public function testTheEditorPageShowsThePickedProblems(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$assignmentId = $this->createAssignment($classroomId, null, '2026-11-01', [3, 1]);

		$this->get('/classes/assignment/' . $assignmentId);
		$page = $this->view;

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
		$this->addMember($classroomId, 'outsider', ClassroomMemberStatusTable::INVITED);
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->addResult($assignmentId, 'pupil', 0, '2026-10-02 10:00:00', 1);
		$this->addResult($assignmentId, 'pupil', 1, '2026-10-09 10:00:00');
		$this->addSolvedStatus('pupil', 2);
		$this->addResult($assignmentId, 'pupil2', 0, null, 2);
		$this->addResult($assignmentId, 'outsider', 0, '2026-10-03 10:00:00');

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

	public function testTheProgressCoversOnlyItsOwnAssignment(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08', [0, 1]);
		$otherAssignmentId = $this->createAssignment($classroomId, '2026-09-01 00:00:00', '2026-09-08', [0]);
		$this->addResult($otherAssignmentId, 'pupil', 0, '2026-09-02 10:00:00');

		$progress = $this->progress($assignmentId);

		$this->assertSame([1, 2], array_column($progress['problems'], 'number'));
		$this->assertSame([null, null], $progress['pupils'][0]['cells']);
	}

	//A result on a problem counts for every published assignment of the pupil's classes that has
	//it: each misplay and the first solve, wherever the problem is played.
	public function testAResultOfAPupilCountsForTheAssignment(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08', [0, 1]);
		$draftId = $this->createAssignment($classroomId, null, '2026-10-08', [0]);

		$this->postResult('pupil', 0, PlayOutcome::Failed);
		$this->postResult('pupil', 0, PlayOutcome::Failed);
		$this->assertSame(['misplays' => 2, 'solved' => null], $this->assignmentResult($assignmentId, 'pupil', 0));

		$this->postResult('pupil', 0, PlayOutcome::SolvedAfterMistake);
		$result = $this->assignmentResult($assignmentId, 'pupil', 0);
		$this->assertSame(2, $result['misplays']);
		$this->assertNotNull($result['solved']);
		$this->assertSame('S', $this->problemStatus('pupil', 0), 'The solve for the assignment solves the problem too');
		$this->assertNull($this->assignmentResult($draftId, 'pupil', 0));
		$this->assertNull($this->assignmentResult($assignmentId, 'pupil', 1));
	}

	//A problem the pupil solved before is played again for the assignment. The play page sends
	//that solve, which leaves the status alone and gives no XP, but counts for the assignment.
	public function testASolveOfAProblemSolvedBeforeCountsForTheAssignment(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08', [0]);
		$this->addSolvedStatus('pupil', 0);
		$xp = $this->xp('pupil');

		$this->postResult('pupil', 0, PlayOutcome::SolvedAfterMistake);

		$this->assertSame(1, $this->assignmentResult($assignmentId, 'pupil', 0)['misplays']);
		$this->assertNotNull($this->assignmentResult($assignmentId, 'pupil', 0)['solved']);
		$this->assertSame($xp, $this->xp('pupil'));
		$this->assertSame('S', $this->problemStatus('pupil', 0));
	}

	public function testAResultOfAPlayerOutsideTheClassDoesntCount(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'outsider', ClassroomMemberStatusTable::REQUESTED);
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08', [0]);

		$this->postResult('outsider', 0, PlayOutcome::SolvedClean);

		$this->assertNull($this->assignmentResult($assignmentId, 'outsider', 0));
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
		$this->addMember($classroomId, 'outsider', ClassroomMemberStatusTable::REQUESTED);
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->login('outsider');

		$this->get('/classes/progress/' . $assignmentId);

		$this->assertResponseCode(403);
	}

	public function testAPupilOpensThePrivateCollectionGivenToTheClass(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$set = new App\Model\Entity\Set(['id' => $this->homeworkSetId(), 'public' => 0, 'user_id' => $this->userId('teacher')]);

		$this->assertTrue(App\Policy\SetPolicy::canView(['id' => $this->userId('pupil')], $set));
		$this->assertFalse(App\Policy\SetPolicy::canView(['id' => $this->userId('outsider')], $set));

		$this->login('pupil');
		$this->get('/sets/' . $this->homeworkSetId());
		$page = $this->view;
		$this->assertTextContains('Homework', $page);
	}

	public function testThePupilPageShowsTheAssignments(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->login('pupil');

		$this->get('/classes/' . $classroomId);
		$page = $this->view;

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

		$this->get('/classes/' . $classroomId);
		$page = $this->view;

		$this->assertTextNotContains('data-assignment-id="' . $assignmentId . '"', $page);
		$this->assertTextContains('Ask to join', $page);
	}

	public function testTheMenuCountsWhatWaitsForAnAnswer(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$otherClassroomId = $this->createClassroom('Friday');
		$this->addMember($classroomId, 'pupil', ClassroomMemberStatusTable::INVITED);
		$this->addMember($otherClassroomId, 'pupil2', ClassroomMemberStatusTable::REQUESTED);
		$this->addMember($classroomId, 'pupil2', ClassroomMemberStatusTable::REQUESTED);

		$this->assertSame(2, ClassroomMemberTable::countAwaitingAnswer($this->userId('teacher')));
		$this->assertSame(1, ClassroomMemberTable::countAwaitingAnswer($this->userId('pupil')));
		$this->assertSame(0, ClassroomMemberTable::countAwaitingAnswer($this->userId('pupil2')));

		$this->login('pupil');
		$this->get('/classes');
		$page = $this->view;
		$this->assertMatchesRegularExpression('#href="/classes">Classes<span class="nav__new-badge nav__new-badge--visible"[^>]*>1</span>#', $page);
	}

	//The teacher keeps the page open while the class plays, and a solve shows up without a reload,
	//with the misplays it took.
	public function testTheTableShowsASolveWhileThePageIsOpen(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, date('Y-m-d H:i:s', strtotime('-1 hour')), date('Y-m-d', strtotime('+7 days')));
		$browser = Browser::instance();

		$browser->get('/classes/' . $classroomId);
		$browser->waitUntilCssSelectorExistsWithText('.classes__solved-count', '0/3');
		$this->assertCount(0, $browser->getElements('.classes__table .classes__cell--solved'));

		$this->addResult($assignmentId, 'pupil', 1, date('Y-m-d H:i:s'));
		$this->addResult($assignmentId, 'pupil', 2, date('Y-m-d H:i:s'), 2);

		$browser->waitUntilCssSelectorExistsWithText('.classes__solved-count', '2/3', 30);
		$this->assertCount(2, $browser->getElements('.classes__table .classes__cell--solved'));
		// a solve after misplays shows how many it took, not only in the tooltip
		$misplayed = $browser->getElements('.classes__table .classes__cell--with-misplays');
		$this->assertCount(1, $misplayed);
		$this->assertSame('✓2', $misplayed[0]->getText());
	}

	public function testAPupilGetsNoProgressOfADraft(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, null, '2026-10-08');
		$this->login('pupil');

		$this->get('/classes/progress/' . $assignmentId);

		$this->assertResponseCode(404);
	}

	//The teacher picks problems of two collections in the popup, some one by one and some as a
	//range of numbers, and publishes; the class page then shows the table of the new assignment.
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

	//The pupil plays the assignment as a row of its own: the buttons are numbered from 1, green
	//for a problem solved for the assignment, light blue (W) for one solved only before, and the
	//title says where in the assignment the pupil is and which problem of which collection it is.
	//Without a problem the route opens the first one not solved for the assignment yet, and a
	//solve of it is sent though it was solved before.
	public function testAPupilPlaysTheAssignmentAsOneRow(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08', [3, 1, 0]);
		$this->fetchTable('ClassroomAssignment')->updateAll(['title' => 'Week two'], ['id' => $assignmentId]);
		$this->addResult($assignmentId, 'pupil', 3, '2026-10-02 10:00:00');
		$this->addSolvedStatus('pupil', 1);
		$this->login('pupil');

		$this->get('/classes/play/' . $assignmentId);

		$this->assertStringContainsString('var tsumegoID = ' . $this->context->tsumegos[1]['id'] . ';', $this->view);
		$buttons = (array) $this->viewVariable('tsumegoButtons');
		$this->assertSame([1, 2, 3], array_map(fn($button) => $button->order, $buttons));
		$this->assertSame(['S', 'W', 'N'], array_map(fn($button) => $button->status, $buttons));
		$this->assertStringContainsString('var assignmentReplay = true;', $this->view);
		$this->assertSame('Homework 1', $buttons[2]->description);
		$text = preg_replace('/\s+/', ' ', $this->view);
		$this->assertStringContainsString('Week two 2/3</a>', $text);
		$this->assertStringContainsString('(Homework 2/3)', $text);
		$this->assertStringContainsString('href="/classes/play/' . $assignmentId . '/' . $this->setConnectionId(0) . '"', $this->view);
		$this->assertSame('/classes/play/' . $assignmentId . '/' . $this->setConnectionId(3), $this->viewVariable('previousLink'));
		$this->assertSame('/classes/play/' . $assignmentId . '/' . $this->setConnectionId(0), $this->viewVariable('nextLink'));
		$this->assertTrue(App\Utility\Auth::isInLevelMode());
	}

	//A pupil solves a new problem in the assignment row: the problem is solved like anywhere
	//else, with its XP, and counts for the assignment, so its button stays green on the next page.
	public function testSolvingInTheAssignmentRowSolvesTheProblemAndTheAssignment(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, date('Y-m-d H:i:s', strtotime('-1 hour')), date('Y-m-d', strtotime('+7 days')), [0, 1]);
		$xp = $this->xp('pupil');
		$this->login('pupil');
		$browser = Browser::instance();

		$browser->get(ClassroomAssignmentTable::playLink($assignmentId));
		$this->assertFalse($browser->driver->executeScript('return assignmentReplay;'));
		$browser->playWithResult('S');
		$this->assertSame('S', $this->buttonStatus($browser, 0));

		$this->assertSame('S', $this->problemStatus('pupil', 0));
		$this->assertGreaterThan($xp, $this->xp('pupil'));
		$this->assertSame(0, $this->assignmentResult($assignmentId, 'pupil', 0)['misplays']);
		$this->assertNotNull($this->assignmentResult($assignmentId, 'pupil', 0)['solved']);

		$browser->get(ClassroomAssignmentTable::playLink($assignmentId));
		$this->assertSame('S', $this->buttonStatus($browser, 0));
		$this->assertSame('V', $this->buttonStatus($browser, 1), 'The row opens at the problem not solved yet');
	}

	//A pupil plays a problem they solved before. Its button is light blue until they solve it
	//again in the assignment row, then green, also on the next page. The page sends that solve,
	//which counts for the assignment and shows in the teacher's table, but gives no XP and leaves
	//the problem solved as it was.
	public function testSolvingAProblemSolvedBeforeInTheAssignmentRowCountsForTheAssignment(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, date('Y-m-d H:i:s', strtotime('-1 hour')), date('Y-m-d', strtotime('+7 days')), [0, 1]);
		$this->addSolvedStatus('pupil', 0);
		$xp = $this->xp('pupil');
		$this->login('pupil');
		$browser = Browser::instance();

		$browser->get(ClassroomAssignmentTable::playLink($assignmentId));
		$this->assertSame('W', $this->buttonStatus($browser, 0));
		$this->assertSame(['state' => 'earlier'], ClassroomAssignmentTable::progress($this->assignment($assignmentId))['pupils'][0]['cells'][0]);
		$this->assertTrue($browser->driver->executeScript('return assignmentReplay;'));
		$browser->playWithResult('S');
		$this->assertSame('S', $this->buttonStatus($browser, 0));

		$this->assertNotNull($this->assignmentResult($assignmentId, 'pupil', 0)['solved']);
		$this->assertSame($xp, $this->xp('pupil'));
		$this->assertSame('S', $this->problemStatus('pupil', 0));
		$this->assertSame('solved', ClassroomAssignmentTable::progress($this->assignment($assignmentId))['pupils'][0]['cells'][0]['state']);

		$browser->get(ClassroomAssignmentTable::playLink($assignmentId, $this->setConnectionId(0)));
		$this->assertSame('S', $this->buttonStatus($browser, 0));
		$this->assertFalse($browser->driver->executeScript('return assignmentReplay;'), 'Once solved for the assignment, a replay sends nothing again');
	}

	public function testAfterTheLastProblemComesTheClass(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08', [3, 1, 0]);
		$this->login('pupil');

		$this->get('/classes/play/' . $assignmentId . '/' . $this->setConnectionId(0));

		$this->assertSame('/classes/' . $classroomId, $this->viewVariable('nextLink'));
	}

	public function testAProblemOutsideTheAssignmentLeadsToItsStart(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08', [3, 1]);
		$this->login('pupil');

		$this->get('/classes/play/' . $assignmentId . '/' . $this->setConnectionId(2));

		$this->assertStringEndsWith('/classes/play/' . $assignmentId, $this->headerValue('Location'));
	}

	public function testOnlyTheClassPlaysItsAssignment(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$draftId = $this->createAssignment($classroomId, null, '2026-10-08');

		$this->login('outsider');
		$this->assertForbidden(fn() => $this->get('/classes/play/' . $assignmentId));

		$this->login('pupil');
		$this->get('/classes/play/' . $draftId);

		$this->assertResponseCode(404);
	}

	public function testThePupilPageLeadsToTheAssignmentRow(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$assignmentId = $this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');
		$this->login('pupil');

		$this->get('/classes/' . $classroomId);
		$page = $this->view;

		$this->assertTextContains('href="/classes/play/' . $assignmentId . '">Solve</a>', $page);
	}

	public function testDeletingTheClassTakesItsMembersAndAssignments(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$this->createAssignment($classroomId, '2026-10-01 00:00:00', '2026-10-08');

		$this->post('/classes/delete/' . $classroomId);

		$this->assertSame(0, $this->fetchTable('Classroom')->find()->count());
		$this->assertSame(0, $this->fetchTable('ClassroomMember')->find()->count());
		$this->assertSame(0, $this->fetchTable('ClassroomAssignment')->find()->count());
	}

	public function testOnlyTheTeacherDeletesTheClass(): void
	{
		$this->prepare();
		$classroomId = $this->createClassroom();
		$this->addMember($classroomId, 'pupil');
		$this->login('pupil');

		$this->post('/classes/delete/' . $classroomId);

		$this->assertResponseCode(403);
	}
}

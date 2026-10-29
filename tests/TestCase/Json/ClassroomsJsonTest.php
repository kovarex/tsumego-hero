<?php

use App\Model\Table\ClassroomMemberStatusTable;
use App\Utility\Util;

//The JSON of the classes: the collection picker of the assignment editor, its saving and the progress.
class ClassroomsJsonTest extends JsonGoldenTestCase
{
	private ContextPreparator $context;

	//A teacher with a class of two pupils, a private collection of two problems and a collection of the
	//site of one problem.
	private function prepareClassroom(): int
	{
		$homework = fn(int $num) => ['name' => 'Homework', 'num' => $num, 'public' => 0, 'user_id' => 'self'];
		$this->context = new ContextPreparator([
			'user' => ['name' => 'teacher'],
			'other-users' => [['name' => 'pupil'], ['name' => 'pupil2']],
			'tsumegos' => [
				['sets' => [$homework(1)], 'sgf' => '(;GM[1]FF[4]SZ[19]AB[cc]AW[dd])'],
				['sets' => [$homework(2)], 'sgf' => '(;GM[1]FF[4]SZ[9]AB[ee])'],
				['sets' => [['name' => 'Site basics', 'num' => 1]], 'sgf' => '(;GM[1]FF[4]SZ[19]AB[aa])'],
			],
		]);
		Util::execute('INSERT INTO classroom (name, teacher_user_id) VALUES (?, ?)', ['Lukas, Thursday', $this->context->user['id']]);
		$classroomId = (int) Util::connection()->lastInsertId();
		foreach ($this->context->otherUsers as $pupil)
			Util::execute('INSERT INTO classroom_member (classroom_id, user_id, classroom_member_status_id) VALUES (?, ?, ?)',
				[$classroomId, $pupil['id'], ClassroomMemberStatusTable::ACCEPTED]);
		return $classroomId;
	}

	//$published: Null for a draft
	private function createAssignment(int $classroomId, ?string $published): int
	{
		$assignmentTable = $this->fetchTable('ClassroomAssignment');
		$assignmentId = $assignmentTable->saveOrFail($assignmentTable->newEntity([
			'classroom_id' => $classroomId,
			'title' => 'Week 1',
			'due' => '2030-01-10',
			'published' => $published,
			'created' => '2030-01-01 10:00:00']))->id;
		foreach ([0, 1] as $index)
			Util::execute('INSERT INTO classroom_assignment_problem (classroom_assignment_id, set_connection_id, position) VALUES (?, ?, ?)',
				[$assignmentId, $this->context->tsumegos[$index]['set-connections'][0]['id'], $index + 1]);
		return $assignmentId;
	}

	public function testCollections(): void
	{
		$this->prepareClassroom();
		$this->get('/classes/collections');
		$this->assertJsonGolden('collections');
	}

	public function testCollectionProblems(): void
	{
		$this->prepareClassroom();
		$this->get('/classes/collection-problems/' . $this->context->tsumegos[0]['sets'][0]['id']);
		$this->assertJsonGolden('collection problems');
	}

	public function testSaveAssignment(): void
	{
		$assignmentId = $this->createAssignment($this->prepareClassroom(), null);
		$this->post('/classes/save-assignment/' . $assignmentId, json_encode([
			'title' => 'Week 1',
			'due' => '2030-01-12',
			'setConnectionIds' => [$this->context->tsumegos[1]['set-connections'][0]['id']]]));
		$this->assertJsonGolden('save assignment');
	}

	public function testSaveAndPublishAssignment(): void
	{
		$assignmentId = $this->createAssignment($this->prepareClassroom(), null);
		$this->post('/classes/save-assignment/' . $assignmentId, json_encode([
			'title' => 'Week 1',
			'due' => '2030-01-12',
			'setConnectionIds' => [$this->context->tsumegos[0]['set-connections'][0]['id']],
			'publish' => true]));
		$this->assertJsonGolden('save and publish assignment');
	}

	//An error answers its message to a fetch() that takes JSON, which the editor shows
	public function testSaveAssignmentWithoutADueDate(): void
	{
		$assignmentId = $this->createAssignment($this->prepareClassroom(), null);
		$this->configRequest(['headers' => ['Accept' => 'application/json']]);
		$this->post('/classes/save-assignment/' . $assignmentId, json_encode(['title' => 'Week 1', 'due' => 'tomorrow', 'setConnectionIds' => []]));
		$this->assertJsonGolden('save assignment without a due date');
	}

	//The pupil solved the first problem, misplayed the second twice and has not solved it yet
	private function prepareProgress(): int
	{
		$assignmentId = $this->createAssignment($this->prepareClassroom(), '2030-01-01 12:00:00');
		$pupilId = $this->context->otherUsers[0]['id'];
		Util::execute('INSERT INTO classroom_assignment_result (classroom_assignment_id, user_id, tsumego_id, misplays, solved) VALUES (?, ?, ?, ?, ?)',
			[$assignmentId, $pupilId, $this->context->tsumegos[0]['id'], 1, '2030-01-02 15:00:00']);
		Util::execute('INSERT INTO classroom_assignment_result (classroom_assignment_id, user_id, tsumego_id, misplays, solved) VALUES (?, ?, ?, ?, ?)',
			[$assignmentId, $pupilId, $this->context->tsumegos[1]['id'], 2, null]);
		return $assignmentId;
	}

	public function testProgressOfTheTeacher(): void
	{
		$assignmentId = $this->prepareProgress();
		$this->get('/classes/progress/' . $assignmentId);
		$this->assertJsonGolden('progress of the teacher');
	}

	public function testProgressOfAPupil(): void
	{
		$assignmentId = $this->prepareProgress();
		$this->login('pupil');
		$this->get('/classes/progress/' . $assignmentId);
		$this->assertJsonGolden('progress of a pupil');
	}
}

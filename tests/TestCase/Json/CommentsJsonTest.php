<?php

use App\Model\Table\TsumegoIssueTable;

//The JSON of the comments and issues of a problem, which the comment section of the play page and
//the issues page read.
class CommentsJsonTest extends JsonGoldenTestCase
{
	//A problem with an issue of two comments, a closed issue and a comment of its own
	private function prepareDiscussion(): ContextPreparator
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Life and death', 'num' => 7]],
				'issues' => [
					['message' => 'wrong answer', 'created' => '2026-01-01 10:00:00'],
					['message' => 'missing variation', 'created' => '2026-01-02 10:00:00', 'status' => TsumegoIssueTable::CLOSED_STATUS],
				],
				'comments' => [['message' => 'nice problem', 'created' => '2026-01-03 10:00:00']],
			],
		]);
		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
		$tsumegoCommentTable->saveOrFail($tsumegoCommentTable->newEntity([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'tsumego_issue_id' => $context->issues[0]['id'],
			'user_id' => $context->user['id'],
			'message' => 'agreed, b2 works too',
			'position' => '{"x":1,"y":1}',
			'created' => '2026-01-04 10:00:00']));
		return $context;
	}

	public function testIndex(): void
	{
		$context = $this->prepareDiscussion();
		$this->get('/tsumego-comments/index/' . $context->tsumegos[0]['id']);
		$this->assertJsonGolden('index');
	}

	public function testAdd(): void
	{
		$context = $this->prepareDiscussion();
		$this->post('/tsumego-comments/add', json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'a new comment', 'position' => '{"x":3,"y":4}']));
		$this->assertJsonGolden('add');
	}

	public function testDelete(): void
	{
		$context = $this->prepareDiscussion();
		$comment = $this->fetchTable('TsumegoComment')->find()->where(['tsumego_issue_id IS' => null])->firstOrFail();
		$this->post('/tsumego-comments/delete/' . $comment->id);
		$this->assertJsonGolden('delete');
	}

	public function testIssueCreate(): void
	{
		$context = $this->prepareDiscussion();
		$this->post('/tsumego-issues/create', json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'the ko is wrong', 'position' => '{"x":2,"y":2}']));
		$this->assertJsonGolden('issue create');
	}

	public function testIssuesApi(): void
	{
		$this->prepareDiscussion();
		$this->get('/tsumego-issues/api');
		$this->assertJsonGolden('issues api');
	}

	public function testIssuesApiOfAllStatuses(): void
	{
		$this->prepareDiscussion();
		$this->get('/tsumego-issues/api?status=all&page=1');
		$this->assertJsonGolden('issues api of all statuses');
	}

	public function testIssueClose(): void
	{
		$context = $this->prepareDiscussion();
		$this->post('/tsumego-issues/close/' . $context->issues[0]['id'], ['Issue' => ['message' => 'fixed now']]);
		$this->assertJsonGolden('issue close');
	}

	public function testIssueReopen(): void
	{
		$context = $this->prepareDiscussion();
		$this->post('/tsumego-issues/reopen/' . $context->issues[1]['id']);
		$this->assertJsonGolden('issue reopen');
	}

	public function testMoveCommentToItsOwn(): void
	{
		$context = $this->prepareDiscussion();
		$comment = $this->fetchTable('TsumegoComment')->find()->where(['message' => 'agreed, b2 works too'])->firstOrFail();
		$this->post('/tsumego-issues/move-comment/' . $comment->id, ['Comment' => ['tsumego_issue_id' => 'standalone']]);
		$this->assertJsonGolden('move comment to its own');
	}

	public function testMoveCommentToANewIssue(): void
	{
		$context = $this->prepareDiscussion();
		$comment = $this->fetchTable('TsumegoComment')->find()->where(['tsumego_issue_id IS' => null])->firstOrFail();
		$this->post('/tsumego-issues/move-comment/' . $comment->id, ['Comment' => ['tsumego_issue_id' => 'new']]);
		$this->assertJsonGolden('move comment to a new issue');
	}

	public function testMoveCommentToAnIssue(): void
	{
		$context = $this->prepareDiscussion();
		$comment = $this->fetchTable('TsumegoComment')->find()->where(['tsumego_issue_id IS' => null])->firstOrFail();
		$this->post('/tsumego-issues/move-comment/' . $comment->id, ['Comment' => ['tsumego_issue_id' => (string) $context->issues[1]['id']]]);
		$this->assertJsonGolden('move comment to an issue');
	}
}

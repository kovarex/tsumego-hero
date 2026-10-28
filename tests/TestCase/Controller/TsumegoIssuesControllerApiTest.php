<?php

//Controller-level tests for the TsumegoIssuesController JSON API.
class TsumegoIssuesControllerApiTest extends IntegrationTestCase
{
	public function testCreateRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->post('/tsumego-issues/create', json_encode(['tsumego_id' => 1, 'text' => 'broken move']));

		$this->assertResponseCode(401);
	}

	public function testCreateRequiresTsumegoIdAndMessage()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->post('/tsumego-issues/create', json_encode([]));

		$this->assertResponseCode(400);
	}

	public function testCreateSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
		]);

		$this->post('/tsumego-issues/create', json_encode(['tsumego_id' => $context->tsumegos[0]['id'], 'text' => 'wrong answer']));

		$this->assertResponseCode(200);
		$body = json_decode($this->view, true);
		$this->assertTrue($body['success']);
		$this->assertNotEmpty($body['issue']['id']);
	}

	//A problem left only in someone's Favorites is shown by its id rather than in their collection.
	public function testApiLeavesOutAFavoritesSetAsTheOnlyCollection()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [['name' => 'Ivan Detkov']],
			'tsumego' => [
				'sets' => [['name' => 'Favorites', 'public' => 0, 'user_id' => 'self', 'num' => 1]],
				'issues' => [['message' => 'wrong answer']],
			],
		]);

		$this->giveFavoritesToTheOtherUser($context);

		$this->get('/tsumego-issues/api');

		$issue = json_decode($this->view, true)['issues'][0];
		$this->assertNull($issue['Set']);
		$this->assertNull($issue['TsumegoNum']);
	}

	//Only the issues of problems that are on the site are listed and counted: a problem in the trash
	//and a problem whose only collection is in the trash are left out, a problem that still has a
	//collection of the site outside the trash stays.
	public function testApiLeavesOutTheIssuesOfProblemsOffTheSite()
	{
		new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumegos' => [
				['sets' => [['name' => 'Live collection', 'num' => 1]], 'issues' => [['message' => 'on the site']]],
				['deleted' => '2026-10-01 00:00:00', 'sets' => [['name' => 'Live collection', 'num' => 2]], 'issues' => [['message' => 'problem in the trash']]],
				['sets' => [['name' => 'Trashed collection', 'deleted' => '2026-10-01 00:00:00', 'num' => 1]], 'issues' => [['message' => 'collection in the trash']]],
				['sets' => [
					['name' => 'Trashed collection', 'deleted' => '2026-10-01 00:00:00', 'num' => 2],
					['name' => 'Live collection', 'num' => 3],
				], 'issues' => [['message' => 'also in a live collection']]],
			],
		]);

		$this->get('/tsumego-issues/api');

		$body = json_decode($this->view, true);
		$messages = array_map(fn($issue) => $issue['comments'][0]['text'], $body['issues']);
		sort($messages);
		$this->assertSame(['also in a live collection', 'on the site'], $messages);
		$this->assertSame(2, $body['counts']['open']);
	}

	public function testCloseNotFound()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->post('/tsumego-issues/close/999999');

		$this->assertResponseCode(404);
	}

	public function testReopenOwnIssueSucceeds()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'admin' => false],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'issues' => [['message' => 'my issue', 'status' => TsumegoIssue::$CLOSED_STATUS]]],
		]);

		$this->post('/tsumego-issues/reopen/' . $context->issues[0]['id']);

		$this->assertResponseCode(200);
		$issue = ClassRegistry::init('TsumegoIssue')->findById($context->issues[0]['id']);
		$this->assertSame(TsumegoIssue::$OPENED_STATUS, (int) $issue['TsumegoIssue']['tsumego_issue_status_id']);
	}

	public function testReopenForbiddenForOtherUsersIssue()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'admin' => false],
			'other-users' => [['name' => 'Ivan Detkov']],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'issues' => [['message' => 'someone elses issue', 'status' => TsumegoIssue::$CLOSED_STATUS]]],
		]);

		// The issue belongs to the acting user by default; hand it to another user
		ClassRegistry::init('TsumegoIssue')->updateAll(
			['TsumegoIssue.user_id' => $context->otherUsers[0]['id']],
			['TsumegoIssue.id' => $context->issues[0]['id']]
		);

		$this->post('/tsumego-issues/reopen/' . $context->issues[0]['id']);

		$this->assertResponseCode(403);
	}

	public function testMoveCommentRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'admin' => false]]);

		$this->post('/tsumego-issues/move-comment/1');

		$this->assertResponseCode(403);
	}

	private function giveFavoritesToTheOtherUser(ContextPreparator $context): void
	{
		ClassRegistry::init('Set')->updateAll(['Set.user_id' => $context->otherUsers[0]['id']], ['Set.title' => 'Favorites']);
	}
}

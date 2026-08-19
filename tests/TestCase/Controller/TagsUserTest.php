<?php

/**
 * Tests for TagsController::user() - the contributions page.
 */
class TagsUserTest extends TestCaseWithAuth
{
	/**
	 * User not found returns 404.
	 */
	public function testUserNotFound()
	{
		$this->expectException(\NotFoundException::class);
		$this->testAction('/tags/user/999999', ['return' => 'view']);
	}

	/**
	 * User with no contributions renders the page with no rows.
	 */
	public function testEmptyContributions()
	{
		new ContextPreparator(['user' => ['name' => 'lonely', 'rating' => 1500]]);
		$user = ClassRegistry::init('User')->findByName('lonely');

		$this->testAction('/tags/user/' . $user['User']['id'], ['return' => 'view']);

		$this->assertTextContains('Tags and proposals by lonely', $this->view);
		// No data rows in the table (only header row)
		$this->assertStringNotContainsString('timeTableLeft', $this->view);
	}

	/**
	 * All six contribution types render with correct labels and status colors.
	 */
	public function testAllSixTypesRenderCorrectly()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'allrounder', 'rating' => 1500],
			'tags' => [['name' => 'tesuji']],
			'tsumegos' => [
				['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19])', 'tags' => [['name' => 'tesuji']]],
				['set_order' => 2, 'sgf' => '(;GM[1]FF[4]SZ[19])'],
				['set_order' => 3, 'sgf' => '(;GM[1]FF[4]SZ[19])'],
			],
		]);

		$userId = $context->user['id'];
		$reject = ClassRegistry::init('Reject');

		// Rejected tag name
		$reject->create();
		$reject->save(['Reject' => [
			'user_id' => $userId, 'tsumego_id' => 0, 'type' => 'tag name', 'text' => 'badname',
		]]);

		// Rejected tag on tsumego 1
		$reject->create();
		$reject->save(['Reject' => [
			'user_id' => $userId, 'tsumego_id' => $context->tsumegos[0]['id'], 'type' => 'tag', 'text' => 'bad tag',
		]]);

		// Rejected proposal on tsumego 2
		$reject->create();
		$reject->save(['Reject' => [
			'user_id' => $userId, 'tsumego_id' => $context->tsumegos[1]['id'], 'type' => 'proposal', 'text' => 'bad sgf',
		]]);

		$this->testAction("/tags/user/{$userId}", ['return' => 'view']);
		$text = strip_tags($this->view);

		// 1. Accepted tag name
		$this->assertStringContainsString('Tag', $text);
		$this->assertStringContainsString('tesuji', $text);
		$this->assertStringContainsString('color:#047804', $this->view); // green = accepted

		// 2. Rejected tag name
		$this->assertStringContainsString('Tag', $text);
		$this->assertStringContainsString('badname', $text);
		$this->assertStringContainsString('color:#ce3a47', $this->view); // red = rejected

		// 3. Accepted tag connection
		$this->assertStringContainsString('was added to', $text);
		$this->assertStringContainsString('tesuji', $text);

		// 4. Rejected tag connection
		$this->assertStringContainsString('bad tag', $text);

		// 5. Accepted proposal
		$this->assertTextContains('Proposal for', $this->view);

		// 6. Rejected proposal
		$this->assertTextContains('Proposal for', $this->view);

		// Verify both colors appear
		$greenCount = substr_count($this->view, '#047804');
		$redCount = substr_count($this->view, '#ce3a47');
		$this->assertGreaterThanOrEqual(3, $greenCount); // 3 accepted items
		$this->assertGreaterThanOrEqual(3, $redCount);   // 3 rejected items
	}

	/**
	 * Items are ordered by created DESC (newest first).
	 */
	public function testOrderingNewestFirst()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'ordered', 'rating' => 1500],
			'tags' => [['name' => 'first'], ['name' => 'second']],
		]);

		$userId = $context->user['id'];

		// Create two tag names with different timestamps
		$tagModel = ClassRegistry::init('Tag');
		$tagModel->create();
		$tagModel->save([
			'Tag' => ['name' => 'alpha', 'user_id' => $userId, 'approved' => 1, 'description' => 'a', 'created' => '2025-01-01 10:00:00'],
		]);
		$tagModel->create();
		$tagModel->save([
			'Tag' => ['name' => 'omega', 'user_id' => $userId, 'approved' => 1, 'description' => 'o', 'created' => '2025-06-01 10:00:00'],
		]);

		$this->testAction("/tags/user/{$userId}", ['return' => 'view']);

		$posAlpha = strpos($this->view, 'alpha');
		$posOmega = strpos($this->view, 'omega');
		$this->assertNotFalse($posAlpha);
		$this->assertNotFalse($posOmega);
		$this->assertLessThan($posAlpha, $posOmega, 'omega (newer) should appear before alpha (older)');
	}

	/**
	 * Rejected items referencing a tsumego without set_connection are excluded.
	 */
	public function testRejectedItemWithoutSetConnectionIsSkipped()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'orphan', 'rating' => 1500],
			'tsumegos' => [
				['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19])'],
			],
		]);

		$userId = $context->user['id'];
		$reject = ClassRegistry::init('Reject');

		// Valid: has set_connection
		$reject->create();
		$reject->save(['Reject' => [
			'user_id' => $userId, 'tsumego_id' => $context->tsumegos[0]['id'], 'type' => 'tag', 'text' => 'visible tag',
		]]);

		// Orphan: tsumego_id that has no set_connection
		$reject->create();
		$reject->save(['Reject' => [
			'user_id' => $userId, 'tsumego_id' => 999999, 'type' => 'tag', 'text' => 'hidden tag',
		]]);

		$this->testAction("/tags/user/{$userId}", ['return' => 'view']);

		$this->assertTextContains('visible tag', $this->view);
		$this->assertTextNotContains('hidden tag', $this->view);
	}

	/**
	 * The page shows the viewed user's name correctly.
	 */
	public function testPageTitleShowsViewedUserName()
	{
		new ContextPreparator(['user' => ['name' => 'pageTitleUser', 'rating' => 1500]]);
		$user = ClassRegistry::init('User')->findByName('pageTitleUser');

		$this->testAction('/tags/user/' . $user['User']['id'], ['return' => 'view']);

		$this->assertTextContains('Tags and proposals by pageTitleUser', $this->view);
	}

	// --- Pending proposals ---

	/**
	 * A pending tag name proposal (approved=0) appears in the contributions list.
	 */
	public function testPendingTagNameAppearsInContributions()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'proposer', 'rating' => 1500],
			'tags' => [['name' => 'snapback', 'approved' => 0]],
		]);

		$this->testAction('/tags/user/' . $context->user['id'], ['return' => 'view']);
		$text = strip_tags($this->view);

		$this->assertStringContainsString('Tag', $text);
		$this->assertStringContainsString('was created', $text);
		$this->assertStringContainsString('snapback', $text);
		$this->assertStringContainsString('pending', $text);
	}

	/**
	 * A pending tag connection (approved=0) appears in the contributions list.
	 */
	public function testPendingTagConnectionAppearsInContributions()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'tagger', 'rating' => 1500],
			'tsumegos' => [[
				'set_order' => 1,
				'sgf' => '(;GM[1]FF[4]SZ[19])',
				'tags' => [['name' => 'atari', 'approved' => 0]],
			]],
		]);

		$this->testAction('/tags/user/' . $context->user['id'], ['return' => 'view']);
		$text = strip_tags($this->view);

		$this->assertStringContainsString('Tag', $text);
		$this->assertStringContainsString('was added to', $text);
		$this->assertStringContainsString('atari', $text);
		$this->assertStringContainsString('pending', $text);
	}

	/**
	 * Pending items appear alongside accepted and rejected items.
	 */
	public function testPendingItemsMixedWithAcceptedAndRejected()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'mixer', 'rating' => 1500],
			'tags' => [['name' => 'tesuji']],
			'tsumegos' => [[
				'set_order' => 1,
				'sgf' => '(;GM[1]FF[4]SZ[19])',
				'tags' => [['name' => 'snapback', 'approved' => 0]],
			]],
		]);

		// Add a rejected tag name
		$reject = ClassRegistry::init('Reject');
		$reject->create();
		$reject->save(['Reject' => [
			'user_id' => $context->user['id'],
			'tsumego_id' => 0,
			'type' => 'tag name',
			'text' => 'badname',
		]]);

		$this->testAction('/tags/user/' . $context->user['id'], ['return' => 'view']);
		$text = strip_tags($this->view);

		// Accepted tag name
		$this->assertStringContainsString('was created', $text);
		$this->assertStringContainsString('accepted', $text);

		// Pending tag connection
		$this->assertStringContainsString('was added to', $text);
		$this->assertStringContainsString('snapback', $text);
		$this->assertStringContainsString('pending', $text);

		// Rejected tag name
		$this->assertStringContainsString('Tag', $text);
		$this->assertStringContainsString('badname', $text);
		$this->assertStringContainsString('rejected', $text);
	}
}

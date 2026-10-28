<?php

use App\Model\Table\RejectTable;
use App\Utility\Auth;

//Tests for TagsController::user() - the contributions page.
class TagsUserTest extends IntegrationTestCase
{
	//User not found returns 404.
	public function testUserNotFound()
	{
		$this->get('/tags/user/999999');

		$this->assertResponseCode(404);
	}

	//User with no contributions renders the page with no rows.
	public function testEmptyContributions()
	{
		new ContextPreparator(['user' => ['name' => 'lonely', 'rating' => 1500]]);
		$user = ClassRegistry::init('User')->findByName('lonely');

		$this->get('/tags/user/' . $user['User']['id']);

		$this->assertTextContains('lonely', $this->view);
		// No legacy classes in the table
		$this->assertStringNotContainsString('timeTableLeft', $this->view);
	}

	//All six contribution types render with correct labels and status colors.
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
		$rejectTable = $this->fetchTable('Reject');

		// Rejected tag name
		$rejectTable->saveOrFail($rejectTable->newEntity([
			'user_id' => $userId, 'reject_type_id' => RejectTable::TYPE_TAG_NAME, 'tag_name' => 'badname',
		]));

		// Rejected tag on tsumego 1
		$rejectTable->saveOrFail($rejectTable->newEntity([
			'user_id' => $userId, 'tsumego_id' => $context->tsumegos[0]['id'], 'reject_type_id' => RejectTable::TYPE_TAG, 'tag_name' => 'bad tag',
		]));

		// Rejected proposal on tsumego 2
		$rejectTable->saveOrFail($rejectTable->newEntity([
			'user_id' => $userId, 'tsumego_id' => $context->tsumegos[1]['id'], 'reject_type_id' => RejectTable::TYPE_PROPOSAL,
		]));

		$this->get("/tags/user/{$userId}");
		$text = strip_tags($this->view);

		// 1. Accepted tag name
		$this->assertStringContainsString('Tag', $text);
		$this->assertStringContainsString('tesuji', $text);
		$this->assertStringContainsString('color:var(--color-green)', $this->view); // green = accepted

		// 2. Rejected tag name
		$this->assertStringContainsString('Tag', $text);
		$this->assertStringContainsString('badname', $text);
		$this->assertStringContainsString('color:var(--color-red)', $this->view); // red = rejected

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
		$greenCount = substr_count($this->view, 'var(--color-green)');
		$redCount = substr_count($this->view, 'var(--color-red)');
		$this->assertGreaterThanOrEqual(3, $greenCount); // 3 accepted items
		$this->assertGreaterThanOrEqual(3, $redCount);   // 3 rejected items
	}

	//Items are ordered by created DESC (newest first).
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

		$this->get("/tags/user/{$userId}");

		$posAlpha = strpos($this->view, 'alpha');
		$posOmega = strpos($this->view, 'omega');
		$this->assertNotFalse($posAlpha);
		$this->assertNotFalse($posOmega);
		$this->assertLessThan($posAlpha, $posOmega, 'omega (newer) should appear before alpha (older)');
	}

	//Rejected items referencing a tsumego without set_connection are excluded.
	public function testRejectedItemWithoutSetConnectionIsSkipped()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'orphan', 'rating' => 1500],
			'tsumegos' => [
				['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19])'],
			],
		]);

		$userId = $context->user['id'];
		$rejectTable = $this->fetchTable('Reject');

		// Valid: has set_connection
		$rejectTable->saveOrFail($rejectTable->newEntity([
			'user_id' => $userId, 'tsumego_id' => $context->tsumegos[0]['id'], 'reject_type_id' => RejectTable::TYPE_TAG, 'tag_name' => 'visible tag',
		]));

		// A tsumego that has no set_connection
		$tsumego = ClassRegistry::init('Tsumego');
		$tsumego->create();
		$tsumego->save(['description' => 'in no set', 'author' => '']);
		$rejectTable->saveOrFail($rejectTable->newEntity([
			'user_id' => $userId, 'tsumego_id' => $tsumego->id, 'reject_type_id' => RejectTable::TYPE_TAG, 'tag_name' => 'hidden tag',
		]));

		$this->get("/tags/user/{$userId}");

		$this->assertTextContains('visible tag', $this->view);
		$this->assertTextNotContains('hidden tag', $this->view);
	}

	//The page shows the viewed user's name correctly.
	public function testPageTitleShowsViewedUserName()
	{
		new ContextPreparator(['user' => ['name' => 'pageTitleUser', 'rating' => 1500]]);
		$user = ClassRegistry::init('User')->findByName('pageTitleUser');

		$this->get('/tags/user/' . $user['User']['id']);

		$this->assertTextContains('pageTitleUser', $this->view);
	}

	// --- Pending proposals ---

	//A pending tag name proposal (approved=0) appears in the contributions list.
	public function testPendingTagNameAppearsInContributions()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'proposer', 'rating' => 1500],
			'tags' => [['name' => 'snapback', 'approved' => 0]],
		]);

		$this->get('/tags/user/' . $context->user['id']);
		$text = strip_tags($this->view);

		$this->assertStringContainsString('Tag', $text);
		$this->assertStringContainsString('was created', $text);
		$this->assertStringContainsString('snapback', $text);
		$this->assertStringContainsString('pending', $text);
	}

	//A pending tag connection (approved=0) appears in the contributions list.
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

		$this->get('/tags/user/' . $context->user['id']);
		$text = strip_tags($this->view);

		$this->assertStringContainsString('Tag', $text);
		$this->assertStringContainsString('was added to', $text);
		$this->assertStringContainsString('atari', $text);
		$this->assertStringContainsString('pending', $text);
	}

	//Pending items appear alongside accepted and rejected items.
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
		$rejectTable = $this->fetchTable('Reject');
		$rejectTable->saveOrFail($rejectTable->newEntity([
			'user_id' => $context->user['id'],
			'reject_type_id' => RejectTable::TYPE_TAG_NAME,
			'tag_name' => 'badname',
		]));

		$this->get('/tags/user/' . $context->user['id']);
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

	// --- Rejections made by an admin ---

	//The admin 'reviewer' is signed in, 'proposer' proposed the tag snapback on a problem.
	private function prepareRejectedTagOnAProblem(): ContextPreparator
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'reviewer', 'admin' => true],
			'other-users' => [['name' => 'proposer']],
			'tsumego' => ['set_order' => 1, 'tags' => [['name' => 'snapback', 'user' => 'proposer', 'approved' => 0]]]]);
		$this->login('reviewer');
		$this->registerTestEmailer();
		$this->post('/users/rejectTagConnectionProposal/' . $context->tsumegos[0]['tag-connections'][0]->id, ['comment' => 'No snapback here.']);
		return $context;
	}

	//It used to show as "Tag  was created", without the tag and the problem.
	public function testRejectedTagOnAProblemShowsTheTagAndTheProblem()
	{
		$context = $this->prepareRejectedTagOnAProblem();

		$this->get('/tags/user/' . $context->otherUsers[0]['id']);

		$this->assertMatchesRegularExpression('/Tag\s+snapback\s+was added to\s+' . preg_quote($context->tsumegos[0]['sets'][0]['title'], '/') . ' - 1\s+rejected/', strip_tags($this->view));
		$this->assertStringContainsString('href="/tags/view/' . $context->tags[0]['id'] . '"', $this->view);
	}

	public function testRejectedTagKeepsItsNameWhenTheTagIsDeleted()
	{
		$context = $this->prepareRejectedTagOnAProblem();
		ClassRegistry::init('Tag')->delete($context->tags[0]['id']);

		$this->get('/tags/user/' . $context->otherUsers[0]['id']);

		$this->assertMatchesRegularExpression('/Tag\s+snapback\s+was added to\s+\S.*\s+rejected/', strip_tags($this->view));
		$this->assertStringNotContainsString('/tags/view/', $this->view);
	}

	public function testRejectedNewTagStaysAmongTheContributions()
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'reviewer', 'admin' => true],
			'other-users' => [['name' => 'proposer']],
			'tags' => [['name' => 'snapback', 'approved' => 0]]]);
		ClassRegistry::init('Tag')->save(['id' => $context->tags[0]['id'], 'user_id' => $context->otherUsers[0]['id']]);
		$this->login('reviewer');
		$this->registerTestEmailer();
		$this->post('/tags/rejectTagProposal/' . $context->tags[0]['id'], ['comment' => 'We have it already.']);

		$this->get('/tags/user/' . $context->otherUsers[0]['id']);

		$this->assertMatchesRegularExpression('/Tag\s+snapback\s+was created\s+rejected/', strip_tags($this->view));
	}
}

<?php

use App\Utility\Auth;
use App\Utility\Util;

//An admin deciding about a proposal (a new SGF version, a tag on a problem, a new tag) writes
//the proposer why, and the proposer gets it as a message from the admin.
class ProposalFeedbackTest extends IntegrationTestCase
{
	private const SGF_VERSION_1 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';
	private const SGF_VERSION_2 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc]AB[dd];B[aa];W[ab];B[ba]C[+])';

	//The admin 'reviewer' is signed in, 'proposer' made the proposals.
	private function prepare(array $tsumego = [], array $tags = []): ContextPreparator
	{
		Auth::logout();
		$options = [
			'user' => ['name' => 'reviewer', 'admin' => true],
			'other-users' => [['name' => 'proposer', 'email' => 'proposer@example.com']]];
		if ($tsumego)
			$options['tsumego'] = $tsumego + ['set_order' => 1, 'status' => 'S'];
		if ($tags)
			$options['tags'] = $tags;
		$context = new ContextPreparator($options);
		$this->login('reviewer');
		$this->registerTestEmailer();
		TestEmailer::$lastEmail = null;
		return $context;
	}

	//A context with an accepted version and a proposal of 'proposer', which is returned.
	private function prepareSgfProposal(?ContextPreparator &$context): array
	{
		$context = $this->prepare(['sgfs' => [
			['data' => self::SGF_VERSION_1, 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
			['data' => self::SGF_VERSION_2, 'sgf_status_id' => Sgf::STATUS_PROPOSED]]]);
		$proposal = $context->tsumegos[0]['sgfs'][1];
		Util::execute('UPDATE sgf SET user_id = ?, description = ? WHERE id = ?', [$context->otherUsers[0]['id'], 'Added the stone at D16', $proposal['id']]);
		return $proposal;
	}

	private function messagesTo(int $userID): array
	{
		return Util::query('SELECT * FROM message WHERE recipient_user_id = ? ORDER BY id', [$userID]);
	}

	public function testRejectedSgfProposalTellsTheProposerWhy(): void
	{
		$proposal = $this->prepareSgfProposal($context);
		$proposer = $context->otherUsers[0];

		$this->post('/users/rejectSGFProposal/' . $proposal['id'], ['comment' => 'The stone at D16 changes nothing.']);

		$this->assertEquals(Sgf::STATUS_REJECTED, ClassRegistry::init('Sgf')->findById($proposal['id'])['Sgf']['sgf_status_id'], 'kept for the link in the message');
		$messages = $this->messagesTo($proposer['id']);
		$this->assertCount(1, $messages);
		$this->assertSame($context->user['id'], (int) $messages[0]['sender_user_id']);
		$text = $messages[0]['text'];
		$this->assertStringContainsString('was rejected by DN_reviewer.', $text);
		$this->assertStringContainsString('You wrote: Added the stone at D16', $text);
		$this->assertStringContainsString('DN_reviewer: The stone at D16 changes nothing.', $text);
		$this->assertStringContainsString('/' . $context->setConnections[0]['id'], $text);
		$current = $context->tsumegos[0]['sgfs'][0];
		$this->assertStringContainsString('Your changes: ' . Router::url('/editor/?sgfID=' . $proposal['id'] . '&diffID=' . $current['id'], true), $text);
		$this->assertSame('proposer@example.com', TestEmailer::$lastEmail['to']);
	}

	//A rejection is listed under the waiting proposals with who decided and why, and can be taken back.
	public function testRejectedSgfProposalCanBeReopened(): void
	{
		$proposal = $this->prepareSgfProposal($context);
		$proposer = $context->otherUsers[0];
		$this->post('/users/rejectSGFProposal/' . $proposal['id'], ['comment' => 'The stone at D16 changes nothing.']);

		$reject = $this->fetchTable('Reject')->find()->where(['sgf_id' => $proposal['id']])->firstOrFail();
		$this->assertSame($proposer['id'], $reject->user_id);
		$this->assertSame($context->user['id'], $reject->rejected_by_user_id);
		$this->assertSame('The stone at D16 changes nothing.', $reject->comment);

		$this->get('/admin/sgf-proposals');
		$this->assertStringContainsString('Rejected SGF Proposals (1)', $this->view);
		$this->assertStringContainsString('SGF Proposals (0)', $this->view);
		$this->assertStringContainsString('href="/editor/?sgfID=' . $proposal['id'] . '&diffID=' . $context->tsumegos[0]['sgfs'][0]['id'] . '"', $this->view);
		$this->assertStringContainsString('The stone at D16 changes nothing.', $this->view);
		$this->assertStringContainsString('id="reopen-' . $proposal['id'] . '"', $this->view);

		$this->post('/users/reopenSGFProposal/' . $proposal['id'], []);

		$this->assertEquals(Sgf::STATUS_PROPOSED, ClassRegistry::init('Sgf')->findById($proposal['id'])['Sgf']['sgf_status_id']);
		$this->assertSame([], Util::query('SELECT * FROM reject WHERE sgf_id = ?', [$proposal['id']]), 'gone from the contributions of the proposer');
		$this->assertSame(1, (int) Util::query('SELECT COUNT(*) AS c FROM admin_activity WHERE type = ?', [AdminActivityType::REOPEN_PROPOSAL])[0]['c']);
		$this->assertCount(1, $this->messagesTo($proposer['id']), 'reopening sends nothing, the next decision does');

		$this->get('/admin/sgf-proposals');
		$this->assertStringContainsString('Rejected SGF Proposals (0)', $this->view);
		$this->assertStringContainsString('id="reject-' . $proposal['id'] . '"', $this->view);
	}

	public function testOnlyARejectedSgfProposalCanBeReopened(): void
	{
		$proposal = $this->prepareSgfProposal($context);

		$this->post('/users/reopenSGFProposal/' . $context->tsumegos[0]['sgfs'][0]['id'], []);
		$this->post('/users/reopenSGFProposal/' . $proposal['id'], []);

		$this->assertEquals(Sgf::STATUS_ACCEPTED, ClassRegistry::init('Sgf')->findById($context->tsumegos[0]['sgfs'][0]['id'])['Sgf']['sgf_status_id']);
		$this->assertEquals(Sgf::STATUS_PROPOSED, ClassRegistry::init('Sgf')->findById($proposal['id'])['Sgf']['sgf_status_id']);
		$this->assertSame([], Util::query('SELECT * FROM admin_activity'));
	}

	public function testSgfProposalIsNotRejectedWithoutAComment(): void
	{
		$proposal = $this->prepareSgfProposal($context);

		$this->post('/users/rejectSGFProposal/' . $proposal['id'], ['comment' => '  ']);

		$this->assertNotEmpty(ClassRegistry::init('Sgf')->findById($proposal['id']));
		$this->assertSame([], $this->messagesTo($context->otherUsers[0]['id']));
	}

	public function testAcceptedSgfProposalTellsTheProposer(): void
	{
		$proposal = $this->prepareSgfProposal($context);

		$this->post('/users/acceptSGFProposal/' . $proposal['id'], ['comment' => '']);

		$this->assertEquals(Sgf::STATUS_ACCEPTED, ClassRegistry::init('Sgf')->findById($proposal['id'])['Sgf']['sgf_status_id']);
		$messages = $this->messagesTo($context->otherUsers[0]['id']);
		$this->assertCount(1, $messages);
		$this->assertStringContainsString('was accepted by DN_reviewer, it is the current version of the problem now.', $messages[0]['text']);
		$this->assertStringNotContainsString('DN_reviewer:', $messages[0]['text']);
		$replaced = $context->tsumegos[0]['sgfs'][0];
		$this->assertStringContainsString('Your changes: ' . Router::url('/editor/?sgfID=' . $proposal['id'] . '&diffID=' . $replaced['id'], true), $messages[0]['text']);
	}

	public function testAcceptingOwnProposalSendsNothing(): void
	{
		$context = $this->prepare(['sgfs' => [
			['data' => self::SGF_VERSION_1, 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
			['data' => self::SGF_VERSION_2, 'sgf_status_id' => Sgf::STATUS_PROPOSED]]]);

		$this->post('/users/acceptSGFProposal/' . $context->tsumegos[0]['sgfs'][1]['id'], ['comment' => 'Thanks me']);

		$this->assertSame([], Util::query('SELECT * FROM message'));
	}

	public function testRejectedTagOnProblemTellsTheProposerWhy(): void
	{
		$context = $this->prepare(['tags' => [['name' => 'snapback', 'user' => 'proposer', 'approved' => 0]]]);
		$tagConnectionID = $context->tsumegos[0]['tag-connections'][0]->id;

		$this->post('/users/rejectTagConnectionProposal/' . $tagConnectionID, ['comment' => 'There is no snapback here.']);

		$this->assertFalse($this->fetchTable('TagConnection')->exists(['id' => $tagConnectionID]));
		$messages = $this->messagesTo($context->otherUsers[0]['id']);
		$this->assertCount(1, $messages);
		$this->assertStringContainsString('Your tag snapback on ', $messages[0]['text']);
		$this->assertStringContainsString('was rejected by DN_reviewer.', $messages[0]['text']);
		$this->assertStringContainsString('DN_reviewer: There is no snapback here.', $messages[0]['text']);
	}

	public function testTagOnProblemIsNotRejectedWithoutAComment(): void
	{
		$context = $this->prepare(['tags' => [['name' => 'snapback', 'user' => 'proposer', 'approved' => 0]]]);
		$tagConnectionID = $context->tsumegos[0]['tag-connections'][0]->id;

		$this->post('/users/rejectTagConnectionProposal/' . $tagConnectionID, []);

		$this->assertTrue($this->fetchTable('TagConnection')->exists(['id' => $tagConnectionID]));
		$this->assertSame([], Util::query('SELECT * FROM message'));
	}

	//Tags are accepted by the dozen, a message only comes with a comment.
	public function testAcceptedTagOnProblemTellsTheProposerOnlyWithAComment(): void
	{
		$context = $this->prepare(['tags' => [
			['name' => 'snapback', 'user' => 'proposer', 'approved' => 0],
			['name' => 'ladder', 'user' => 'proposer', 'approved' => 0]]]);
		$proposerID = $context->otherUsers[0]['id'];

		$this->post('/users/acceptTagConnectionProposal/' . $context->tsumegos[0]['tag-connections'][0]->id, ['comment' => '']);
		$this->assertSame([], $this->messagesTo($proposerID));

		$this->post('/users/acceptTagConnectionProposal/' . $context->tsumegos[0]['tag-connections'][1]->id, ['comment' => 'Nice find.']);
		$messages = $this->messagesTo($proposerID);
		$this->assertCount(1, $messages);
		$this->assertStringContainsString('Your tag ladder on ', $messages[0]['text']);
		$this->assertStringContainsString('was accepted by DN_reviewer.', $messages[0]['text']);
	}

	public function testRejectedNewTagTellsTheProposerWhy(): void
	{
		$context = $this->prepare([], [['name' => 'snapback', 'approved' => 0]]);
		$tagID = $context->tags[0]['id'];
		ClassRegistry::init('Tag')->save(['id' => $tagID, 'user_id' => $context->otherUsers[0]['id']]);

		$this->post('/tags/rejectTagProposal/' . $tagID, []);
		$this->assertNotEmpty(ClassRegistry::init('Tag')->findById($tagID), 'rejected without a comment');

		$this->post('/tags/rejectTagProposal/' . $tagID, ['comment' => 'We have it already.']);
		$this->assertEmpty(ClassRegistry::init('Tag')->findById($tagID));
		$messages = $this->messagesTo($context->otherUsers[0]['id']);
		$this->assertCount(1, $messages);
		$this->assertStringContainsString('Your new tag snapback was rejected by DN_reviewer.', $messages[0]['text']);
		$this->assertStringContainsString('DN_reviewer: We have it already.', $messages[0]['text']);
	}

	public function testProposalNeedsADescription(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'proposer', 'rating' => App\Utility\Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => self::SGF_VERSION_1]]);
		$this->login('proposer');
		$data = ['sgf' => self::SGF_VERSION_2, 'firstMoveColor' => 'B', 'correctMoves' => 'aa', 'setConnectionID' => $context->setConnections[0]['id']];

		$this->disableErrorHandlerMiddleware();
		try
		{
			$this->post('/tsumegos/setupNewSgfStep2', $data + ['description' => ' ']);
			$this->fail('A proposal without a description was saved');
		}
		catch (BadRequestException $e)
		{
			$this->assertSame('Describe what you changed and why.', $e->getMessage());
		}

		$this->post('/tsumegos/setupNewSgfStep2', $data + ['description' => "Added D16\r\nbecause it is needed"]);
		$proposal = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']], 'order' => 'id DESC'])['Sgf'];
		$this->assertEquals(Sgf::STATUS_PROPOSED, $proposal['sgf_status_id']);
		$this->assertNull($proposal['accepted_by_user_id'], 'an admin accepts it later');
		$this->assertSame("Added D16\nbecause it is needed", $proposal['description']);
	}
}

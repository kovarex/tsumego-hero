<?php

use App\Utility\Auth;
use App\Utility\Util;

/**
 * An admin deciding about a proposal (a new SGF version, a tag on a problem, a new tag) writes
 * the proposer why, and the proposer gets it as a message from the admin.
 */
class ProposalFeedbackTest extends TestCaseWithAuth
{
	private const SGF_VERSION_1 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';
	private const SGF_VERSION_2 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc]AB[dd];B[aa];W[ab];B[ba]C[+])';

	/** The admin 'reviewer' is signed in, 'proposer' made the proposals. */
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
		TestEmailer::$lastEmail = null;
		return $context;
	}

	/** A context with an accepted version and a proposal of 'proposer', which is returned. */
	private function prepareSgfProposal(?ContextPreparator &$context): array
	{
		$context = $this->prepare(['sgfs' => [
			['data' => self::SGF_VERSION_1, 'accepted' => true],
			['data' => self::SGF_VERSION_2, 'accepted' => false]]]);
		$proposal = $context->tsumegos[0]['sgfs'][1];
		Util::execute('UPDATE sgf SET user_id = ?, description = ? WHERE id = ?', [$context->otherUsers[0]['id'], 'Added the stone at D16', $proposal['id']]);
		return $proposal;
	}

	private function post(string $controller, string $url, array $data): void
	{
		$this->registerTestEmailer($controller);
		$this->testAction($url, ['method' => 'post', 'data' => $data]);
	}

	private function messagesTo(int $userID): array
	{
		return Util::query('SELECT * FROM message WHERE recipient_user_id = ? ORDER BY id', [$userID]);
	}

	public function testRejectedSgfProposalTellsTheProposerWhy(): void
	{
		$proposal = $this->prepareSgfProposal($context);
		$proposer = $context->otherUsers[0];

		$this->post('Users', '/users/rejectSGFProposal/' . $proposal['id'], ['comment' => 'The stone at D16 changes nothing.']);

		$this->assertEmpty(ClassRegistry::init('Sgf')->findById($proposal['id']));
		$messages = $this->messagesTo($proposer['id']);
		$this->assertCount(1, $messages);
		$this->assertSame($context->user['id'], (int) $messages[0]['sender_user_id']);
		$text = $messages[0]['text'];
		$this->assertStringContainsString('was rejected by DN_reviewer.', $text);
		$this->assertStringContainsString('You wrote: Added the stone at D16', $text);
		$this->assertStringContainsString('DN_reviewer: The stone at D16 changes nothing.', $text);
		$this->assertStringContainsString('/' . $context->setConnections[0]['id'], $text);
		$this->assertSame('proposer@example.com', TestEmailer::$lastEmail['to']);
	}

	public function testSgfProposalIsNotRejectedWithoutAComment(): void
	{
		$proposal = $this->prepareSgfProposal($context);

		$this->post('Users', '/users/rejectSGFProposal/' . $proposal['id'], ['comment' => '  ']);

		$this->assertNotEmpty(ClassRegistry::init('Sgf')->findById($proposal['id']));
		$this->assertSame([], $this->messagesTo($context->otherUsers[0]['id']));
	}

	public function testAcceptedSgfProposalTellsTheProposer(): void
	{
		$proposal = $this->prepareSgfProposal($context);

		$this->post('Users', '/users/acceptSGFProposal/' . $proposal['id'], ['comment' => '']);

		$this->assertEquals(1, ClassRegistry::init('Sgf')->findById($proposal['id'])['Sgf']['accepted']);
		$messages = $this->messagesTo($context->otherUsers[0]['id']);
		$this->assertCount(1, $messages);
		$this->assertStringContainsString('was accepted by DN_reviewer, it is the current version of the problem now.', $messages[0]['text']);
		$this->assertStringNotContainsString('DN_reviewer:', $messages[0]['text']);
	}

	public function testAcceptingOwnProposalSendsNothing(): void
	{
		$context = $this->prepare(['sgfs' => [
			['data' => self::SGF_VERSION_1, 'accepted' => true],
			['data' => self::SGF_VERSION_2, 'accepted' => false]]]);

		$this->post('Users', '/users/acceptSGFProposal/' . $context->tsumegos[0]['sgfs'][1]['id'], ['comment' => 'Thanks me']);

		$this->assertSame([], Util::query('SELECT * FROM message'));
	}

	public function testRejectedTagOnProblemTellsTheProposerWhy(): void
	{
		$context = $this->prepare(['tags' => [['name' => 'snapback', 'user' => 'proposer', 'approved' => 0]]]);
		$tagConnectionID = $context->tsumegos[0]['tag-connections'][0]['id'];

		$this->post('Users', '/users/rejectTagConnectionProposal/' . $tagConnectionID, ['comment' => 'There is no snapback here.']);

		$this->assertEmpty(ClassRegistry::init('TagConnection')->findById($tagConnectionID));
		$messages = $this->messagesTo($context->otherUsers[0]['id']);
		$this->assertCount(1, $messages);
		$this->assertStringContainsString('Your tag snapback on ', $messages[0]['text']);
		$this->assertStringContainsString('was rejected by DN_reviewer.', $messages[0]['text']);
		$this->assertStringContainsString('DN_reviewer: There is no snapback here.', $messages[0]['text']);
	}

	public function testTagOnProblemIsNotRejectedWithoutAComment(): void
	{
		$context = $this->prepare(['tags' => [['name' => 'snapback', 'user' => 'proposer', 'approved' => 0]]]);
		$tagConnectionID = $context->tsumegos[0]['tag-connections'][0]['id'];

		$this->post('Users', '/users/rejectTagConnectionProposal/' . $tagConnectionID, []);

		$this->assertNotEmpty(ClassRegistry::init('TagConnection')->findById($tagConnectionID));
		$this->assertSame([], Util::query('SELECT * FROM message'));
	}

	/** Tags are accepted by the dozen, a message only comes with a comment. */
	public function testAcceptedTagOnProblemTellsTheProposerOnlyWithAComment(): void
	{
		$context = $this->prepare(['tags' => [
			['name' => 'snapback', 'user' => 'proposer', 'approved' => 0],
			['name' => 'ladder', 'user' => 'proposer', 'approved' => 0]]]);
		$proposerID = $context->otherUsers[0]['id'];

		$this->post('Users', '/users/acceptTagConnectionProposal/' . $context->tsumegos[0]['tag-connections'][0]['id'], ['comment' => '']);
		$this->assertSame([], $this->messagesTo($proposerID));

		$this->post('Users', '/users/acceptTagConnectionProposal/' . $context->tsumegos[0]['tag-connections'][1]['id'], ['comment' => 'Nice find.']);
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

		$this->post('Tags', '/tags/rejectTagProposal/' . $tagID, []);
		$this->assertNotEmpty(ClassRegistry::init('Tag')->findById($tagID), 'rejected without a comment');

		$this->post('Tags', '/tags/rejectTagProposal/' . $tagID, ['comment' => 'We have it already.']);
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

		try
		{
			$this->testAction('/tsumegos/setupNewSgfStep2', ['method' => 'post', 'data' => $data + ['description' => ' ']]);
			$this->fail('A proposal without a description was saved');
		}
		catch (BadRequestException $e)
		{
			$this->assertSame('Describe what you changed and why.', $e->getMessage());
		}

		$this->testAction('/tsumegos/setupNewSgfStep2', ['method' => 'post', 'data' => $data + ['description' => "Added D16\r\nbecause it is needed"]]);
		$proposal = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']], 'order' => 'id DESC'])['Sgf'];
		$this->assertEquals(0, $proposal['accepted']);
		$this->assertSame("Added D16\nbecause it is needed", $proposal['description']);
	}
}

<?php

use App\Lib\Error\UnprocessableEntityException;
use App\Utility\Auth;
use App\Utility\Util;

/**
 * Messages between players: sending, the email that announces one, the conversations
 * and the search of a player to write to.
 */
class MessagesControllerTest extends TestCaseWithAuth
{
	/**
	 * The player 'messageSender' is signed in, the others can be written to.
	 *
	 * @param list<array<string, mixed>> $others
	 */
	private function prepare(array $others = [['name' => 'messageRecipient', 'email' => 'recipient@example.com']]): ContextPreparator
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'messageSender'], 'other-users' => $others]);
		$this->login('messageSender');
		TestEmailer::$lastEmail = null;
		return $context;
	}

	private function send(int $recipientUserId, string $text, bool $withEmailer = true): array
	{
		if ($withEmailer)
			$this->registerTestEmailer('Messages');
		$this->testAction('/messages/conversations/' . $recipientUserId, [
			'method' => 'post',
			'data' => json_encode(['text' => $text]),
			'return' => 'contents',
		]);
		return json_decode($this->controller->response->body(), true);
	}

	private function getJson(string $url): array
	{
		$this->testAction($url, ['method' => 'get', 'return' => 'contents']);
		return json_decode($this->controller->response->body(), true);
	}

	/**
	 * @return string|null The message of the refusal, null when the message was sent
	 */
	private function refusal(int $recipientUserId, string $text): ?string
	{
		try
		{
			$this->send($recipientUserId, $text);
		}
		catch (UnprocessableEntityException $e)
		{
			return $e->getMessage();
		}
		return null;
	}

	private function storedMessages(): array
	{
		return Util::query('SELECT * FROM message ORDER BY id');
	}

	public function testSendStoresTheMessage(): void
	{
		$context = $this->prepare();
		$recipient = $context->otherUsers[0];

		$response = $this->send($recipient['id'], "  Hello,\r\nnice problem!  ");

		$stored = $this->storedMessages();
		$this->assertCount(1, $stored);
		$this->assertSame($context->user['id'], (int) $stored[0]['sender_user_id']);
		$this->assertSame($recipient['id'], (int) $stored[0]['recipient_user_id']);
		$this->assertSame("Hello,\nnice problem!", $stored[0]['text']);
		$this->assertNull($stored[0]['read_at']);
		$this->assertSame((int) $stored[0]['id'], $response['message']['id']);
		$this->assertSame("Hello,\nnice problem!", $response['message']['text']);
		$this->assertSame($context->user['id'], $response['message']['senderUserId']);
	}

	public function testTheRecipientIsEmailed(): void
	{
		$context = $this->prepare();
		$recipient = $context->otherUsers[0];

		$this->send($recipient['id'], 'Hello there');

		$email = TestEmailer::$lastEmail;
		$this->assertSame('recipient@example.com', $email['to']);
		$this->assertSame('New message from DN_messageSender on Tsumego Hero', $email['subject']);
		$this->assertStringContainsString('Hello there', $email['body']);
		$this->assertStringContainsString('/messages/' . $context->user['id'], $email['body']);
		$this->assertStringContainsString('/users/settings', $email['body']);
	}

	/**
	 * A lively conversation would otherwise send an email for every line. The next email
	 * comes once the recipient read what was announced.
	 */
	public function testOnlyTheFirstUnreadMessageIsEmailed(): void
	{
		$context = $this->prepare();
		$recipient = $context->otherUsers[0];

		$this->send($recipient['id'], 'First');
		$this->assertSame('recipient@example.com', TestEmailer::$lastEmail['to']);

		$this->send($recipient['id'], 'Second');
		$this->assertSame([], TestEmailer::$lastEmail, 'The second unread message was emailed too');

		Message::markRead($recipient['id'], $context->user['id']);
		$this->send($recipient['id'], 'Third');
		$this->assertStringContainsString('Third', TestEmailer::$lastEmail['body']);
	}

	public function testNoEmailWhenTheRecipientTurnedItOff(): void
	{
		$context = $this->prepare([['name' => 'messageQuiet', 'email_on_new_message' => 0]]);

		$this->send($context->otherUsers[0]['id'], 'Hello');

		$this->assertCount(1, $this->storedMessages());
		$this->assertSame([], TestEmailer::$lastEmail);
	}

	/**
	 * The message is what the sender wanted to deliver; an email server that does not answer
	 * only loses the announcement.
	 */
	public function testTheMessageIsKeptWhenTheEmailCannotBeSent(): void
	{
		$context = $this->prepare();

		try
		{
			TestEmailer::$unreachable = true;
			$this->send($context->otherUsers[0]['id'], 'Hello');
		}
		finally
		{
			TestEmailer::$unreachable = false;
		}

		$this->assertCount(1, $this->storedMessages());
	}

	public function testRefusedMessages(): void
	{
		$context = $this->prepare([['name' => 'messageRecipient'], ['name' => 'messageGone', 'deleted' => true]]);
		$recipientId = $context->otherUsers[0]['id'];

		$this->assertSame('The message is empty.', $this->refusal($recipientId, " \n "));
		$this->assertStringStartsWith('The message is too long', $this->refusal($recipientId, str_repeat('a', Message::MAX_LENGTH + 1)));
		$this->assertSame('You can not send a message to yourself.', $this->refusal($context->user['id'], 'Hi me'));
		$this->assertSame('The player does not exist.', $this->refusal($context->otherUsers[1]['id'], 'Hello'));
		$this->assertSame([], $this->storedMessages());

		$this->assertNull($this->refusal($recipientId, str_repeat('a', Message::MAX_LENGTH)));
	}

	public function testUnknownRecipientIsNotFound(): void
	{
		$context = $this->prepare();

		$this->expectException(NotFoundException::class);
		$this->send($context->otherUsers[0]['id'] + 1000, 'Hello');
	}

	public function testGuestCanNotSend(): void
	{
		$context = $this->prepare();
		$this->logout();

		$this->expectException(UnauthorizedException::class);
		$this->send($context->otherUsers[0]['id'], 'Hello');
	}

	/**
	 * Writing to many players who never answer is what a spammer does, so it stops for the
	 * day; players who wrote back can always be answered.
	 */
	public function testPlayersWhoDidNotAnswerAreLimitedPerDay(): void
	{
		$others = [];
		for ($i = 0; $i <= Message::DAILY_UNANSWERED_RECIPIENTS + 1; $i++)
			$others[] = ['name' => 'messageStranger' . $i];
		$context = $this->prepare($others);
		$strangers = $context->otherUsers;

		for ($i = 0; $i < Message::DAILY_UNANSWERED_RECIPIENTS; $i++)
			$this->assertNull($this->refusal($strangers[$i]['id'], 'Hello ' . $i));

		$overLimit = $strangers[Message::DAILY_UNANSWERED_RECIPIENTS];
		$this->assertStringStartsWith('You wrote to ' . Message::DAILY_UNANSWERED_RECIPIENTS . ' players', (string) $this->refusal($overLimit['id'], 'Hello'));

		// One more message to a player already written to is the same conversation.
		$this->assertNull($this->refusal($strangers[0]['id'], 'Are you there?'));

		// A player who wrote first can be answered.
		$friend = $strangers[Message::DAILY_UNANSWERED_RECIPIENTS + 1];
		Message::send($friend['id'], $context->user['id'], 'Hi!', new TestEmailer());
		$this->assertNull($this->refusal($friend['id'], 'Hi back'));

		// An answer frees the place its sender took.
		Message::send($strangers[1]['id'], $context->user['id'], 'Hello to you', new TestEmailer());
		$this->assertNull($this->refusal($overLimit['id'], 'Hello'));
	}

	public function testConversationsNewestFirstWithUnreadCounts(): void
	{
		$context = $this->prepare([['name' => 'messageOld'], ['name' => 'messageNew', 'deleted' => true]]);
		[$old, $gone] = $context->otherUsers;
		$me = $context->user['id'];
		Message::send($me, $old['id'], 'Hi old friend', new TestEmailer());
		Message::send($old['id'], $me, 'Hello', new TestEmailer());
		Message::send($old['id'], $me, 'How are you?', new TestEmailer());
		Util::execute('INSERT INTO message (sender_user_id, recipient_user_id, text, created) VALUES (?, ?, ?, NOW())', [$gone['id'], $me, 'Bye']);

		$conversations = $this->getJson('/messages/conversations')['conversations'];

		$this->assertCount(2, $conversations);
		$this->assertSame($gone['id'], $conversations[0]['partner']['id']);
		$this->assertNull($conversations[0]['partner']['displayName'], 'A deleted account is shown without its name');
		$this->assertSame('Bye', $conversations[0]['lastMessage']['text']);
		$this->assertSame(1, $conversations[0]['unreadCount']);
		$this->assertSame('DN_messageOld', $conversations[1]['partner']['displayName']);
		$this->assertSame('How are you?', $conversations[1]['lastMessage']['text']);
		$this->assertSame(2, $conversations[1]['unreadCount']);
	}

	public function testConversationLoadsOlderMessagesOnRequest(): void
	{
		$context = $this->prepare();
		$me = $context->user['id'];
		$partner = $context->otherUsers[0]['id'];
		$total = Message::PAGE_SIZE + 5;
		for ($i = 1; $i <= $total; $i++)
			Util::execute(
				'INSERT INTO message (sender_user_id, recipient_user_id, text, created) VALUES (?, ?, ?, NOW())',
				$i % 2 ? [$me, $partner, 'Message ' . $i] : [$partner, $me, 'Message ' . $i]);

		$newest = $this->getJson('/messages/conversations/' . $partner);
		$this->assertSame($partner, $newest['partner']['id']);
		$this->assertTrue($newest['hasOlder']);
		$this->assertCount(Message::PAGE_SIZE, $newest['messages']);
		$this->assertSame('Message 6', $newest['messages'][0]['text']);
		$this->assertSame('Message ' . $total, end($newest['messages'])['text']);

		$older = $this->getJson('/messages/conversations/' . $partner . '?before=' . $newest['messages'][0]['id']);
		$this->assertFalse($older['hasOlder']);
		$this->assertSame(['Message 1', 'Message 2', 'Message 3', 'Message 4', 'Message 5'], array_column($older['messages'], 'text'));
	}

	public function testOpeningAConversationReadsOnlyThePartnersMessages(): void
	{
		$context = $this->prepare([['name' => 'messageFirst'], ['name' => 'messageSecond']]);
		$me = $context->user['id'];
		[$first, $second] = $context->otherUsers;
		Message::send($first['id'], $me, 'From first', new TestEmailer());
		Message::send($second['id'], $me, 'From second', new TestEmailer());
		Message::send($me, $first['id'], 'To first', new TestEmailer());

		$this->testAction('/messages/conversations/' . $first['id'] . '/read', ['method' => 'post', 'return' => 'contents']);

		$this->assertSame(['unreadCount' => 1], json_decode($this->controller->response->body(), true));
		$this->assertSame(0, Message::unreadCountFrom($me, $first['id']));
		$this->assertSame(1, Message::unreadCountFrom($me, $second['id']));
		$this->assertSame(1, Message::unreadCountFrom($first['id'], $me), 'The message to the partner became read too');
	}

	public function testRecipientSearch(): void
	{
		$context = $this->prepare([
			['name' => 'search1', 'display_name' => 'Go Seigen'],
			['name' => 'search2', 'display_name' => 'Seigo'],
			['name' => 'search3', 'display_name' => 'Old Seigen', 'deleted' => true],
			['name' => 'search4', 'display_name' => '100% Seigen'],
		]);

		$names = fn(string $query) => array_column($this->getJson('/messages/recipients?query=' . urlencode($query))['players'], 'displayName');

		$this->assertSame(['Seigo', 'Go Seigen', '100% Seigen'], $names('seig'), 'A name that starts with the query comes first');
		$this->assertSame(['100% Seigen'], $names('0%'), 'The % of a name is not a wildcard');
		$this->assertSame([], $names('messageSender'), 'The player searching is left out');
		$this->assertSame([], $names(' '));

		$player = $this->getJson('/messages/recipients?query=Seigo')['players'][0];
		$this->assertSame($context->otherUsers[1]['id'], $player['id']);
		$this->assertNotEmpty($player['avatarUrl']);
	}

	public function testGuestIsSentToLoginFromThePage(): void
	{
		$this->prepare();
		$this->logout();

		$this->testAction('/messages', ['method' => 'get']);

		$this->assertStringEndsWith('/users/login', $this->headers['Location']);
	}

	public function testEmailPreferenceIsStored(): void
	{
		$context = $this->prepare();

		$this->testAction('/users/emailOnNewMessagePreference', ['method' => 'post', 'data' => ['enabled' => '0']]);
		$this->assertSame(0, (int) $context->reloadUser()['email_on_new_message']);

		$this->testAction('/users/emailOnNewMessagePreference', ['method' => 'post', 'data' => ['enabled' => '1']]);
		$this->assertSame(1, (int) $context->reloadUser()['email_on_new_message']);
	}
}

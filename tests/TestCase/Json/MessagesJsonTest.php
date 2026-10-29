<?php

use App\Model\Table\MessageTable;
use App\Utility\Auth;

//The JSON of the messages between players, which the messages page reads.
class MessagesJsonTest extends JsonGoldenTestCase
{
	//'messageSender' is signed in and wrote with Anna, and Bob wrote to them
	private function prepareConversations(): ContextPreparator
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'messageSender'],
			'other-users' => [['name' => 'anna', 'display_name' => 'Anna Seigen'], ['name' => 'bob', 'display_name' => 'Bob']],
		]);
		$this->login('messageSender');
		[$anna, $bob] = $context->otherUsers;
		$me = $context->user['id'];
		MessageTable::send($me, $anna['id'], 'Hello Anna', new TestEmailer());
		MessageTable::send($anna['id'], $me, 'Hi, how are you?', new TestEmailer());
		MessageTable::send($bob['id'], $me, 'A question about problem 5', new TestEmailer());
		return $context;
	}

	public function testConversations(): void
	{
		$this->prepareConversations();
		$this->get('/messages/conversations');
		$this->assertJsonGolden('conversations');
	}

	public function testConversation(): void
	{
		$context = $this->prepareConversations();
		$this->get('/messages/conversations/' . $context->otherUsers[0]['id']);
		$this->assertJsonGolden('conversation');
	}

	public function testConversationBefore(): void
	{
		$context = $this->prepareConversations();
		$newest = $this->fetchTable('Message')->find()->where(['text' => 'Hi, how are you?'])->firstOrFail();
		$this->get('/messages/conversations/' . $context->otherUsers[0]['id'] . '?before=' . $newest->id);
		$this->assertJsonGolden('conversation before');
	}

	public function testSend(): void
	{
		$context = $this->prepareConversations();
		$this->registerTestEmailer();
		$this->post('/messages/conversations/' . $context->otherUsers[1]['id'], json_encode(['text' => 'It is solved by the hane']));
		$this->assertJsonGolden('send');
	}

	public function testRead(): void
	{
		$context = $this->prepareConversations();
		$this->post('/messages/conversations/' . $context->otherUsers[0]['id'] . '/read');
		$this->assertJsonGolden('read');
	}

	public function testRecipients(): void
	{
		$this->prepareConversations();
		$this->get('/messages/recipients?query=' . urlencode('seig'));
		$this->assertJsonGolden('recipients');
	}
}

<?php

use App\Model\Table\RejectsTable;

//The Cake 5 ORM runs on the connection of Cake 2, see SharedConnectionDriver.
class SharedConnectionDriverTest extends IntegrationTestCase
{
	private function saveReject(int $userID): void
	{
		$rejects = $this->fetchTable('Rejects');
		$rejects->saveOrFail($rejects->newEntity(['user_id' => $userID, 'reject_type_id' => RejectsTable::TYPE_TAG_NAME, 'tag_name' => 'ko']));
	}

	public function testTransactionOfCakeTwoCoversWhatCakeFiveSaves(): void
	{
		$context = new ContextPreparator();
		$dataSource = ClassRegistry::init('User')->getDataSource();

		$dataSource->begin();
		$this->saveReject($context->user['id']);
		$this->assertSame(1, $this->fetchTable('Rejects')->find()->count());
		$dataSource->rollback();

		$this->assertSame(0, $this->fetchTable('Rejects')->find()->count());
	}

	public function testCakeTwoReadsWhatCakeFiveSaved(): void
	{
		$context = new ContextPreparator();
		$this->saveReject($context->user['id']);

		$this->assertSame('ko', ClassRegistry::init('User')->query('SELECT tag_name FROM reject')[0]['reject']['tag_name']);
	}

	public function testSavedRowIsTypedAndHasItsCreationTime(): void
	{
		$context = new ContextPreparator();
		$this->saveReject($context->user['id']);

		$reject = $this->fetchTable('Rejects')->find()->firstOrFail();
		$this->assertSame($context->user['id'], $reject->user_id);
		$this->assertSame(RejectsTable::TYPE_TAG_NAME, $reject->reject_type_id);
		$this->assertNull($reject->tsumego_id);
		$this->assertEqualsWithDelta(time(), $reject->created->getTimestamp(), 60);
	}
}

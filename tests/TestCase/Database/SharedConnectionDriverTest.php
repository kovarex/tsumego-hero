<?php

use App\Model\Entity\Reject;
use App\Model\Table\RejectTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Utility\Util;
use Cake\Datasource\Exception\MissingPropertyException;

//The Cake 5 ORM runs on the connection of Cake 2, see SharedConnectionDriver.
class SharedConnectionDriverTest extends IntegrationTestCase
{
	private function saveReject(int $userID): void
	{
		$rejectTable = $this->fetchTable('Reject');
		$rejectTable->saveOrFail($rejectTable->newEntity(['user_id' => $userID, 'reject_type_id' => RejectTable::TYPE_TAG_NAME, 'tag_name' => 'ko']));
	}

	public function testTransactionOfCakeTwoCoversWhatCakeFiveSaves(): void
	{
		$context = new ContextPreparator();
		$dataSource = Util::database();

		$dataSource->begin();
		$this->saveReject($context->user['id']);
		$this->assertSame(1, $this->fetchTable('Reject')->find()->count());
		$dataSource->rollback();

		$this->assertSame(0, $this->fetchTable('Reject')->find()->count());
	}

	public function testCakeTwoReadsWhatCakeFiveSaved(): void
	{
		$context = new ContextPreparator();
		$this->saveReject($context->user['id']);

		$this->assertSame('ko', Util::database()->fetchAll('SELECT tag_name FROM reject')[0]['reject']['tag_name']);
	}

	//The singular names of the database need no configuration, see AppTable.
	public function testSingularNameGivesTheTableAndTheEntity(): void
	{
		$rejectTable = $this->fetchTable('Reject');

		$this->assertInstanceOf(RejectTable::class, $rejectTable);
		$this->assertSame('reject', $rejectTable->getTable());
		$this->assertSame(Reject::class, $rejectTable->getEntityClass());
	}

	//Code still reading the Cake 2 shape fails loudly, see AppEntity.
	public function testReadingAFieldTheRowDoesNotHaveThrows(): void
	{
		$category = $this->fetchTable('TimeModeCategory')->get(TimeModeCategoryTable::BLITZ);

		$this->expectException(MissingPropertyException::class);
		$category['TimeModeCategory'];
	}

	public function testSavedRowIsTypedAndHasItsCreationTime(): void
	{
		$context = new ContextPreparator();
		$this->saveReject($context->user['id']);

		$reject = $this->fetchTable('Reject')->find()->firstOrFail();
		$this->assertSame($context->user['id'], $reject->user_id);
		$this->assertSame(RejectTable::TYPE_TAG_NAME, $reject->reject_type_id);
		$this->assertNull($reject->tsumego_id);
		$this->assertEqualsWithDelta(time(), $reject->created->getTimestamp(), 60);
	}
}

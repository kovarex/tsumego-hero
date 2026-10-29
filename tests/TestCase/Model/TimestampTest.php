<?php

use App\Utility\Util;

//Every table fills created on a new row and modified on every save, with the time of PHP in UTC, where
//it has those columns and the save doesn't set them itself (AppTable, the Timestamp behavior).
class TimestampTest extends IntegrationTestCase
{
	//mistake_training_pool has both columns, without a default of the database
	private function savePoolRow(array $fields = []): int
	{
		$context = new ContextPreparator(['user' => ['name' => 'kovarex'], 'tsumego' => ['set_order' => 1]]);
		$poolTable = $this->fetchTable('MistakeTrainingPool');
		return $poolTable->saveOrFail($poolTable->newEntity($fields + [
			'user_id' => $context->user['id'],
			'tsumego_id' => $context->tsumegos[0]['id']]))->id;
	}

	//The columns as the database holds them
	private static function poolRow(int $id): array
	{
		return Util::query('SELECT created, modified FROM mistake_training_pool WHERE id = ?', [$id])[0];
	}

	private function assertNow(string $written, string $message): void
	{
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $written, $message);
		$this->assertEqualsWithDelta(time(), (new DateTimeImmutable($written, new DateTimeZone('UTC')))->getTimestamp(), 5, $message . ' is now in UTC');
	}

	public function testANewRowGetsCreatedAndModified(): void
	{
		$row = self::poolRow($this->savePoolRow());

		$this->assertNow($row['created'], 'created');
		$this->assertNow($row['modified'], 'modified');
	}

	public function testANewRowKeepsTheTimesItIsSavedWith(): void
	{
		$row = self::poolRow($this->savePoolRow(['created' => '2026-01-01 10:00:00', 'modified' => '2026-01-02 10:00:00']));

		$this->assertSame(['created' => '2026-01-01 10:00:00', 'modified' => '2026-01-02 10:00:00'], $row);
	}

	public function testAChangedRowGetsModifiedAndKeepsCreated(): void
	{
		$id = $this->savePoolRow(['created' => '2026-01-01 10:00:00', 'modified' => '2026-01-01 10:00:00']);
		$poolTable = $this->fetchTable('MistakeTrainingPool');
		$poolTable->saveOrFail($poolTable->get($id)->set('lapses', 1));

		$row = self::poolRow($id);
		$this->assertSame('2026-01-01 10:00:00', $row['created']);
		$this->assertNow($row['modified'], 'modified');
	}

	public function testAChangedRowKeepsTheModifiedItIsSavedWith(): void
	{
		$id = $this->savePoolRow();
		$poolTable = $this->fetchTable('MistakeTrainingPool');
		$poolTable->saveOrFail($poolTable->get($id)->patch(['lapses' => 1, 'modified' => '2026-01-03 10:00:00']));

		$this->assertSame('2026-01-03 10:00:00', self::poolRow($id)['modified']);
	}

	//schedule has neither column
	public function testATableWithoutTheColumnsSavesAsItIs(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'kovarex'], 'tsumego' => ['sets' => [['name' => 'Target', 'num' => 1]]]]);
		$scheduleTable = $this->fetchTable('Schedule');
		$schedule = $scheduleTable->saveOrFail($scheduleTable->newEntity([
			'date' => '2030-01-01',
			'tsumego_id' => $context->tsumegos[0]['id'],
			'set_id' => $context->tsumegos[0]['sets'][0]['id']]));

		$this->assertFalse($schedule->has('created'));
		$this->assertFalse($schedule->has('modified'));
		$this->assertSame('2030-01-01', Util::query('SELECT date FROM schedule WHERE id = ?', [$schedule->id])[0]['date']);
	}
}

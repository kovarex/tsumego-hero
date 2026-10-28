<?php

namespace App\Utility;

use App\Model\Entity\Sgf;
use App\Model\Table\SgfTable;
use App\Model\Table\TsumegoStatusTable;
use App\Model\Table\AdminActivityTypeTable;
use Cake\ORM\TableRegistry;
use Exception;

class TsumegoMerger
{
	public function __construct(int $masterTsumegoID, int $slaveTsumegoID)
	{
		$this->masterTsumegoID = $masterTsumegoID;
		$this->slaveTsumegoID = $slaveTsumegoID;
	}

	private function checkInput(): ?array
	{
		$tsumegoTable = TableRegistry::getTableLocator()->get('Tsumego');
		if (!$tsumegoTable->exists(['id' => $this->masterTsumegoID]))
			return ['message' => 'Merge masterTsumego not found', 'type' => 'error'];

		if (!$tsumegoTable->exists(['id' => $this->slaveTsumegoID]))
			return ['message' => 'Slave tsumego does not exist.', 'type' => 'error'];

		if ($this->masterTsumegoID == $this->slaveTsumegoID)
			return ['message' => 'Tsumegos already merged.', 'type' => 'error'];
		return null;
	}

	private function mergeSlaveSetConnections(): void
	{
		$setConnectionTable = TableRegistry::getTableLocator()->get('SetConnection');
		foreach ($setConnectionTable->find()->where(['tsumego_id' => $this->slaveTsumegoID])->all() as $slaveTsumegoBrother)
		{
			// If this set already has the master, delete the slave connection (dedup)
			if ($setConnectionTable->exists(['set_id' => $slaveTsumegoBrother->set_id, 'tsumego_id' => $this->masterTsumegoID]))
			{
				$setConnectionTable->deleteOrFail($slaveTsumegoBrother);
				continue;
			}

			$slaveTsumegoBrother->tsumego_id = $this->masterTsumegoID;
			$setConnectionTable->saveOrFail($slaveTsumegoBrother);
		}
	}

	private function mergeStatus(?int $statusID1, ?int $statusID2): void
	{
		if (!$statusID1)
		{
			$statusTable = TableRegistry::getTableLocator()->get('TsumegoStatus');
			$tsumegoStatus = $statusTable->get($statusID2);
			$tsumegoStatus->tsumego_id = $this->masterTsumegoID;
			$statusTable->saveOrFail($tsumegoStatus);
			return;
		}

		// second status doesn't exist, so we just keep the master one
		if (!$statusID2)
			return;

		$statusTable = TableRegistry::getTableLocator()->get('TsumegoStatus');
		$masterStatus = $statusTable->get($statusID1);
		$slaveStatus = $statusTable->get($statusID2);
		$masterStatus->status = TsumegoStatusTable::less($masterStatus->status, $slaveStatus->status) ? $slaveStatus->status : $masterStatus->status;
		$statusTable->saveOrFail($masterStatus);
	}

	private function mergeStatuses(): void
	{
		$statusMergeSources = Util::query("
SELECT
    user_id,
    MAX(CASE WHEN tsumego_id = :id1 THEN id END)     AS tsumego_status_id_1,
    MAX(CASE WHEN tsumego_id = :id1 THEN status END) AS tsumego_status_1,
    MAX(CASE WHEN tsumego_id = :id2 THEN id END)     AS tsumego_status_id_2,
    MAX(CASE WHEN tsumego_id = :id2 THEN status END) AS tsumego_status_2
FROM tsumego_status
WHERE tsumego_id IN (:id1, :id2)
GROUP BY user_id
HAVING
    COUNT(*) BETWEEN 1 AND 2", [':id1' => $this->masterTsumegoID, ':id2' => $this->slaveTsumegoID]);
		foreach ($statusMergeSources as $statusMergeSource)
			$this->mergeStatus($statusMergeSource['tsumego_status_id_1'], $statusMergeSource['tsumego_status_id_2']);
	}

	private function mergeTsumegoAttempts(): void
	{
		Util::execute('UPDATE tsumego_attempt SET tsumego_id = ? WHERE tsumego_id = ?', [$this->masterTsumegoID, $this->slaveTsumegoID]);
	}

	private function mergeComments(): void
	{
		TableRegistry::getTableLocator()->get('TsumegoComment')->updateAll(['tsumego_id' => $this->masterTsumegoID], ['tsumego_id' => $this->slaveTsumegoID]);
	}

	private function mergeTagConnections(): void
	{
		$tagMergeSources = Util::query("
SELECT
    tag_id,
    MAX(CASE WHEN tsumego_id = :id1 THEN id END)     AS tag_connection_id_1,
    MAX(CASE WHEN tsumego_id = :id2 THEN id END)     AS tag_connection_id_2
FROM tag_connection
WHERE tsumego_id IN (:id1, :id2)
GROUP BY tag_id
HAVING
    COUNT(*) BETWEEN 1 AND 2", [':id1' => $this->masterTsumegoID, ':id2' => $this->slaveTsumegoID]);
		foreach ($tagMergeSources as $tagMergeSource)
		{
			// slave is empty, nothing to do
			if (!$tagMergeSource['tag_connection_id_2'])
				continue;

			// master is empty, we change the slave to master
			if (!$tagMergeSource['tag_connection_id_1'])
			{
				$tagConnectionTable = TableRegistry::getTableLocator()->get('TagConnection');
				$tagConnection = $tagConnectionTable->get($tagMergeSource['tag_connection_id_2']);
				$tagConnection->set('tsumego_id', $this->masterTsumegoID);
				$tagConnectionTable->saveOrFail($tagConnection);
				continue;
			}
			// when both master and slave is present, we don't have to do anything, the slave one will be removed by
			// foreign key cascade
		}
	}

	public function mergeTimeModeAttempts(): void
	{
		Util::query('UPDATE time_mode_attempt SET tsumego_id = :master_tsumego_id WHERE tsumego_id = :slave_tsumego_id',
			[':master_tsumego_id' => $this->masterTsumegoID, ':slave_tsumego_id' => $this->slaveTsumegoID]);
	}

	public function mergeIssues(): void
	{
		Util::query('UPDATE tsumego_issue SET tsumego_id = :master_tsumego_id WHERE tsumego_id = :slave_tsumego_id',
			[':master_tsumego_id' => $this->masterTsumegoID, ':slave_tsumego_id' => $this->slaveTsumegoID]);
	}

	//The current version of the problem, the latest accepted one.
	private static function currentSgf(int $tsumegoID): ?Sgf
	{
		return TableRegistry::getTableLocator()->get('Sgf')->find()
			->select(['sgf', 'correct_moves', 'first_move_color'])
			->where(['tsumego_id' => $tsumegoID, 'sgf_status_id' => SgfTable::STATUS_ACCEPTED])
			->orderByDesc('id')
			->first();
	}

	public function execute(): array
	{
		if ($result = $this->checkInput())
			return $result;

		$db = Util::database();
		try
		{
			$masterSgf = self::currentSgf($this->masterTsumegoID);
			$slaveSgf = self::currentSgf($this->slaveTsumegoID);
			$oldData = [
				'tsumego_old' => $this->slaveTsumegoID,
				'master_sgf' => $masterSgf?->sgf,
				'master_correct_moves' => $masterSgf?->correct_moves,
				'master_first_move_color' => $masterSgf?->first_move_color,
				'slave_sgf' => $slaveSgf?->sgf,
				'slave_correct_moves' => $slaveSgf?->correct_moves,
				'slave_first_move_color' => $slaveSgf?->first_move_color];

			$db->begin();
			$this->mergeSlaveSetConnections();
			$this->mergeStatuses();
			$this->mergeTsumegoAttempts();
			$this->mergeComments();
			$this->mergeTagConnections();
			$this->mergeTimeModeAttempts();
			$this->mergeIssues();
			TableRegistry::getTableLocator()->get('Tsumego')->deleteAll(['id' => $this->slaveTsumegoID]);
			AdminActivityLogger::log(
				AdminActivityTypeTable::TSUMEGO_MERGE,
				$this->masterTsumegoID,
				null,
				json_encode($oldData, JSON_UNESCAPED_UNICODE));
			$db->commit();
			return ['message' => 'Tsumegos merged.', 'type' => 'success'];
		}
		catch (Exception $e)
		{
			$db->rollback();
			return ['message' => $e->getMessage(), 'type' => 'error'];
		}
	}

	private int $masterTsumegoID;
	private int $slaveTsumegoID;
}

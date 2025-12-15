<?php

class TsumegoMerger
{
	public function __construct($masterTsumegoID, $slaveTsumegoID)
	{
		$this->masterTsumegoID = $masterTsumegoID;
		$this->slaveTsumegoID = $slaveTsumegoID;
	}

	private function checkInput(): ?array
	{
		$masterTsumego = ClassRegistry::init('Tsumego')->findById($this->masterTsumegoID);
		if (!$masterTsumego)
			return ['message' => 'Merge masterTsumego not found', 'type' => 'error'];

		$slaveTsumego = ClassRegistry::init('Tsumego')->findById($this->slaveTsumegoID);
		if (!$slaveTsumego)
			return ['message' => 'Slave tsumego does not exist.', 'type' => 'error'];

		if ($this->masterTsumegoID == $this->slaveTsumegoID)
			return ['message' => 'Tsumegos already merged.', 'type' => 'error'];
		return null;
	}

	private function mergeSlaveSetConnections()
	{
		$slaveSetConnectionBrothers = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => $this->slaveTsumegoID]]);
		foreach ($slaveSetConnectionBrothers as $slaveTsumegoBrother)
		{
			$slaveTsumegoBrother['SetConnection']['tsumego_id'] = $this->masterTsumegoID;
			ClassRegistry::init('SetConnection')->save($slaveTsumegoBrother);
		}
	}

	private function mergeStatus(?int $statusID1, ?int $statusID2): void
	{
		if (!$statusID1)
		{
			$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->findById($statusID2)['TsumegoStatus'];
			$tsumegoStatus['TsumegoStatus']['tsumego_id'] = $this->masterTsumegoID;
			ClassRegistry::init('TsumegoStatus')->save($tsumegoStatus);
			return;
		}

		// second status doesn't exist, so we just keep the master one
		if (!$statusID2)
			return;

		$masterStatus = ClassRegistry::init('TsumegoStatus')->findById($statusID1)['TsumegoStatus'];
		$slaveStatus = ClassRegistry::init('TsumegoStatus')->findById($statusID2)['TsumegoStatus'];
		$masterStatus['status'] = TsumegoStatus::less($masterStatus['status'], $slaveStatus['status']) ? $slaveStatus['status'] : $masterStatus['status'];
		ClassRegistry::init('TsumegoStatus')->save($masterStatus);
	}

	private function mergeStatuses()
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

	private function mergeTsumegoAttempts()
	{
		$slaveAttempts = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $this->slaveTsumegoID]]);
		foreach ($slaveAttempts as $slaveAttempt)
		{
			$slaveAttempt['TsumegoAttempt']['tsumego_id'] = $this->masterTsumegoID;
			ClassRegistry::init('TsumegoAttempt')->save($slaveAttempt);
		}
	}

	private function mergeComments()
	{
		$slaveComments = ClassRegistry::init('TsumegoComment')->find('all', ['conditions' => ['tsumego_id' => $this->slaveTsumegoID]]);
		foreach ($slaveComments as $slaveComment)
		{
			$slaveComment['TsumegoComment']['tsumego_id'] = $this->masterTsumegoID;
			ClassRegistry::init('TsumegoComment')->save($slaveComment);
		}
	}

	private function mergeFavorites()
	{
		$favoritesMergeSource = Util::query("
SELECT
    user_id,
    MAX(CASE WHEN tsumego_id = :id1 THEN id END)     AS favorite_id_1,
    MAX(CASE WHEN tsumego_id = :id2 THEN id END)     AS favorite_id_2
FROM favorite
WHERE tsumego_id IN (:id1, :id2)
GROUP BY user_id
HAVING
    COUNT(*) BETWEEN 1 AND 2", [':id1' => $this->masterTsumegoID, ':id2' => $this->slaveTsumegoID]);
		foreach ($favoritesMergeSource as $favoriteMergeSource)
		{
			// slave is empty, nothing to do
			if (!$favoriteMergeSource['favorite_id_2'])
				continue;

			// master is empty, we change the slave to master
			if (!$favoriteMergeSource['favorite_id_1'])
			{
				$favorite = ClassRegistry::init('Favorite')->findById($favoriteMergeSource['favorite_id_2'])['Favorite'];
				$favorite['tsumego_id'] = $this->masterTsumegoID;
				ClassRegistry::init('Favorite')->save($favorite);
				continue;
			}
			// when both master and slave is present, we don't have to do anything, the slave one will be removed by
			// foreign key cascade
		}
	}

	private function mergeTagConnections()
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
				$tagConnection = ClassRegistry::init('TagConnection')->findById($tagMergeSource['tag_connection_id_2'])['TagConnection'];
				$tagConnection['tsumego_id'] = $this->masterTsumegoID;
				ClassRegistry::init('TagConnection')->save($tagConnection);
				continue;
			}
			// when both master and slave is present, we don't have to do anything, the slave one will be removed by
			// foreign key cascade
		}
	}

	public function mergeTimeModeAttempts()
	{
		Util::query('UPDATE time_mode_attempt SET tsumego_id = :master_tsumego_id WHERE tsumego_id = :slave_tsumego_id',
			[':master_tsumego_id' => $this->masterTsumegoID, ':slave_tsumego_id' => $this->slaveTsumegoID]);
	}

	public function mergeIssues()
	{
		Util::query('UPDATE tsumego_issue SET tsumego_id = :master_tsumego_id WHERE tsumego_id = :slave_tsumego_id',
			[':master_tsumego_id' => $this->masterTsumegoID, ':slave_tsumego_id' => $this->slaveTsumegoID]);
	}

	public function execute(): array
	{
		if ($result = $this->checkInput())
			return $result;

		$db = ClassRegistry::init('Tsumego')->getDataSource();
		$db->begin();
		$this->mergeSlaveSetConnections();
		$this->mergeStatuses();
		$this->mergeTsumegoAttempts();
		$this->mergeComments();
		$this->mergeFavorites();
		$this->mergeTagConnections();
		$this->mergeTimeModeAttempts();
		$this->mergeIssues();
		ClassRegistry::init('Tsumego')->delete($this->slaveTsumegoID);
		$db->commit();
		return ['message' => 'Tsumegos merged.', 'type' => 'success'];
	}

	private $masterTsumegoID;
	private $slaveTsumegoID;
}

<?php

class TsumegoButtons extends ArrayObject {
	public static function deriveFrom(TsumegoButtons $other) {
		$result = new TsumegoButtons();
		$result->highestTsumegoOrder = $other->highestTsumegoOrder;
		$result->partition = $other->partition;
		$result->isPartitioned = $other->isPartitioned;
		$result->currentOrder = $other->currentOrder;
		return $result;
	}

	public function fill(string $condition, ?array $rankFilters = null) {
		if (!Auth::hasPremium()) {
			Util::addSqlCondition($condition, '`set`.premium = false');
		}

		$rankConditions = '';
		foreach ($rankFilters as $rankFilter) {
			$rankCondition = '';
			RatingBounds::coverRank($rankFilter, '15k')->addSqlConditions($rankCondition);
			Util::addSqlOrCondition($rankConditions, $rankCondition);
		}
		Util::addSqlCondition($condition, $rankConditions);
		Util::addSqlCondition($condition, 'tsumego.public = true');

		$query = "SELECT tsumego.id, set_connection.id, set_connection.num, tsumego.alternative_response, tsumego.pass";
		if (Auth::isLoggedIn()) {
			$query .= ', tsumego_status.status';
		}
		$query .= " FROM tsumego JOIN set_connection ON set_connection.tsumego_id = tsumego.id";
		$query .= " JOIN `set` ON `set`.id=set_connection.set_id";
		if (Auth::isLoggedIn()) {
			$query .= ' LEFT JOIN tsumego_status ON tsumego_status.user_id = ' . Auth::getUserID() . ' AND tsumego_status.tsumego_id = tsumego.id';
		}
		$query .= ' WHERE ' . $condition;
		$query .= " ORDER BY set_connection.num";
		$result = ClassRegistry::init('Tsumego')->query($query);

		foreach ($result as $index => $row) {
			$this [] = new TsumegoButton(
				$row['tsumego']['id'],
				$row['set_connection']['id'],
				$row['set_connection']['num'],
				Auth::isLoggedIn() ? ($row['tsumego_status']['status'] ?: 'N') : 'N',
				$row['tsumego']['alternative_response'],
				$row['tsumego']['pass']
			);
		}
		$this->updateHighestTsumegoOrder();
	}

	public function resetOrders(): void {
		$this->currentOrder = -1;
		foreach ($this as $key => $tsumegoButton) {
			$tsumegoButton->order = $key + 1;
			if ($tsumegoButton->isCurrentlyOpened) {
				$this->currentOrder = $tsumegoButton->order;
			}
		}
		$this->updateHighestTsumegoOrder();
	}
	public function filterByTags(array $tagIDs): void {
		if (empty($tagIDs)) {
			return;
		}
		$this->exchangeArray(array_values(array_filter((array) $this, function ($tsumegoButton) use ($tagIDs): bool {
			return ClassRegistry::init('Tag')->find('first', [
				'conditions' => [
					'tsumego_id' => $tsumegoButton->tsumegoID,
					'tag_name_id' => $tagIDs,
				]]) != null;
		})));
		$this->updateHighestTsumegoOrder();
	}

	private function filterByPartition($collectionSize): void {
		$from = $this->partition * $collectionSize;
		$to = ($this->partition + 1) * $collectionSize - 1;
		$this->isPartitioned = $from > 0 || $to + 1 < count($this);
		$this->exchangeArray(array_values(array_filter(
			(array) $this,
			function ($tsumegoButton, $index) use ($from, $to): bool {
				return $index >= $from && $index <= $to;
			},
			ARRAY_FILTER_USE_BOTH
		)));
	}

	public function partitionByParameter($partition, $collectionSize): void {
		$this->partition = $partition;
		$this->filterByPartition($collectionSize);
	}

	public function partitionByCurrentOne($currentSetConnectionID, $collectionSize): void {
		$currentIndex = array_find_key((array) $this, function ($tsumegoButton) use ($currentSetConnectionID) { return $tsumegoButton->setConnectionID === $currentSetConnectionID; });
		// mark the problem we are going to visit as the currently opened one
		$this[$currentIndex]->isCurrentlyOpened = true;
		$this->currentOrder = $this[$currentIndex]->order;
		$this->partition = (int) floor($currentIndex / $collectionSize);

		if ($collectionSize < count($this)) {
			$this->filterByPartition($collectionSize);
		}
	}

	private function updateHighestTsumegoOrder() {
		$this->highestTsumegoOrder = -1;
		$this->currentOrder = -1;
		foreach ($this as $tsumegoButton) {
			if ($tsumegoButton->isCurrentlyOpened) {
				$this->currentOrder = $tsumegoButton->order;
			}
			$this->highestTsumegoOrder = max($this->highestTsumegoOrder, $tsumegoButton->order);
		}
	}

	public function getPartitionLinkSuffix(): string {
		if (!$this->isPartitioned) {
			return '';
		}
		return '/' . ($this->partition + 1);
	}

	public function getPartitionTitleSuffix(): string {
		if (!$this->isPartitioned) {
			return '';
		}
		return ' #' . ($this->partition + 1);
	}

	public int $partition = 0;
	public bool $isPartitioned = false;
	public int $highestTsumegoOrder = -1;
	public ?int $currentOrder = -1;
}

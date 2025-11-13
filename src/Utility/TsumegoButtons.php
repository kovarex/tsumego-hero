<?php

App::uses('TsumegoButtonsQueryBuilder', 'Utility');

class TsumegoButtons extends ArrayObject {
	public function __construct(?TsumegoFilters $tsumegoFilters = null, ?int $currentSetConnectionID = null, ?int $partition = null, ?string $id = null) {
		if (!$tsumegoFilters) {
			return; // Temporary until also the favorites are covered
		}
		$condition = "";
		$this->fill($condition, $tsumegoFilters, $id);
		$this->filterByTags($tsumegoFilters->tagIDs);

		// in topics we respect the orders specified by set connections, in other cases, it is kind of a
		// 'virtual set' and we just order it from 1 to max
		if ($tsumegoFilters->query != 'topics') {
			$this->resetOrders();
		}
		if (!is_null($currentSetConnectionID)) {
			$this->partitionByCurrentOne($currentSetConnectionID, $tsumegoFilters->collectionSize);
		} else {
			$this->partitionByParameter($partition, $tsumegoFilters->collectionSize);
		}
	}

	public static function deriveFrom(TsumegoButtons $other) {
		$result = new TsumegoButtons();
		$result->highestTsumegoOrder = $other->highestTsumegoOrder;
		$result->partition = $other->partition;
		$result->isPartitioned = $other->isPartitioned;
		$result->currentOrder = $other->currentOrder;
		return $result;
	}

	public function fill(string $condition, TsumegoFilters $tsumegoFilters, $id) {
		$queryBuilder = new TsumegoButtonsQueryBuilder($tsumegoFilters, $id);
		$result = ClassRegistry::init('Tsumego')->query($queryBuilder->query);
		$this->description = $queryBuilder->description;

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
	public ?string $description = null;
}

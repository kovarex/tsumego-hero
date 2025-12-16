<?php

App::uses('SgfParser', 'Utility');
App::uses('TsumegoButtonsQueryBuilder', 'Utility');

class TsumegoButtons extends ArrayObject
{
	public function __construct(?TsumegoFilters $tsumegoFilters = null, ?int $currentSetConnectionID = null, ?int $partition = null, ?string $id = null)
	{
		if (!$tsumegoFilters)
			return; // Temporary until also the favorites are covered
		$condition = "";
		$this->fill($condition, $tsumegoFilters, $id);

		// in topics we respect the orders specified by set connections, in other cases, it is kind of a
		// 'virtual set' and we just order it from 1 to max
		if ($tsumegoFilters->query != 'topics' && $tsumegoFilters->query != 'published')
			$this->resetOrders();

		if (!is_null($currentSetConnectionID))
		{
			$currentIndex = $this->deduceCurrentIndex($currentSetConnectionID);
			if (is_null($currentIndex))
				if ($tsumegoFilters->query == 'favorites')
				{
					$tsumegoFilters->setQuery('topics');
					$this->fill($condition, $tsumegoFilters, $id);
					$currentIndex = $this->deduceCurrentIndex($currentSetConnectionID);
				}

			// mark the problem we are going to visit as the currently opened one
			$this[$currentIndex]->isCurrentlyOpened = true;
			$this->currentOrder = $this[$currentIndex]->order;
			$this->partitionByCurrentOne($currentIndex, $tsumegoFilters->collectionSize);
		}
		elseif (!is_null($partition))
			$this->partitionByParameter($partition, $tsumegoFilters->collectionSize);
	}

	public static function deriveFrom(TsumegoButtons $other)
	{
		$result = new TsumegoButtons();
		$result->highestTsumegoOrder = $other->highestTsumegoOrder;
		$result->partition = $other->partition;
		$result->isPartitioned = $other->isPartitioned;
		$result->currentOrder = $other->currentOrder;
		return $result;
	}

	public function fill(string $condition, TsumegoFilters $tsumegoFilters, $id)
	{
		$queryBuilder = new TsumegoButtonsQueryBuilder($tsumegoFilters, $id);
		$result = Util::query($queryBuilder->query);
		$this->description = $queryBuilder->description;

		foreach ($result as $index => $row)
			$this [] = new TsumegoButton(
				$row['tsumego_id'],
				$row['set_connection_id'],
				$row['num'],
				Auth::isLoggedIn() ? ($row['status'] ?: 'N') : 'N');
		$this->updateHighestTsumegoOrder();
	}

	public function resetOrders(): void
	{
		$this->currentOrder = -1;
		foreach ($this as $key => $tsumegoButton)
		{
			$tsumegoButton->order = $key + 1;
			if ($tsumegoButton->isCurrentlyOpened)
				$this->currentOrder = $tsumegoButton->order;
		}
		$this->updateHighestTsumegoOrder();
	}

	private function filterByPartition($collectionSize): void
	{
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

	public function partitionByParameter($partition, $collectionSize): void
	{
		$this->partition = $partition;
		$this->filterByPartition($collectionSize);
	}

	public function partitionByCurrentOne($currentIndex, $collectionSize): void
	{
		$this->partition = (int) floor($currentIndex / $collectionSize);

		if ($collectionSize < count($this))
			$this->filterByPartition($collectionSize);
	}

	private function deduceCurrentIndex($currentSetConnectionID): ?int
	{
		return array_find_key((array) $this, function ($tsumegoButton) use ($currentSetConnectionID) { return $tsumegoButton->setConnectionID === $currentSetConnectionID; });
	}

	private function updateHighestTsumegoOrder()
	{
		$this->highestTsumegoOrder = -1;
		$this->currentOrder = -1;
		foreach ($this as $tsumegoButton)
		{
			if ($tsumegoButton->isCurrentlyOpened)
				$this->currentOrder = $tsumegoButton->order;
			$this->highestTsumegoOrder = max($this->highestTsumegoOrder, $tsumegoButton->order);
		}
	}

	public function getPartitionLinkSuffix(): string
	{
		if (!$this->isPartitioned)
			return '';
		return '/' . ($this->partition + 1);
	}

	public function getPartitionTitleSuffix(): string
	{
		if (!$this->isPartitioned)
			return '';
		return ' #' . ($this->partition + 1);
	}

	public function exportCurrentAndPreviousLink($setFunction, $tsumegoFilters, $setConnectionID, $set)
	{
		$indexOfCurrent = array_find_key((array) $this, function ($tsumegoButton) use ($setConnectionID) { return $tsumegoButton->setConnectionID == $setConnectionID; });

		if (isset($indexOfCurrent) && $indexOfCurrent > 0)
			$previousSetConnectionID = $this[$indexOfCurrent - 1]->setConnectionID;
		$setFunction('previousLink', TsumegosController::tsumegoOrSetLink($tsumegoFilters, isset($previousSetConnectionID) ? $previousSetConnectionID : null, $tsumegoFilters->getSetID($set)));

		if (isset($indexOfCurrent) && count($this) > $indexOfCurrent + 1)
			$nextSetConnectionID = $this[$indexOfCurrent + 1]->setConnectionID;
		$setFunction('nextLink', TsumegosController::tsumegoOrSetLink($tsumegoFilters, isset($nextSetConnectionID) ? $nextSetConnectionID : null, $tsumegoFilters->getSetID($set)));
	}

	public int $partition = 0;
	public bool $isPartitioned = false;
	public int $highestTsumegoOrder = -1;
	public ?int $currentOrder = -1;
	public ?string $description = null;
}

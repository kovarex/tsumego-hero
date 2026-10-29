<?php

namespace App\Utility;

use App\Model\Entity\Set;
use App\Model\Table\SgfTable;
use ArrayObject;
use App\Model\Table\SetTable;

class TsumegoButtons extends ArrayObject
{
	public function __construct(?TsumegoFilters $tsumegoFilters = null, ?int $currentSetConnectionID = null, ?int $partition = null, string|int|null $id = null)
	{
		if (!$tsumegoFilters)
			return;
		$this->fill('', $tsumegoFilters, $id);

		// The opened problem might not be part of the difficulty or tag collection (another tab switched
		// the collection, or the rating of the problem moved it to another rank). The navigation would
		// have no current problem then, so we show the set of the problem instead.
		if (!is_null($currentSetConnectionID)
			&& ($tsumegoFilters->query == 'difficulty' || $tsumegoFilters->query == 'tags')
			&& is_null($this->deduceCurrentIndex($currentSetConnectionID)))
		{
			$tsumegoFilters->query = 'topics';
			$this->exchangeArray([]);
			$this->fill('', $tsumegoFilters, $id);
		}

		// in topics we respect the orders specified by set connections, in other cases, it is kind of a
		// 'virtual set' and we just order it from 1 to max
		$resetOrders = $tsumegoFilters->query != 'topics' && $tsumegoFilters->query != 'published';
		$this->finalize($currentSetConnectionID, $partition, $tsumegoFilters->collectionSize, $resetOrders);
	}

	//Build buttons directly from a row set, used by dynamic queues such as mistake training.
	//Rows must expose tsumego_id, set_connection_id, num, status, rating and sgf.
	//$linkPrefix points every problem link at the route that owns the queue, so
	//following the queue does not drop the player back into plain play.
	public static function fromRows(array $rows, ?int $currentSetConnectionID = null, int $collectionSize = 200, string $linkPrefix = '/'): TsumegoButtons
	{
		$result = new TsumegoButtons();
		$result->linkPrefix = $linkPrefix;
		foreach ($rows as $row)
		{
			$button = new TsumegoButton(
				(int) $row['tsumego_id'],
				(int) $row['set_connection_id'],
				(int) $row['num'],
				Auth::isLoggedIn() ? ($row['status'] ?: 'N') : 'N',
				isset($row['rating']) ? (float) $row['rating'] : 0,
				$row['sgf'] ?? ''
			);
			$button->linkPrefix = $linkPrefix;
			$result[] = $button;
		}
		$result->finalize($currentSetConnectionID, null, $collectionSize, true);
		return $result;
	}

	//Apply virtual-set ordering, mark the currently opened problem and partition.
	private function finalize(?int $currentSetConnectionID, ?int $partition, int $collectionSize, bool $resetOrders): void
	{
		$this->totalCount = count($this);
		if ($resetOrders)
			$this->resetOrders();

		if (!is_null($currentSetConnectionID))
		{
			$currentIndex = $this->deduceCurrentIndex($currentSetConnectionID);
			if (!is_null($currentIndex))
			{
				// mark the problem we are going to visit as the currently opened one
				$this[$currentIndex]->isCurrentlyOpened = true;
				$this->currentOrder = $this[$currentIndex]->order;
			}
			$this->partitionByCurrentOne($currentIndex, $collectionSize);
		}
		elseif (!is_null($partition))
			$this->partitionByParameter($partition, $collectionSize);
	}

	public static function deriveFrom(TsumegoButtons $other): TsumegoButtons
	{
		$result = new TsumegoButtons();
		$result->highestTsumegoOrder = $other->highestTsumegoOrder;
		$result->totalCount = $other->totalCount;
		$result->partition = $other->partition;
		$result->isPartitioned = $other->isPartitioned;
		$result->currentOrder = $other->currentOrder;
		$result->linkPrefix = $other->linkPrefix;
		return $result;
	}

	public function fill(string $condition, TsumegoFilters $tsumegoFilters, string|int|null $id): void
	{
		$queryBuilder = new TsumegoButtonsQueryBuilder($tsumegoFilters, $id);
		$result = Util::query($queryBuilder->query->str());
		$this->description = $queryBuilder->description;

		foreach ($result as $index => $row)
		{
			$tsumegoButton = new TsumegoButton(
				$row['tsumego_id'],
				$row['set_connection_id'],
				$row['num'],
				Auth::isLoggedIn() ? ($row['status'] ?: 'N') : 'N',
				$row['rating'],
				null);
			$tsumegoButton->setID = isset($row['set_id']) ? (int) $row['set_id'] : null;
			$tsumegoButton->setTitle = $row['set_title'] ?? null;
			$this [] = $tsumegoButton;
		}
		$this->updateHighestTsumegoOrder();
	}

	//Loads the stored preview of the current version of the buttons that have none yet, in one query.
	//A collection is filled without them, as only the shown buttons need them: one page of a set, or
	//the dozen around the opened problem on the play page, not the whole set.
	public function loadPreviews(): void
	{
		$tsumegoIDs = [];
		foreach ($this as $tsumegoButton)
			if ($tsumegoButton->preview === null && $tsumegoButton->sgf === null)
				$tsumegoIDs[] = $tsumegoButton->tsumegoID;
		if (empty($tsumegoIDs))
			return;
		$previews = self::currentPreviews($tsumegoIDs);
		foreach ($this as $tsumegoButton)
			if ($tsumegoButton->preview === null && $tsumegoButton->sgf === null)
				$tsumegoButton->preview = $previews[$tsumegoButton->tsumegoID] ?? '';
	}

	/**
	 * The stored preview of the current (latest accepted) version of each of the tsumegos.
	 * @param int[] $tsumegoIDs
	 * @return array<int, string> by tsumego id, a tsumego without an accepted version is left out
	 */
	public static function currentPreviews(array $tsumegoIDs): array
	{
		$rows = Util::query('SELECT sgf.tsumego_id, sgf.preview FROM sgf'
			. ' JOIN (SELECT MAX(id) AS id FROM sgf WHERE tsumego_id IN (' . implode(',', array_map('intval', array_unique($tsumegoIDs))) . ')'
			. ' AND sgf_status_id = ' . SgfTable::STATUS_ACCEPTED . ' GROUP BY tsumego_id) current_sgf ON current_sgf.id = sgf.id');
		$result = [];
		foreach ($rows as $row)
			$result[(int) $row['tsumego_id']] = (string) $row['preview'];
		return $result;
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

	private function filterByPartition(int $collectionSize): void
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

	public function partitionByParameter(int $partition, int $collectionSize): void
	{
		$this->partition = $partition;
		$this->filterByPartition($collectionSize);
	}

	public function partitionByCurrentOne(?int $currentIndex, int $collectionSize): void
	{
		$this->partition = $currentIndex === null ? 0 : (int) floor($currentIndex / $collectionSize);

		if ($collectionSize < count($this))
			$this->filterByPartition($collectionSize);
	}

	private function deduceCurrentIndex(int $currentSetConnectionID): ?int
	{
		return array_find_key((array) $this, function ($tsumegoButton) use ($currentSetConnectionID) {
			return $tsumegoButton->setConnectionID === $currentSetConnectionID;
		});
	}

	private function updateHighestTsumegoOrder(): void
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

	//How many pages the whole collection splits into. Pages are cut by position, so the count
	//follows the number of problems, not the highest problem number, which leaves gaps when
	//problems were moved out of the collection.
	public function getPageCount(int $collectionSize): int
	{
		return (int) ceil($this->totalCount / $collectionSize);
	}

	//The page of the collection a link should open, counting from one.
	public function getPartition(): int
	{
		return $this->isPartitioned ? $this->partition + 1 : 1;
	}

	//The page of the collection a title should name, counting from one.
	public function getPartitionTitleSuffix(): string
	{
		return $this->isPartitioned ? ' #' . $this->getPartition() : '';
	}

	public function exportCurrentAndPreviousLink(callable $setFunction, ?TsumegoFilters $tsumegoFilters, int $setConnectionID, ?Set $set, ?string $edgeLink = null): void
	{
		$indexOfCurrent = array_find_key((array) $this, function ($tsumegoButton) use ($setConnectionID) {
			return $tsumegoButton->setConnectionID == $setConnectionID;
		});

		if ($edgeLink === null && $tsumegoFilters !== null)
			$edgeLink = SetTable::url($tsumegoFilters->getSetID($set), $this->getPartition());

		if (isset($indexOfCurrent) && $indexOfCurrent > 0)
			$previousSetConnectionID = $this[$indexOfCurrent - 1]->setConnectionID;
		$setFunction('previousLink', isset($previousSetConnectionID) ? $this->linkPrefix . $previousSetConnectionID : $edgeLink);

		if (isset($indexOfCurrent) && count($this) > $indexOfCurrent + 1)
			$nextSetConnectionID = $this[$indexOfCurrent + 1]->setConnectionID;
		$setFunction('nextLink', isset($nextSetConnectionID) ? $this->linkPrefix . $nextSetConnectionID : $edgeLink);
	}

	public function getProblemsSolvedPercent(): float
	{
		$solvedCount = 0;
		foreach ($this as $tsumegoButton)
			if (TsumegoUtil::isSolvedStatus($tsumegoButton->status))
				$solvedCount++;
		return Util::getPercentButAvoid100UntilComplete($solvedCount, count($this));
	}

	public function getProblemsRating(): float
	{
		if (count($this) == 0)
			return 0;
		$ratingSum = 0;
		foreach ($this as $tsumegoButton)
			$ratingSum += $tsumegoButton->rating;
		return $ratingSum / count($this);
	}

	public int $partition = 0;
	public bool $isPartitioned = false;
	public string $linkPrefix = '/';
	public int $highestTsumegoOrder = -1;
	public int $totalCount = 0; // problems in the whole collection, before it was cut to one page
	public ?int $currentOrder = -1;
	public ?string $description = null;
}

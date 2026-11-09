<?php

namespace App\Utility;

use App\Model\Table\SetTable;
use App\Model\Table\TsumegoStatusTable;

class SetsSelector
{
	public function __construct(TsumegoFilters $tsumegoFilters)
	{
		$this->tsumegoFilters = $tsumegoFilters;
		if ($this->tsumegoFilters->query == 'tags')
			$this->selectByTags();
		elseif ($this->tsumegoFilters->query == 'topics')
			$this->selectByTopics();
		elseif ($this->tsumegoFilters->query == 'difficulty')
			$this->selectByDifficulty();
		// the topics rows are the problems calculateCount() counts, under the same conditions
		if ($this->problemsFound === null)
			$this->problemsFound = $this->tsumegoFilters->calculateCount();
	}

	private function selectByTags(): void
	{
		// The name and color of a tag come once per tag below, not with each of its problems
		$query = new Query('FROM tag_connection tc');
		$query->selects[] = 'tc.tag_id';
		$query->selects[] = 'tc.tsumego_id';
		$query->selects[] = 'tsumego.rating';
		$query->query .= ' JOIN tsumego ON tsumego.id = tc.tsumego_id';
		// a tag a player proposed is not on the problem until an admin accepts it
		$query->conditions[] = 'tc.approved = 1';
		$query->conditions[] = $this->tsumegoFilters->publicMembershipCondition('tc.tsumego_id');
		$query->conditions[] = 'tsumego.deleted IS NULL';
		// picked tags show only their own cards, each with the problems its page opens
		if (!empty($this->tsumegoFilters->tagIDs))
			$query->conditions[] = 'tc.tag_id IN (' . implode(',', $this->tsumegoFilters->tagIDs) . ')';
		if ($rankCondition = $this->tsumegoFilters->rankCondition())
			$query->conditions[] = $rankCondition;
		$rows = Util::query($query->str());

		$tagInfo = [];
		foreach (Util::query('SELECT id, name, color FROM tag') as $tag)
			$tagInfo[$tag['id']] = ['name' => $tag['name'], 'color' => $tag['color'], 'order' => null];
		$sets = $this->buildPartitionedSets($rows, 'tag_id', null, null, null, ['tsumego_id'], $tagInfo);
		usort($sets, function ($a, $b) {
			if ($a['total_count'] != $b['total_count'])
				return $b['total_count'] <=> $a['total_count'];
			if ($a['name'] != $b['name'])
				return strcmp($a['name'], $b['name']);
			return $a['partition'] <=> $b['partition'];
		});
		$this->sets = [];
		// a tag's card is addressed by its name
		foreach ($sets as $set)
			$this->sets[] = self::card(['id' => $set['name']] + $set, self::TAG_COLORS[(int) $set['color']], false);
	}

	//The colors of the tags by tag.color
	private const TAG_COLORS = [
		'rgb(217, 135, 135)',
		'rgb(135, 149, 101)',
		'rgb(190, 151, 131)',
		'rgb(188, 116, 45)',
		'rgb(153, 111, 31)',
		'rgb(159, 54, 0)',
		'rgb(153, 151, 31)',
		'rgb(114, 9, 183)',
		'rgb(149, 77, 63)',
		'rgb(179, 181, 37)',
		'rgb(137, 153, 31)',
		'rgb(145, 61, 91)',
		'rgb(79, 68, 68)',
		'rgb(182, 137, 199)',
		'rgb(166, 88, 125)',
		'rgb(45, 37, 79)',
		'rgb(154, 50, 138)',
		'rgb(102, 51, 122)',
		'rgb(184, 46, 126)',
		'rgb(119, 50, 154)',
		'rgb(187, 70, 196)',
		'rgb(125, 8, 8)',
		'rgb(136, 67, 56)',
		'rgb(190, 165, 136)',
		'rgb(128, 118, 123)',
	];

	private function selectByTopics(): void
	{
		// The title, color and order of a set come once per set below, not with each of its problems
		$query = new Query('FROM `set` s');
		$query->selects[] = 's.id AS set_id';
		$query->selects[] = 'sc.num';
		$query->selects[] = 'sc.tsumego_id';
		$query->selects[] = 'tsumego.rating';
		$query->query .= ' JOIN set_connection sc ON sc.set_id = s.id';
		$query->query .= ' JOIN tsumego ON tsumego.id = sc.tsumego_id';
		$query->conditions[] = SetTable::officialCondition('s');
		$query->conditions[] = 'tsumego.deleted IS NULL';
		if (!empty($this->tsumegoFilters->setIDs))
			$query->conditions[] = 's.id IN (' . implode(',', $this->tsumegoFilters->setIDs) . ')';
		if (!empty($this->tsumegoFilters->tagIDs))
			$query->conditions[] = $this->tsumegoFilters->tagMembershipCondition('sc.tsumego_id');
		if ($rankCondition = $this->tsumegoFilters->rankCondition())
			$query->conditions[] = $rankCondition;
		$rows = Util::query($query->str());
		$this->problemsFound = count(array_unique(array_column($rows, 'tsumego_id')));

		$setInfo = [];
		foreach (Util::query('SELECT id, title, color, `order` FROM `set` s WHERE ' . SetTable::officialCondition('s')) as $set)
			$setInfo[$set['id']] = ['name' => $set['title'], 'color' => $set['color'], 'order' => $set['order']];

		// Set cards are ordered in PHP below; items are sorted within each set by
		// buildPartitionedSets, so this query does not need an ORDER BY.
		$sets = $this->buildPartitionedSets($rows, 'set_id', null, null, null, ['num', 'tsumego_id'], $setInfo);
		usort($sets, function ($a, $b) {
			// sets without a curated order sort last, matching displayOrderForSetSql
			$aOrder = $a['order'] ?? PHP_INT_MAX;
			$bOrder = $b['order'] ?? PHP_INT_MAX;
			if ($aOrder != $bOrder)
				return $aOrder <=> $bOrder;
			if ($a['total_count'] != $b['total_count'])
				return $b['total_count'] <=> $a['total_count'];
			if ($a['partition'] != $b['partition'])
				return $a['partition'] <=> $b['partition'];
			return $a['id'] <=> $b['id'];
		});
		$this->sets = array_map(fn($set) => self::card($set, $set['color'], true), $sets);
	}

	private function selectByDifficulty(): void
	{
		// assign each tsumego to a rank band using the same bounds RatingBounds uses
		$bands = [];
		$rankOrder = 0;
		foreach ($this->rankBands() as [$weakest, $strongest])
		{
			$bounds = RatingBounds::coverRankSpec($weakest . '-' . $strongest, Rating::weakestRank());
			$bands[] = [
				'name' => $weakest === $strongest ? $weakest : $weakest . '-' . $strongest,
				'color' => Rating::rankColorVar($strongest),
				'order' => $rankOrder,
				'min' => $bounds->min,
				'max' => $bounds->max,
			];
			$rankOrder++;
		}

		$query = new Query('FROM tsumego');
		$query->selects[] = 'tsumego.id AS tsumego_id';
		$query->selects[] = 'tsumego.rating';
		$query->conditions[] = 'tsumego.deleted IS NULL';
		$query->conditions[] = $this->tsumegoFilters->publicMembershipCondition('tsumego.id');
		if (!empty($this->tsumegoFilters->tagIDs))
			$query->conditions[] = $this->tsumegoFilters->tagMembershipCondition('tsumego.id');
		// restrict to the rating range covered by the bands, so rows that would
		// never match a band are not transferred; rows falling into a gap between
		// two bands are dropped by the assignment loop below.
		// a band open at one end leaves the range open at that end
		$minimums = array_column($bands, 'min');
		$maximums = array_column($bands, 'max');
		$minRating = $minimums === [] || in_array(null, $minimums, true) ? null : min($minimums);
		$maxRating = $maximums === [] || in_array(null, $maximums, true) ? null : max($maximums);
		if ($minRating !== null)
			$query->conditions[] = 'tsumego.rating >= ' . $minRating;
		if ($maxRating !== null)
			$query->conditions[] = 'tsumego.rating < ' . $maxRating;
		$rows = Util::query($query->str());

		$collectionRows = [];
		foreach ($rows as $row)
		{
			$rating = (float) $row['rating'];
			foreach ($bands as $band)
				if (($band['min'] === null || $rating >= $band['min']) && ($band['max'] === null || $rating < $band['max']))
				{
					$collectionRows[] = [
						'rank_label' => $band['name'],
						'rank_color' => $band['color'],
						'rank_order' => $band['order'],
						'tsumego_id' => $row['tsumego_id'],
						'rating' => $row['rating'],
					];
					break;
				}
		}

		$sets = $this->buildPartitionedSets($collectionRows, 'rank_label', 'rank_label', 'rank_color', 'rank_order', ['tsumego_id']);
		usort($sets, function ($a, $b) {
			if ($a['order'] != $b['order'])
				return $a['order'] <=> $b['order'];
			return $a['partition'] <=> $b['partition'];
		});
		$this->sets = array_map(fn($set) => self::card($set, $set['color'], true), $sets);
	}

	//Rank bands to display, weakest first, as [weakest, strongest] pairs.
	//Without a rank filter every rank is its own band. Each rank filter is one
	//band: separately picked ranks stay separate boxes, and a picked range
	//becomes one box spanning it.
	/** @return array<int, array{0: string, 1: string}> */
	private function rankBands(): array
	{
		if (empty($this->tsumegoFilters->rankSpecs))
			return array_map(function ($rank) {
				return [$rank, $rank];
			}, Rating::ranks());

		$bands = [];
		foreach ($this->tsumegoFilters->rankSpecs as $spec)
			$bands[] = Rating::parseReadableRankRange($spec) ?? [$spec, $spec];
		usort($bands, function ($a, $b) {
			return Rating::getRankFromReadableRank($a[0]) <=> Rating::getRankFromReadableRank($b[0]);
		});
		return $bands;
	}

	//Group rows by collection, split each into partitions of the user's collection size,
	//and compute per-partition stats (count, rating sum, solved count).
	//Items are sorted within each collection by the $itemSortKeys row fields (e.g.
	//num, tsumego_id) so partitions always hold consecutive problems in the canonical
	//order (the same order a collection page uses), regardless of the row order the
	//database returns.
	//$rows: Query rows, each with $idField, $nameField, $colorField, tsumego_id and rating.
	//$idField: Row field identifying the collection.
	//$nameField: Row field with the collection display name.
	//$colorField: Row field with the collection color.
	//$orderField: Optional row field with the collection sort key.
	/**
	 * @param string[] $itemSortKeys Optional integer row fields to sort items by within each collection, at most two.
	 *   Each item has its own combination of them.
	 * @param array<int|string, array{name: string, color: string, order: mixed}>|null $collectionInfo The name, color
	 *   and order of each collection by its id, in place of the row fields.
	 * @return array[] Collection chunks, each with id, name, color, order, partition, total_count,
	 *   usage_count, rating_sum, solved_count, perfect and part_count (0 for a collection not split).
	 */
	private function buildPartitionedSets(array $rows, string $idField, ?string $nameField, ?string $colorField, ?string $orderField = null, array $itemSortKeys = [], ?array $collectionInfo = null): array
	{
		// The catalogue has a row for every problem of every collection, so the items are kept small: the
		// rating and the tsumego id under an integer sort key, which ksort() orders without a callback.
		$collections = [];
		$items = [];
		$firstKey = $itemSortKeys[0] ?? null;
		$secondKey = $itemSortKeys[1] ?? null;
		foreach ($rows as $index => $row)
		{
			$id = $row[$idField];
			if (!isset($collections[$id]))
				$collections[$id] = $collectionInfo[$id] ?? [
					'name' => $row[$nameField],
					'color' => $row[$colorField],
					'order' => $orderField ? $row[$orderField] : null,
				];
			if ($firstKey === null)
				$sortKey = $index;
			elseif ($secondKey === null)
				$sortKey = (int) $row[$firstKey];
			else
				$sortKey = ((int) $row[$firstKey] << 32) + (int) $row[$secondKey];
			$items[$id][$sortKey] = [(float) $row['rating'], (int) $row['tsumego_id']];
		}

		$size = $this->tsumegoFilters->collectionSize;
		$solved = $this->getSolvedTsumegoIDs();

		$sets = [];
		// the problems of each finished card, by the card's index in $sets, to see which were finished
		// without a mistake
		$finished = [];
		foreach ($collections as $id => $collection)
		{
			$collectionItems = $items[$id];
			if ($firstKey !== null)
				ksort($collectionItems);
			$total = count($collectionItems);
			$partition = $total <= $size ? -1 : 0;
			$partCount = $partition === -1 ? 0 : (int) ceil($total / $size);
			$count = $ratingSum = $solvedCount = 0;
			$cardTsumegoIDs = [];
			foreach ($collectionItems as [$rating, $tsumegoID])
			{
				$count++;
				$ratingSum += $rating;
				$cardTsumegoIDs[] = $tsumegoID;
				if (isset($solved[$tsumegoID]))
					$solvedCount++;
				if ($count === $size || $partition === -1 && $count === $total)
				{
					if ($solvedCount === $count)
						$finished[count($sets)] = $cardTsumegoIDs;
					$sets[] = self::partitionCard($id, $collection, $partition, $partCount, $total, $count, $ratingSum, $solvedCount);
					$partition++;
					$count = $ratingSum = $solvedCount = 0;
					$cardTsumegoIDs = [];
				}
			}
			if ($count > 0)
			{
				if ($solvedCount === $count)
					$finished[count($sets)] = $cardTsumegoIDs;
				$sets[] = self::partitionCard($id, $collection, $partition, $partCount, $total, $count, $ratingSum, $solvedCount);
			}
		}

		$perfect = $this->getPerfectTsumegoIDs(array_merge(...array_values($finished)));
		foreach ($finished as $index => $tsumegoIDs)
		{
			$sets[$index]['perfect'] = true;
			foreach ($tsumegoIDs as $tsumegoID)
				if (!isset($perfect[$tsumegoID]))
				{
					$sets[$index]['perfect'] = false;
					break;
				}
		}
		return $sets;
	}

	//The card of a part of a collection as the page shows it. The parts of a tag have no difficulty.
	private static function card(array $set, ?string $color, bool $withDifficulty): array
	{
		$card = [
			'id' => $set['id'],
			'name' => $set['name'],
			'amount' => $set['usage_count'],
			'color' => $color,
			'solved_percent' => Util::getPercentButAvoid100UntilComplete($set['solved_count'], $set['usage_count']),
			'perfect' => $set['perfect'],
			'partition' => $set['partition'],
			'part_count' => $set['part_count'],
		];
		if ($withDifficulty)
			$card['difficulty'] = Rating::getReadableRankFromRating($set['rating_sum'] / $set['usage_count']);
		return $card;
	}

	/** @param array{name: string, color: string, order: mixed} $collection */
	private static function partitionCard(int|string $id, array $collection, int $partition, int $partCount, int $total, int $count, float $ratingSum, int $solvedCount): array
	{
		return [
			'id' => $id,
			'name' => $collection['name'],
			'color' => $collection['color'],
			'order' => $collection['order'],
			'partition' => $partition,
			'part_count' => $partCount,
			'total_count' => $total,
			'usage_count' => $count,
			'rating_sum' => $ratingSum,
			'solved_count' => $solvedCount,
			'perfect' => false,
		];
	}

	//Of the given problems, those the current user solved without a mistake: attempted, and never failed.
	//A card is finished with 100% accuracy when all its problems are, the same accuracy the collection's
	//page shows (TsumegoButtons::getAccuracy), where a problem solved without a recorded attempt counts as
	//a miss. Only the problems of finished cards are asked for, through the (user_id, tsumego_id) index.
	/**
	 * @param int[] $tsumegoIDs
	 * @return array<int, true>
	 */
	private function getPerfectTsumegoIDs(array $tsumegoIDs): array
	{
		if (!Auth::isLoggedIn() || empty($tsumegoIDs))
			return [];
		$perfect = [];
		foreach (array_chunk(array_values(array_unique($tsumegoIDs)), 1000) as $chunk)
		{
			$rows = Util::query('SELECT tsumego_id FROM tsumego_attempt WHERE user_id = ? AND tsumego_id IN ('
				. implode(',', array_fill(0, count($chunk), '?')) . ') GROUP BY tsumego_id HAVING MIN(solved) = 1',
				[Auth::getUserID(), ...$chunk]);
			foreach ($rows as $row)
				$perfect[(int) $row['tsumego_id']] = true;
		}
		return $perfect;
	}

	//The tsumego IDs the current user has solved (status S, W or C), or an empty array for guests.
	/** @return array<int, true> */
	private function getSolvedTsumegoIDs(): array
	{
		if (!Auth::isLoggedIn())
			return [];
		$rows = Util::query('SELECT tsumego_id FROM tsumego_status WHERE user_id = ? AND ' . TsumegoStatusTable::solvedCondition('status'), [Auth::getUserID()]);
		$solved = [];
		foreach ($rows as $row)
			$solved[(int) $row['tsumego_id']] = true;
		return $solved;
	}

	public TsumegoFilters $tsumegoFilters;
	public array $sets = [];
	public ?int $problemsFound = null;
}

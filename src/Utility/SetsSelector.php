<?php

namespace App\Utility;

use App\Model\Table\SetTable;

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
		$query->conditions[] = $this->publicMembershipCondition('tc.tsumego_id');
		$query->conditions[] = 'tsumego.deleted IS NULL';
		if (!empty($this->tsumegoFilters->tagIDs))
			$query->conditions[] = $this->tagMembershipCondition('tc.tsumego_id');
		if ($rankCondition = $this->rankCondition())
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
		foreach ($sets as $set)
		{
			$partition = $set['partition'];
			$colorValue = 1 - (($partition == -1) ? 0 : -($partition * 0.15));
			$this->sets[] = [
				'id' => $set['name'],
				'amount' => $set['usage_count'],
				'name' => $set['name'],
				'color' => str_replace('[o]', (string) $colorValue, SetsSelector::getTagColor((int) $set['color'])),
				'solved_percent' => Util::getPercentButAvoid100UntilComplete($set['solved_count'], $set['usage_count']),
				'partition' => $partition,
			];
		}
	}

	private static function getTagColor(int $pos): string
	{
		$colors = [];
		$colors[0] = 'rgba(217, 135, 135, [o])';
		$colors[1] = 'rgba(135, 149, 101, [o])';
		$colors[2] = 'rgba(190, 151, 131, [o])';
		$colors[3] = 'rgba(188, 116, 45, [o])';
		$colors[4] = 'rgba(153, 111, 31, [o])';
		$colors[5] = 'rgba(159, 54, 0, [o])';
		$colors[6] = 'rgba(153, 151, 31, [o])';
		$colors[7] = 'rgba(114, 9, 183, [o])';
		$colors[8] = 'rgba(149, 77, 63, [o])';
		$colors[9] = 'rgba(179, 181, 37, [o])';
		$colors[10] = 'rgba(137, 153, 31, [o])';
		$colors[11] = 'rgba(145, 61, 91, [o])';
		$colors[12] = 'rgba(79, 68, 68, [o])';
		$colors[13] = 'rgba(182, 137, 199, [o])';
		$colors[14] = 'rgba(166, 88, 125, [o])';
		$colors[15] = 'rgba(45, 37, 79, [o])';
		$colors[16] = 'rgba(154, 50, 138, [o])';
		$colors[17] = 'rgba(102, 51, 122, [o])';
		$colors[18] = 'rgba(184, 46, 126, [o])';
		$colors[19] = 'rgba(119, 50, 154, [o])';
		$colors[20] = 'rgba(187, 70, 196, [o])';
		$colors[21] = 'rgba(125, 8, 8, [o])';
		$colors[22] = 'rgba(136, 67, 56, [o])';
		$colors[23] = 'rgba(190, 165, 136, [o])';
		$colors[24] = 'rgba(128, 118, 123, [o])';

		return $colors[$pos];
	}

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
			$query->conditions[] = $this->tagMembershipCondition('sc.tsumego_id');
		if ($rankCondition = $this->rankCondition())
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
		$this->sets = [];
		foreach ($sets as $set)
		{
			$this->sets[] = [
				'id' => $set['id'],
				'name' => $set['name'],
				'amount' => $set['usage_count'],
				'color' => $set['color'],
				'solved_percent' => Util::getPercentButAvoid100UntilComplete($set['solved_count'], $set['usage_count']),
				'difficulty' => Rating::getReadableRankFromRating($set['rating_sum'] / $set['usage_count']),
				'partition' => $set['partition'],
			];
		}
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
		$query->conditions[] = $this->publicMembershipCondition('tsumego.id');
		if (!empty($this->tsumegoFilters->tagIDs))
			$query->conditions[] = $this->tagMembershipCondition('tsumego.id');
		// restrict to the rating range covered by the bands, so rows that would
		// never match a band are not transferred; rows falling into a gap between
		// two bands are dropped by the assignment loop below.
		$hasOpenLower = false;
		$hasOpenUpper = false;
		$minRating = null;
		$maxRating = null;
		foreach ($bands as $band)
		{
			if ($band['min'] === null)
				$hasOpenLower = true;
			elseif ($minRating === null || $band['min'] < $minRating)
				$minRating = $band['min'];
			if ($band['max'] === null)
				$hasOpenUpper = true;
			elseif ($maxRating === null || $band['max'] > $maxRating)
				$maxRating = $band['max'];
		}
		if ($hasOpenLower)
			$minRating = null;
		if ($hasOpenUpper)
			$maxRating = null;
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
		$this->sets = [];
		foreach ($sets as $set)
		{
			$partition = $set['partition'];
			$opacity = ($partition === -1) ? 1 : 1 - ($partition * 0.15);
			$this->sets[] = [
				'id' => $set['name'],
				'name' => $set['name'],
				'amount' => $set['usage_count'],
				'partition' => $partition,
				'color' => str_replace('[o]', (string) $opacity, $set['color']),
				'solved_percent' => Util::getPercentButAvoid100UntilComplete($set['solved_count'], $set['usage_count']),
				'difficulty' => Rating::getReadableRankFromRating($set['rating_sum'] / $set['usage_count']),
			];
		}
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
	 *   usage_count, rating_sum and solved_count.
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
		foreach ($collections as $id => $collection)
		{
			$collectionItems = $items[$id];
			if ($firstKey !== null)
				ksort($collectionItems);
			$total = count($collectionItems);
			$partition = $total <= $size ? -1 : 0;
			$count = $ratingSum = $solvedCount = 0;
			foreach ($collectionItems as [$rating, $tsumegoID])
			{
				$count++;
				$ratingSum += $rating;
				if (isset($solved[$tsumegoID]))
					$solvedCount++;
				if ($count === $size || $partition === -1 && $count === $total)
				{
					$sets[] = self::partitionCard($id, $collection, $partition, $total, $count, $ratingSum, $solvedCount);
					$partition++;
					$count = $ratingSum = $solvedCount = 0;
				}
			}
			if ($count > 0)
				$sets[] = self::partitionCard($id, $collection, $partition, $total, $count, $ratingSum, $solvedCount);
		}
		return $sets;
	}

	/** @param array{name: string, color: string, order: mixed} $collection */
	private static function partitionCard(int|string $id, array $collection, int $partition, int $total, int $count, float $ratingSum, int $solvedCount): array
	{
		return [
			'id' => $id,
			'name' => $collection['name'],
			'color' => $collection['color'],
			'order' => $collection['order'],
			'partition' => $partition,
			'total_count' => $total,
			'usage_count' => $count,
			'rating_sum' => $ratingSum,
			'solved_count' => $solvedCount,
		];
	}

	//The tsumego IDs the current user has solved (status S, W or C), or an empty array for guests.
	/** @return array<int, true> */
	private function getSolvedTsumegoIDs(): array
	{
		if (!Auth::isLoggedIn())
			return [];
		$rows = Util::query("SELECT tsumego_id FROM tsumego_status WHERE user_id = " . Auth::getUserID() . " AND status IN ('S', 'W', 'C')");
		$solved = [];
		foreach ($rows as $row)
			$solved[(int) $row['tsumego_id']] = true;
		return $solved;
	}

	//EXISTS condition for a tsumego belonging to at least one public set (optionally limited to filtered sets).
	private function publicMembershipCondition(string $tsumegoRef): string
	{
		$setCondition = SetTable::officialCondition();
		if (!empty($this->tsumegoFilters->setIDs))
			$setCondition .= ' AND `set`.id IN (' . implode(',', $this->tsumegoFilters->setIDs) . ')';
		return 'EXISTS (SELECT 1 FROM set_connection sc JOIN `set` ON `set`.id = sc.set_id AND ' . $setCondition . ' WHERE sc.tsumego_id = ' . $tsumegoRef . ')';
	}

	//EXISTS condition for a tsumego carrying any of the filtered tags.
	private function tagMembershipCondition(string $tsumegoRef): string
	{
		return 'EXISTS (SELECT 1 FROM tag_connection tc2 WHERE tc2.tsumego_id = ' . $tsumegoRef . ' AND tc2.approved = 1 AND tc2.tag_id IN (' . implode(',', $this->tsumegoFilters->tagIDs) . '))';
	}

	//Rating bound conditions for the filtered ranks, referencing the unaliased tsumego table.
	private function rankCondition(): string
	{
		$rankConditions = '';
		foreach ($this->tsumegoFilters->rankSpecs as $rankFilter)
		{
			$rankCondition = '';
			RatingBounds::coverRankSpec($rankFilter, Rating::weakestRank())->addSqlConditions($rankCondition);
			Util::addSqlOrCondition($rankConditions, $rankCondition);
		}
		return $rankConditions;
	}

	public TsumegoFilters $tsumegoFilters;
	public array $sets = [];
	public ?int $problemsFound = null;
}

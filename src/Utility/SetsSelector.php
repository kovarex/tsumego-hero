<?php

App::uses('Query', 'Utility');

class SetsSelector
{
	public function __construct($tsumegoFilters)
	{
		$this->tsumegoFilters = $tsumegoFilters;
		if ($this->tsumegoFilters->query == 'tags')
			$this->selectByTags();
		elseif ($this->tsumegoFilters->query == 'topics')
			$this->selectByTopics();
		elseif ($this->tsumegoFilters->query == 'difficulty')
			$this->selectByDifficulty();
		$this->problemsFound = $this->tsumegoFilters->calculateCount();
	}

	private function selectByTags()
	{
		$query = new Query('FROM tag_connection tc');
		$query->selects[] = 'tag.id AS tag_id';
		$query->selects[] = 'tag.name AS tag_name';
		$query->selects[] = 'tag.color AS tag_color';
		$query->selects[] = 'tc.tsumego_id';
		$query->selects[] = 'tsumego.rating';
		$query->query .= ' JOIN tag ON tag.id = tc.tag_id';
		$query->query .= ' JOIN tsumego ON tsumego.id = tc.tsumego_id';
		$query->conditions[] = $this->publicMembershipCondition('tc.tsumego_id');
		$query->conditions[] = 'tsumego.deleted IS NULL';
		if (!empty($this->tsumegoFilters->tagIDs))
			$query->conditions[] = $this->tagMembershipCondition('tc.tsumego_id');
		if ($rankCondition = $this->rankCondition())
			$query->conditions[] = $rankCondition;
		$query->orderBy[] = 'tag.id, tc.tsumego_id';
		$rows = Util::query($query->str());

		$sets = $this->buildPartitionedSets($rows, 'tag_id', 'tag_name', 'tag_color');
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

	private static function getTagColor($pos)
	{
		$c = [];
		$c[0] = 'rgba(217, 135, 135, [o])';
		$c[1] = 'rgba(135, 149, 101, [o])';
		$c[2] = 'rgba(190, 151, 131, [o])';
		$c[3] = 'rgba(188, 116, 45, [o])';
		$c[4] = 'rgba(153, 111, 31, [o])';
		$c[5] = 'rgba(159, 54, 0, [o])';
		$c[6] = 'rgba(153, 151, 31, [o])';
		$c[7] = 'rgba(114, 9, 183, [o])';
		$c[8] = 'rgba(149, 77, 63, [o])';
		$c[9] = 'rgba(179, 181, 37, [o])';
		$c[10] = 'rgba(137, 153, 31, [o])';
		$c[11] = 'rgba(145, 61, 91, [o])';
		$c[12] = 'rgba(79, 68, 68, [o])';
		$c[13] = 'rgba(182, 137, 199, [o])';
		$c[14] = 'rgba(166, 88, 125, [o])';
		$c[15] = 'rgba(45, 37, 79, [o])';
		$c[16] = 'rgba(154, 50, 138, [o])';
		$c[17] = 'rgba(102, 51, 122, [o])';
		$c[18] = 'rgba(184, 46, 126, [o])';
		$c[19] = 'rgba(119, 50, 154, [o])';
		$c[20] = 'rgba(187, 70, 196, [o])';
		$c[21] = 'rgba(125, 8, 8, [o])';
		$c[22] = 'rgba(136, 67, 56, [o])';
		$c[23] = 'rgba(190, 165, 136, [o])';
		$c[24] = 'rgba(128, 118, 123, [o])';

		return $c[$pos];
	}

	private function selectByTopics()
	{
		$query = new Query('FROM `set` s');
		$query->selects[] = 's.`order` AS set_order';
		$query->selects[] = 's.id AS set_id';
		$query->selects[] = 's.title AS set_title';
		$query->selects[] = 's.color AS set_color';
		$query->selects[] = 'sc.num';
		$query->selects[] = 'sc.tsumego_id';
		$query->selects[] = 'tsumego.rating';
		$query->query .= ' JOIN set_connection sc ON sc.set_id = s.id';
		$query->query .= ' JOIN tsumego ON tsumego.id = sc.tsumego_id';
		$query->conditions[] = 's.public = 1';
		$query->conditions[] = 'tsumego.deleted IS NULL';
		if (!empty($this->tsumegoFilters->setIDs))
			$query->conditions[] = 's.id IN (' . implode(',', $this->tsumegoFilters->setIDs) . ')';
		if (!empty($this->tsumegoFilters->tagIDs))
			$query->conditions[] = $this->tagMembershipCondition('sc.tsumego_id');
		if ($rankCondition = $this->rankCondition())
			$query->conditions[] = $rankCondition;
		$query->orderBy[] = 's.`order`, s.id, sc.num, sc.tsumego_id';
		$rows = Util::query($query->str());

		$sets = $this->buildPartitionedSets($rows, 'set_id', 'set_title', 'set_color', 'set_order');
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

	private function selectByDifficulty()
	{
		$ranks = SetsController::getExistingRanksArray();

		if (!empty($this->tsumegoFilters->ranks))
			$ranks = array_values(array_filter($ranks, function ($r) {
				return in_array($r['rank'], $this->tsumegoFilters->ranks);
			}));

		// assign each tsumego to a rank band using the same bounds RatingBounds::coverRank uses
		$bands = [];
		$rankOrder = 0;
		foreach ($ranks as $rank)
		{
			$bounds = RatingBounds::coverRank($rank['rank'], '15k');
			$bands[] = [
				'name' => $rank['rank'],
				'color' => $rank['color'],
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
		// never match a band are not transferred; safe because bands are contiguous
		// and any band left open (min or max null) leaves that side unbounded.
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
		$query->orderBy[] = 'tsumego.id';
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

		$sets = $this->buildPartitionedSets($collectionRows, 'rank_label', 'rank_label', 'rank_color', 'rank_order');
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

	/**
	 * Group rows by collection, split each into partitions of the user's collection size,
	 * and compute per-partition stats (count, rating sum, solved count).
	 *
	 * Rows must arrive already ordered so that within a collection they are in the
	 * canonical problem order (the same order the /sets/view page uses).
	 *
	 * @param array $rows Query rows, each with $idField, $nameField, $colorField, tsumego_id and rating.
	 * @param string $idField Row field identifying the collection.
	 * @param string $nameField Row field with the collection display name.
	 * @param string $colorField Row field with the collection color.
	 * @param string|null $orderField Optional row field with the collection sort key.
	 * @return array[] Collection chunks, each with id, name, color, order, partition, total_count,
	 *   usage_count, rating_sum and solved_count.
	 */
	private function buildPartitionedSets(array $rows, string $idField, string $nameField, string $colorField, ?string $orderField = null): array
	{
		$collections = [];
		foreach ($rows as $row)
		{
			$id = $row[$idField];
			if (!isset($collections[$id]))
			{
				$collections[$id] = [
					'id' => $id,
					'name' => $row[$nameField],
					'color' => $row[$colorField],
					'order' => $orderField ? $row[$orderField] : null,
					'items' => [],
				];
			}
			$collections[$id]['items'][] = $row;
		}

		$size = $this->tsumegoFilters->collectionSize;
		$solved = $this->getSolvedTsumegoIDs();

		$sets = [];
		foreach ($collections as $collection)
		{
			$total = count($collection['items']);
			$partitions = [];
			foreach ($collection['items'] as $index => $item)
			{
				$partition = ($total <= $size) ? -1 : intdiv($index, $size);
				$partitions[$partition][] = $item;
			}
			foreach ($partitions as $partition => $items)
			{
				$ratingSum = 0.0;
				$solvedCount = 0;
				foreach ($items as $item)
				{
					$ratingSum += (float) $item['rating'];
					if (isset($solved[(int) $item['tsumego_id']]))
						$solvedCount++;
				}
				$sets[] = [
					'id' => $collection['id'],
					'name' => $collection['name'],
					'color' => $collection['color'],
					'order' => $collection['order'],
					'partition' => $partition,
					'total_count' => $total,
					'usage_count' => count($items),
					'rating_sum' => $ratingSum,
					'solved_count' => $solvedCount,
				];
			}
		}
		return $sets;
	}

	/**
	 * The tsumego IDs the current user has solved (status S, W or C), or an empty array for guests.
	 *
	 * @return array<int, true>
	 */
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

	/**
	 * EXISTS condition for a tsumego belonging to at least one public set (optionally limited to filtered sets).
	 */
	private function publicMembershipCondition(string $tsumegoRef): string
	{
		$setCondition = '`set`.public = 1';
		if (!empty($this->tsumegoFilters->setIDs))
			$setCondition .= ' AND `set`.id IN (' . implode(',', $this->tsumegoFilters->setIDs) . ')';
		return 'EXISTS (SELECT 1 FROM set_connection sc JOIN `set` ON `set`.id = sc.set_id AND ' . $setCondition . ' WHERE sc.tsumego_id = ' . $tsumegoRef . ')';
	}

	/**
	 * EXISTS condition for a tsumego carrying any of the filtered tags.
	 */
	private function tagMembershipCondition(string $tsumegoRef): string
	{
		return 'EXISTS (SELECT 1 FROM tag_connection tc2 WHERE tc2.tsumego_id = ' . $tsumegoRef . ' AND tc2.tag_id IN (' . implode(',', $this->tsumegoFilters->tagIDs) . '))';
	}

	/**
	 * Rating bound conditions for the filtered ranks, referencing the unaliased tsumego table.
	 */
	private function rankCondition(): string
	{
		$rankConditions = '';
		foreach ($this->tsumegoFilters->ranks as $rankFilter)
		{
			$rankCondition = '';
			RatingBounds::coverRank($rankFilter, '15k')->addSqlConditions($rankCondition);
			Util::addSqlOrCondition($rankConditions, $rankCondition);
		}
		return $rankConditions;
	}

	public TsumegoFilters $tsumegoFilters;
	public $sets = [];
	public int $problemsFound = 0;
}

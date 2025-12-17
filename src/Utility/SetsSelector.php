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
		$innerQuery = new Query('FROM tsumego');
		$innerQuery->selects[] = 'tag.id AS tag_id';
		$innerQuery->selects[] = 'tag.name AS tag_name';
		$innerQuery->selects[] = 'tag.color AS tag_color';
		$innerQuery->selects[] = 'COUNT(tsumego.id) AS total_count';
		$innerQuery->groupBy[] = 'tag.id';
		$innerQuery->query .= ' JOIN tag_connection ON tag_connection.tsumego_id = tsumego.id';
		$innerQuery->query .= ' JOIN tag ON tag_connection.tag_id = tag.id';
		$this->tsumegoFilters->addConditionsToQuery($innerQuery);

		$query = "
WITH tag_counts AS (" . $innerQuery->str() . "),
numbered AS (
  SELECT
    tag.id AS tag_id,
    tag.name AS tag_name,
    tag.color AS tag_color,
    tsumego.id AS tsumego_id,
    ROW_NUMBER() OVER (PARTITION BY tag.id ORDER BY tsumego.id) AS rn,
    tsumego_status.status
  FROM tsumego
  JOIN tag_connection ON tag_connection.tsumego_id = tsumego.id
  JOIN tag ON tag.id = tag_connection.tag_id
  LEFT JOIN tsumego_status
      ON tsumego_status.user_id = " . Auth::getUserID() . "
      AND tsumego_status.tsumego_id = tsumego.id
),
partitioned AS (
  SELECT
    n.tag_name AS name,
    n.tag_color AS color,
    t.total_count,
    CASE
      WHEN t.total_count <= " . $this->tsumegoFilters->collectionSize . " THEN -1
      ELSE FLOOR((n.rn - 1) / " . $this->tsumegoFilters->collectionSize . ")
    END AS partition_number,
    COUNT(*) AS usage_count,
    COUNT(CASE WHEN n.status IN ('S', 'W', 'C') THEN 1 END) AS solved_count
  FROM numbered n
  JOIN tag_counts t ON t.tag_id = n.tag_id
  GROUP BY n.tag_name, n.tag_color, t.total_count, partition_number
)
SELECT *
FROM partitioned
ORDER BY total_count DESC, partition_number";

		$tagsRaw = Util::query($query);
		foreach ($tagsRaw as $key => $tagRaw)
		{
			$tag = [];
			$tag['id'] = $tagRaw['name'];
			$tag['amount'] = $tagRaw['usage_count'];
			$tag['name'] = $tagRaw['name'];
			$partition = $tagRaw['partition_number'];
			$colorValue =  1 - (($partition == -1) ? 0 : -($partition * 0.15));
			$tag['color'] = str_replace('[o]', (string) $colorValue, SetsSelector::getTagColor($tagRaw['color']));
			$tag['solved_percent'] = round(Util::getPercent($tagRaw['solved_count'], $tagRaw['usage_count']));
			$tag['partition'] = $partition;
			$this->sets [] = $tag;
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
		$filteredTsumego = new Query('FROM tsumego');
		$filteredTsumego->selects [] = 'DISTINCT tsumego.id';
		$filteredTsumego->selects [] = 'tsumego.rating';
		$this->tsumegoFilters->addConditionsToQuery($filteredTsumego);

		$query = "
WITH filtered_tsumego AS (" . $filteredTsumego->str() . "),

set_counts AS (
  SELECT
    s.id AS set_id,
    s.title AS set_title,
    s.color AS set_color,
    COUNT(sc.tsumego_id) AS total_count
  FROM filtered_tsumego ft
  JOIN set_connection sc ON sc.tsumego_id = ft.id
  JOIN `set` s ON s.id = sc.set_id
  WHERE s.public = 1
  GROUP BY s.id
),

numbered AS (
  SELECT
  	s.`order` AS set_order,
    s.id AS set_id,
    s.title AS set_title,
    s.color AS set_color,
    ft.rating AS rating,
    ft.id AS tsumego_id,
    ROW_NUMBER() OVER (
      PARTITION BY s.id
      ORDER BY ft.id
    ) AS rn,
    ts.status as status
  FROM filtered_tsumego ft
  JOIN set_connection sc ON sc.tsumego_id = ft.id
  JOIN `set` s ON s.id = sc.set_id
  LEFT JOIN tsumego_status ts
    ON ts.user_id = " . Auth::getUserID() . "
    AND ts.tsumego_id = ft.id
  " . (empty($this->tsumegoFilters->setIDs) ? '' : (' WHERE s.id IN (' . implode(',', $this->tsumegoFilters->setIDs) . ')')) . "
),

partitioned AS (
  SELECT
  	numbered.set_order as order_value,
  	numbered.set_id as id,
    numbered.set_title AS title,
    numbered.set_color AS color,
    sc.total_count,
    CASE
      WHEN sc.total_count <= " . $this->tsumegoFilters->collectionSize . " THEN -1
      ELSE FLOOR((numbered.rn - 1) / " . $this->tsumegoFilters->collectionSize . ")
    END AS partition_number,
    COUNT(*) AS usage_count,
    COUNT(CASE WHEN numbered.status IN ('S', 'W', 'C') THEN 1 END) AS solved_count,
    SUM(numbered.rating) AS rating_sum
  FROM numbered
  JOIN set_counts sc ON sc.set_id = numbered.set_id
  GROUP BY
  	numbered.set_order,
  	numbered.set_id,
    numbered.set_title,
    numbered.set_color,
    sc.total_count,
    partition_number
)

SELECT *
FROM partitioned
ORDER BY order_value, total_count DESC, partition_number
";
		$rows = Util::query($query);
		foreach ($rows as $row)
		{
			$set = [];
			$set['id'] = $row['id'];
			$set['name'] = $row['title'];
			$set['amount'] = $row['usage_count'];
			$partition = $row['partition_number'];
			$set['color'] = $row['color'];
			$set['premium'] = $row['premium'];
			$set['solved_percent'] = round(Util::getPercent($row['solved_count'], $row['usage_count']));
			$set['difficulty'] = Rating::getReadableRankFromRating($row['rating_sum'] / $row['usage_count']);
			$set['partition'] = $partition;
			$this->sets[] = $set;
		}
	}

	private function selectByDifficulty()
	{
		$ranksArray = SetsController::getExistingRanksArray();
		$newRanksArray = [];
		if (!empty($this->tsumegoFilters->ranks))
		{
			$ranksArray2 = [];
			$ranksCounter = 0;
			foreach ($this->tsumegoFilters->ranks as $rank)
			{
				$ranksArrayCount2 = count($ranksArray);
				for ($j = 0; $j < $ranksArrayCount2; $j++)
					if ($rank == $ranksArray[$j]['rank'])
					{
						$ranksArray2[$ranksCounter]['rank'] = $ranksArray[$j]['rank'];
						$ranksArray2[$ranksCounter]['color'] = $ranksArray[$j]['color'];
						$ranksCounter++;
					}
			}
			$ranksArray = $ranksArray2;
		}
		foreach ($ranksArray as $rank)
		{
			$condition = "";
			RatingBounds::coverRank($rank['rank'], '15k')->addSqlConditions($condition);
			if (!Auth::hasPremium())
				Util::addSqlCondition($condition, '`set`.premium = false');
			Util::addSqlCondition($condition, 'tsumego.deleted is NULL');
			Util::addSqlCondition($condition, '`set`.public = 1');
			if (!empty($this->tsumegoFilters->setIDs))
				Util::addSqlCondition($condition, '`set`.id IN (' . implode(',', $this->tsumegoFilters->setIDs) . ')');
			$tsumegoIDs = ClassRegistry::init('Tsumego')->query(
				"SELECT tsumego.id "
				. "FROM tsumego JOIN set_connection ON set_connection.tsumego_id = tsumego.id"
				. " JOIN `set` ON `set`.id=set_connection.set_id WHERE " . $condition
			) ?: [];
			$currentIds = [];
			foreach ($tsumegoIDs as $tsumegoID)
				$currentIds [] = $tsumegoID['tsumego']['id'];
			$setAmount = count($tsumegoIDs);

			if (count($this->tsumegoFilters->tags) > 0)
			{
				$idsTemp = [];
				$tsTagsFiltered = ClassRegistry::init('TagConnection')->find('all', [
					'conditions' => [
						'tsumego_id' => $currentIds,
						'tag_id' => $this->tsumegoFilters->tagIDs,
					],
				]) ?: [];
				$tsTagsFilteredCount2 = count($tsTagsFiltered);
				for ($j = 0; $j < $tsTagsFilteredCount2; $j++)
					array_push($idsTemp, $tsTagsFiltered[$j]['TagConnection']['tsumego_id']);

				$currentIds = array_unique($idsTemp);
				$setAmount = count($currentIds);
			}

			$this->problemsFound += $setAmount;

			$rTemp = [];
			$rTemp['id'] = $rank['rank'];
			$rTemp['name'] = $rank['rank'];
			$rTemp['amount'] = $setAmount;
			$rTemp['currentIds'] = $currentIds;
			$rTemp['color'] = $rank['color'];
			if (!empty($currentIds))
				$newRanksArray [] = $rTemp;
		}
		$tsumegoStatusMap = Auth::isLoggedIn() ? TsumegoUtil::getMapForCurrentUser() : [];
		$this->sets = $this->partitionCollections($newRanksArray, $this->tsumegoFilters->collectionSize, $tsumegoStatusMap);
	}

	private function partitionCollections($list, $size, $tsumegoStatusMap)
	{
		$newList = [];
		$listCount = count($list);
		for ($i = 0; $i < $listCount; $i++)
		{
			$amountTags = $list[$i]['amount'];
			$amountCounter = 0;
			$amountFrom = 0;
			$amountTo = $size - 1;
			while ($amountTags > $size)
			{
				$newList = $this->partitionCollection($newList, $list[$i], $size, $tsumegoStatusMap, $amountFrom, $amountTo + 1, $amountCounter, true);
				$amountTags -= $size;
				$amountCounter++;
				$amountFrom += $size;
				$amountTo += $size;
			}
			$amountTo = $amountFrom + $amountTags;
			$newList = $this->partitionCollection($newList, $list[$i], $amountTags, $tsumegoStatusMap, $amountFrom, $amountTo, $amountCounter, false);
		}

		return $newList;
	}

	private function partitionCollection($newList, $list, $size, $tsumegoStatusMap, $from, $to, $amountCounter, $inLoop)
	{
		$tl = [];
		$tl['id'] = $list['id'];
		$colorValue = 1;
		if (!$inLoop && $amountCounter == 0)
			$tl['partition'] = -1;
		else
		{
			$tl['partition'] = $amountCounter;
			$step = 1.5;
			$colorValue = 1 - ($amountCounter * 0.1 * $step);
		}
		$tl['name'] = $list['name'];
		$tl['amount'] = $size;
		$tl['color'] = str_replace('[o]', (string) $colorValue, $list['color']);
		if (isset($list['premium']))
			$tl['premium'] = $list['premium'];
		else
			$tl['premium'] = 0;
		$currentIds = [];
		for ($i = $from; $i < $to; $i++)
			array_push($currentIds, $list['currentIds'][$i]);
		$difficultyAndSolved = SetsController::getDifficultyAndSolved($currentIds, $tsumegoStatusMap);
		$tl['difficulty'] = $difficultyAndSolved['difficulty'];
		$tl['solved_percent'] = $difficultyAndSolved['solved'];
		array_push($newList, $tl);

		return $newList;
	}

	public TsumegoFilters $tsumegoFilters;
	public $sets = [];
	public int $problemsFound = 0;
}

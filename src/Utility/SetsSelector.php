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
			$tag['solved_percent'] = Util::getPercentButAvoid100UntilComplete($tagRaw['solved_count'], $tagRaw['usage_count']);
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
      ORDER BY sc.num, ft.id
    ) AS rn,
    ts.status as status
  FROM filtered_tsumego ft
  JOIN set_connection sc ON sc.tsumego_id = ft.id
  JOIN `set` s ON s.id = sc.set_id AND s.public = 1
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
ORDER BY order_value, total_count DESC, partition_number, id
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
			$set['solved_percent'] = Util::getPercentButAvoid100UntilComplete($row['solved_count'], $row['usage_count']);
			$set['difficulty'] = Rating::getReadableRankFromRating($row['rating_sum'] / $row['usage_count']);
			$set['partition'] = $partition;
			$this->sets[] = $set;
		}
	}

	private function selectByDifficulty()
	{
		$ranks = SetsController::getExistingRanksArray();

		if (!empty($this->tsumegoFilters->ranks))
			$ranks = array_values(array_filter($ranks, function ($r) { return in_array($r['rank'], $this->tsumegoFilters->ranks); }));

		$rankSelects = [];
		$rankOrder = 0;

		foreach ($ranks as $rank)
		{
			$rankQuery = new Query('FROM tsumego');
			RatingBounds::coverRank($rank['rank'], '15k')->addQueryConditions($rankQuery);

			$rankQuery->conditions[] = 'tsumego.deleted IS NULL';
			$rankQuery->conditions[] = '`set`.public = 1';
			if (!empty($this->tsumegoFilters->setIDs))
				$rankQuery->conditions[] = '`set`.id IN (' . implode(',', $this->tsumegoFilters->setIDs) . ')';

			if (!empty($this->tsumegoFilters->tagIDs))
				$rankQuery->conditions[]
				= 'EXISTS (
						SELECT 1 FROM tag_connection tc
						WHERE tc.tsumego_id = tsumego.id
						AND tc.tag_id IN (' . implode(',', $this->tsumegoFilters->tagIDs) . ')
					)';
			$rankQuery->selects[] = 'DISTINCT tsumego.id AS tsumego_id';
			$rankQuery->selects[] = 'tsumego.rating';
			$rankQuery->selects[] = "'{$rank['rank']}' AS rank_label";
			$rankQuery->selects[] = "{$rankOrder} AS rank_order";
			$rankQuery->selects[] = "'{$rank['color']}' AS rank_color";
			$rankQuery->query .= " JOIN set_connection sc ON sc.tsumego_id = tsumego.id
				JOIN `set` ON `set`.id = sc.set_id";
			$rankSelects[] = $rankQuery->str();
			$rankOrder++;
		}

		$rankUnion = implode("\nUNION ALL\n", $rankSelects);

		$query = "
	WITH ranked_tsumego AS ({$rankUnion}),

	rank_counts AS (
    SELECT
        rank_label,
        COUNT(*) AS total_count
    FROM ranked_tsumego
    GROUP BY rank_label
),

	numbered AS (
		SELECT
			rt.rank_label,
			rt.rank_order,
			rt.rank_color,
			rt.tsumego_id,
			rt.rating,
			ROW_NUMBER() OVER (
				PARTITION BY rt.rank_label
				ORDER BY rt.tsumego_id
			) AS rn,
			ts.status
		FROM ranked_tsumego rt
		LEFT JOIN tsumego_status ts
			ON ts.user_id = " . Auth::getUserID() . "
			AND ts.tsumego_id = rt.tsumego_id
	),

	partitioned AS (
    SELECT
        n.rank_label AS id,
        n.rank_label AS name,
        n.rank_color AS color,
        n.rank_order,
        rc.total_count,
        CASE
            WHEN rc.total_count <= {$this->tsumegoFilters->collectionSize} THEN -1
            ELSE FLOOR((n.rn - 1) / {$this->tsumegoFilters->collectionSize})
        END AS partition_number,
        COUNT(*) AS usage_count,
        COUNT(CASE WHEN n.status IN ('S','W','C') THEN 1 END) AS solved_count,
        SUM(n.rating) AS rating_sum
    FROM numbered n
    JOIN rank_counts rc
        ON rc.rank_label = n.rank_label
    GROUP BY
        n.rank_label,
        n.rank_color,
        n.rank_order,
        rc.total_count,
        partition_number
)

	SELECT *
	FROM partitioned
	ORDER BY rank_order, partition_number";

		$rows = Util::query($query);
		foreach ($rows as $row)
		{
			$set = [];
			$set['id'] = $row['id'];
			$set['name'] = $row['name'];
			$set['amount'] = $row['usage_count'];
			$set['partition'] = $row['partition_number'];

			$opacity = ($row['partition_number'] === -1)
				? 1
				: 1 - ($row['partition_number'] * 0.15);

			$set['color'] = str_replace('[o]', (string) $opacity, $row['color']);
			$set['solved_percent'] = Util::getPercentButAvoid100UntilComplete($row['solved_count'], $row['usage_count']);
			$set['difficulty'] = Rating::getReadableRankFromRating($row['rating_sum'] / $row['usage_count']);

			$this->sets[] = $set;
		}
	}

	public TsumegoFilters $tsumegoFilters;
	public $sets = [];
	public int $problemsFound = 0;
}

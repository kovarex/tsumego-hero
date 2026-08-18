<?php

App::uses('Query', 'Utility');

class TsumegoButtonsQueryBuilder
{
	public Query $query;
	public string $description = '';
	private TsumegoFilters $tsumegoFilters;

	public function __construct($tsumegoFilters, $id)
	{
		$this->tsumegoFilters = $tsumegoFilters;

		if ($tsumegoFilters->query == 'mistake_training')
		{
			$this->buildMistakeTrainingQuery();
			return;
		}

		$this->query = new Query('FROM tsumego');
		if ($tsumegoFilters->query != 'topics')
		{
			$this->query->selects[] = "ROW_NUMBER() OVER (PARTITION BY tsumego.id ORDER BY set_connection.id, set_connection.num, tsumego.id) AS rn";
			$this->query->prefix = "SELECT tsumego_id, set_connection_id, num, rating, sgf";
			if (Auth::isLoggedIn())
				$this->query->prefix .= ", status";
			if ($tsumegoFilters->query == 'published')
				$this->query->prefix .= ", set_id, set_title";
			$this->query->prefix .= " FROM (";
			$this->query->suffix = ") x WHERE rn = 1 ORDER BY tsumego_id";
			$this->query->orderBy[] = 'tsumego.id';
		}
		else
			$this->query->orderBy[] = 'set_connection.num, set_connection.id';
		$this->tsumegoFilters = $tsumegoFilters;

		$this->query->selects[] = 'tsumego.id as tsumego_id';
		$this->query->selects[] = 'tsumego.rating as rating';
		$this->query->selects[] = 'set_connection.id as set_connection_id';
		$this->query->selects[] = 'set_connection.num as num';
		$this->query->selects[] = 'COALESCE(sgf.sgf, \'\') as sgf';
		if ($tsumegoFilters->query == 'published')
		{
			$this->query->selects[] = 'set_connection.set_id as set_id';
			$this->query->selects[] = '`set`.title as set_title';
		}
		if (Auth::isLoggedIn())
			$this->query->selects[] = 'tsumego_status.status as status';

		$this->query->query .= " JOIN set_connection ON set_connection.tsumego_id = tsumego.id";
		$this->query->query .= " LEFT JOIN sgf ON sgf.id = (SELECT MAX(s2.id) FROM sgf s2 WHERE s2.tsumego_id = tsumego.id)";
		$this->query->conditions[] = 'tsumego.deleted is NULL';

		// when I'm quering by topics (which means sets), and I'm viewing private set
		// It means I explicitelly want to view that.
		// In all other cases, private set is not included.
		if ($tsumegoFilters->query != 'topics')
			$this->query->conditions [] = '`set`.public = 1';

		$this->query->query .= " JOIN `set` ON `set`.id=set_connection.set_id";
		if (Auth::isLoggedIn())
			$this->query->query .= ' LEFT JOIN tsumego_status ON tsumego_status.user_id = ' . Auth::getUserID() . ' AND tsumego_status.tsumego_id = tsumego.id';

		$this->filterRanks();
		$this->filterSets();
		$this->filterTags();
		$this->queryRank();
		$this->queryTag();
		$this->querySet($id);
		$this->queryPublished();
	}

	private function filterRanks(): void
	{
		if ($this->tsumegoFilters->query == 'difficulty') // we filter by ranks unless we query a specific difficulty
			return;
		$this->tsumegoFilters->filterRanks($this->query);
	}

	private function filterSets()
	{
		if ($this->tsumegoFilters->query == 'topics') // we filter by sets unless we query a specific set
			return;
		$this->tsumegoFilters->filterSets($this->query);
	}

	private function filterTags()
	{
		if ($this->tsumegoFilters->query == 'tags') // we filter by tags unless we query a specific tag
			return;
		$this->tsumegoFilters->filterTags($this->query);
	}

	private function queryRank()
	{
		if ($this->tsumegoFilters->query != 'difficulty')
			return;
		if (empty($_COOKIE['lastSet']))
		{
			// when we don't have lastSet, we fallback to showing by topics (the set of current tsumego)
			$this->tsumegoFilters->query = 'topics';
			return;
		}
		try
		{
			$ratingBounds = RatingBounds::coverRank($_COOKIE['lastSet'], '15k');
			$ratingBounds->addQueryConditions($this->query);
			$this->description = $_COOKIE['lastSet'] . ' are problems that have a rating ' . $ratingBounds->textualDescription() . '.';
		}
		catch (RatingParseException $e)
		{
			// when we get bad lastSet value, we fallback to showing by topics (the set of current tsumego)
			$this->tsumegoFilters->query = 'topics';
		}
	}

	private function queryTag()
	{
		if ($this->tsumegoFilters->query != 'tags')
			return;

		$currentTag = $_COOKIE['lastSet'] ?? '';
		if (empty($currentTag))
		{
			$this->tsumegoFilters->query = 'topics';
			return;
		}

		$tag = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $currentTag]]);
		if (!$tag)
		{
			$this->tsumegoFilters->query = 'topics';
			return;
		}
		$this->query->query .= ' LEFT JOIN tag_connection ON tag_connection.tsumego_id=tsumego.id';
		$this->query->conditions[] = 'tag_connection.tag_id=' . $tag['Tag']['id'];
	}

	private function querySet($id)
	{
		if ($this->tsumegoFilters->query != 'topics')
			return;
		$this->query->conditions[] = '`set`.id=' . $id;
	}

	private function queryPublished()
	{
		if ($this->tsumegoFilters->query != 'published')
			return;
		$this->query->query .= ' JOIN schedule ON `schedule`.tsumego_id = tsumego.id AND schedule.set_id = `set`.id';
		$date = $this->tsumegoFilters->publishedDate ?: date('Y-m-d');
		$this->query->conditions[] = "`schedule`.date = '" . $date . "'";
		$this->query->conditions[] = '`schedule`.published = 1';
	}

	/**
	 * Build a completely custom query for mistake training.
	 * Starts from tsumego_status instead of tsumego, deduplicates by tsumego_id,
	 * orders by mt_due ASC (most overdue first).
	 */
	private function buildMistakeTrainingQuery(): void
	{
		// Build the complete SQL directly, since we start from tsumego_status
		// rather than tsumego and cannot reuse Query::str().
		$this->mistakeTrainingSql = "
			SELECT
				ts.tsumego_id,
				sc.id AS set_connection_id,
				sc.num,
				ts.status,
				t.rating,
				COALESCE(sgf.sgf, '') AS sgf
			FROM tsumego_status ts
			JOIN set_connection sc ON sc.tsumego_id = ts.tsumego_id
			JOIN tsumego t ON t.id = ts.tsumego_id
			LEFT JOIN sgf ON sgf.id = (SELECT MAX(s2.id) FROM sgf s2 WHERE s2.tsumego_id = ts.tsumego_id)
			WHERE ts.user_id = ?
			  AND ts.mt_due IS NOT NULL
			  AND ts.mt_due <= NOW()
			  AND t.deleted IS NULL
			ORDER BY ts.mt_due ASC, sc.id
		";
	}

	/**
	 * Get the SQL string. For mistake_training, returns the custom SQL directly.
	 * For all other query types, uses the standard Query builder.
	 */
	public function getSql(): string
	{
		if ($this->mistakeTrainingSql !== '')
			return $this->mistakeTrainingSql;
		return $this->query->str();
	}

	/**
	 * Get query params. For mistake_training, returns the user ID.
	 * For all other query types, returns empty (no params needed).
	 */
	public function getParams(): array
	{
		return $this->mistakeTrainingSql !== '' ? [Auth::getUserID()] : [];
	}

	private string $mistakeTrainingSql = '';
}

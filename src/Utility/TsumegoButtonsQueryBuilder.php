<?php

class TsumegoButtonsQueryBuilder
{
	public function __construct($tsumegoFilters, $id)
	{
		$this->orderBy = 'set_connection.num, set_connection.id';
		$this->tsumegoFilters = $tsumegoFilters;
		$this->query = "SELECT tsumego.id, set_connection.id, set_connection.num, tsumego.alternative_response, tsumego.pass";
		if (Auth::isLoggedIn())
			$this->query .= ', tsumego_status.status';

		$this->query .= " FROM tsumego JOIN set_connection ON set_connection.tsumego_id = tsumego.id";
		Util::addSqlCondition($this->condition, 'tsumego.deleted is NULL');

		// when I'm quering by topics (which means sets), and I'm viewing private set
		// It means I explicitelly want to view that.
		// In all other cases, private set is not included.
		if ($tsumegoFilters->query != 'topics')
			Util::addSqlCondition($this->condition, '`set`.public = 1');

		$this->query .= " JOIN `set` ON `set`.id=set_connection.set_id";
		if (Auth::isLoggedIn())
			$this->query .= ' LEFT JOIN tsumego_status ON tsumego_status.user_id = ' . Auth::getUserID() . ' AND tsumego_status.tsumego_id = tsumego.id';
		if (!Auth::hasPremium())
			Util::addSqlCondition($this->condition, '`set`.premium = false');

		$this->filterRanks();
		$this->filterSets();
		$this->filterTags();
		$this->queryRank();
		$this->queryTag();
		$this->querySet($id);
		$this->queryFavorites();
		$this->queryPublished();

		$this->query .= ' WHERE ' . $this->condition;
		$this->query .= " ORDER BY " . $this->orderBy;
	}

	private function filterRanks()
	{
		if ($this->tsumegoFilters->query == 'difficulty') // we filter by ranks unless we query a specific difficulty
		{return;
		}

		$rankConditions = '';
		foreach ($this->tsumegoFilters->ranks as $rankFilter)
		{
			$rankCondition = '';
			RatingBounds::coverRank($rankFilter, '15k')->addSqlConditions($rankCondition);
			Util::addSqlOrCondition($rankConditions, $rankCondition);
		}
		Util::addSqlCondition($this->condition, $rankConditions);
	}

	private function filterSets()
	{
		if ($this->tsumegoFilters->query == 'topics') // we filter by sets unless we query a specific set
		{return;
		}

		if (!empty($this->tsumegoFilters->setIDs))
			Util::addSqlCondition($this->condition, '`set`.id IN (' . implode(',', $this->tsumegoFilters->setIDs) . ')');
	}

	private function filterTags()
	{
		if ($this->tsumegoFilters->query == 'tags') // we filter by tags unless we query a specific tag
		{return;
		}

		if (empty($this->tsumegoFilters->tagIDs))
			return;

		Util::addSqlCondition($this->condition, '`tag_connection`.tag_id IN (' . implode(',', $this->tsumegoFilters->tagIDs) . ')');
		$this->query .= ' LEFT JOIN tag_connection ON tag_connection.tsumego_id=tsumego.id';
	}

	private function queryRank()
	{
		if ($this->tsumegoFilters->query != 'difficulty')
			return;
		$currentRank = $_COOKIE['lastSet'] ?? '15k';
		$ratingBounds = RatingBounds::coverRank($currentRank, '15k');
		$ratingBounds->addSqlConditions($this->condition);
		$this->description = $currentRank . ' are problems that have a rating ' . $ratingBounds->textualDescription() . '.';
	}

	private function queryTag()
	{
		if ($this->tsumegoFilters->query != 'tags')
			return;

		$currentTag = $_COOKIE['lastSet'] ?? '';
		$tag = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $currentTag]]);
		if (!$tag)
			throw new Exception("The tag selected to view ('.$currentTag.') couldn't be found");
		$this->query .= ' LEFT JOIN tag_connection ON tag_connection.tsumego_id=tsumego.id';
		Util::addSqlCondition($this->condition, 'tag_connection.tag_id=' . $tag['Tag']['id']);
	}

	private function querySet($id)
	{
		if ($this->tsumegoFilters->query != 'topics')
			return;
		Util::addSqlCondition($this->condition, '`set`.id=' . $id);
	}

	private function queryFavorites()
	{
		if ($this->tsumegoFilters->query != 'favorites')
			return;
		$this->query .= ' JOIN favorite ON `favorite`.user_id =' . Auth::getUserID() . ' AND favorite.tsumego_id = tsumego.id';
		$this->orderBy = 'favorite.id ASC';
	}

	private function queryPublished()
	{
		if ($this->tsumegoFilters->query != 'published')
			return;
		$this->query .= ' JOIN schedule ON `schedule`.tsumego_id = tsumego.id AND schedule.set_id = `set`.id';
		Util::addSqlCondition($this->condition, "`schedule`.date = '" . date('Y-m-d') . "'");
	}

	private TsumegoFilters $tsumegoFilters;
	private string $condition = "";
	public string $query = "";
	public string $description = "";
	public string $orderBy = "";
}

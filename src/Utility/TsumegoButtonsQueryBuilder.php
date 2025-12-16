<?php

App::uses('Query', 'Utility');

class TsumegoButtonsQueryBuilder
{
	public function __construct($tsumegoFilters, $id)
	{
		$this->query = new Query('FROM tsumego');
		$this->query->orderBy[]= 'set_connection.num, set_connection.id';
		$this->tsumegoFilters = $tsumegoFilters;
		$this->query->selects[] = 'tsumego.id as tsumego_id';
		$this->query->selects[] = 'set_connection.id as set_connection_id';
		$this->query->selects[] = 'set_connection.num as num';
		if (Auth::isLoggedIn())
			$this->query->selects[]= 'tsumego_status.status as status';

		$this->query->query .= " JOIN set_connection ON set_connection.tsumego_id = tsumego.id";
		$this->query->conditions[]= 'tsumego.deleted is NULL';

		// when I'm quering by topics (which means sets), and I'm viewing private set
		// It means I explicitelly want to view that.
		// In all other cases, private set is not included.
		if ($tsumegoFilters->query != 'topics')
			$this->query->conditions []= '`set`.public = 1';

		$this->query->query .= " JOIN `set` ON `set`.id=set_connection.set_id";
		if (Auth::isLoggedIn())
			$this->query->query .= ' LEFT JOIN tsumego_status ON tsumego_status.user_id = ' . Auth::getUserID() . ' AND tsumego_status.tsumego_id = tsumego.id';
		if (!Auth::hasPremium())
			$this->query->conditions[]= '`set`.premium = false';

		$this->filterRanks();
		$this->filterSets();
		$this->filterTags();
		$this->queryRank();
		$this->queryTag();
		$this->querySet($id);
		$this->queryFavorites();
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
		$currentRank = $_COOKIE['lastSet'] ?? '15k';
		$ratingBounds = RatingBounds::coverRank($currentRank, '15k');
		$ratingBounds->addQueryConditions($this->query);
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
		$this->query->query .= ' LEFT JOIN tag_connection ON tag_connection.tsumego_id=tsumego.id';
		$this->query->conditions[]= 'tag_connection.tag_id=' . $tag['Tag']['id'];
	}

	private function querySet($id)
	{
		if ($this->tsumegoFilters->query != 'topics')
			return;
		$this->query->conditions[]= '`set`.id=' . $id;
	}

	private function queryFavorites()
	{
		if ($this->tsumegoFilters->query != 'favorites')
			return;
		$this->query->query .= ' JOIN favorite ON `favorite`.user_id =' . Auth::getUserID() . ' AND favorite.tsumego_id = tsumego.id';
		$this->query->orderBy = ['favorite.id ASC'];
	}

	private function queryPublished()
	{
		if ($this->tsumegoFilters->query != 'published')
			return;
		$this->query->query .= ' JOIN schedule ON `schedule`.tsumego_id = tsumego.id AND schedule.set_id = `set`.id';
		$this->query->conditions[]= "`schedule`.date = '" . date('Y-m-d') . "'";
	}

	private TsumegoFilters $tsumegoFilters;
	public Query $query;
	public string $description = "";
}

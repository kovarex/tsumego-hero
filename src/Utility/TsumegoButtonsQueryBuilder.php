<?php

class TsumegoButtonsQueryBuilder {
	public function __construct($tsumegoFilters, $id) {
		$this->tsumegoFilters = $tsumegoFilters;
		$this->query = "SELECT tsumego.id, set_connection.id, set_connection.num, tsumego.alternative_response, tsumego.pass";
		if (Auth::isLoggedIn()) {
			$this->query .= ', tsumego_status.status';
		}

		$this->query .= " FROM tsumego JOIN set_connection ON set_connection.tsumego_id = tsumego.id";
		Util::addSqlCondition($this->condition, 'tsumego.deleted is NULL');
		$this->query .= " JOIN `set` ON `set`.id=set_connection.set_id";
		if (Auth::isLoggedIn()) {
			$this->query .= ' LEFT JOIN tsumego_status ON tsumego_status.user_id = ' . Auth::getUserID() . ' AND tsumego_status.tsumego_id = tsumego.id';
		}
		if (!Auth::hasPremium()) {
			Util::addSqlCondition($this->condition, '`set`.premium = false');
		}

		$this->filterRanks();
		$this->filterSets();
		$this->filterTags();
		$this->queryRank();
		$this->queryTag();
		$this->querySet($id);

		$this->query .= ' WHERE ' . $this->condition;
		$this->query .= " ORDER BY set_connection.num";
	}

	private function filterRanks() {
		if ($this->tsumegoFilters->query == 'difficulty') { // we filter by ranks unless we query a specific difficulty
			return;
		}

		$rankConditions = '';
		foreach ($this->tsumegoFilters->ranks as $rankFilter) {
			$rankCondition = '';
			RatingBounds::coverRank($rankFilter, '15k')->addSqlConditions($rankCondition);
			Util::addSqlOrCondition($rankConditions, $rankCondition);
		}
		Util::addSqlCondition($this->condition, $rankConditions);
	}

	private function filterSets() {
		if ($this->tsumegoFilters->query == 'topics') { // we filter by sets unless we query a specific set
			return;
		}

		if (!empty($this->tsumegoFilters->setIDs)) {
			Util::addSqlCondition($this->condition, '`set`.id IN (' . implode(',', $this->tsumegoFilters->setIDs) . ')');
		}
	}

	private function filterTags() {
		if ($this->tsumegoFilters->query == 'tags') { // we filter by tags unless we query a specific tag
			return;
		}

		if (empty($this->tsumegoFilters->tagIDs)) {
			return;
		}

		Util::addSqlCondition($this->condition, '`tag`.id IN (' . implode(',', $this->tsumegoFilters->tagIDs) . ')');
		$this->query .= ' LEFT JOIN tag ON tag.tsumego_id=tsumego.id';
	}

	private function queryRank() {
		if ($this->tsumegoFilters->query != 'difficulty') {
			return;
		}
		$currentRank = CakeSession::read('lastSet');
		$ratingBounds = RatingBounds::coverRank($currentRank, '15k');
		$ratingBounds->addSqlConditions($this->condition);
		$this->description = $currentRank . ' are problems that have a rating ' . $ratingBounds->textualDescription() . '.';
	}

	private function queryTag() {
		if ($this->tsumegoFilters->query != 'tags') {
			return;
		}

		$currentTag = CakeSession::read('lastSet');
		$tag = ClassRegistry::init('TagName')->find('first', ['conditions' => ['name' => $currentTag]]);
		if (!$tag) {
			throw new Exception("The tag selected to view ('.$currentTag.') couldn't be found");
		}
		$this->query .= ' LEFT JOIN tag ON tag.tsumego_id=tsumego.id';
		Util::addSqlCondition($this->condition, '`tag`.tag_name_id=' . $tag['TagName']['id']);
	}

	private function querySet($id) {
		if ($this->tsumegoFilters->query != 'topics') {
			return;
		}
		Util::addSqlCondition($this->condition, '`set`.id=' . $id);
	}

	private TsumegoFilters $tsumegoFilters;
	private string $condition = "";
	public string $query = "";
	public string $description = "";
}

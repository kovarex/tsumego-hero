<?php

class TsumegoButtons extends ArrayObject {
	public function fill(string $condition, ?array $rankFilters = null) {
		if (!Auth::hasPremium()) {
			Util::addSqlCondition($condition, '`set`.premium = false');
		}

		$rankConditions = '';
		foreach ($rankFilters as $rankFilter) {
			$rankCondition = '';
			RatingBounds::coverRank($rankFilter, '15k')->addSqlConditions($rankCondition);
			Util::addSqlOrCondition($rankConditions, $rankCondition);
		}
		Util::addSqlCondition($condition, $rankConditions);
		Util::addSqlCondition($condition, 'tsumego.public = true');

		$query = "SELECT tsumego.id, set_connection.id, set_connection.num, tsumego.alternative_response, tsumego.pass";
		if (Auth::isLoggedIn()) {
			$query .= ', tsumego_status.status';
		}
		$query .= " FROM tsumego JOIN set_connection ON set_connection.tsumego_id = tsumego.id";
		$query .= " JOIN `set` ON `set`.id=set_connection.set_id";
		if (Auth::isLoggedIn()) {
			$query .= ' LEFT JOIN tsumego_status ON tsumego_status.user_id = ' . Auth::getUserID() . ' AND tsumego_status.tsumego_id = tsumego.id';
		}
		$query .= ' WHERE ' . $condition;
		$query .= " ORDER BY set_connection.num";
		$result = ClassRegistry::init('Tsumego')->query($query);

		foreach ($result as $index => $row) {
			$this [] = new TsumegoButton(
				$row['tsumego']['id'],
				$row['set_connection']['id'],
				$row['set_connection']['num'],
				Auth::isLoggedIn() ? ($row['tsumego_status']['status'] ?: 'N') : 'N',
				$row['tsumego']['alternative_response'],
				$row['tsumego']['pass']
			);
		}
	}

	public function resetOrders(): void {
		foreach ($this as $key => $tsumegoButton) {
			$tsumegoButton->order = $key + 1;
		}
	}
	public function filterByTags(array $tagIDs): void {
		if (empty($tagIDs)) {
			return;
		}
		$this->exchangeArray(array_values(array_filter((array) $this, function ($tsumegoButton) use ($tagIDs): bool {
			return ClassRegistry::init('Tag')->find('first', [
				'conditions' => [
					'tsumego_id' => $tsumegoButton->tsumegoID,
					'tag_name_id' => $tagIDs,
				]]) != null;
		})));
	}

	public function filterByIndexRange(int $from, int $to): void {
		$this->exchangeArray(array_values(array_filter(
			(array) $this,
			function ($tsumegoButton, $index) use ($from, $to): bool {
				return $index >= $from && $index <= $to;
			},
			ARRAY_FILTER_USE_BOTH
		)));
	}
}

<?php

App::uses('Preferences', 'Utility');
App::uses('Query', 'Utility');
App::uses('Rating', 'Utility');
App::uses('Constants', 'Utility');
App::uses('SetsController', 'Controller');

class TsumegoFilters
{
	public function __construct(?string $newQuery = null, ?string $publishedDate = null)
	{
		$this->publishedDate = $publishedDate;
		if ($newQuery == 'published')
		{
			$this->query = $newQuery;
			return;
		}

		$this->query = self::processItem('query', 'topics', null, $newQuery);
		if (!in_array($this->query, ['topics', 'difficulty', 'tags'], true))
			$this->query = 'topics';
		$this->collectionSize = max(Constants::$MIN_COLLECTION_SIZE, min(Constants::$MAX_COLLECTION_SIZE, (int) self::processItem('collection_size', (string) Constants::$DEFAULT_COLLECTION_SIZE)));
		$rawSets = self::processItem('filtered_sets', [], function ($input) {
			return array_values(array_filter(explode('@', $input)));
		});

		foreach ($rawSets as $set)
		{
			$found = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $set, 'public' => 1]]);
			if ($found)
			{
				$this->sets[] = $set;
				$this->setIDs[] = $found['Set']['id'];
			}
		}

		$this->ranks = self::processItem('filtered_ranks', [], function ($input) {
			return array_values(array_filter(explode('@', $input), fn($rank) => Rating::isValidReadableRank($rank)));
		});
		$rawTags = self::processItem('filtered_tags', [], function ($input) {
			return array_values(array_filter(explode('@', $input)));
		});

		foreach ($rawTags as $tag)
		{
			$found = ClassRegistry::init('Tag')->findByName($tag);
			if ($found)
			{
				$this->tags[] = $tag;
				$this->tagIDs[] = $found['Tag']['id'];
			}
		}
	}


	/**
	 * Process a preference item with optional transformation and new value override.
	 *
	 * @param string $name The preference key
	 * @param mixed $default Default value if not set
	 * @param callable|null $processToResult Optional callback to transform the stored string value
	 * @param string|null $newValue Optional new value to set
	 * @return mixed The processed value
	 */
	private static function processItem(string $name, mixed $default, $processToResult = null, ?string $newValue = null)
	{
		// Get current value from Preferences (handles both logged-in and guest storage)
		$stringResult = Preferences::get($name, '');

		// Check for cookie override (used for filter links like /topics?filtered_sets=SetName)
		if (!empty($_COOKIE[$name]))
		{
			$stringResult = $_COOKIE[$name];
			if ($stringResult == 'clear')
			{
				Util::clearCookie($name);
				$stringResult = '';
			}
		}

		// Apply new value if provided
		if ($newValue)
		{
			$stringResult = $newValue;
			// Clear the cookie override so subsequent requests
			// read from preferences instead of stale cookie value.
			if (!empty($_COOKIE[$name]))
				Util::clearCookie($name);
		}

		// Save back to preferences if value changed or was overridden
		Preferences::set($name, $stringResult);

		// Return default if empty
		if (!$stringResult)
			return $default;

		// Apply transformation if provided
		return $processToResult ? $processToResult($stringResult) : $stringResult;
	}

	public function getSetTitle($set): string
	{
		if ($this->query == 'topics')
			return $set['Set']['title'];
		if ($this->query == 'difficulty')
			return $_COOKIE['lastSet'] ?? 'Tsumego';
		if ($this->query == 'tags')
			return $_COOKIE['lastSet'] ?? 'Tsumego';

		throw new Exception('Unknown query: ""' . $this->query);
	}

	public function getSetID($set): string
	{
		if ($this->query == 'topics')
			return $set['Set']['id'];
		if ($this->query == 'difficulty')
			return $_COOKIE['lastSet'] ?? 'favorites';
		if ($this->query == 'tags')
			return $_COOKIE['lastSet'] ?? 'favorites';

		return "Unsupported yet";
	}

	public function filterRanks(Query $query): void
	{
		if (empty($this->ranks))
			return;

		$rankConditions = '';
		foreach ($this->ranks as $rankFilter)
		{
			$rankCondition = '';
			RatingBounds::coverRank($rankFilter, '15k')->addSqlConditions($rankCondition);
			Util::addSqlOrCondition($rankConditions, $rankCondition);
		}
		$query->conditions[] = $rankConditions;
	}

	public function filterTags(Query $query): void
	{
		if (empty($this->tagIDs))
			return;
		if (!str_contains($query->query, 'JOIN tag_connection'))
			$query->query .= ' JOIN tag_connection ON tag_connection.tsumego_id = tsumego.id';
		$query->conditions[] = 'tag_connection.tag_id IN (' . implode(',', $this->tagIDs) . ')';
	}

	public function filterSets(Query $query): void
	{
		if (empty($this->setIDs))
			return;
		if (!str_contains($query->query, 'JOIN set_connection'))
			$query->query .= ' JOIN set_connection ON set_connection.tsumego_id = tsumego.id';
		if (!str_contains($query->query, 'JOIN `set`'))
			$query->query .= ' JOIN `set` ON `set`.id = set_connection.set_id';
		$query->conditions[] = '`set`.id IN (' . implode(',', $this->setIDs) . ')';
	}

	/**
	 * EXISTS condition for a tsumego belonging to at least one public set (optionally limited to filtered sets).
	 */
	private function publicMembershipCondition(): string
	{
		$setCondition = '`set`.public = 1';
		if (!empty($this->setIDs))
			$setCondition .= ' AND `set`.id IN (' . implode(',', $this->setIDs) . ')';
		return 'EXISTS (SELECT 1 FROM set_connection sc JOIN `set` ON `set`.id = sc.set_id AND ' . $setCondition . ' WHERE sc.tsumego_id = tsumego.id)';
	}

	public function calculateCount(): int
	{
		$query = new Query('FROM tsumego');
		$query->selects[] = 'COUNT(DISTINCT tsumego.id) AS total';
		$query->conditions[] = $this->publicMembershipCondition();
		$query->conditions[] = 'tsumego.deleted IS NULL';
		$this->filterTags($query);

		// Count only the problems the active mode actually displays
		if ($this->query == 'tags' && empty($this->tagIDs))
			// tags mode shows only tagged problems unless a specific tag is filtered
				$query->conditions[] = 'EXISTS (SELECT 1 FROM tag_connection tc WHERE tc.tsumego_id = tsumego.id)';

		if ($this->query == 'difficulty')
		{
			// difficulty mode shows no band above 9d, so higher ratings are not reachable
			if (empty($this->ranks))
			{
				$ranks = Rating::ranks();
				$query->conditions[] = 'tsumego.rating < ' . RatingBounds::coverRank(end($ranks), '15k')->max;
			}
			else
				$this->filterRanks($query);
		}
		else
			$this->filterRanks($query);

		return Util::query($query->str())[0]['total'];
	}

	public ?string $publishedDate = null;
	public string $query = '';
	public int $collectionSize = 0;
	public array $sets = [];
	public array $setIDs = [];
	public array $ranks = [];
	public array $tags = [];
	public array $tagIDs = [];
}

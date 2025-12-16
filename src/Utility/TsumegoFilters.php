<?php

App::uses('Preferences', 'Utility');

class TsumegoFilters
{
	public function __construct(?string $newQuery = null)
	{
		if ($newQuery == 'published')
		{
			$this->query = $newQuery;
			return;
		}

		$this->query = self::processItem('query', 'topics', null, $newQuery);
		$this->collectionSize = (int) self::processItem('collection_size', '200');
		$this->sets = self::processItem('filtered_sets', [], function ($input) { return array_values(array_filter(explode('@', $input))); });

		$this->setIDs = [];
		foreach ($this->sets as $set)
			$this->setIDs[] = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $set, 'public' => 1]])['Set']['id'];

		$this->ranks = self::processItem('filtered_ranks', [], function ($input) { return array_values(array_filter(explode('@', $input))); });
		$this->tags = self::processItem('filtered_tags', [], function ($input) { return array_values(array_filter(explode('@', $input))); });

		$this->tagIDs = [];
		foreach ($this->tags as $tag)
			$this->tagIDs[] = ClassRegistry::init('Tag')->findByName($tag)['Tag']['id'];
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
			$stringResult = $newValue;

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

		if ($this->query == 'favorites')
			return 'Favorites';
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

		if ($this->query == 'favorites')
			return 'favorites';
		return "Unsupported yet";
	}

	public function setQuery($query)
	{
		$this->query = self::processItem('query', 'topics', null, $query);
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

	public string $query;
	public int $collectionSize = 0;
	public array $sets = [];
	public array $setIDs = [];
	public array $ranks = [];
	public array $tags = [];
	public array $tagIDs = [];
}

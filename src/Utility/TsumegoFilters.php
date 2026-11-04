<?php

namespace App\Utility;

use App\Model\Entity\Set;
use Exception;
use App\Model\Table\SetTable;

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

		foreach (self::findByNames($rawSets, '`set`', 'title', SetTable::officialCondition('candidate')) as $position => $found)
		{
			$this->sets[] = $rawSets[$position];
			$this->setIDs[] = (int) $found['id'];
		}

		$rawRanks = self::processItem('filtered_ranks', [], function ($input) {
			return array_values(array_filter(explode('@', $input)));
		});
		foreach ($rawRanks as $spec)
		{
			$range = Rating::parseReadableRankRange($spec);
			if ($range !== null)
			{
				$this->rankSpecs[] = $range[0] . '-' . $range[1];
				foreach (Rating::ranksBetween($range[0], $range[1]) as $rank)
					$this->ranks[] = $rank;
			}
			elseif (Rating::isValidReadableRank($spec))
			{
				$this->rankSpecs[] = $spec;
				$this->ranks[] = $spec;
			}
		}
		$rawTags = self::processItem('filtered_tags', [], function ($input) {
			return array_values(array_filter(explode('@', $input)));
		});

		foreach (self::findByNames($rawTags, 'tag', 'name') as $position => $found)
		{
			$tag = $rawTags[$position];
			$this->tags[] = $tag;
			$this->tagIDs[] = (int) $found['id'];
			$this->tagColors[$tag] = (int) $found['color'];
		}
	}

	//The row of the table each of the names matches, by the position of the name; a name nothing matches is left
	//out. The names are compared in the database, as the collation of the column says (as one lookup per name
	//did before), and all of them in one query.
	//$table: Table to look in
	//$condition: More conditions on the row, as alias `candidate`
	/**
	 * @param string[] $names
	 * @return array<int, array<string, mixed>>
	 */
	private static function findByNames(array $names, string $table, string $nameColumn, string $condition = '1 = 1'): array
	{
		if (empty($names))
			return [];
		$parameters = [];
		foreach (array_values($names) as $position => $name)
			array_push($parameters, $position, $name);
		$found = [];
		foreach (Util::query('SELECT names.position, found.* FROM ('
			. implode(' UNION ALL ', array_fill(0, count($names), 'SELECT ? AS position, ? AS name')) . ') names'
			. " JOIN $table found ON found.id = (SELECT candidate.id FROM $table candidate WHERE candidate.$nameColumn = names.name AND $condition LIMIT 1)"
			. ' ORDER BY names.position', $parameters) as $row)
				$found[(int) $row['position']] = $row;
		return $found;
	}


	//Process a preference item with optional transformation and new value override.
	//$name: The preference key
	//$default: Default value if not set
	//$processToResult: Optional callback to transform the stored string value
	//$newValue: Optional new value to set
	//Returns the processed value
	private static function processItem(string $name, mixed $default, ?callable $processToResult = null, ?string $newValue = null): mixed
	{
		// Get current value from Preferences (handles both logged-in and guest storage)
		$stringResult = Preferences::get($name, '');

		// Check for cookie override (used for filter links like /topics?filtered_sets=SetName)
		if (!empty(Util::cookie($name)))
		{
			$stringResult = Util::cookie($name);
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
			if (!empty(Util::cookie($name)))
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

	public function getSetTitle(Set $set): string
	{
		if ($this->query == 'topics')
			return $set->title;
		if ($this->query == 'difficulty')
			return Util::cookie('lastSet') ?? 'Tsumego';
		if ($this->query == 'tags')
			return Util::cookie('lastSet') ?? 'Tsumego';

		throw new Exception('Unknown query: ""' . $this->query);
	}

	public function getSetID(?Set $set): string
	{
		if ($this->query == 'topics')
			return (string) $set->id;
		if ($this->query == 'difficulty')
			return Util::cookie('lastSet') ?? 'favorites';
		if ($this->query == 'tags')
			return Util::cookie('lastSet') ?? 'favorites';

		return "Unsupported yet";
	}

	//Whether rank or tag filters narrow which problems a set holds.
	public function hasProblemFilters(): bool
	{
		return !empty($this->ranks) || !empty($this->tagIDs);
	}

	public function filterRanks(Query $query): void
	{
		if ($rankCondition = $this->rankCondition())
			$query->conditions[] = $rankCondition;
	}

	//Rating bound conditions for the filtered ranks on the tsumego table, '' when no rank is filtered.
	public function rankCondition(): string
	{
		$rankConditions = '';
		foreach ($this->rankSpecs as $rankFilter)
		{
			$rankCondition = '';
			RatingBounds::coverRankSpec($rankFilter, Rating::weakestRank())->addSqlConditions($rankCondition);
			Util::addSqlOrCondition($rankConditions, $rankCondition);
		}
		return $rankConditions;
	}

	//The ranks the From/To selects should show: the picked range, or the one
	//picked rank when it fits both ends. A filter of several separate ranks
	//cannot be shown in the two selects, so it reports ['', ''].
	/** @return array{0: string, 1: string} */
	public function rankRange(): array
	{
		$singleRanks = [];
		foreach ($this->rankSpecs as $spec)
		{
			$range = Rating::parseReadableRankRange($spec);
			if ($range !== null)
				return $range;
			$singleRanks[] = $spec;
		}
		if (count($singleRanks) === 1)
			return [$singleRanks[0], $singleRanks[0]];
		return ['', ''];
	}

	//The rank filter labels as the user picked them: single ranks stay single,
	//a range stays one label such as "20k-16k".
	/** @return string[] */
	public function rankFilterLabels(): array
	{
		return $this->rankSpecs;
	}

	public function filterTags(Query $query): void
	{
		if (!empty($this->tagIDs))
			$query->conditions[] = $this->tagMembershipCondition('tsumego.id');
	}

	//EXISTS condition for a tsumego carrying any of the filtered tags; only call it when a tag is filtered.
	public function tagMembershipCondition(string $tsumegoRef): string
	{
		return 'EXISTS (SELECT 1 FROM tag_connection tc2 WHERE tc2.tsumego_id = ' . $tsumegoRef
			. ' AND tc2.approved = 1 AND tc2.tag_id IN (' . implode(',', $this->tagIDs) . '))';
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

	//EXISTS condition for a tsumego belonging to at least one public set (optionally limited to filtered sets).
	public function publicMembershipCondition(string $tsumegoRef = 'tsumego.id'): string
	{
		$setCondition = SetTable::officialCondition();
		if (!empty($this->setIDs))
			$setCondition .= ' AND `set`.id IN (' . implode(',', $this->setIDs) . ')';
		return 'EXISTS (SELECT 1 FROM set_connection sc JOIN `set` ON `set`.id = sc.set_id AND ' . $setCondition . ' WHERE sc.tsumego_id = ' . $tsumegoRef . ')';
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
				$query->conditions[] = 'EXISTS (SELECT 1 FROM tag_connection tc WHERE tc.tsumego_id = tsumego.id AND tc.approved = 1)';

		if ($this->query == 'difficulty')
		{
			// difficulty mode shows no band above 9d, so higher ratings are not reachable
			if (empty($this->ranks))
			{
				$ranks = Rating::ranks();
				$query->conditions[] = 'tsumego.rating < ' . RatingBounds::coverRank(end($ranks), Rating::weakestRank())->max;
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
	public array $rankSpecs = [];
	public array $tags = [];
	public array $tagIDs = [];
	public array $tagColors = [];
}

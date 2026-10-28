<?php

namespace App\Utility;

use App\Model\Entity\Tag;
use Cake\ORM\TableRegistry;
use Set;
use App\Model\Table\SetConnectionTable;

class TsumegoButtonsQueryBuilder
{
	public Query $query;
	public string $description = '';
	private TsumegoFilters $tsumegoFilters;
	private ?RatingBounds $ratingBounds = null;
	private ?Tag $tag = null;

	public function __construct(TsumegoFilters $tsumegoFilters, string|int|null $id)
	{
		$this->query = new Query('FROM tsumego');
		$this->tsumegoFilters = $tsumegoFilters;
		// The fallback to topics has to be decided first, as the shape of the query and the filters
		// applied depend on the final query type.
		$this->resolveQueryType();
		if ($tsumegoFilters->query != 'topics')
		{
			$this->query->selects[] = "ROW_NUMBER() OVER (PARTITION BY tsumego.id ORDER BY " . SetConnectionTable::displayOrderSql('`set`', 'set_connection') . ") AS rn";
			$this->query->prefix = "SELECT tsumego_id, set_connection_id, num, rating";
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

		$this->query->selects[] = 'tsumego.id as tsumego_id';
		$this->query->selects[] = 'tsumego.rating as rating';
		$this->query->selects[] = 'set_connection.id as set_connection_id';
		$this->query->selects[] = 'set_connection.num as num';
		if ($tsumegoFilters->query == 'published')
		{
			$this->query->selects[] = 'set_connection.set_id as set_id';
			$this->query->selects[] = '`set`.title as set_title';
		}
		if (Auth::isLoggedIn())
			$this->query->selects[] = 'tsumego_status.status as status';

		$this->query->query .= " JOIN set_connection ON set_connection.tsumego_id = tsumego.id";
		$this->query->conditions[] = 'tsumego.deleted is NULL';

		// when I'm quering by topics (which means sets), and I'm viewing private set
		// It means I explicitelly want to view that.
		// In all other cases, private set is not included.
		if ($tsumegoFilters->query != 'topics')
			$this->query->conditions [] = Set::officialCondition();

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

	private function filterSets(): void
	{
		if ($this->tsumegoFilters->query == 'topics') // we filter by sets unless we query a specific set
			return;
		$this->tsumegoFilters->filterSets($this->query);
	}

	private function filterTags(): void
	{
		if ($this->tsumegoFilters->query == 'tags') // we filter by tags unless we query a specific tag
			return;
		$this->tsumegoFilters->filterTags($this->query);
	}

	//Difficulty and tag queries name their collection by the lastSet cookie. When it is missing or
	//doesn't name a valid rank or an existing tag (another tab or device switched the collection,
	//or the cookie expired), we fall back to showing by topics (the set of the current tsumego).
	private function resolveQueryType(): void
	{
		$lastSet = $_COOKIE['lastSet'] ?? '';
		if ($this->tsumegoFilters->query == 'difficulty')
		{
			if (empty($lastSet))
			{
				$this->tsumegoFilters->query = 'topics';
				return;
			}
			try
			{
				$this->ratingBounds = RatingBounds::coverRankSpec($lastSet, Rating::weakestRank());
			}
			catch (RatingParseException $e)
			{
				$this->tsumegoFilters->query = 'topics';
			}
		}
		elseif ($this->tsumegoFilters->query == 'tags')
		{
			if (!empty($lastSet))
				$this->tag = TableRegistry::getTableLocator()->get('Tag')->find()->where(['name' => $lastSet])->first();
			if (!$this->tag)
				$this->tsumegoFilters->query = 'topics';
		}
	}

	private function queryRank(): void
	{
		if ($this->tsumegoFilters->query != 'difficulty')
			return;
		$this->ratingBounds->addQueryConditions($this->query);
		$this->description = $_COOKIE['lastSet'] . ' are problems that have a rating' . $this->ratingBounds->textualDescription() . '.';
	}

	private function queryTag(): void
	{
		if ($this->tsumegoFilters->query != 'tags')
			return;
		$this->query->query .= ' LEFT JOIN tag_connection ON tag_connection.tsumego_id=tsumego.id';
		$this->query->conditions[] = 'tag_connection.tag_id=' . $this->tag->id;
	}

	private function querySet(string|int|null $id): void
	{
		if ($this->tsumegoFilters->query != 'topics')
			return;
		$this->query->conditions[] = '`set`.id=' . $id;
	}

	private function queryPublished(): void
	{
		if ($this->tsumegoFilters->query != 'published')
			return;
		$this->query->query .= ' JOIN schedule ON `schedule`.tsumego_id = tsumego.id AND schedule.set_id = `set`.id';
		$date = $this->tsumegoFilters->publishedDate ?: date('Y-m-d');
		$this->query->conditions[] = "`schedule`.date = '" . $date . "'";
		$this->query->conditions[] = '`schedule`.published = 1';
	}

}

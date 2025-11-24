<?php

class TsumegoFilters
{
	public function __construct(?string $newQuery = null)
	{
		if ($newQuery == 'published')
		{
			$this->query = $newQuery;
		    return;
		}
		$userContribution = Auth::isLoggedIn() ? ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]) : null;
		$this->query = self::processItem('query', 'topics', $userContribution, null, $newQuery);
		$this->collectionSize = self::processItem('collection_size', '200', $userContribution);
		$this->sets = self::processItem('filtered_sets', [], $userContribution, function ($input) { return array_values(array_filter(explode('@', $input))); });

		$this->setIDs = [];
		foreach ($this->sets as $set)
			$this->setIDs[] = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $set, 'public' => 1]])['Set']['id'];

		$this->ranks = self::processItem('filtered_ranks', [], $userContribution, function ($input) { return array_values(array_filter(explode('@', $input))); });
		$this->tags = self::processItem('filtered_tags', [], $userContribution, function ($input) { return array_values(array_filter(explode('@', $input))); });

		$this->tagIDs = [];
		foreach ($this->tags as $tag)
			$this->tagIDs[] = ClassRegistry::init('TagName')->findByName($tag)['TagName']['id'];

		if ($userContribution)
			ClassRegistry::init('UserContribution')->save($userContribution);
	}

	private static function processItem(string $name, mixed $default, &$userContribution, $processToResult = null, ?string $newValue = null)
	{
		$stringResult = '';
		if ($userContribution)
		{
			if ($value = $userContribution['UserContribution'][$name])
				$stringResult = $value;
		}
		elseif (CakeSession::check('loggedOff_' . $name))
			$stringResult = CakeSession::read('loggedOff_' . $name);

		if (!empty($_COOKIE[$name]))
		{
			$stringResult = $_COOKIE[$name];
			if ($stringResult == 'clear')
			{
				Util::clearCookie($name);
				$stringResult = '';
			}
		}

		if ($newValue)
			$stringResult = $newValue;

		if ($userContribution)
			$userContribution['UserContribution'][$name] = $stringResult;
		else
			CakeSession::write('loggedOff_' . $name, $stringResult);

		if (!$stringResult)
			return $default;

		return $processToResult ? $processToResult($stringResult) : $stringResult;
	}

	public function getSetTitle($set): string
	{
		if ($this->query == 'topics')
			return $set['Set']['title'];
		if ($this->query == 'difficulty')
			return CakeSession::read('lastSet');
		if ($this->query == 'tags')
			return CakeSession::read('lastSet');

		if ($this->query == 'favorites')
			return 'Favorites';
		throw new Exception('Unknown query: ""' . $this->query);
	}

	public function getSetID($set): string
	{
		if ($this->query == 'topics')
			return $set['Set']['id'];
		if ($this->query == 'difficulty')
			return CakeSession::read('lastSet');
		if ($this->query == 'tags')
			return CakeSession::read('lastSet');

		if ($this->query == 'favorites')
			return 'favorites';
		return "Unsupported yet";
	}

	public function setQuery($query)
	{
		$userContribution = Auth::isLoggedIn() ? ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]) : null;
		$this->query = self::processItem('query', 'topics', $userContribution, null, $query);
		if ($userContribution)
			ClassRegistry::init('UserContribution')->save($userContribution);
	}

	public string $query;
	public int $collectionSize;
	public array $sets;
	public array $setIDs;
	public array $ranks;
	public array $tags;
	public array $tagIDs;
}

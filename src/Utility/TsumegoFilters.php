<?php

class TsumegoFilters {
	public function __construct() {
		$userContribution = Auth::isLoggedIn() ? ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]) : null;
		$this->query = self::processItem('query', 'topics', $userContribution);
		$this->collectionSize = self::processItem('collectionSize', '200', $userContribution);
		$this->sets = self::processItem('filtered_sets', [], $userContribution, function ($input) { return array_values(array_filter(explode('@', $input))); });

		$this->setIDs = [];
		foreach ($this->sets as $set) {
			$this->setIDs[] = ClassRegistry::init('Set')->find('first', ['conditions' => ['title' => $set]]);
		}

		$this->ranks = self::processItem('filtered_ranks', [], $userContribution, function ($input) { return array_values(array_filter(explode('@', $input))); });
		$this->tags = self::processItem('filtered_tags', [], $userContribution, function ($input) { return array_values(array_filter(explode('@', $input))); });

		$this->tagIDs = [];
		foreach ($this->tags as $tag) {
			$this->tagIDs[] = ClassRegistry::init('TagName')->findByName($tag)['TagName']['id'];
		}

		if ($userContribution) {
			ClassRegistry::init('UserContribution')->save($userContribution);
		}
	}

	private static function processItem(string $name, mixed $default, &$userContribution, $processToResult = null) {
		$stringResult = null;
		if ($userContribution) {
			if ($value = $userContribution['UserContribution'][$name]) {
				$stringResult = $value;
			}
		} elseif (CakeSession::check('loggedOff_' . $name)) {
			$stringResult = CakeSession::read('loggedOff_' . $name);
		}

		if (!empty($_COOKIE[$name])) {
			$stringResult = $_COOKIE[$name];
			if ($stringResult == 'clear') {
				Util::clearCookie($name);
				$stringResult = '';
			}
		}

		if ($userContribution) {
			$userContribution['UserContribution'][$name] = $stringResult;
		} else {
			CakeSession::write('loggedOff_' . $name, $stringResult);
		}

		if (!$stringResult) {
			return $default;
		}

		return $processToResult ? $processToResult($stringResult) : $stringResult;
	}

	public string $query;
	public int $collectionSize;
	public array $sets;
	public array $setIDs;
	public array $ranks;
	public array $tags;
	public array $tagIDs;
}

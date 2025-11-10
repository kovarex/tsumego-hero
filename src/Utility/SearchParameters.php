<?php

class SearchParameters {
	public static function processItem(string $name, mixed $default, $userContribution, $processToResult = null) {
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
		}

		if (!$stringResult) {
			return $default;
		}

		if ($userContribution) {
			$userContribution['UserContribution'][$name] = $stringResult;
		} else {
			CakeSession::write('loggedOff_' . $name, $stringResult);
		}
		return $processToResult ? $processToResult($stringResult) : $stringResult;
	}

	public static function process() {
		$userContribution = Auth::isLoggedIn() ? ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]) : null;

		$result = [];
		$result [] = self::processItem('query', 'topics', $userContribution);
		$result [] = self::processItem('collectionSize', '200', $userContribution);
		$result [] = self::processItem('search1', [], $userContribution, function ($input) { return array_values(array_filter(explode('@', $input))); });
		$result [] = self::processItem('search2', [], $userContribution, function ($input) { return array_values(array_filter(explode('@', $input))); });
		$result [] = self::processItem('search3', [], $userContribution, function ($input) { return array_values(array_filter(explode('@', $input))); });
		if ($userContribution) {
			ClassRegistry::init('UserContribution')->save($userContribution);
		}
		return $result;
	}
}

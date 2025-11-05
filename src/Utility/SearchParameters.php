<?php

class SearchParameters {
	static public function process() {
		$query = 'topics';
		$collectionSize = 200;
		$search1 = [];
		$search2 = [];
		$search3 = [];
		if (Auth::isLoggedIn()) {
			//db
			$uc = ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]);
			if (strlen($uc['UserContribution']['query']) > 0) {
				$query = $uc['UserContribution']['query'];
			}
			if (strlen($uc['UserContribution']['collectionSize']) > 0) {
				$collectionSize = $uc['UserContribution']['collectionSize'];
			}
			if (strlen($uc['UserContribution']['search1']) > 0) {
				$search1 = self::removeEmptyFields(explode('@', $uc['UserContribution']['search1']));
			}
			if (strlen($uc['UserContribution']['search2']) > 0) {
				$search2 = self::removeEmptyFields(explode('@', $uc['UserContribution']['search2']));
			}
			if (strlen($uc['UserContribution']['search3']) > 0) {
				$search3 = self::removeEmptyFields(explode('@', $uc['UserContribution']['search3']));
			}
			//cookies
			if (strlen($_COOKIE['query']) > 0) {
				$uc['UserContribution']['query'] = $_COOKIE['query'];
				$query = $_COOKIE['query'];
			}
			if (strlen($_COOKIE['collectionSize']) > 0) {
				$uc['UserContribution']['collectionSize'] = $_COOKIE['collectionSize'];
				$collectionSize = $_COOKIE['collectionSize'];
			}
			if (strlen($_COOKIE['search1']) > 0) {
				$uc['UserContribution']['search1'] = $_COOKIE['search1'];
				$search1 = self::removeEmptyFields(explode('@', $_COOKIE['search1']));
			}
			if (strlen($_COOKIE['search2']) > 0) {
				$uc['UserContribution']['search2'] = $_COOKIE['search2'];
				$search2 = self::removeEmptyFields(explode('@', $_COOKIE['search2']));
			}
			if (strlen($_COOKIE['search3']) > 0) {
				$uc['UserContribution']['search3'] = $_COOKIE['search3'];
				$search3 = self::removeEmptyFields(explode('@', $_COOKIE['search3']));
			}
			ClassRegistry::init('UserContribution')->save($uc);
		} else {
			//session
			if (CakeSession::check('noQuery')) {
				$query = CakeSession::read('noQuery');
			}
			if (CakeSession::check('noCollectionSize')) {
				$collectionSize = CakeSession::read('noCollectionSize');
			}
			if (CakeSession::check('noSearch1')) {
				$search1 = self::removeEmptyFields(explode('@', CakeSession::read('noSearch1')));
			}
			if (CakeSession::check('noSearch2')) {
				$search2 = self::removeEmptyFields(explode('@', CakeSession::read('noSearch2')));
			}
			if (CakeSession::check('noSearch3')) {
				$search3 = self::removeEmptyFields(explode('@', CakeSession::read('noSearch3')));
			}
			//cookies
			if (strlen($_COOKIE['query']) > 0) {
				CakeSession::write('noQuery', $_COOKIE['query']);
				$query = $_COOKIE['query'];
			}
			if (strlen($_COOKIE['collectionSize']) > 0) {
				CakeSession::write('noCollectionSize', $_COOKIE['collectionSize']);
				$collectionSize = $_COOKIE['collectionSize'];
			}
			if (strlen($_COOKIE['search1']) > 0) {
				CakeSession::write('noSearch1', $_COOKIE['search1']);
				$search1 = self::removeEmptyFields(explode('@', $_COOKIE['search1']));
			}
			if (strlen($_COOKIE['search2']) > 0) {
				CakeSession::write('noSearch2', $_COOKIE['search2']);
				$search2 = self::removeEmptyFields(explode('@', $_COOKIE['search2']));
			}
			if (strlen($_COOKIE['search3']) > 0) {
				CakeSession::write('noSearch3', $_COOKIE['search3']);
				$search3 = self::removeEmptyFields(explode('@', $_COOKIE['search3']));
			}
		}

		$r = [];
		array_push($r, $query);
		array_push($r, $collectionSize);
		array_push($r, $search1);
		array_push($r, $search2);
		array_push($r, $search3);

		return $r;
	}

	static function removeEmptyFields(array $arr): array {
		$arr2 = [];
		$arrCount = count($arr);
		for ($i = 0; $i < $arrCount; $i++) {
			if (strlen($arr[$i]) > 0) {
				array_push($arr2, $arr[$i]);
			}
		}
		return $arr2;
	}
}

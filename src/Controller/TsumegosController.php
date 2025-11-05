<?php

App::uses('TsumegoUtil', 'Utility');
App::uses('AdminActivityUtil', 'Utility');
App::uses('TsumegoButton', 'Utility');
App::uses('AppException', 'Utility');
require_once(__DIR__ . "/Component/Play.php");

class TsumegosController extends AppController {
	public $helpers = ['Html', 'Form'];

	private function deduceRelevantSetConnection(array $setConnections): array {
		if (!isset($this->params->query['sid'])) {
			return $setConnections[0];
		}
		foreach ($setConnections as $setConnection) {
			if ($setConnection['SetConnection']['set_id'] == $this->params->query['sid']) {
				return $setConnection;
			}
		}
		throw new AppException("Problem doesn't exist in the specified set");
	}

	public static function getMatchingSetConnectionOfOtherTsumego(int $tsumegoID, int $currentSetID): ?int {
		if ($setConnections = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => $tsumegoID]])) {
			if ($result = array_find($setConnections, function (array $setConnection) use (&$currentSetID): bool {
				return $setConnection['SetConnection']['set_id'] == $currentSetID;
			})) {
				return $result['SetConnection']['id'];
			}
		}
		return null;
	}

	public static function tsumegoOrSetLink(?int $setConnectionID, ?int $tsumegoID, int $setID): string {
		if ($setConnectionID) {
			return '/' . $setConnectionID;
		}
		if ($tsumegoID) {
			return '/tsumegos/play/' . $tsumegoID;
		} // temporary, until we retrieve setConnectionID for everything
		return '/sets/view/' . $setID; // edge of the set (last or first), so we return to the set
	}

	public function play($id = null, $setConnectionID = null) {
		if (Auth::isLoggedIn() && !Auth::isInLevelMode()) {
			Auth::getUser()['mode'] = Constants::$LEVEL_MODE;
			Auth::saveUser();
		}

		if ($setConnectionID) {
			return new Play(function ($name, $value) { $this->set($name, $value); })->play($setConnectionID);
		}

		if (!$id) {
			throw new AppException("Tsumego id not provided");
		}

		$setConnections = TsumegoUtil::getSetConnectionsWithTitles($id);
		if (!$setConnections) {
			throw new AppException("Problem without any set connection");
		} // some redirect/nicer message ?
		$setConnection = $this->deduceRelevantSetConnection($setConnections);
		return new Play(function ($name, $value) { $this->set($name, $value); })->play($setConnection['SetConnection']['id']);
	}

	public static function getPopularTags($tags) {
		// for some reason, this returns null in the test environment
		$json = json_decode(file_get_contents('json/popular_tags.json')) ?: [];
		$a = [];
		$tn = ClassRegistry::init('TagName')->find('all');
		if (!$tn) {
			$tn = [];
		}
		$tnKeys = [];
		$tnCount = count($tn);

		for ($i = 0; $i < $tnCount; $i++) {
			$tnKeys[$tn[$i]['TagName']['id']] = $tn[$i]['TagName']['name'];
		}
		$jsonCount = count($json);

		for ($i = 0; $i < $jsonCount; $i++) {
			array_push($a, $tnKeys[$json[$i]->id]);
		}
		$aNew = [];
		$added = 0;
		$x = 0;
		while ($added < 10 && $x < count($a)) {
			$found = false;
			$tagsCount = count($tags);

			for ($i = 0; $i < $tagsCount; $i++) {
				if ($a[$x] == $tags[$i]['Tag']['name']) {
					$found = true;
				}
			}
			if (!$found) {
				array_push($aNew, $a[$x]);
				$added++;
			}
			$x++;
		}

		return $aNew;
	}

	public static function getTags($tsumego_id) {
		$tags = ClassRegistry::init('Tag')->find('all', ['conditions' => ['tsumego_id' => $tsumego_id]]);
		if (!$tags) {
			$tags = [];
		}
		$tagsCount = count($tags);

		for ($i = 0; $i < $tagsCount; $i++) {
			$tn = ClassRegistry::init('TagName')->findById($tags[$i]['Tag']['tag_name_id']);
			$tags[$i]['Tag']['name'] = $tn['TagName']['name'];
			$tags[$i]['Tag']['hint'] = $tn['TagName']['hint'];
		}

		return $tags;
	}

	public static function checkTagDuplicates($array) {
		$newArray = [];
		$arrayCount = count($array);

		for ($i = 0; $i < $arrayCount; $i++) {
			if (!TsumegosController::inArrayX($array[$i], $newArray)) {
				array_push($newArray, $array[$i]);
			}
		}

		return $newArray;
	}

	public static function inArrayX($x, $newArray) {
		$newArrayCount = count($newArray);

		for ($i = 0; $i < $newArrayCount; $i++) {
			if ($x['Tag']['tag_name_id'] == $newArray[$i]['Tag']['tag_name_id'] && $x['Tag']['approved'] == 1) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string|int|null $id Tsumego ID
	 * @param string|int|null $sgf1 First SGF ID
	 * @param string|int|null $sgf2 Second SGF ID
	 * @return void
	 */
	public function open($id = null, $sgf1 = null, $sgf2 = null) {
		$this->loadModel('Sgf');
		$s2 = null;
		$t = $this->Tsumego->findById($id);
		$s1 = $this->Sgf->findById($sgf1);
		$s1['Sgf']['sgf'] = str_replace('ß', 'ss', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace(';', '@', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace("\r", '', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace("\n", '€', $s1['Sgf']['sgf']);
		$s1['Sgf']['sgf'] = str_replace('+', '%2B', $s1['Sgf']['sgf']);
		if ($sgf2 != null) {
			$s2 = $this->Sgf->findById($sgf2);
			$s2['Sgf']['sgf'] = str_replace('ß', 'ss', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace(';', '@', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace("\r", '', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace("\n", '€', $s2['Sgf']['sgf']);
			$s2['Sgf']['sgf'] = str_replace('+', '%2B', $s2['Sgf']['sgf']);
		}
		$this->set('t', $t);
		$this->set('s1', $s1);
		$this->set('s2', $s2);
	}

	/**
	 * @param string|int|null $id Tsumego ID
	 * @return void
	 */
	public function duplicatesearchx($id = null) {
		$this->loadModel('Sgf');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');

		$maxDifference = 1;
		$includeSandbox = 'false';
		$includeColorSwitch = 'false';
		$hideSandbox = false;

		if (isset($this->params['url']['diff'])) {
			$maxDifference = $this->params['url']['diff'];
			$includeSandbox = $this->params['url']['sandbox'];
			$includeColorSwitch = $this->params['url']['colorSwitch'];
			$loop = false;
		} else {
			$loop = true;
		}
		$similarId = [];
		$similarArr = [];
		$similarArrInfo = [];
		$similarArrBoardSize = [];
		$similarTitle = [];
		$similarDiff = [];
		$similarDiffType = [];
		$similarOrder = [];
		$t = $this->Tsumego->findById($id);
		$sc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
		if (!$sc) {
			throw new NotFoundException('Set connection not found');
		}
		$s = $this->Set->findById($sc['SetConnection']['set_id']);
		$title = $s['Set']['title'] . ' - ' . $t['Tsumego']['num'];
		$sgf = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $id]]);
		if (!$sgf) {
			throw new NotFoundException('SGF not found');
		}
		$tSgfArr = $this->processSGF($sgf['Sgf']['sgf']);
		$tNumStones = isset($tSgfArr[1]) ? count($tSgfArr[1]) : 0;

		$sets2 = [];
		$sets3 = [];
		$sets3content = [];
		$sets1 = $this->Set->find('all', [
			'conditions' => [
				'public' => '1',
				'NOT' => [
					'id' => [6473, 11969, 29156, 31813, 33007, 71790, 74761, 81578],
				],
			],
		]);

		if (Auth::isLoggedIn()) {
			if (!Auth::hasPremium()) {
				$includeSandbox = 'false';
				$hideSandbox = true;
			} else {
				array_push($sets3content, 6473);
				array_push($sets3content, 11969);
				array_push($sets3content, 29156);
				array_push($sets3content, 31813);
				array_push($sets3content, 33007);
				array_push($sets3content, 71790);
				array_push($sets3content, 74761);
				array_push($sets3content, 81578);
			}
			$sets3 = $this->Set->find('all', ['conditions' => ['id' => $sets3content]]);
		} else {
			$includeSandbox = 'false';
			$hideSandbox = true;
		}
		if ($includeSandbox == 'true') {
			$sets2 = $this->Set->find('all', ['conditions' => ['public' => '0']]);
		}
		$sets = array_merge($sets1, $sets2, $sets3);

		$setsCount = count($sets);

		for ($h = 0; $h < $setsCount; $h++) {
			$ts = TsumegoUtil::collectTsumegosFromSet($sets[$h]['Set']['id']);
			$tsCount = count($ts);

			for ($i = 0; $i < $tsCount; $i++) {
				if ($ts[$i]['Tsumego']['id'] != $id) {
					$sgf = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
					$sgfArr = $this->processSGF($sgf['Sgf']['sgf']);
					$numStones = count($sgfArr[1]);
					$stoneNumberDiff = abs($numStones - $tNumStones);
					if ($stoneNumberDiff <= $maxDifference) {
						if ($includeColorSwitch == 'true') {
							$compare = $this->compare($tSgfArr[0], $sgfArr[0], true);
						} else {
							$compare = $this->compare($tSgfArr[0], $sgfArr[0], false);
						}
						if ($compare[0] <= $maxDifference) {
							array_push($similarId, $ts[$i]['Tsumego']['id']);
							array_push($similarArr, $sgfArr[0]);
							array_push($similarArrInfo, $sgfArr[2]);
							array_push($similarArrBoardSize, $sgfArr[3]);
							array_push($similarDiff, $compare[0]);
							if ($compare[1] == 0) {
								array_push($similarDiffType, '');
							} elseif ($compare[1] == 1) {
								array_push($similarDiffType, 'Shifted position.');
							} elseif ($compare[1] == 2) {
								array_push($similarDiffType, 'Shifted and rotated.');
							} elseif ($compare[1] == 3) {
								array_push($similarDiffType, 'Switched colors.');
							} elseif ($compare[1] == 4) {
								array_push($similarDiffType, 'Switched colors and shifted position.');
							} elseif ($compare[1] == 5) {
								array_push($similarDiffType, 'Switched colors, shifted and rotated.');
							}
							array_push($similarOrder, $compare[2]);
							$set = $this->Set->findById($ts[$i]['Tsumego']['set_id']);
							$title2 = $set['Set']['title'] . ' - ' . $ts[$i]['Tsumego']['num'];
							array_push($similarTitle, $title2);
						}
					}
				}
			}
		}

		array_multisort($similarOrder, $similarArr, $similarArrInfo, $similarTitle, $similarDiff, $similarDiffType, $similarId);

		$this->set('tSgfArr', $tSgfArr[0]);
		$this->set('tSgfArrInfo', $tSgfArr[2]);
		$this->set('tSgfArrBoardSize', $tSgfArr[3]);
		$this->set('similarId', $similarId);
		$this->set('similarArr', $similarArr);
		$this->set('similarArrInfo', $similarArrInfo);
		$this->set('similarArrBoardSize', $similarArrBoardSize);
		$this->set('similarTitle', $similarTitle);
		$this->set('similarDiff', $similarDiff);
		$this->set('similarDiffType', $similarDiffType);
		$this->set('title', $title);
		$this->set('t', $t);
		$this->set('maxDifference', $maxDifference);
		$this->set('includeSandbox', $includeSandbox);
		$this->set('includeColorSwitch', $includeColorSwitch);
		$this->set('hideSandbox', $hideSandbox);
	}

	/**
	 * @param string|int|null $id Tsumego ID
	 * @return void
	 */
	public function duplicatesearch($id = null) {
		$this->loadModel('Sgf');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		$this->loadModel('Signature');
		$this->Session->write('page', 'play');

		$similarId = [];
		$similarArr = [];
		$similarArrInfo = [];
		$similarArrBoardSize = [];
		$similarTitle = [];
		$similarDiff = [];
		$similarDiffType = [];
		$similarOrder = [];

		$t = $this->Tsumego->findById($id);
		$sc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
		if (!$sc) {
			throw new NotFoundException('Set connection not found');
		}
		$s = $this->Set->findById($sc['SetConnection']['set_id']);
		$title = $s['Set']['title'] . ' - ' . $t['Tsumego']['num'];
		$sgf = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $id]]);
		if (!$sgf) {
			throw new NotFoundException('SGF not found');
		}
		$tSgfArr = $this->processSGF($sgf['Sgf']['sgf']);
		$tNumStones = isset($tSgfArr[1]) ? count($tSgfArr[1]) : 0;

		$this->Session->write('title', $s['Set']['title'] . ' ' . $t['Tsumego']['num'] . ' on Tsumego Hero');

		$t = $this->Tsumego->findById($id);
		$sig = $this->Signature->find('all', ['conditions' => ['tsumego_id' => $id]]);
		if (!$sig) {
			$sig = [];
		}
		$ts = [];
		$sigCount = count($sig);

		for ($i = 0; $i < $sigCount; $i++) {
			$sig2 = $this->Signature->find('all', ['conditions' => ['signature' => $sig[$i]['Signature']['signature']]]);
			if (!$sig2) {
				$sig2 = [];
			}
			$sig2Count = count($sig2);

			for ($j = 0; $j < $sig2Count; $j++) {
				array_push($ts, $this->Tsumego->findById($sig2[$j]['Signature']['tsumego_id']));
			}
		}

		$tsCount = count($ts);

		for ($i = 0; $i < $tsCount; $i++) {
			if ($ts[$i]['Tsumego']['id'] != $id) {
				$sgf = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
				if (!$sgf) {
					continue;
				}
				$sgfArr = $this->processSGF($sgf['Sgf']['sgf']);
				$numStones = isset($sgfArr[1]) ? count($sgfArr[1]) : 0;
				$stoneNumberDiff = abs($numStones - $tNumStones);
				$compare = $this->compare($tSgfArr[0], $sgfArr[0], false);
				array_push($similarId, $ts[$i]['Tsumego']['id']);
				array_push($similarArr, $sgfArr[0]);
				array_push($similarArrInfo, $sgfArr[2]);
				array_push($similarArrBoardSize, $sgfArr[3]);
				array_push($similarDiff, $compare[0]);
				if ($compare[1] == 0) {
					array_push($similarDiffType, '');
				} elseif ($compare[1] == 1) {
					array_push($similarDiffType, 'Shifted position.');
				} elseif ($compare[1] == 2) {
					array_push($similarDiffType, 'Shifted and rotated.');
				} elseif ($compare[1] == 3) {
					array_push($similarDiffType, 'Switched colors.');
				} elseif ($compare[1] == 4) {
					array_push($similarDiffType, 'Switched colors and shifted position.');
				} elseif ($compare[1] == 5) {
					array_push($similarDiffType, 'Switched colors, shifted and rotated.');
				}
				array_push($similarOrder, $compare[2]);
				$scx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
				$set = $this->Set->findById($scx['SetConnection']['set_id']);
				$title2 = $set['Set']['title'] . ' - ' . $ts[$i]['Tsumego']['num'];
				array_push($similarTitle, $title2);
			}
		}

		if (count($similarOrder) > 30) {
			$orderCounter = 0;
			$orderThreshold = 5;
			while ($orderCounter < 30) {
				$orderThreshold++;
				$orderCounter = 0;
				$similarOrderCount = count($similarOrder);

				for ($i = 0; $i < $similarOrderCount; $i++) {
					if (substr($similarOrder[$i], 0, 2) < $orderThreshold) {
						$orderCounter++;
					}
				}
			}
			$similarOrder2 = [];
			$similarArr2 = [];
			$similarArrInfo2 = [];
			$similarTitle2 = [];
			$similarDiff2 = [];
			$similarDiffType2 = [];
			$similarId2 = [];
			$similarArrBoardSize2 = [];

			$similarOrderCount = count($similarOrder);

			for ($i = 0; $i < $similarOrderCount; $i++) {
				if (substr($similarOrder[$i], 0, 2) < $orderThreshold) {
					array_push($similarOrder2, $similarOrder[$i]);
					array_push($similarArr2, $similarArr[$i]);
					array_push($similarArrInfo2, $similarArrInfo[$i]);
					array_push($similarTitle2, $similarTitle[$i]);
					array_push($similarDiff2, $similarDiff[$i]);
					array_push($similarDiffType2, $similarDiffType[$i]);
					array_push($similarId2, $similarId[$i]);
					array_push($similarArrBoardSize2, $similarArrBoardSize[$i]);
				}
			}
			$similarOrder = $similarOrder2;
			$similarArr = $similarArr2;
			$similarArrInfo = $similarArrInfo2;
			$similarTitle = $similarTitle2;
			$similarDiff = $similarDiff2;
			$similarDiffType = $similarDiffType2;
			$similarId = $similarId2;
			$similarArrBoardSize = $similarArrBoardSize2;
		}
		array_multisort($similarOrder, $similarArr, $similarArrInfo, $similarTitle, $similarDiff, $similarDiffType, $similarId, $similarArrBoardSize);

		$this->set('tSgfArr', $tSgfArr[0]);
		$this->set('tSgfArrInfo', $tSgfArr[2]);
		$this->set('tSgfArrBoardSize', $tSgfArr[3]);
		$this->set('similarId', $similarId);
		$this->set('similarArr', $similarArr);
		$this->set('similarArrInfo', $similarArrInfo);
		$this->set('similarArrBoardSize', $similarArrBoardSize);
		$this->set('similarTitle', $similarTitle);
		$this->set('similarDiff', $similarDiff);
		$this->set('similarDiffType', $similarDiffType);
		$this->set('title', $title);
		$this->set('t', $t);
	}

	public static function getTheIdForTheThing($num) {
		$t = [];
		$s = ClassRegistry::init('Set')->find('all', ['order' => 'id ASC', 'conditions' => ['public' => 1]]) ?: [];
		$sCount = count($s);

		for ($i = 0; $i < $sCount; $i++) {
			$sc = ClassRegistry::init('SetConnection')->find('all', ['order' => 'tsumego_id ASC', 'conditions' => ['set_id' => $s[$i]['Set']['id']]]) ?: [];
			$scCount = count($sc);

			for ($j = 0; $j < $scCount; $j++) {
				array_push($t, $sc[$j]['SetConnection']['tsumego_id']);
			}
		}
		if ($num >= count($t)) {
			return -1;
		}

		return $t[$num];
	}

	public static function getStartingPlayer($sgf) {
		$bStart = strpos($sgf, ';B');
		$wStart = strpos($sgf, ';W');
		if ($wStart == 0) {
			return 0;
		}
		if ($bStart == 0) {
			return 1;
		}
		if ($bStart <= $wStart) {
			return 0;
		}

		return 1;
	}

	private function getLowest($a) {
		$lowestX = 19;
		$lowestY = 19;
		$aCount = count($a);

		for ($y = 0; $y < $aCount; $y++) {
			$aYCount = count($a[$y]);
			for ($x = 0; $x < $aYCount; $x++) {
				if ($a[$x][$y] == 'x' || $a[$x][$y] == 'o') {
					if ($x < $lowestX) {
						$lowestX = $x;
					}
					if ($y < $lowestY) {
						$lowestY = $y;
					}
				}
			}
		}
		$arr = [];
		array_push($arr, $lowestX);
		array_push($arr, $lowestY);

		return $arr;
	}
	private function shiftToCorner($a, $lowestX, $lowestY) {
		if ($lowestX != 0) {
			$aCount = count($a);

			for ($y = 0; $y < $aCount; $y++) {
				$aYCount = count($a[$y]);

				for ($x = 0; $x < $aYCount; $x++) {
					if ($a[$x][$y] == 'x' || $a[$x][$y] == 'o') {
						$c = $a[$x][$y];
						$a[$x - $lowestX][$y] = $c;
						$a[$x][$y] = '-';
					}
				}
			}
		}
		if ($lowestY != 0) {
			$aCount = count($a);

			for ($y = 0; $y < $aCount; $y++) {
				$aYCount = count($a[$y]);

				for ($x = 0; $x < $aYCount; $x++) {
					if ($a[$x][$y] == 'x' || $a[$x][$y] == 'o') {
						$c = $a[$x][$y];
						$a[$x][$y - $lowestY] = $c;
						$a[$x][$y] = '-';
					}
				}
			}
		}

		return $a;
	}

	/**
	 * @param array $b Board array
	 * @param bool $trigger Trigger flag
	 * @return void
	 */
	private function displayArray($b, $trigger = false) {
		$bCount = 0;
		if ($trigger) {
			$bCount = count($b);
		}

		for ($y = 0; $y < $bCount; $y++) {
			$bYCount = count($b[$y]);

			for ($x = 0; $x < $bYCount; $x++) {
				echo '&nbsp;&nbsp;' . $b[$x][$y] . ' ';
			}
			if ($y != 18) {
				echo '<br>';
			}
		}
	}
	private function compare($a, $b, $switch = false) {
		$compare = [];
		$this->displayArray($a);
		$diff1 = $this->compareSingle($a, $b);
		array_push($compare, $diff1);
		$this->displayArray($b);
		if ($switch) {
			$d = $this->colorSwitch($b);
		}
		$arr = $this->getLowest($a);
		$a = $this->shiftToCorner($a, $arr[0], $arr[1]);
		$arr = $this->getLowest($b);
		$b = $this->shiftToCorner($b, $arr[0], $arr[1]);
		if ($switch) {
			$c = $this->colorSwitch($b);
		}
		$diff2 = $this->compareSingle($a, $b);
		array_push($compare, $diff2);
		$this->displayArray($b);

		$b = $this->mirror($b);
		$diff3 = $this->compareSingle($a, $b);
		array_push($compare, $diff3);
		$this->displayArray($b);

		if ($switch) {
			$diff4 = $this->compareSingle($a, $d);
			array_push($compare, $diff4);
			$this->displayArray($d);

			$this->displayArray($c);
			$diff5 = $this->compareSingle($a, $c);
			array_push($compare, $diff5);

			$c = $this->mirror($c);
			$diff6 = $this->compareSingle($a, $c);
			array_push($compare, $diff6);
			$this->displayArray($c);
		}
		$lowestCompare = 6;
		$lowestCompareNum = 100;
		$compareCount = count($compare);

		for ($i = 0; $i < $compareCount; $i++) {
			if ($compare[$i] < $lowestCompareNum) {
				$lowestCompareNum = $compare[$i];
				$lowestCompare = $i;
			}
		}
		if ($lowestCompareNum < 10) {
			$lowestCompareNum = '0' . $lowestCompareNum;
		} elseif ($lowestCompareNum > 99) {
			$lowestCompareNum = 99;
		}
		$order = $lowestCompareNum . '-' . $lowestCompare;

		return [$lowestCompareNum, $lowestCompare, $order];
	}

	private function colorSwitch($b) {
		$bCount = count($b);

		for ($y = 0; $y < $bCount; $y++) {
			$bYCount = count($b[$y]);

			for ($x = 0; $x < $bYCount; $x++) {
				if ($b[$x][$y] == 'x') {
					$b[$x][$y] = 'o';
				} elseif ($b[$x][$y] == 'o') {
					$b[$x][$y] = 'x';
				}
			}
		}

		return $b;
	}

	private function compareSingle($a, $b) {
		$diff = 0;
		$bCount = count($b);

		for ($y = 0; $y < $bCount; $y++) {
			$bYCount = count($b[$y]);

			for ($x = 0; $x < $bYCount; $x++) {
				if ($a[$x][$y] != $b[$x][$y]) {
					$diff++;
				}
			}
		}

		return $diff;
	}

	private function mirror($a) {
		$a1 = [];
		$black = [];
		$white = [];
		$aCount = count($a);

		for ($y = 0; $y < $aCount; $y++) {
			$a1[$y] = [];
			$aYCount = count($a[$y]);

			for ($x = 0; $x < $aYCount; $x++) {
				$a1[$y][$x] = $a[$x][$y];
			}
		}

		return $a1;
	}

	public static function findUt($id = null, $utsMap = null) {
		if (!isset($utsMap[$id])) {
			return null;
		}
		$ut = [];
		$ut['TsumegoStatus']['tsumego_id'] = $id;
		$ut['TsumegoStatus']['user_id'] = Auth::getUserID();
		$ut['TsumegoStatus']['status'] = $utsMap[$id];
		$ut['TsumegoStatus']['created'] = date('Y-m-d H:i:s');

		return $ut;
	}

	public static function commentCoordinates($c = null, $counter = null, $noSyntax = null) {
		if (!is_string($c)) {
			return [$c, ''];
		}
		$m = str_split($c);
		$n = '';
		$n2 = '';
		$hasLink = false;
		$mCount = count($m);

		for ($i = 0; $i < $mCount; $i++) {
			if (isset($m[$i + 1])) {
				if (preg_match('/[a-tA-T]/', $m[$i]) && is_numeric($m[$i + 1])) {
					if (!preg_match('/[a-tA-T]/', $m[$i - 1]) && !is_numeric($m[$i - 1])) {
						if (is_numeric($m[$i + 2])) {
							if (!preg_match('/[a-tA-T]/', $m[$i + 3]) && !is_numeric($m[$i + 3])) {
								$n .= $m[$i] . $m[$i + 1] . $m[$i + 2] . ' ';
								$n2 .= $i . '/' . ($i + 2) . ' ';
							}
						} else {
							if (!preg_match('/[a-tA-T]/', $m[$i + 2])) {
								$n .= $m[$i] . $m[$i + 1] . ' ';
								$n2 .= $i . '/' . ($i + 1) . ' ';
							}
						}
					}
				}
			}

			if ($m[$i] == '<' && $m[$i + 1] == '/' && $m[$i + 2] == 'a' && $m[$i + 3] == '>') {
				$hasLink = true;
			}
		}
		if (substr($n, -1) == ' ') {
			$n = substr($n, 0, -1);
		}
		if (substr($n2, -1) == ' ') {
			$n2 = substr($n2, 0, -1);
		}

		if ($hasLink) {
			$n2 = [];
		}
		$coordForBesogo = [];

		if (is_string($n2) && strlen($n2) > 1) {
			$n2x = explode(' ', $n2);
			$fn = 1;
			$n2xCount = count($n2x);
			for ($i = $n2xCount - 1; $i >= 0; $i--) {
				$n2xx = explode('/', $n2x[$i]);
				$a = substr($c, 0, (int) $n2xx[0]);
				$cx = substr($c, (int) $n2xx[0], (int) $n2xx[1] - (int) $n2xx[0] + 1);
				if ($noSyntax) {
					$b = '<a href="#" title="original: ' . $cx . '" id="ccIn' . $counter . $fn . '" onmouseover="ccIn' . $counter . $fn . '()" onmouseout="ccOut' . $counter . $fn . '()" return false;>';
				} else {
					$b = '<a href=\"#\" title="original: ' . $cx . '" id="ccIn' . $counter . $fn . '" onmouseover=\"ccIn' . $counter . $fn . '()\" onmouseout=\"ccOut' . $counter . $fn . '()\" return false;>';
				}

				$d = '</a>';
				$e = substr($c, $n2xx[1] + 1, strlen($c) - 1);
				$coordForBesogo[$i] = $cx;
				$c = $a . $b . $cx . $d . $e;
				$fn++;
			}
		}

		$xx = explode(' ', $n);
		$coord1 = 0;
		$finalCoord = '';
		$xxCount = count($xx);

		for ($i = 0; $i < $xxCount; $i++) {
			if (strlen($xx[$i]) > 1) {
				$xxxx = [];
				$xxx = str_split($xx[$i]);
				$xxxCount = count($xxx);

				for ($j = 0; $j < $xxxCount; $j++) {
					if (preg_match('/[0-9]/', $xxx[$j])) {
						array_push($xxxx, $xxx[$j]);
					}
					if (preg_match('/[a-tA-T]/', $xxx[$j])) {
						$coord1 = TsumegosController::convertCoord($xxx[$j]);
					}
				}
				$coord2 = TsumegosController::convertCoord2(implode('', $xxxx));
				if ($coord1 != -1 && $coord2 != -1) {
					$finalCoord .= $coord1 . '-' . $coord2 . '-' . $coordForBesogo[$i] . ' ';
				}
			}
		}
		if (substr($finalCoord, -1) == ' ') {
			$finalCoord = substr($finalCoord, 0, -1);
		}

		$array = [];
		array_push($array, $c);
		array_push($array, $finalCoord);

		return $array;
	}

	public static function convertCoord($l = null) {
		switch (strtolower($l)) {
			case 'a':
				return 0;
			case 'b':
				return 1;
			case 'c':
				return 2;
			case 'd':
				return 3;
			case 'e':
				return 4;
			case 'f':
				return 5;
			case 'g':
				return 6;
			case 'h':
				return 7;
			case 'j':
				return 8;
			case 'k':
				return 9;
			case 'l':
				return 10;
			case 'm':
				return 11;
			case 'n':
				return 12;
			case 'o':
				return 13;
			case 'p':
				return 14;
			case 'q':
				return 15;
			case 'r':
				return 16;
			case 's':
				return 17;
			case 't':
				return 18;
		}

		return 0;
	}
	public static function convertCoord2($n = null) {
		switch ($n) {
			case '0':
				return 19;
			case '1':
				return 18;
			case '2':
				return 17;
			case '3':
				return 16;
			case '4':
				return 15;
			case '5':
				return 14;
			case '6':
				return 13;
			case '7':
				return 12;
			case '8':
				return 11;
			case '9':
				return 10;
			case '10':
				return 9;
			case '11':
				return 8;
			case '12':
				return 7;
			case '13':
				return 6;
			case '14':
				return 5;
			case '15':
				return 4;
			case '16':
				return 3;
			case '17':
				return 2;
			case '18':
				return 1;
		}

		return 0;
	}

}

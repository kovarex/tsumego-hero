<?php

App::uses('Auth', 'Utility');
App::uses('TsumegoFilters', 'Utility');
App::uses('TimeMode', 'Utility');

class AppController extends Controller
{
	public $viewClass = 'App';

	public $components = [
		'Session',
		//'DebugKit.Toolbar',
		'Flash',
		'PlayResultProcessor'
	];

	public static function processSGF($sgf)
	{
		$aw = strpos($sgf, 'AW');
		$ab = strpos($sgf, 'AB');
		$boardSizePos = strpos($sgf, 'SZ');
		$boardSize = 19;
		$sgfArr = str_split($sgf);
		if ($boardSizePos !== false)
			$boardSize = $sgfArr[$boardSizePos + 3] . '' . $sgfArr[$boardSizePos + 4];
		if (substr($boardSize, 1) == ']')
			$boardSize = substr($boardSize, 0, 1);

		$black = AppController::getInitialPosition($ab, $sgfArr, 'x');
		$white = AppController::getInitialPosition($aw, $sgfArr, 'o');
		$stones = array_merge($black, $white);

		$board = [];
		for ($i = 0; $i < 19; $i++)
		{
			$board[$i] = [];
			for ($j = 0; $j < 19; $j++)
				$board[$i][$j] = '-';
		}
		$lowestX = 18;
		$lowestY = 18;
		$highestX = 0;
		$highestY = 0;
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++)
		{
			if ($stones[$i][0] < $lowestX)
				$lowestX = $stones[$i][0];
			if ($stones[$i][0] > $highestX)
				$highestX = $stones[$i][0];
			if ($stones[$i][1] < $lowestY)
				$lowestY = $stones[$i][1];
			if ($stones[$i][1] > $highestY)
				$highestY = $stones[$i][1];
		}
		if (18 - $lowestX < $lowestX)
			$stones = AppController::xFlip($stones);
		if (18 - $lowestY < $lowestY)
			$stones = AppController::yFlip($stones);
		$highestX = 0;
		$highestY = 0;
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++)
		{
			if ($stones[$i][0] > $highestX)
				$highestX = $stones[$i][0];
			if ($stones[$i][1] > $highestY)
				$highestY = $stones[$i][1];
			$board[$stones[$i][0]][$stones[$i][1]] = $stones[$i][2];
		}
		$tInfo = [];
		$tInfo[0] = $highestX;
		$tInfo[1] = $highestY;
		$arr = [];
		$arr[0] = $board;
		$arr[1] = $stones;
		$arr[2] = $tInfo;
		$arr[3] = $boardSize;

		return $arr;
	}

	public static function xFlip($stones)
	{
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++)
			$stones[$i][0] = 18 - $stones[$i][0];

		return $stones;
	}

	public static function yFlip($stones)
	{
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++)
			$stones[$i][1] = 18 - $stones[$i][1];

		return $stones;
	}

	public static function getInitialPositionEnd($pos, $sgfArr)
	{
		$endCondition = 0;
		$currentPos1 = $pos + 2;
		$currentPos2 = $pos + 5;
		while ($sgfArr[$currentPos1] == '[' && $sgfArr[$currentPos2] == ']')
		{
			$endCondition = $currentPos2;
			$currentPos1 += 4;
			$currentPos2 += 4;
		}

		return $endCondition;
	}

	public static function getInitialPosition($pos, $sgfArr, $color)
	{
		$arr = [];
		$end = AppController::getInitialPositionEnd($pos, $sgfArr);
		for ($i = $pos + 2; $i < $end; $i++)
			if ($sgfArr[$i] != '[' && $sgfArr[$i] != ']')
				array_push($arr, strtolower($sgfArr[$i]));
		$alphabet = array_flip(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z']);
		$xy = true;
		$arr2 = [];
		$c = 0;
		$arrCount = count($arr);
		for ($i = 0; $i < $arrCount; $i++)
		{
			$arr[$i] = $alphabet[$arr[$i]];
			if ($xy)
			{
				$arr2[$c] = [];
				$arr2[$c][0] = $arr[$i];
			}
			else
			{
				$arr2[$c][1] = $arr[$i];
				$arr2[$c][2] = $color;
				$c++;
			}
			$xy = !$xy;
		}

		return $arr2;
	}

	protected function getDeletedSets()
	{
		$dSets = [];
		$de = $this->Set->find('all', ['conditions' => ['public' => -1]]);
		if (!$de)
			$de = [];
		foreach ($de as $item)
			$dSets[] = $item['Set']['id'];

		return $dSets;
	}

	public static function getStartpage(): string
	{
		$result = '';
		$latest = ClassRegistry::init('AchievementStatus')->find('all', ['limit' => 7, 'order' => 'created DESC']) ?: [];
		$latestCount = count($latest);
		for ($i = 0; $i < $latestCount; $i++)
		{
			$a = ClassRegistry::init('Achievement')->findById($latest[$i]['AchievementStatus']['achievement_id']);
			$u = ClassRegistry::init('User')->findById($latest[$i]['AchievementStatus']['user_id']);
			if (substr($u['User']['name'], 0, 3) == 'g__' && $u['User']['external_id'] != null)
				$startPageUser = AppController::checkPicture($u);
			else
				$startPageUser = $u['User']['name'];
			$latest[$i]['AchievementStatus']['name'] = $a['Achievement']['name'];
			$latest[$i]['AchievementStatus']['color'] = $a['Achievement']['color'];
			$latest[$i]['AchievementStatus']['image'] = $a['Achievement']['image'];
			$latest[$i]['AchievementStatus']['user'] = $startPageUser;
			$result .= '<div class="quote1"><div class="quote1a"><a href="/achievements/view/' . $a['Achievement']['id'] . '"><img src="/img/' . $a['Achievement']['image'] . '.png" width="34px"></a></div>';
			$result .= '<div class="quote1b">Achievement gained by ' . $startPageUser . ':<br><div class=""><b>' . $a['Achievement']['name'] . '</b></div></div></div>';
		}
		return $result;
	}

	protected function saveSolvedNumber($uid)
	{
		$this->loadModel('User');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');

		$solvedUts2 = 0;
		$tsumegos = $this->SetConnection->find('all');
		if (!$tsumegos)
			$tsumegos = [];
		$uts = $this->TsumegoStatus->find('all', ['order' => 'updated DESC', 'conditions' => ['user_id' => $uid]]);
		if (!$uts)
			$uts = [];
		$setKeys = [];
		$setArray = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		if (!$setArray)
			$setArray = [];

		$setArrayCount = count($setArray);
		for ($i = 0; $i < $setArrayCount; $i++)
			$setKeys[$setArray[$i]['Set']['id']] = $setArray[$i]['Set']['id'];

		$scs = [];
		$tsumegosCount = count($tsumegos);
		for ($j = 0; $j < $tsumegosCount; $j++)
			if (!isset($scs[$tsumegos[$j]['SetConnection']['tsumego_id']]))
				$scs[$tsumegos[$j]['SetConnection']['tsumego_id']] = 1;
			else
				$scs[$tsumegos[$j]['SetConnection']['tsumego_id']]++;
		$utsCount = count($uts);
		for ($j = 0; $j < $utsCount; $j++)
			if ($uts[$j]['TsumegoStatus']['status'] == 'S' || $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C')
				if (isset($scs[$uts[$j]['TsumegoStatus']['tsumego_id']]))
					$solvedUts2 += $scs[$uts[$j]['TsumegoStatus']['tsumego_id']];
		Auth::getUser()['solved'] = $solvedUts2;
		Auth::saveUser();

		return $solvedUts2;
	}

	/**
	 * @return void
	 */
	protected function resetUserElos()
	{
		$this->loadModel('User');

		$u = $this->User->find('all', [
			'conditions' => [
				'id >=' => 15000,
				'id <=' => 19000,
			],
		]);
		if (!$u)
			$u = [];

		$uCount = count($u);
		for ($i = 0; $i < $uCount; $i++)
		{
			$u[$i]['User']['rating'] = 900;
			$u[$i]['User']['solved2'] = 0;
			$this->User->save($u[$i]);
		}
	}

	/**
	 * @param int $uid User ID
	 * @param string $action Action type
	 *
	 * @return void
	 */
	public static function handleContribution($uid, $action)
	{
		$uc = ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => $uid]]);
		if ($uc == null)
		{
			$uc = [];
			$uc['UserContribution']['user_id'] = $uid;
			$uc['UserContribution']['added_tag'] = 0;
			$uc['UserContribution']['created_tag'] = 0;
			$uc['UserContribution']['made_proposal'] = 0;
			$uc['UserContribution']['reviewed'] = 0;
			$uc['UserContribution']['score'] = 0;
			ClassRegistry::init('UserContribution')->create();
		}
		$uc['UserContribution'][$action] += 1;
		$uc['UserContribution']['score']
		= $uc['UserContribution']['added_tag']
		+ $uc['UserContribution']['created_tag'] * 3
		+ $uc['UserContribution']['made_proposal'] * 5
		+ $uc['UserContribution']['reviewed'] * 2;
		ClassRegistry::init('UserContribution')->save($uc);
	}

	public static function getAllTags($not)
	{
		$a = [];
		$notApproved = ClassRegistry::init('TagName')->find('all', ['conditions' => ['approved' => 0]]);
		if (!$notApproved)
			$notApproved = [];
		$notCount = count($not);
		for ($i = 0; $i < $notCount; $i++)
			array_push($a, $not[$i]['Tag']['tag_name_id']);
		$notApprovedCount = count($notApproved);
		for ($i = 0; $i < $notApprovedCount; $i++)
			array_push($a, $notApproved[$i]['TagName']['id']);
		$tn = ClassRegistry::init('TagName')->find('all', [
			'conditions' => [
				'NOT' => ['id' => $a],
			],
		]);
		if (!$tn)
			$tn = [];
		$sorted = [];
		$keys = [];
		$tnCount = count($tn);
		for ($i = 0; $i < $tnCount; $i++)
		{
			array_push($sorted, $tn[$i]['TagName']['name']);
			$keys[$tn[$i]['TagName']['name']] = $tn[$i];
		}
		sort($sorted);
		$s2 = [];
		$sortedCount = count($sorted);
		for ($i = 0; $i < $sortedCount; $i++)
			array_push($s2, $keys[$sorted[$i]]);

		return $s2;
	}

	public static function encrypt($str = null)
	{
		$secret_key = 'my_simple_secret_keyx';
		$secret_iv = 'my_simple_secret_ivx';
		$encrypt_method = 'AES-256-CBC';
		$key = hash('sha256', $secret_key);
		$iv = substr(hash('sha256', $secret_iv), 0, 16);

		return base64_encode(openssl_encrypt($str, $encrypt_method, $key, 0, $iv));
	}

	protected function checkPictureLarge($u)
	{
		if (substr($u['User']['name'], 0, 3) == 'g__' && $u['User']['external_id'] != null)
			return '<img class="google-profile-image-large" src="/img/google/' . $u['User']['picture'] . '">' . substr($u['User']['name'], 3);

		return $u['User']['name'];
	}
	public static function checkPicture($user)
	{
		if (substr($user['name'], 0, 3) == 'g__' && $user['external_id'] != null)
			return '<img class="google-profile-image" src="/img/google/' . $user['picture'] . '">' . substr($user['name'], 3);

		return $user['name'];
	}

	public static function saveDanSolveCondition($solvedTsumegoRank, $tId): void
	{
		if ($solvedTsumegoRank == '1d' || $solvedTsumegoRank == '2d' || $solvedTsumegoRank == '3d' || $solvedTsumegoRank == '4d' || $solvedTsumegoRank == '5d')
		{
			$danSolveCategory = 'danSolve' . $solvedTsumegoRank;
			$danSolveCondition = ClassRegistry::init('AchievementCondition')->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => $danSolveCategory,
				],
			]);
			if (!$danSolveCondition)
			{
				$danSolveCondition = [];
				$danSolveCondition['AchievementCondition']['value'] = 0;
				ClassRegistry::init('AchievementCondition')->create();
			}
			$danSolveCondition['AchievementCondition']['category'] = $danSolveCategory;
			$danSolveCondition['AchievementCondition']['user_id'] = Auth::getUserID();
			$danSolveCondition['AchievementCondition']['set_id'] = $tId;
			$danSolveCondition['AchievementCondition']['value']++;

			ClassRegistry::init('AchievementCondition')->save($danSolveCondition);
		}
	}

	public static function updateSprintCondition(bool $trigger = false): void
	{
		if (Auth::isLoggedIn())
		{
			$sprintCondition = ClassRegistry::init('AchievementCondition')->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => 'sprint',
				],
			]);
			if (!$sprintCondition)
			{
				$sprintCondition = [];
				$sprintCondition['AchievementCondition']['value'] = 0;
				ClassRegistry::init('AchievementCondition')->create();
			}
			$sprintCondition['AchievementCondition']['category'] = 'sprint';
			$sprintCondition['AchievementCondition']['user_id'] = Auth::getUserID();
			if ($trigger)
				$sprintCondition['AchievementCondition']['value']++;
			else
				$sprintCondition['AchievementCondition']['value'] = 0;
			ClassRegistry::init('AchievementCondition')->save($sprintCondition);
		}
	}

	public static function updateGoldenCondition(bool $trigger = false): void
	{
		$goldenCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'golden',
			],
		]);
		if (!$goldenCondition)
		{
			$goldenCondition = [];
			$goldenCondition['AchievementCondition']['value'] = 0;
			ClassRegistry::init('AchievementCondition')->create();
		}
		$goldenCondition['AchievementCondition']['category'] = 'golden';
		$goldenCondition['AchievementCondition']['user_id'] = Auth::getUserID();
		if ($trigger)
			$goldenCondition['AchievementCondition']['value']++;
		else
			$goldenCondition['AchievementCondition']['value'] = 0;
		ClassRegistry::init('AchievementCondition')->save($goldenCondition);
	}

	public static function setPotionCondition(): void
	{
		$potionCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'potion',
			],
		]);
		if (!$potionCondition)
		{
			$potionCondition = [];
			ClassRegistry::init('AchievementCondition')->create();
		}
		$potionCondition['AchievementCondition']['category'] = 'potion';
		$potionCondition['AchievementCondition']['user_id'] = Auth::getUserID();
		$potionCondition['AchievementCondition']['value'] = 1;
		ClassRegistry::init('AchievementCondition')->save($potionCondition);
	}

	public static function updateGems(string $rank): void
	{
		$datex = new DateTime('today');
		$dateGem = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => $datex->format('Y-m-d')]]);
		if ($dateGem != null)
		{
			$gems = explode('-', $dateGem['DayRecord']['gems']);
			$gemValue = '';
			$gemValue2 = '';
			$gemValue3 = '';
			$condition1 = 500;
			$condition2 = 200;
			$condition3 = 5;
			$found1 = false;
			$found2 = false;
			$found3 = false;
			if ($rank == '15k' || $rank == '14k' || $rank == '13k' || $rank == '12k' || $rank == '11k' || $rank == '10k')
			{
				if ($gems[0] == 0)
					$gemValue = '15k';
				elseif ($gems[0] == 1)
					$gemValue = '12k';
				elseif ($gems[0] == 2)
					$gemValue = '10k';
				if ($rank == $gemValue)
				{
					$dateGem['DayRecord']['gemCounter1']++;
					if ($dateGem['DayRecord']['gemCounter1'] == $condition1)
						$found1 = true;
				}
			}
			elseif ($rank == '9k' || $rank == '8k' || $rank == '7k' || $rank == '6k' || $rank == '5k' || $rank == '4k' || $rank == '3k' || $rank == '2k' || $rank == '1k')
			{
				if ($gems[1] == 0)
				{
					$gemValue = '9k';
					$gemValue2 = 'x';
					$gemValue3 = 'y';
				}
				elseif ($gems[1] == 1)
				{
					$gemValue = '6k';
					$gemValue2 = '5k';
					$gemValue3 = '4k';
				}
				elseif ($gems[1] == 2)
				{
					$gemValue = 'x';
					$gemValue2 = '2k';
					$gemValue3 = '1k';
				}
				if ($rank == $gemValue || $rank == $gemValue2 || $rank == $gemValue3)
				{
					$dateGem['DayRecord']['gemCounter2']++;
					if ($dateGem['DayRecord']['gemCounter2'] == $condition2)
						$found2 = true;
				}
			}
			elseif ($rank == '1d' || $rank == '2d' || $rank == '3d' || $rank == '4d' || $rank == '5d' || $rank == '6d' || $rank == '7d')
			{
				if ($gems[2] == 0)
				{
					$gemValue = '1d';
					$gemValue2 = '2d';
					$gemValue3 = '3d';
				}
				elseif ($gems[2] == 1)
				{
					$gemValue = '2d';
					$gemValue2 = '3d';
					$gemValue3 = '4d';
				}
				elseif ($gems[2] == 2)
				{
					$gemValue = '5d';
					$gemValue2 = '6d';
					$gemValue3 = '7d';
				}
				if ($rank == $gemValue || $rank == $gemValue2 || $rank == $gemValue3)
				{
					$dateGem['DayRecord']['gemCounter3']++;
					if ($dateGem['DayRecord']['gemCounter3'] == $condition3)
						$found3 = true;
				}
			}
			if ($found1)
			{
				$aCondition = ClassRegistry::init('AchievementCondition')->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'emerald',
					],
				]);
				if ($aCondition == null)
				{
					$aCondition = [];
					$aCondition['AchievementCondition']['category'] = 'emerald';
					$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
					$aCondition['AchievementCondition']['value'] = 1;
					ClassRegistry::init('AchievementCondition')->save($aCondition);
				}
				else
					$dateGem['DayRecord']['gemCounter1']--;
			}
			elseif ($found2)
			{
				$aCondition = ClassRegistry::init('AchievementCondition')->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'sapphire',
					],
				]);
				if ($aCondition == null)
				{
					$aCondition = [];
					$aCondition['AchievementCondition']['category'] = 'sapphire';
					$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
					$aCondition['AchievementCondition']['value'] = 1;
					ClassRegistry::init('AchievementCondition')->save($aCondition);
				}
				else
					$dateGem['DayRecord']['gemCounter2']--;
			}
			elseif ($found3)
			{
				$aCondition = ClassRegistry::init('AchievementCondition')->find('first', [
					'order' => 'value DESC',
					'conditions' => [
						'user_id' => Auth::getUserID(),
						'category' => 'ruby',
					],
				]);
				if ($aCondition == null)
				{
					$aCondition = [];
					$aCondition['AchievementCondition']['category'] = 'ruby';
					$aCondition['AchievementCondition']['user_id'] = Auth::getUserID();
					$aCondition['AchievementCondition']['value'] = 1;
					ClassRegistry::init('AchievementCondition')->save($aCondition);
				}
				else
					$dateGem['DayRecord']['gemCounter3']--;
			}
		}
		ClassRegistry::init('DayRecord')->save($dateGem);
	}

	public static function checkProblemNumberAchievements()
	{
		if (!Auth::isLoggedIn())
			return;

		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer)
			$buffer = [];
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++)
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$achievementId = 1;
		$solvedCount = Auth::getUser()['solved'];
		if ($solvedCount >= 1000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 2;
		if ($solvedCount >= 2000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 3;
		if ($solvedCount >= 3000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 4;
		if ($solvedCount >= 4000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 5;
		if ($solvedCount >= 5000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 6;
		if ($solvedCount >= 6000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 7;
		if ($solvedCount >= 7000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 8;
		if ($solvedCount >= 8000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 9;
		if ($solvedCount >= 9000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 10;
		if ($solvedCount >= 10000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		//uotd achievement
		$achievementId = 11;
		if (!isset($existingAs[$achievementId]))
		{
			$condition = ClassRegistry::init('AchievementCondition')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'category' => 'uotd']]);
			if ($condition != null)
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
		}

		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++)
		{
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	public static function checkDanSolveAchievements()
	{
		if (Auth::isLoggedIn())
		{
			$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
			if (!$buffer)
				$buffer = [];
			$ac = ClassRegistry::init('AchievementCondition')->find('all', [
				'order' => 'category ASC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'OR' => [
						['category' => 'danSolve1d'],
						['category' => 'danSolve2d'],
						['category' => 'danSolve3d'],
						['category' => 'danSolve4d'],
						['category' => 'danSolve5d'],
						['category' => 'emerald'],
						['category' => 'sapphire'],
						['category' => 'ruby'],
						['category' => 'sprint'],
						['category' => 'golden'],
						['category' => 'potion'],
					],
				],
			]);
			if (!$ac)
				$ac = [];
			$ac1 = [];
			$acCount = count($ac);
			for ($i = 0; $i < $acCount; $i++)
				if ($ac[$i]['AchievementCondition']['category'] == 'danSolve1d')
					$ac1['1d'] = $ac[$i]['AchievementCondition']['value'];
				elseif ($ac[$i]['AchievementCondition']['category'] == 'danSolve2d')
					$ac1['2d'] = $ac[$i]['AchievementCondition']['value'];
				elseif ($ac[$i]['AchievementCondition']['category'] == 'danSolve3d')
					$ac1['3d'] = $ac[$i]['AchievementCondition']['value'];
				elseif ($ac[$i]['AchievementCondition']['category'] == 'danSolve4d')
					$ac1['4d'] = $ac[$i]['AchievementCondition']['value'];
				elseif ($ac[$i]['AchievementCondition']['category'] == 'danSolve5d')
					$ac1['5d'] = $ac[$i]['AchievementCondition']['value'];
				elseif ($ac[$i]['AchievementCondition']['category'] == 'emerald')
					$ac1['emerald'] = $ac[$i]['AchievementCondition']['value'];
				elseif ($ac[$i]['AchievementCondition']['category'] == 'sapphire')
					$ac1['sapphire'] = $ac[$i]['AchievementCondition']['value'];
				elseif ($ac[$i]['AchievementCondition']['category'] == 'ruby')
					$ac1['ruby'] = $ac[$i]['AchievementCondition']['value'];
				elseif ($ac[$i]['AchievementCondition']['category'] == 'sprint')
					$ac1['sprint'] = $ac[$i]['AchievementCondition']['value'];
				elseif ($ac[$i]['AchievementCondition']['category'] == 'golden')
					$ac1['golden'] = $ac[$i]['AchievementCondition']['value'];
				elseif ($ac[$i]['AchievementCondition']['category'] == 'potion')
					$ac1['potion'] = $ac[$i]['AchievementCondition']['value'];

			$existingAs = [];
			$bufferCount = count($buffer);
			for ($i = 0; $i < $bufferCount; $i++)
				$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
			$as = [];
			$as['AchievementStatus']['user_id'] = Auth::getUserID();
			$updated = [];
			$achievementId = 101;
			if ($ac1['1d'] > 0 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 102;
			if ($ac1['2d'] > 0 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 103;
			if ($ac1['3d'] > 0 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 104;
			if ($ac1['4d'] > 0 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 105;
			if ($ac1['5d'] > 0 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 106;
			if ($ac1['1d'] >= 10 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 107;
			if ($ac1['2d'] >= 10 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 108;
			if ($ac1['3d'] >= 10 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 109;
			if ($ac1['4d'] >= 10 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 110;
			if ($ac1['5d'] >= 10 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 111;
			if (isset($ac1['emerald']))
				if ($ac1['emerald'] == 1 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			$achievementId = 112;
			if (isset($ac1['sapphire']))
				if ($ac1['sapphire'] == 1 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			$achievementId = 113;
			if (isset($ac1['ruby']))
				if ($ac1['ruby'] == 1 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			$achievementId = 114;
			if (!isset($existingAs[$achievementId]) && isset($existingAs[111]) && isset($existingAs[112]) && isset($existingAs[113]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 96;
			if (!isset($existingAs[$achievementId]) && $ac1['sprint'] >= 30)
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 97;
			if (!isset($existingAs[$achievementId]) && $ac1['golden'] >= 10)
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 98;
			if (!isset($existingAs[$achievementId]) && $ac1['potion'] >= 1)
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$updatedCount = count($updated);
			for ($i = 0; $i < $updatedCount; $i++)
			{
				$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
				$updated[$i] = [];
				$updated[$i][0] = $a['Achievement']['name'];
				$updated[$i][1] = $a['Achievement']['description'];
				$updated[$i][2] = $a['Achievement']['image'];
				$updated[$i][3] = $a['Achievement']['color'];
				$updated[$i][4] = $a['Achievement']['xp'];
				$updated[$i][5] = $a['Achievement']['id'];
			}

			return $updated;
		}
	}

	protected function checkForLocked($t, $setsWithPremium)
	{
		$scCheck = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
		if ($scCheck && in_array($scCheck['SetConnection']['set_id'], $setsWithPremium) && !Auth::hasPremium())
			$t['Tsumego']['locked'] = true;
		else
			$t['Tsumego']['locked'] = false;

		return $t;
	}
	public static function checkNoErrorAchievements()
	{
		if (Auth::isLoggedIn())
		{

			$ac = ClassRegistry::init('AchievementCondition')->find('first', [
				'order' => 'value DESC',
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'category' => 'err',
				],
			]);

			$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
			if (!$buffer)
				$buffer = [];
			$existingAs = [];
			$bufferCount = count($buffer);
			for ($i = 0; $i < $bufferCount; $i++)
				$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
			$as = [];
			$as['AchievementStatus']['user_id'] = Auth::getUserID();
			$updated = [];

			$achievementId = 53;
			if ($ac['AchievementCondition']['value'] >= 10 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 54;
			if ($ac['AchievementCondition']['value'] >= 20 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 55;
			if ($ac['AchievementCondition']['value'] >= 30 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 56;
			if ($ac['AchievementCondition']['value'] >= 50 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 57;
			if ($ac['AchievementCondition']['value'] >= 100 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$achievementId = 58;
			if ($ac['AchievementCondition']['value'] >= 200 && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
			$updatedCount = count($updated);
			for ($i = 0; $i < $updatedCount; $i++)
			{
				$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
				$updated[$i] = [];
				$updated[$i][0] = $a['Achievement']['name'];
				$updated[$i][1] = $a['Achievement']['description'];
				$updated[$i][2] = $a['Achievement']['image'];
				$updated[$i][3] = $a['Achievement']['color'];
				$updated[$i][4] = $a['Achievement']['xp'];
				$updated[$i][5] = $a['Achievement']['id'];
			}

			return $updated;
		}
	}

	protected function checkTimeModeAchievements()
	{
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('TimeModeSession');

		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer)
			$buffer = [];
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++)
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$rBlitz = $this->TimeModeSession->find('all', ['conditions' => ['time_mode_category_id' => TimeModeUtil::$CATEGORY_BLITZ, 'user_id' => Auth::getUserID()]]);
		if (!$rBlitz)
			$rBlitz = [];
		$rFast = $this->TimeModeSession->find('all', ['conditions' => ['time_mode_category_id' => TimeModeUtil::$CATEGORY_FAST_SPEED, 'user_id' => Auth::getUserID()]]);
		if (!$rFast)
			$rFast = [];
		$rSlow = $this->TimeModeSession->find('all', ['conditions' => ['time_mode_category_id' => TimeModeUtil::$CATEGORY_SLOW_SPEED, 'user_id' => Auth::getUserID()]]);
		if (!$rSlow)
			$rSlow = [];
		$r = $this->TimeModeSession->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$r)
			$r = [];

		$timeModeAchievements = [];
		for ($i = 70; $i <= 91; $i++)
			$timeModeAchievements[$i] = false;
		$rCount = count($r);
		for ($i = 0; $i < $rCount; $i++)
		{
			if ($r[$i]['TimeModeSession']['status'] == 's')
				if ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '5k')
				{
					if ($r[$i]['TimeModeSession']['mode'] == 2)
						$timeModeAchievements[70] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 1)
						$timeModeAchievements[76] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 0)
						$timeModeAchievements[82] = true;
				}
				elseif ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '4k')
				{
					if ($r[$i]['TimeModeSession']['mode'] == 2)
						$timeModeAchievements[71] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 1)
						$timeModeAchievements[77] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 0)
						$timeModeAchievements[83] = true;
				}
				elseif ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '3k')
				{
					if ($r[$i]['TimeModeSession']['mode'] == 2)
						$timeModeAchievements[72] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 1)
						$timeModeAchievements[78] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 0)
						$timeModeAchievements[84] = true;
				}
				elseif ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '2k')
				{
					if ($r[$i]['TimeModeSession']['mode'] == 2)
						$timeModeAchievements[73] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 1)
						$timeModeAchievements[79] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 0)
						$timeModeAchievements[85] = true;
				}
				elseif ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '1k')
				{
					if ($r[$i]['TimeModeSession']['mode'] == 2)
						$timeModeAchievements[74] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 1)
						$timeModeAchievements[80] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 0)
						$timeModeAchievements[86] = true;
				}
				elseif ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '1d')
					if ($r[$i]['TimeModeSession']['mode'] == 2)
						$timeModeAchievements[75] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 1)
						$timeModeAchievements[81] = true;
					elseif ($r[$i]['TimeModeSession']['mode'] == 0)
						$timeModeAchievements[87] = true;
			if ($r[$i]['TimeModeSession']['points'] >= 850
			&& ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '4k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '4d'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5d'))
				$timeModeAchievements[91] = true;
			if ($r[$i]['TimeModeSession']['points'] >= 875
			&& ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '4k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '4d'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '6k'))
				$timeModeAchievements[90] = true;
			if ($r[$i]['TimeModeSession']['points'] >= 900
			&& ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '4k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '4d'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '6k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '7k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '8k'))
				$timeModeAchievements[89] = true;
			if ($r[$i]['TimeModeSession']['points'] >= 950
			&& ($r[$i]['TimeModeSession']['TimeModeAttempt'] == '4k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '1d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '2d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '3d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '4d'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5d' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '5k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '6k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '7k'
			|| $r[$i]['TimeModeSession']['TimeModeAttempt'] == '8k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '9k' || $r[$i]['TimeModeSession']['TimeModeAttempt'] == '10k'))
				$timeModeAchievements[88] = true;
		}
		for ($i = 70; $i <= 91; $i++)
		{
			$achievementId = $i;
			if ($timeModeAchievements[$achievementId] == true && !isset($existingAs[$achievementId]))
			{
				$as['AchievementStatus']['achievement_id'] = $achievementId;
				ClassRegistry::init('AchievementStatus')->create();
				ClassRegistry::init('AchievementStatus')->save($as);
				array_push($updated, $achievementId);
			}
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++)
		{
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	public static function checkRatingAchievements()
	{
		if (!Auth::isLoggedIn())
			return;

		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer)
			$buffer = [];
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++)
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$achievementId = 59;
		$currentElo = Auth::getUser()['rating'];
		if ($currentElo >= 1500 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 60;
		if ($currentElo >= 1600 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 61;
		if ($currentElo >= 1700 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 62;
		if ($currentElo >= 1800 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 63;
		if ($currentElo >= 1900 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 64;
		if ($currentElo >= 2000 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 65;
		if ($currentElo >= 2100 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 66;
		if ($currentElo >= 2200 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 67;
		if ($currentElo >= 2300 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 68;
		if ($currentElo >= 2400 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 69;
		if ($currentElo >= 2500 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++)
		{
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	public static function checkLevelAchievements()
	{
		if (!Auth::isLoggedIn())
			return;
		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer)
			$buffer = [];
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++)
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$achievementId = 36;
		$userLevel = Auth::getUser()['level'];
		if ($userLevel >= 10 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 37;
		if ($userLevel >= 20 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 38;
		if ($userLevel >= 30 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 39;
		if ($userLevel >= 40 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 40;
		if ($userLevel >= 50 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 41;
		if ($userLevel >= 60 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 42;
		if ($userLevel >= 70 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 43;
		if ($userLevel >= 80 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 44;
		if ($userLevel >= 90 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 45;
		if ($userLevel >= 100 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 100;
		if (Auth::hasPremium() && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++)
		{
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	protected function checkSetCompletedAchievements()
	{
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');

		$ac = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'set',
			],
		]);

		if (!$ac)
			return [];

		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer)
			$buffer = [];
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++)
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$achievementId = 47;
		if ($ac['AchievementCondition']['value'] >= 10 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 48;
		if ($ac['AchievementCondition']['value'] >= 20 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 49;
		if ($ac['AchievementCondition']['value'] >= 30 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 50;
		if ($ac['AchievementCondition']['value'] >= 40 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 51;
		if ($ac['AchievementCondition']['value'] >= 50 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 52;
		if ($ac['AchievementCondition']['value'] >= 60 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++)
		{
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	protected function setAchievementSpecial($s = null)
	{
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('SetConnection');

		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer)
			$buffer = [];
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++)
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$tsIds = [];
		$completed = '';
		if ($s == 'cc1')
		{
			$ts1 = TsumegoUtil::collectTsumegosFromSet(50);
			$ts2 = TsumegoUtil::collectTsumegosFromSet(52);
			$ts3 = TsumegoUtil::collectTsumegosFromSet(53);
			$ts4 = TsumegoUtil::collectTsumegosFromSet(54);
			$ts = array_merge($ts1, $ts2, $ts3, $ts4);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++)
				array_push($tsIds, $ts[$i]['Tsumego']['id']);
			$uts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]);
			if (!$uts)
				$uts = [];
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++)
				for ($k = 0; $k < $tsCount; $k++)
					if ($uts[$j]['TsumegoStatus']['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['TsumegoStatus']['status'] == 'S'
					|| $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C'))
						$counter++;
			if ($counter == count($ts))
				$completed = $s;
		}
		elseif ($s == 'cc2')
		{
			$ts1 = TsumegoUtil::collectTsumegosFromSet(41);
			$ts2 = TsumegoUtil::collectTsumegosFromSet(49);
			$ts3 = TsumegoUtil::collectTsumegosFromSet(65);
			$ts4 = TsumegoUtil::collectTsumegosFromSet(66);
			$ts = array_merge($ts1, $ts2, $ts3, $ts4);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++)
				array_push($tsIds, $ts[$i]['Tsumego']['id']);
			$uts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]);
			if (!$uts)
				$uts = [];
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++)
				for ($k = 0; $k < $tsCount; $k++)
					if ($uts[$j]['TsumegoStatus']['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['TsumegoStatus']['status'] == 'S'
					|| $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C'))
						$counter++;
			if ($counter == count($ts))
				$completed = $s;
		}
		elseif ($s == 'cc3')
		{
			$ts1 = TsumegoUtil::collectTsumegosFromSet(186);
			$ts2 = TsumegoUtil::collectTsumegosFromSet(187);
			$ts3 = TsumegoUtil::collectTsumegosFromSet(196);
			$ts4 = TsumegoUtil::collectTsumegosFromSet(203);
			$ts = array_merge($ts1, $ts2, $ts3, $ts4);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++)
				array_push($tsIds, $ts[$i]['Tsumego']['id']);
			$uts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]);
			if (!$uts)
				$uts = [];
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++)
				for ($k = 0; $k < $tsCount; $k++)
					if ($uts[$j]['TsumegoStatus']['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['TsumegoStatus']['status'] == 'S'
					|| $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C'))
						$counter++;
			if ($counter == count($ts))
				$completed = $s;
		}
		elseif ($s == '1000w1')
		{
			$ts1 = TsumegoUtil::collectTsumegosFromSet(190);
			$ts2 = TsumegoUtil::collectTsumegosFromSet(193);
			$ts3 = TsumegoUtil::collectTsumegosFromSet(198);
			$ts = array_merge($ts1, $ts2, $ts3);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++)
				array_push($tsIds, $ts[$i]['Tsumego']['id']);
			$uts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]);
			if (!$uts)
				$uts = [];
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++)
				for ($k = 0; $k < $tsCount; $k++)
					if ($uts[$j]['TsumegoStatus']['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['TsumegoStatus']['status'] == 'S'
					|| $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C'))
						$counter++;
			if ($counter == count($ts))
				$completed = $s;
		}
		elseif ($s == '1000w2')
		{
			$ts = TsumegoUtil::collectTsumegosFromSet(216);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++)
				array_push($tsIds, $ts[$i]['Tsumego']['id']);
			$uts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]);
			if (!$uts)
				$uts = [];
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++)
				for ($k = 0; $k < $tsCount; $k++)
					if ($uts[$j]['TsumegoStatus']['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['TsumegoStatus']['status'] == 'S'
					|| $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C'))
						$counter++;
			if ($counter == count($ts))
				$completed = $s;
		}

		$achievementId = 92;
		if ($completed == 'cc1' && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 93;
		if ($completed == 'cc2' && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 94;
		if ($completed == 'cc3' && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 95;
		if ($completed == '1000w1' && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$achievementId = 115;
		if ($completed == '1000w2' && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++)
		{
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	protected function checkSetAchievements($sid = null)
	{
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');

		//$tNum = count($this->Tsumego->find('all', array('conditions' => array('set_id' => $sid))));
		$tNum = count(TsumegoUtil::collectTsumegosFromSet($sid));
		$s = $this->Set->findById($sid);
		$acA = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'set_id' => $sid,
				'user_id' => Auth::getUserID(),
				'category' => '%',
			],
		]);
		if (!$acA)
			return [];
		$acS = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value ASC',
			'conditions' => [
				'set_id' => $sid,
				'user_id' => Auth::getUserID(),
				'category' => 's',
			],
		]);
		$buffer = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		if (!$buffer)
			$buffer = [];
		$existingAs = [];
		$bufferCount = count($buffer);
		for ($i = 0; $i < $bufferCount; $i++)
			$existingAs[$buffer[$i]['AchievementStatus']['achievement_id']] = $buffer[$i];
		$as = [];
		$as['AchievementStatus']['user_id'] = Auth::getUserID();
		$updated = [];

		$achievementId = 99;
		if ($sid == -1 && !isset($existingAs[$achievementId]))
		{
			$as['AchievementStatus']['achievement_id'] = $achievementId;
			ClassRegistry::init('AchievementStatus')->create();
			ClassRegistry::init('AchievementStatus')->save($as);
			array_push($updated, $achievementId);
		}
		if ($tNum >= 100)
		{
			if ($s['Set']['difficulty'] < 1300)
			{
				$achievementId = 12;
				if ($acA['AchievementCondition']['value'] >= 75 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 13;
				if ($acA['AchievementCondition']['value'] >= 85 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 14;
				if ($acA['AchievementCondition']['value'] >= 95 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 24;
				if ($acS['AchievementCondition']['value'] < 15 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 25;
				if ($acS['AchievementCondition']['value'] < 10 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 26;
				if ($acS['AchievementCondition']['value'] < 5 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			}
			elseif ($s['Set']['difficulty'] >= 1300 && $s['Set']['difficulty'] < 1500)
			{
				$achievementId = 15;
				if ($acA['AchievementCondition']['value'] >= 75 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 16;
				if ($acA['AchievementCondition']['value'] >= 85 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 17;
				if ($acA['AchievementCondition']['value'] >= 95 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 27;
				if ($acS['AchievementCondition']['value'] < 18 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 28;
				if ($acS['AchievementCondition']['value'] < 13 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 29;
				if ($acS['AchievementCondition']['value'] < 8 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			}
			elseif ($s['Set']['difficulty'] >= 1500 && $s['Set']['difficulty'] < 1700)
			{
				$achievementId = 18;
				if ($acA['AchievementCondition']['value'] >= 75 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 19;
				if ($acA['AchievementCondition']['value'] >= 85 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 20;
				if ($acA['AchievementCondition']['value'] >= 95 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 30;
				if ($acS['AchievementCondition']['value'] < 30 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 31;
				if ($acS['AchievementCondition']['value'] < 20 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 32;
				if ($acS['AchievementCondition']['value'] < 10 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			}
			else
			{
				$achievementId = 21;
				if ($acA['AchievementCondition']['value'] >= 75 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 22;
				if ($acA['AchievementCondition']['value'] >= 85 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 23;
				if ($acA['AchievementCondition']['value'] >= 95 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 33;
				if ($acS['AchievementCondition']['value'] < 30 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 34;
				if ($acS['AchievementCondition']['value'] < 20 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				$achievementId = 35;
				if ($acS['AchievementCondition']['value'] < 10 && !isset($existingAs[$achievementId]))
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
			}
			$achievementId = 46;
			if ($acA['AchievementCondition']['value'] >= 100)
			{
				$ac100 = ClassRegistry::init('AchievementCondition')->find('all', ['conditions' => ['user_id' => Auth::getUserID(), 'category' => '%', 'value >=' => 100]]);
				if (!$ac100)
					$ac100 = [];
				$ac100counter = 0;
				$ac100Count = count($ac100);
				for ($j = 0; $j < $ac100Count; $j++)
					if (count(TsumegoUtil::collectTsumegosFromSet($ac100[$j]['AchievementCondition']['set_id'])) >= 100)
						$ac100counter++;
				$as100 = ClassRegistry::init('AchievementStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'achievement_id' => $achievementId]]);
				if ($as100 == null)
				{
					$as['AchievementStatus']['achievement_id'] = $achievementId;
					$as['AchievementStatus']['value'] = 1;
					ClassRegistry::init('AchievementStatus')->create();
					ClassRegistry::init('AchievementStatus')->save($as);
					array_push($updated, $achievementId);
				}
				elseif ($as100['AchievementStatus']['value'] != $ac100counter)
				{
					$as100['AchievementStatus']['value'] = $ac100counter;
					ClassRegistry::init('AchievementStatus')->save($as100);
					array_push($updated, $achievementId);
				}
			}
		}
		$updatedCount = count($updated);
		for ($i = 0; $i < $updatedCount; $i++)
		{
			$a = ClassRegistry::init('Achievement')->findById($updated[$i]);
			$updated[$i] = [];
			$updated[$i][0] = $a['Achievement']['name'];
			$updated[$i][1] = $a['Achievement']['description'];
			$updated[$i][2] = $a['Achievement']['image'];
			$updated[$i][3] = $a['Achievement']['color'];
			$updated[$i][4] = $a['Achievement']['xp'];
			$updated[$i][5] = $a['Achievement']['id'];
		}

		return $updated;
	}

	public static function updateXP($userID, $achievementData): void
	{
		$xpBonus = 0;
		$aCount = count($achievementData);
		for ($i = 0; $i < $aCount; $i++)
			$xpBonus += $achievementData[$i][4];
		if ($xpBonus == 0)
			return;
		$user = ClassRegistry::init('User')->findById($userID);
		$user['User']['xp'] = $xpBonus;
		Level::checkLevelUp($user['User']);
		ClassRegistry::init('User')->save($user);
	}

	/**
	 * @param int $uid User ID
	 * @return void
	 */
	protected function handleSearchSettings($uid)
	{
		$this->loadModel('UserContribution');
		$uc = $this->UserContribution->find('first', ['conditions' => ['user_id' => $uid]]);
		if ($uc == null)
		{
			$uc = [];
			$uc['UserContribution']['user_id'] = $uid;
			$uc['UserContribution']['added_tag'] = 0;
			$uc['UserContribution']['created_tag'] = 0;
			$uc['UserContribution']['made_proposal'] = 0;
			$uc['UserContribution']['reviewed'] = 0;
			$uc['UserContribution']['score'] = 0;
			$this->UserContribution->create();
			$this->UserContribution->save($uc);
		}
		new TsumegoFilters();
	}

	protected function signIn(array $user): void
	{
		Auth::init($user);
		$vs = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $user['User']['id']], 'order' => 'updated DESC']);
		if ($vs)
			$this->Session->write('lastVisit', $vs['TsumegoStatus']['tsumego_id']);
		$this->Session->write('texture', $user['User']['texture']);
		$this->Session->write('check1', $user['User']['id']);
	}

	public function beforeFilter(): void
	{
		$this->loadModel('User');
		$this->loadModel('Activate');
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoRatingAttempt');
		$this->loadModel('Set');
		$this->loadModel('TimeModeAttempt');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Comment');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('AdminActivity');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');
		$this->loadModel('SetConnection');
		$this->loadModel('Tag');
		$this->loadModel('TagName');
		$this->loadModel('Favorite');

		Auth::init();
		$timeMode = new TimeMode();

		$highscoreLink = 'highscore';
		$lightDark = 'light';
		$resetCookies = false;
		$levelBar = 1;
		$lastProfileLeft = 1;
		$lastProfileRight = 2;
		$hasFavs = false;

		if (Auth::isLoggedIn())
		{
			if ($lastTimeModeCategoryID = Util::clearCookie('lastTimeModeCategoryID'))
				Auth::getUser()['last_time_mode_category_id'] = $lastTimeModeCategoryID;

			if (isset($_COOKIE['addTag']) && $_COOKIE['addTag'] != 0 && $this->Session->read('page') != 'set')
			{
				$newAddTag = explode('-', $_COOKIE['addTag']);
				$tagId = $newAddTag[0];
				$newTagName = $this->TagName->find('first', ['conditions' => ['name' => str_replace($tagId . '-', '', $_COOKIE['addTag'])]]);
				if ($newTagName)
				{
					$saveTag = [];
					$saveTag['Tag']['tag_name_id'] = $newTagName['TagName']['id'];
					$saveTag['Tag']['tsumego_id'] = $tagId;
					$saveTag['Tag']['user_id'] = Auth::getUserID();
					$saveTag['Tag']['approved'] = 0;
					$this->Tag->save($saveTag);
				}
				$this->set('removeCookie', 'addTag');
			}
			if (isset($_COOKIE['z_sess']) && $_COOKIE['z_sess'] != 0
			&& strlen($_COOKIE['z_sess']) > 5)
			{
				Auth::getUser()['_sessid'] = $_COOKIE['z_sess'];
				Auth::saveUser();
			}
			if (Auth::getUser()['lastHighscore'] == 1)
				$highscoreLink = 'highscore';
			elseif (Auth::getUser()['lastHighscore'] == 2)
				$highscoreLink = 'rating';
			elseif (Auth::getUser()['lastHighscore'] == 3)
				$highscoreLink = 'leaderboard';
			elseif (Auth::getUser()['lastHighscore'] == 4)
				$highscoreLink = 'highscore3';

			if (isset($_COOKIE['lastMode']) && $_COOKIE['lastMode'] != 0)
			{
				Auth::getUser()['lastMode'] = $_COOKIE['lastMode'];
				Auth::saveUser();
			}
			if (isset($_COOKIE['sound']) && $_COOKIE['sound'] != '0')
			{
				Auth::getUser()['sound'] = $_COOKIE['sound'];
				Auth::saveUser();
				unset($_COOKIE['sound']);
			}
			$this->set('ac', true);
			$this->set('user', Auth::getUser());
		}

		if (isset($_COOKIE['lightDark']) && $_COOKIE['lightDark'] != '0')
		{
			$lightDark = $_COOKIE['lightDark'];
			if (Auth::isLoggedIn())
			{
				// Convert string to integer for database storage
				$lightDarkInt = ($lightDark === 'light') ? 0 : 2;
				Auth::getUser()['lastLight'] = $lightDarkInt;
			}
		}
		elseif (Auth::isLoggedIn())
			if (Auth::getUser()['lastLight'] == 0
			|| Auth::getUser()['lastLight'] == 1)
				$lightDark = 'light';
			else
				$lightDark = 'dark';

		if (Auth::isLoggedIn())
		{
			$this->handleSearchSettings(Auth::getUserID());
			if (isset($_COOKIE['levelBar']) && $_COOKIE['levelBar'] != '0')
			{
				$levelBar = $_COOKIE['levelBar'];
				Auth::getUser()['levelBar'] = $levelBar;
			}
			elseif (Auth::getUser()['levelBar'] == 0
		  || Auth::getUser()['levelBar'] == 'level')
		  	$levelBar = 1;
			else
				$levelBar = 2;

			if (isset($_COOKIE['lastProfileLeft']) && $_COOKIE['lastProfileLeft'] != '0')
			{
				$lastProfileLeft = $_COOKIE['lastProfileLeft'];
				Auth::getUser()['lastProfileLeft'] = $lastProfileLeft;
			}
			else
			{
				$lastProfileLeft = Auth::getUser()['lastProfileLeft'];
				if ($lastProfileLeft == 0)
					$lastProfileLeft = 1;
			}
			if (isset($_COOKIE['lastProfileRight']) && $_COOKIE['lastProfileRight'] != '0')
			{
				$lastProfileRight = $_COOKIE['lastProfileRight'];
				Auth::getUser()['lastProfileRight'] = $lastProfileRight;
			}
			else
			{
				$lastProfileRight = Auth::getUser()['lastProfileRight'];
				if ($lastProfileRight == 0)
					$lastProfileRight = 1;
			}
		}
		$mode = 1;
		if (isset($_COOKIE['mode']) && $_COOKIE['mode'] != '0')
			if ($_COOKIE['mode'] == 1)
				$mode = 1;
			else
				$mode = 2;

		if (Auth::isLoggedIn() && Auth::getUser()['mode'] == 2)
			$mode = 2;

		if ($_COOKIE['sprint'] != 1)
			$this->updateSprintCondition();

		if (Auth::isLoggedIn())
		{
			if (isset($_COOKIE['revelation']) && $_COOKIE['revelation'] != 0)
				Auth::getUser()['revelation'] -= 1;

			if (!$this->request->is('ajax'))
				$this->PlayResultProcessor->checkPreviousPlay($timeMode);
		}
		$boardNames = [];
		$enabledBoards = [];
		$boardPositions = [];

		$boardNames[1] = 'Pine';
		$boardNames[2] = 'Ash';
		$boardNames[3] = 'Maple';
		$boardNames[4] = 'Shin Kaya';
		$boardNames[5] = 'Birch';
		$boardNames[6] = 'Wenge';
		$boardNames[7] = 'Walnut';
		$boardNames[8] = 'Mahogany';
		$boardNames[9] = 'Blackwood';
		$boardNames[10] = 'Marble 1';
		$boardNames[11] = 'Marble 2';
		$boardNames[12] = 'Marble 3';
		$boardNames[13] = 'Tibet Spruce';
		$boardNames[14] = 'Marble 4';
		$boardNames[15] = 'Marble 5';
		$boardNames[16] = 'Quarry 1';
		$boardNames[17] = 'Flowers';
		$boardNames[18] = 'Nova';
		$boardNames[19] = 'Spring';
		$boardNames[20] = 'Moon';
		$boardNames[21] = 'Apex';
		$boardNames[22] = 'Gold 1';
		$boardNames[23] = 'Amber';
		$boardNames[24] = 'Marble 6';
		$boardNames[25] = 'Marble 7';
		$boardNames[26] = 'Marble 8';
		$boardNames[27] = 'Marble 9';
		$boardNames[28] = 'Marble 10';
		$boardNames[29] = 'Jade';
		$boardNames[30] = 'Quarry 2';
		$boardNames[31] = 'Black Bricks';
		$boardNames[32] = 'Wallpaper 1';
		$boardNames[33] = 'Wallpaper 2';
		$boardNames[34] = 'Gold & Gray';
		$boardNames[35] = 'Gold & Pink';
		$boardNames[36] = 'Veil';
		$boardNames[37] = 'Tiles';
		$boardNames[38] = 'Mars';
		$boardNames[39] = 'Pink Cloud';
		$boardNames[40] = 'Reptile';
		$boardNames[41] = 'Mezmerizing';
		$boardNames[42] = 'Magenta Sky';
		$boardNames[43] = 'Tsumego Hero';
		$boardNames[44] = 'Pretty';
		$boardNames[45] = 'Hunting';
		$boardNames[46] = 'Haunted';
		$boardNames[47] = 'Carnage';
		$boardNames[48] = 'Blind Spot';
		$boardNames[49] = 'Giants';
		$boardNames[50] = 'Gems';
		$boardNames[51] = 'Grandmaster';
		$boardPositions[1] = [1, 'texture1', 'black34.png', 'white34.png'];
		$boardPositions[2] = [2, 'texture2', 'black34.png', 'white34.png'];
		$boardPositions[3] = [3, 'texture3', 'black34.png', 'white34.png'];
		$boardPositions[4] = [4, 'texture4', 'black.png', 'white.png'];
		$boardPositions[5] = [5, 'texture5', 'black34.png', 'white34.png'];
		$boardPositions[6] = [6, 'texture6', 'black.png', 'white.png'];
		$boardPositions[7] = [7, 'texture7', 'black34.png', 'white34.png'];
		$boardPositions[8] = [8, 'texture8', 'black.png', 'white.png'];
		$boardPositions[9] = [9, 'texture9', 'black.png', 'white.png'];
		$boardPositions[10] = [10, 'texture10', 'black34.png', 'white34.png'];
		$boardPositions[11] = [11, 'texture11', 'black34.png', 'white34.png'];
		$boardPositions[12] = [12, 'texture12', 'black34.png', 'white34.png'];
		$boardPositions[13] = [13, 'texture13', 'black34.png', 'white34.png'];
		$boardPositions[14] = [14, 'texture14', 'black34.png', 'white34.png'];
		$boardPositions[15] = [15, 'texture15', 'black.png', 'white.png'];
		$boardPositions[16] = [16, 'texture16', 'black34.png', 'white34.png'];
		$boardPositions[17] = [17, 'texture17', 'black34.png', 'white34.png'];
		$boardPositions[18] = [18, 'texture18', 'black.png', 'white.png'];
		$boardPositions[19] = [19, 'texture19', 'black34.png', 'white34.png'];
		$boardPositions[20] = [20, 'texture20', 'black34.png', 'white34.png'];
		$boardPositions[21] = [33, 'texture33', 'black34.png', 'white34.png'];
		$boardPositions[22] = [21, 'texture21', 'black.png', 'whiteKo.png'];
		$boardPositions[23] = [22, 'texture22', 'black34.png', 'white34.png'];
		$boardPositions[24] = [34, 'texture34', 'black.png', 'white.png'];
		$boardPositions[25] = [35, 'texture35', 'black34.png', 'white34.png'];
		$boardPositions[26] = [36, 'texture36', 'black.png', 'white.png'];
		$boardPositions[27] = [37, 'texture37', 'black34.png', 'white34.png'];
		$boardPositions[28] = [38, 'texture38', 'black38.png', 'white34.png'];
		$boardPositions[29] = [39, 'texture39', 'black.png', 'white.png'];
		$boardPositions[30] = [40, 'texture40', 'black34.png', 'white34.png'];
		$boardPositions[31] = [41, 'texture41', 'black34.png', 'white34.png'];
		$boardPositions[32] = [42, 'texture42', 'black34.png', 'white42.png'];
		$boardPositions[33] = [43, 'texture43', 'black34.png', 'white42.png'];
		$boardPositions[34] = [44, 'texture44', 'black34.png', 'white34.png'];
		$boardPositions[35] = [45, 'texture45', 'black34.png', 'white42.png'];
		$boardPositions[36] = [47, 'texture47', 'black34.png', 'white34.png'];
		$boardPositions[37] = [48, 'texture48', 'black34.png', 'white34.png'];
		$boardPositions[38] = [49, 'texture49', 'black.png', 'white.png'];
		$boardPositions[39] = [50, 'texture50', 'black34.png', 'white34.png'];
		$boardPositions[40] = [51, 'texture51', 'black34.png', 'white34.png'];
		$boardPositions[41] = [52, 'texture52', 'black34.png', 'white34.png'];
		$boardPositions[42] = [53, 'texture53', 'black34.png', 'white34.png'];
		$boardPositions[43] = [54, 'texture54', 'black54.png', 'white54.png'];
		$boardPositions[44] = [23, 'texture23', 'black.png', 'whiteFlower.png'];
		$boardPositions[45] = [24, 'texture24', 'black24.png', 'white24.png'];
		$boardPositions[46] = [25, 'texture25', 'blackGhost.png', 'white.png'];
		$boardPositions[47] = [26, 'texture26', 'blackInvis.png', 'whiteCarnage.png'];
		$boardPositions[48] = [27, 'texture27', 'black27.png', 'white27.png'];
		$boardPositions[49] = [28, 'texture28', 'blackGiant.png', 'whiteKo.png'];
		$boardPositions[50] = [29, 'texture29', 'blackKo.png', 'whiteKo.png'];
		$boardPositions[51] = [30, 'texture55', 'blackGalaxy.png', 'whiteGalaxy.png'];

		$boardCount = 51;

		$bitmask = 0b11111111; // Default: first 8 boards enabled

		if ($this->Session->check('boards_bitmask') || (isset($_COOKIE['texture']) && $_COOKIE['texture'] != '0'))
		{
			if (isset($_COOKIE['texture']) && $_COOKIE['texture'] != '0')
			{
				// Convert cookie string to bitmask
				$textureCookie = $_COOKIE['texture'];
				$bitmask = 0;
				$length = strlen($textureCookie);
				$limit = min($length, 63);
				for ($i = 0; $i < $limit; $i++)
					if ($textureCookie[$i] == '2')
						$bitmask |= (1 << $i);
				if (Auth::isLoggedIn())
					Auth::getUser()['boards_bitmask'] = $bitmask;
				$this->Session->write('boards_bitmask', $bitmask);
				// Pass the cookie back to view to maintain JS compatibility for now
				$this->set('textureCookies', $textureCookie);
			}
			elseif (Auth::isLoggedIn())
			{
				$bitmask = (int) Auth::getUser()['boards_bitmask'];
				$this->Session->write('boards_bitmask', $bitmask);
			}
			elseif ($this->Session->check('boards_bitmask'))
				$bitmask = (int) $this->Session->read('boards_bitmask');

			if (Auth::isLoggedIn())
				Auth::saveUser();
		}
		else
		{
			// Default state if no session/cookie
			$this->Session->write('boards_bitmask', $bitmask);
		}

		// Populate enabledBoards array based on bitmask
		for ($i = 0; $i < $boardCount; $i++)
		{
			if (($bitmask & (1 << $i)) !== 0)
				$enabledBoards[$i + 1] = 'checked';
			else
				$enabledBoards[$i + 1] = '';
		}
		$achievementUpdate = [];
		if ($this->Session->check('initialLoading'))
		{
			$achievementUpdate1 = $this->checkLevelAchievements();
			$achievementUpdate2 = $this->checkProblemNumberAchievements();
			$achievementUpdate3 = $this->checkRatingAchievements();
			$achievementUpdate4 = $this->checkTimeModeAchievements();
			$achievementUpdate5 = $this->checkDanSolveAchievements();
			$achievementUpdate = array_merge(
				$achievementUpdate1 ?: [],
				$achievementUpdate2 ?: [],
				$achievementUpdate3 ?: [],
				$achievementUpdate4 ?: [],
				$achievementUpdate5 ?: []
			);
			$this->Session->delete('initialLoading');
		}

		if (count($achievementUpdate) > 0)
			$this->updateXP(Auth::getUserID(), $achievementUpdate);

		$nextDay = new DateTime('tomorrow');
		if (Auth::isLoggedIn())
		{
			Auth::getUser()['name'] = $this->checkPicture(Auth::getUser());
			$this->set('user', Auth::getUser());
		}
		$this->set('mode', $mode);
		$this->set('nextDay', $nextDay->format('m/d/Y'));
		$this->set('boardNames', $boardNames);
		$this->set('enabledBoards', $enabledBoards);
		$this->set('boardPositions', $boardPositions);
		$this->set('highscoreLink', $highscoreLink);
		$this->set('achievementUpdate', $achievementUpdate);
		$this->set('lightDark', $lightDark);
		$this->set('levelBar', $levelBar);
		$this->set('lastProfileLeft', $lastProfileLeft);
		$this->set('lastProfileRight', $lastProfileRight);
		$this->set('resetCookies', $resetCookies);
		$this->set('hasFavs', $hasFavs);
	}

	public function afterFilter() {}
}

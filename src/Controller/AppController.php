<?php

App::uses('Auth', 'Utility');
App::uses('BoardSelector', 'Utility');
App::uses('TsumegoFilters', 'Utility');
App::uses('AchievementChecker', 'Utility');
App::uses('TimeMode', 'Utility');

class AppController extends Controller
{
	public $viewClass = 'App';

	public $helpers = ['Pagination', 'AssetCompress.AssetCompress'];

	public $components = [
		//'DebugKit.Toolbar',
		'PlayResultProcessor'
	];

	/**
	 * Check if the current request is an HTMX request.
	 *
	 * @return bool True if this is an HTMX AJAX request
	 */
	protected function isHtmxRequest(): bool
	{
		return $this->request->header('HX-Request') === 'true';
	}

	/**
	 * Render a partial view (element) without layout for HTMX requests.
	 *
	 * @param string $element The element path (e.g., 'TsumegoIssues/list')
	 * @param array $data Variables to pass to the element
	 * @return void
	 */
	protected function renderPartial(string $element, array $data = []): void
	{
		$this->autoRender = false;
		$view = new View($this);
		$html = $view->element($element, $data);
		$this->response->body($html);
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
			$uc['UserContribution']['created_tag'] = 0;
			$uc['UserContribution']['made_proposal'] = 0;
			$uc['UserContribution']['reviewed'] = 0;
			$uc['UserContribution']['score'] = 0;
			ClassRegistry::init('UserContribution')->create();
		}
		$uc['UserContribution'][$action] += 1;
		$uc['UserContribution']['score']
		= $uc['UserContribution']['created_tag'] * 3
		+ $uc['UserContribution']['made_proposal'] * 5
		+ $uc['UserContribution']['reviewed'] * 2;
		ClassRegistry::init('UserContribution')->save($uc);
	}

	public static function getAllTags($not)
	{
		$a = [];
		$notApproved = ClassRegistry::init('Tag')->find('all', ['conditions' => ['approved' => 0]]);
		if (!$notApproved)
			$notApproved = [];
		foreach ($not as $item)
			$a[] = $item['tag_id'];
		$notApprovedCount = count($notApproved);
		for ($i = 0; $i < $notApprovedCount; $i++)
			array_push($a, $notApproved[$i]['Tag']['id']);
		$tn = ClassRegistry::init('Tag')->find('all', [
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
			array_push($sorted, $tn[$i]['Tag']['name']);
			$keys[$tn[$i]['Tag']['name']] = $tn[$i];
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

	public static function checkProblemNumberAchievements(AchievementChecker $achievementChecker)
	{
		$solvedCount = Auth::getUser()['solved'];
		if ($solvedCount >= 1000)
			$achievementChecker->gained(Achievement::PROBLEMS_1000);
		if ($solvedCount >= 2000)
			$achievementChecker->gained(Achievement::PROBLEMS_2000);
		if ($solvedCount >= 3000)
			$achievementChecker->gained(Achievement::PROBLEMS_3000);
		if ($solvedCount >= 4000)
			$achievementChecker->gained(Achievement::PROBLEMS_4000);
		if ($solvedCount >= 5000)
			$achievementChecker->gained(Achievement::PROBLEMS_5000);
		if ($solvedCount >= 6000)
			$achievementChecker->gained(Achievement::PROBLEMS_6000);
		if ($solvedCount >= 7000)
			$achievementChecker->gained(Achievement::PROBLEMS_7000);
		if ($solvedCount >= 8000)
			$achievementChecker->gained(Achievement::PROBLEMS_8000);
		if ($solvedCount >= 9000)
			$achievementChecker->gained(Achievement::PROBLEMS_9000);
		if ($solvedCount >= 10000)
			$achievementChecker->gained(Achievement::PROBLEMS_10000);

		if (ClassRegistry::init('AchievementCondition')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'category' => 'uotd']]))
			$achievementChecker->gained(Achievement::USER_OF_THE_DAY);
	}

	public static function checkDanSolveAchievements(AchievementChecker $achivementChecker)
	{
		$achievementConditions = ClassRegistry::init('AchievementCondition')->find('all', [
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
		]) ?: [];
		$ac1 = [];
		foreach ($achievementConditions as $achievementCondition)
			if ($achievementCondition['AchievementCondition']['category'] == 'danSolve1d')
				$ac1['1d'] = $achievementCondition['AchievementCondition']['value'];
			elseif ($achievementCondition['AchievementCondition']['category'] == 'danSolve2d')
				$ac1['2d'] = $achievementCondition['AchievementCondition']['value'];
			elseif ($achievementCondition['AchievementCondition']['category'] == 'danSolve3d')
				$ac1['3d'] = $achievementCondition['AchievementCondition']['value'];
			elseif ($achievementCondition['AchievementCondition']['category'] == 'danSolve4d')
				$ac1['4d'] = $achievementCondition['AchievementCondition']['value'];
			elseif ($achievementCondition['AchievementCondition']['category'] == 'danSolve5d')
				$ac1['5d'] = $achievementCondition['AchievementCondition']['value'];
			elseif ($achievementCondition['AchievementCondition']['category'] == 'emerald')
				$ac1['emerald'] = $achievementCondition['AchievementCondition']['value'];
			elseif ($achievementCondition['AchievementCondition']['category'] == 'sapphire')
				$ac1['sapphire'] = $achievementCondition['AchievementCondition']['value'];
			elseif ($achievementCondition['AchievementCondition']['category'] == 'ruby')
				$ac1['ruby'] = $achievementCondition['AchievementCondition']['value'];
			elseif ($achievementCondition['AchievementCondition']['category'] == 'sprint')
				$ac1['sprint'] = $achievementCondition['AchievementCondition']['value'];
			elseif ($achievementCondition['AchievementCondition']['category'] == 'golden')
				$ac1['golden'] = $achievementCondition['AchievementCondition']['value'];
			elseif ($achievementCondition['AchievementCondition']['category'] == 'potion')
				$ac1['potion'] = $achievementCondition['AchievementCondition']['value'];

		if ($ac1['1d'] > 0)
			$achivementChecker->gained(Achievement::SOLVE_1D);
		if ($ac1['2d'] > 0)
			$achivementChecker->gained(Achievement::SOLVE_2D);
		if ($ac1['3d'] > 0)
			$achivementChecker->gained(Achievement::SOLVE_3D);
		if ($ac1['4d'] > 0)
			$achivementChecker->gained(Achievement::SOLVE_4D);
		if ($ac1['5d'] > 0)
			$achivementChecker->gained(Achievement::SOLVE_5D);
		if ($ac1['1d'] >= 10)
			$achivementChecker->gained(Achievement::SOLVE_10_1D);
		if ($ac1['2d'] >= 10)
			$achivementChecker->gained(Achievement::SOLVE_10_2D);
		if ($ac1['3d'] >= 10)
			$achivementChecker->gained(Achievement::SOLVE_10_3D);
		if ($ac1['4d'] >= 10)
			$achivementChecker->gained(Achievement::SOLVE_10_4D);
		if ($ac1['5d'] >= 10)
			$achivementChecker->gained(Achievement::SOLVE_10_5D);
		if (isset($ac1['emerald']) && $ac1['emerald'] == 1)
			$achivementChecker->gained(Achievement::EMERALD);
		if (isset($ac1['sapphire']) && $ac1['sapphire'] == 1)
			$achivementChecker->gained(Achievement::SAPPHIRE);
		if (isset($ac1['ruby']) && $ac1['ruby'] == 1)
			$achivementChecker->gained(Achievement::RUBY);

		if ($achivementChecker->unlocked(Achievement::EMERALD)
			&& $achivementChecker->unlocked(Achievement::SAPPHIRE)
			&& $achivementChecker->unlocked(Achievement::RUBY))
				$achivementChecker->gained(Achievement::DIAMOND);
		if ($ac1['sprint'] >= 30)
			$achivementChecker->gained(Achievement::SPRINT);
		if ($ac1['golden'] >= 10)
			$achivementChecker->gained(Achievement::GOLD_DIGGER);
		if ($ac1['potion'] >= 1)
			$achivementChecker->gained(Achievement::BAD_POTION);
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

	public static function checkNoErrorAchievements(AchievementChecker $achievementChecker)
	{
		$ac = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => ['user_id' => Auth::getUserID(), 'category' => 'err']]);
		if ($ac['AchievementCondition']['value'] >= 10)
			$achievementChecker->gained(Achievement::NO_ERROR_STREAK_I);
		if ($ac['AchievementCondition']['value'] >= 20)
			$achievementChecker->gained(Achievement::NO_ERROR_STREAK_II);
		if ($ac['AchievementCondition']['value'] >= 30)
			$achievementChecker->gained(Achievement::NO_ERROR_STREAK_III);
		if ($ac['AchievementCondition']['value'] >= 50)
			$achievementChecker->gained(Achievement::NO_ERROR_STREAK_IV);
		if ($ac['AchievementCondition']['value'] >= 100)
			$achievementChecker->gained(Achievement::NO_ERROR_STREAK_V);
		if ($ac['AchievementCondition']['value'] >= 200)
			$achievementChecker->gained(Achievement::NO_ERROR_STREAK_VI);
	}

	public static function checkTimeModeAchievements(AchievementChecker $achievementChecker): void
	{
		$timeModeSessions = ClassRegistry::init('TimeModeSession')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]) ?: [];
		foreach ($timeModeSessions as $timeModeSession)
		{
			$timeModeSession = $timeModeSession['TimeModeSession'];
			// Compare IDs directly - no need for recursive loading
			$statusId = isset($timeModeSession['time_mode_session_status_id']) ? $timeModeSession['time_mode_session_status_id'] : 0;
			$rankId = isset($timeModeSession['time_mode_rank_id']) ? $timeModeSession['time_mode_rank_id'] : 0;
			$categoryId = isset($timeModeSession['time_mode_category_id']) ? $timeModeSession['time_mode_category_id'] : 0;

			if ($statusId == TimeModeSessionStatus::SOLVED)
				if ($rankId == TimeModeRank::RANK_5K)
				{
					if ($categoryId == TimeModeCategory::SLOW)
						$achievementChecker->gained(Achievement::TIME_MODE_APPRENTICE_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$achievementChecker->gained(Achievement::TIME_MODE_APPRENTICE_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$achievementChecker->gained(Achievement::TIME_MODE_APPRENTICE_BLITZ);
				}
				elseif ($rankId == TimeModeRank::RANK_4K)
				{
					if ($categoryId == TimeModeCategory::SLOW)
						$achievementChecker->gained(Achievement::TIME_MODE_SCHOLAR_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$achievementChecker->gained(Achievement::TIME_MODE_SCHOLAR_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$achievementChecker->gained(Achievement::TIME_MODE_SCHOLAR_BLITZ);

				}
				elseif ($rankId == TimeModeRank::RANK_3K)
				{
					if ($categoryId == TimeModeCategory::SLOW)
						$achievementChecker->gained(Achievement::TIME_MODE_LABOURER_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$achievementChecker->gained(Achievement::TIME_MODE_LABOURER_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$achievementChecker->gained(Achievement::TIME_MODE_LABOURER_BLITZ);

				}
				elseif ($rankId == TimeModeRank::RANK_2K)
				{
					if ($categoryId == TimeModeCategory::SLOW)
						$achievementChecker->gained(Achievement::TIME_MODE_ADEPT_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$achievementChecker->gained(Achievement::TIME_MODE_ADEPT_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$achievementChecker->gained(Achievement::TIME_MODE_ADEPT_BLITZ);
				}
				elseif ($rankId == TimeModeRank::RANK_1K)
				{
					if ($categoryId == TimeModeCategory::SLOW)
						$achievementChecker->gained(Achievement::TIME_MODE_EXPERT_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$achievementChecker->gained(Achievement::TIME_MODE_EXPERT_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$achievementChecker->gained(Achievement::TIME_MODE_EXPERT_BLITZ);
				}
				elseif ($rankId == TimeModeRank::RANK_1D)
					if ($categoryId == TimeModeCategory::SLOW)
						$achievementChecker->gained(Achievement::TIME_MODE_MASTER_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$achievementChecker->gained(Achievement::TIME_MODE_MASTER_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$achievementChecker->gained(Achievement::TIME_MODE_MASTER_BLITZ);

			// Precision achievements based on points and rank
			$points = isset($timeModeSession['points']) ? $timeModeSession['points'] : 0;
			if ($points >= 850 && $rankId >= TimeModeRank::RANK_4K)
				$achievementChecker->gained(Achievement::TIME_MODE_PRECISION_IV);
			if ($points >= 875 && $rankId >= TimeModeRank::RANK_6K)
				$achievementChecker->gained(Achievement::TIME_MODE_PRECISION_III);
			if ($points >= 900 && $rankId >= TimeModeRank::RANK_8K)
				$achievementChecker->gained(Achievement::TIME_MODE_PRECISION_II);
			if ($points >= 950 && $rankId >= TimeModeRank::RANK_10K)
				$achievementChecker->gained(Achievement::TIME_MODE_PRECISION_I);
		}
	}

	public static function checkRatingAchievements(AchievementChecker $achievementChecker)
	{
		$rating = Auth::getUser()['rating'];
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('6k'))
			$achievementChecker->gained(Achievement::RATING_6_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('5k'))
			$achievementChecker->gained(Achievement::RATING_5_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('4k'))
			$achievementChecker->gained(Achievement::RATING_4_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('3k'))
			$achievementChecker->gained(Achievement::RATING_3_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('2k'))
			$achievementChecker->gained(Achievement::RATING_2_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('1k'))
			$achievementChecker->gained(Achievement::RATING_1_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('1d'))
			$achievementChecker->gained(Achievement::RATING_1_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('2d'))
			$achievementChecker->gained(Achievement::RATING_2_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('3d'))
			$achievementChecker->gained(Achievement::RATING_3_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('4d'))
			$achievementChecker->gained(Achievement::RATING_4_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('5d'))
			$achievementChecker->gained(Achievement::RATING_5_DAN);
	}

	public static function checkLevelAchievements(AchievementChecker $achievementChecker)
	{
		$userLevel = Auth::getUser()['level'];
		if ($userLevel >= 10)
			$achievementChecker->gained(Achievement::LEVEL_UP);
		if ($userLevel >= 20)
			$achievementChecker->gained(Achievement::FIRST_HERO_POWER);
		if ($userLevel >= 30)
			$achievementChecker->gained(Achievement::UPGRADED_INTUITION);
		if ($userLevel >= 40)
			$achievementChecker->gained(Achievement::MORE_POWER);
		if ($userLevel >= 50)
			$achievementChecker->gained(Achievement::HALF_WAY_TO_TOP);
		if ($userLevel >= 60)
			$achievementChecker->gained(Achievement::CONGRATS_MORE_PROBLEMS);
		if ($userLevel >= 70)
			$achievementChecker->gained(Achievement::NICE_LEVEL);
		if ($userLevel >= 80)
			$achievementChecker->gained(Achievement::DID_LOT_OF_TSUMEGO);
		if ($userLevel >= 90)
			$achievementChecker->gained(Achievement::STILL_DOING_TSUMEGO);
		if ($userLevel >= 100)
			$achievementChecker->gained(Achievement::THE_TOP);
		if (Auth::hasPremium())
			$achievementChecker->gained(Achievement::PREMIUM);
	}

	public function checkSetCompletedAchievements()
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

	public function setAchievementSpecial($s = null)
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

	public function checkSetAchievements($sid = null)
	{
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');

		// Check Achievement 99 (Favorites) FIRST - doesn't need achievement_condition
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

			// Format and return early - favorites don't have other set-based achievements
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
		foreach ($achievementData as $achievement)
			$xpBonus += $achievement['xp'];
		if ($xpBonus == 0)
			return;
		$user = ClassRegistry::init('User')->findById($userID);
		$user['User']['xp'] += $xpBonus;
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
			Util::setCookie('lastVisit', $vs['TsumegoStatus']['tsumego_id']);
		Util::setCookie('texture', $user['User']['texture']);
		Util::setCookie('check1', $user['User']['id']);
	}

	public function beforeFilter(): void
	{
		$this->loadModel('User');
		$this->loadModel('Activate');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('TimeModeAttempt');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('AdminActivity');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('AchievementCondition');
		$this->loadModel('SetConnection');
		$this->loadModel('Tag');
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

			if (!$this->request->is('ajax') && !$this->isHtmxRequest())
				$this->PlayResultProcessor->checkPreviousPlay($timeMode);
		}
		$boardNames = [];
		$enabledBoards = [];
		$boardCount = 51;

		if (!is_null($boardsBitmask = Util::clearCookie('boards_bitmask')))
		{
			if (Auth::isLoggedIn())
			{
				Auth::getUser()['boards_bitmask'] = BoardSelector::filterValidBits($boardsBitmask);
				Auth::saveUser();
			}
		}
		else
			$boardsBitmask = BoardSelector::filterValidBits(Auth::isLoggedIn() ? Auth::getUser()['boards_bitmask'] : BoardSelector::$DEFAULT_BOARDS_BITMASK);

		$this->set('boardsBitmask', $boardsBitmask);

		$achievementChecker = new AchievementChecker();
		if (Auth::isLoggedIn() && Util::clearCookie('initialLoading'))
		{
			self::checkLevelAchievements($achievementChecker);
			self::checkProblemNumberAchievements($achievementChecker);
			self::checkRatingAchievements($achievementChecker);
			self::checkTimeModeAchievements($achievementChecker);
			self::checkDanSolveAchievements($achievementChecker);
			self::checkNoErrorAchievements($achievementChecker);
		}

		if (count($achievementChecker->updated) > 0)
			$this->updateXP(Auth::getUserID(), $achievementChecker->updated);

		$nextDay = new DateTime('tomorrow');
		if (Auth::isLoggedIn())
		{
			Auth::getUser()['name'] = $this->checkPicture(Auth::getUser());
			$this->set('user', Auth::getUser());
		}
		$this->set('mode', $mode);
		$this->set('nextDay', $nextDay->format('m/d/Y'));
		$this->set('boardNames', $boardNames);
		$this->set('highscoreLink', $highscoreLink);
		$this->set('achievementUpdates', $achievementChecker->updated);
		$this->set('lightDark', $lightDark);
		$this->set('levelBar', $levelBar);
		$this->set('lastProfileLeft', $lastProfileLeft);
		$this->set('lastProfileRight', $lastProfileRight);
		$this->set('resetCookies', $resetCookies);
		$this->set('hasFavs', $hasFavs);
		$this->set('timeMode', $timeMode);
		if (Auth::isLoggedIn())
			Auth::saveUser();
	}

	public function afterFilter() {}
}

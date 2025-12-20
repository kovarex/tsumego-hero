<?php

class AchievementChecker
{
	public function __construct()
	{
		$this->fillExistingStatuses();
	}

	public function gained($achievementID): void
	{
		if ($this->existingStatuses[$achievementID])
			return;
		$achievementStatus = [];
		$achievementStatus['achievement_id'] = $achievementID;
		$achievementStatus['user_id'] = Auth::getUserID();
		ClassRegistry::init('AchievementStatus')->create();
		ClassRegistry::init('AchievementStatus')->save($achievementStatus);

		$achievement = ClassRegistry::init('Achievement')->findById($achievementID);
		$this->updated [] = $achievement['Achievement'];
	}

	private function fillExistingStatuses(): void
	{
		$achievementStatuses = ClassRegistry::init('AchievementStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]) ?: [];
		foreach ($achievementStatuses as $achievementStatus)
			$this->existingStatuses[$achievementStatus['AchievementStatus']['achievement_id']] = true;
	}

	public function unlocked($achievementID): bool
	{
		return isset($this->existingStatuses[$achievementID]);
	}

	public function checkProblemNumberAchievements(): AchievementChecker
	{
		$solvedCount = Auth::getUser()['solved'];
		if ($solvedCount >= 1000)
			$this->gained(Achievement::PROBLEMS_1000);
		if ($solvedCount >= 2000)
			$this->gained(Achievement::PROBLEMS_2000);
		if ($solvedCount >= 3000)
			$this->gained(Achievement::PROBLEMS_3000);
		if ($solvedCount >= 4000)
			$this->gained(Achievement::PROBLEMS_4000);
		if ($solvedCount >= 5000)
			$this->gained(Achievement::PROBLEMS_5000);
		if ($solvedCount >= 6000)
			$this->gained(Achievement::PROBLEMS_6000);
		if ($solvedCount >= 7000)
			$this->gained(Achievement::PROBLEMS_7000);
		if ($solvedCount >= 8000)
			$this->gained(Achievement::PROBLEMS_8000);
		if ($solvedCount >= 9000)
			$this->gained(Achievement::PROBLEMS_9000);
		if ($solvedCount >= 10000)
			$this->gained(Achievement::PROBLEMS_10000);

		if (ClassRegistry::init('AchievementCondition')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'category' => 'uotd']]))
			$this->gained(Achievement::USER_OF_THE_DAY);
		return $this;
	}

	public function checkDanSolveAchievements(): void
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
			$this->gained(Achievement::SOLVE_1D);
		if ($ac1['2d'] > 0)
			$this->gained(Achievement::SOLVE_2D);
		if ($ac1['3d'] > 0)
			$this->gained(Achievement::SOLVE_3D);
		if ($ac1['4d'] > 0)
			$this->gained(Achievement::SOLVE_4D);
		if ($ac1['5d'] > 0)
			$this->gained(Achievement::SOLVE_5D);
		if ($ac1['1d'] >= 10)
			$this->gained(Achievement::SOLVE_10_1D);
		if ($ac1['2d'] >= 10)
			$this->gained(Achievement::SOLVE_10_2D);
		if ($ac1['3d'] >= 10)
			$this->gained(Achievement::SOLVE_10_3D);
		if ($ac1['4d'] >= 10)
			$this->gained(Achievement::SOLVE_10_4D);
		if ($ac1['5d'] >= 10)
			$this->gained(Achievement::SOLVE_10_5D);
		if (isset($ac1['emerald']) && $ac1['emerald'] == 1)
			$this->gained(Achievement::EMERALD);
		if (isset($ac1['sapphire']) && $ac1['sapphire'] == 1)
			$this->gained(Achievement::SAPPHIRE);
		if (isset($ac1['ruby']) && $ac1['ruby'] == 1)
			$this->gained(Achievement::RUBY);

		if ($this->unlocked(Achievement::EMERALD)
			&& $this->unlocked(Achievement::SAPPHIRE)
			&& $this->unlocked(Achievement::RUBY))
				$this->gained(Achievement::DIAMOND);
		if ($ac1['sprint'] >= 30)
			$this->gained(Achievement::SPRINT);
		if ($ac1['golden'] >= 10)
			$this->gained(Achievement::GOLD_DIGGER);
		if ($ac1['potion'] >= 1)
			$this->gained(Achievement::BAD_POTION);
	}

	public function checkNoErrorAchievements(): void
	{
		$ac = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => ['user_id' => Auth::getUserID(), 'category' => 'err']]);
		if ($ac['AchievementCondition']['value'] >= Achievement::NO_ERROR_STREAK_I_STREAK_COUNT)
			$this->gained(Achievement::NO_ERROR_STREAK_I);
		if ($ac['AchievementCondition']['value'] >= Achievement::NO_ERROR_STREAK_II_STREAK_COUNT)
			$this->gained(Achievement::NO_ERROR_STREAK_II);
		if ($ac['AchievementCondition']['value'] >= Achievement::NO_ERROR_STREAK_III_STREAK_COUNT)
			$this->gained(Achievement::NO_ERROR_STREAK_III);
		if ($ac['AchievementCondition']['value'] >= Achievement::NO_ERROR_STREAK_IV_STREAK_COUNT)
			$this->gained(Achievement::NO_ERROR_STREAK_IV);
		if ($ac['AchievementCondition']['value'] >= Achievement::NO_ERROR_STREAK_V_STREAK_COUNT)
			$this->gained(Achievement::NO_ERROR_STREAK_V);
		if ($ac['AchievementCondition']['value'] >= Achievement::NO_ERROR_STREAK_VI_STREAK_COUNT)
			$this->gained(Achievement::NO_ERROR_STREAK_VI);
	}

	public function checkTimeModeAchievements(): AchievementChecker
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
						$this->gained(Achievement::TIME_MODE_APPRENTICE_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$this->gained(Achievement::TIME_MODE_APPRENTICE_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$this->gained(Achievement::TIME_MODE_APPRENTICE_BLITZ);
				}
				elseif ($rankId == TimeModeRank::RANK_4K)
				{
					if ($categoryId == TimeModeCategory::SLOW)
						$this->gained(Achievement::TIME_MODE_SCHOLAR_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$this->gained(Achievement::TIME_MODE_SCHOLAR_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$this->gained(Achievement::TIME_MODE_SCHOLAR_BLITZ);

				}
				elseif ($rankId == TimeModeRank::RANK_3K)
				{
					if ($categoryId == TimeModeCategory::SLOW)
						$this->gained(Achievement::TIME_MODE_LABOURER_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$this->gained(Achievement::TIME_MODE_LABOURER_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$this->gained(Achievement::TIME_MODE_LABOURER_BLITZ);
				}
				elseif ($rankId == TimeModeRank::RANK_2K)
				{
					if ($categoryId == TimeModeCategory::SLOW)
						$this->gained(Achievement::TIME_MODE_ADEPT_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$this->gained(Achievement::TIME_MODE_ADEPT_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$this->gained(Achievement::TIME_MODE_ADEPT_BLITZ);
				}
				elseif ($rankId == TimeModeRank::RANK_1K)
				{
					if ($categoryId == TimeModeCategory::SLOW)
						$this->gained(Achievement::TIME_MODE_EXPERT_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$this->gained(Achievement::TIME_MODE_EXPERT_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$this->gained(Achievement::TIME_MODE_EXPERT_BLITZ);
				}
				elseif ($rankId == TimeModeRank::RANK_1D)
					if ($categoryId == TimeModeCategory::SLOW)
						$this->gained(Achievement::TIME_MODE_MASTER_SLOW);
					elseif ($categoryId == TimeModeCategory::FAST)
						$this->gained(Achievement::TIME_MODE_MASTER_FAST);
					elseif ($categoryId == TimeModeCategory::BLITZ)
						$this->gained(Achievement::TIME_MODE_MASTER_BLITZ);

			// Precision achievements based on points and rank
			$points = isset($timeModeSession['points']) ? $timeModeSession['points'] : 0;
			if ($points >= 850 && $rankId >= TimeModeRank::RANK_4K)
				$this->gained(Achievement::TIME_MODE_PRECISION_IV);
			if ($points >= 875 && $rankId >= TimeModeRank::RANK_6K)
				$this->gained(Achievement::TIME_MODE_PRECISION_III);
			if ($points >= 900 && $rankId >= TimeModeRank::RANK_8K)
				$this->gained(Achievement::TIME_MODE_PRECISION_II);
			if ($points >= 950 && $rankId >= TimeModeRank::RANK_10K)
				$this->gained(Achievement::TIME_MODE_PRECISION_I);
		}
		return $this;
	}

	public function checkRatingAchievements(): AchievementChecker
	{
		$rating = Auth::getUser()['rating'];
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('6k'))
			$this->gained(Achievement::RATING_6_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('5k'))
			$this->gained(Achievement::RATING_5_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('4k'))
			$this->gained(Achievement::RATING_4_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('3k'))
			$this->gained(Achievement::RATING_3_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('2k'))
			$this->gained(Achievement::RATING_2_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('1k'))
			$this->gained(Achievement::RATING_1_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('1d'))
			$this->gained(Achievement::RATING_1_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('2d'))
			$this->gained(Achievement::RATING_2_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('3d'))
			$this->gained(Achievement::RATING_3_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('4d'))
			$this->gained(Achievement::RATING_4_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('5d'))
			$this->gained(Achievement::RATING_5_DAN);
		return $this;
	}

	public function checkLevelAchievements(): AchievementChecker
	{
		$userLevel = Auth::getUser()['level'];
		if ($userLevel >= 10)
			$this->gained(Achievement::LEVEL_UP);
		if ($userLevel >= 20)
			$this->gained(Achievement::FIRST_HERO_POWER);
		if ($userLevel >= 30)
			$this->gained(Achievement::UPGRADED_INTUITION);
		if ($userLevel >= 40)
			$this->gained(Achievement::MORE_POWER);
		if ($userLevel >= 50)
			$this->gained(Achievement::HALF_WAY_TO_TOP);
		if ($userLevel >= 60)
			$this->gained(Achievement::CONGRATS_MORE_PROBLEMS);
		if ($userLevel >= 70)
			$this->gained(Achievement::NICE_LEVEL);
		if ($userLevel >= 80)
			$this->gained(Achievement::DID_LOT_OF_TSUMEGO);
		if ($userLevel >= 90)
			$this->gained(Achievement::STILL_DOING_TSUMEGO);
		if ($userLevel >= 100)
			$this->gained(Achievement::THE_TOP);
		if (Auth::hasPremium())
			$this->gained(Achievement::PREMIUM);
		return $this;
	}

	public function checkSetCompletedAchievements(): AchievementChecker
	{
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'set']]);

		if (!$achievementCondition)
			return $this;
		$achievementCondition = $achievementCondition['AchievementCondition'];
		if ($achievementCondition['value'] >= Achievement::COMPLETE_SETS_I_SETS_COUNT)
			$this->gained(Achievement::COMPLETE_SETS_I);
		if ($achievementCondition['value'] >= Achievement::COMPLETE_SETS_II_SETS_COUNT)
			$this->gained(Achievement::COMPLETE_SETS_II);
		if ($achievementCondition['value'] >= Achievement::COMPLETE_SETS_III_SETS_COUNT)
			$this->gained(Achievement::COMPLETE_SETS_III);
		if ($achievementCondition['value'] >= Achievement::COMPLETE_SETS_IV_SETS_COUNT)
			$this->gained(Achievement::COMPLETE_SETS_IV);
		if ($achievementCondition['value'] >= Achievement::COMPLETE_SETS_V_SETS_COUNT)
			$this->gained(Achievement::COMPLETE_SETS_V);
		if ($achievementCondition['value'] >= Achievement::COMPLETE_SETS_VI_SETS_COUNT)
			$this->gained(Achievement::COMPLETE_SETS_VI);
		return $this;
	}

	public function setAchievementSpecial($s = null): AchievementChecker
	{
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
			$uts = ClassRegistry::init('TsumegoStatus')->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds]]) ?: [];
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
			$uts = ClassRegistry::init('TsumegoStatus')->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds]]) ?: [];
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
			$uts = ClassRegistry::init('TsumegoStatus')->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds,
				],
			]) ?: [];
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
			$uts = ClassRegistry::init('TsumegoStatus')->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds]]) ?: [];
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
			$uts = ClassRegistry::init('TsumegoStatus')->find('all', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsIds]]) ?: [];
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

		if ($completed == 'cc1')
			$this->gained(Achievement::LIFE_DEATH_ELEMENTARY);
		if ($completed == 'cc2')
			$this->gained(Achievement::LIFE_DEATH_INTERMEDIATE);
		if ($completed == 'cc3')
			$this->gained(Achievement::LIFE_DEATH_ADVANCED);
		if ($completed == '1000w1')
			$this->gained(Achievement::WEIQI_1000_FIRST_HALF);
		if ($completed == '1000w2')
			$this->gained(Achievement::WEIQI_1000_SECOND_HALF);
		return $this;
	}

	public function checkSetAchievements($sid = null, $setRating = 0): AchievementChecker
	{
		if ($sid == -1)
		{
			$this->gained(Achievement::FAVORITES);
			return $this;
		}

		$tNum = count(TsumegoUtil::collectTsumegosFromSet($sid));
		$acA = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'set_id' => $sid,
				'user_id' => Auth::getUserID(),
				'category' => '%']]);
		if (!$acA)
			return $this;
		$acS = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value ASC',
			'conditions' => [
				'set_id' => $sid,
				'user_id' => Auth::getUserID(),
				'category' => 's']]);

		if ($tNum < 100)
			return $this;

		if ($setRating < 1300)
		{
			if ($acA['AchievementCondition']['value'] >= 75)
				$this->gained(Achievement::ACCURACY_I);
			if  ($acA['AchievementCondition']['value'] >= 85)
				$this->gained(Achievement::ACCURACY_II);
			if ($acA['AchievementCondition']['value'] >= 95)
				$this->gained(Achievement::ACCURACY_III);
			if ($acS['AchievementCondition']['value'] < 15)
				$this->gained(Achievement::SPEED_I);
			if ($acS['AchievementCondition']['value'] < 10)
				$this->gained(Achievement::SPEED_II);
			if ($acS['AchievementCondition']['value'] < 5)
				$this->gained(Achievement::SPEED_III);
		}
		elseif ($setRating >= 1300 && $setRating < 1500)
		{
			if ($acA['AchievementCondition']['value'] >= 75)
				$this->gained(Achievement::ACCURACY_IV);
			if ($acA['AchievementCondition']['value'] >= 85)
				$this->gained(Achievement::ACCURACY_V);
			if ($acA['AchievementCondition']['value'] >= 95)
				$this->gained(Achievement::ACCURACY_VI);
			if ($acS['AchievementCondition']['value'] < 18)
				$this->gained(Achievement::SPEED_IV);
			if ($acS['AchievementCondition']['value'] < 13)
				$this->gained(Achievement::SPEED_V);
			if ($acS['AchievementCondition']['value'] < 8)
				$this->gained(Achievement::SPEED_VI);
		}
		elseif ($setRating >= 1500 && $setRating < 1700)
		{
			if ($acA['AchievementCondition']['value'] >= 75)
				$this->gained(Achievement::ACCURACY_VII);
			if ($acA['AchievementCondition']['value'] >= 85)
				$this->gained(Achievement::ACCURACY_VIII);
			if ($acA['AchievementCondition']['value'] >= 95)
				$this->gained(Achievement::ACCURACY_IX);
			if ($acS['AchievementCondition']['value'] < 30)
				$this->gained(Achievement::SPEED_VII);
			if ($acS['AchievementCondition']['value'] < 20)
				$this->gained(Achievement::SPEED_VIII);
			if ($acS['AchievementCondition']['value'] < 10)
				$this->gained(Achievement::SPEED_IX);
		}
		else
		{
			if ($acA['AchievementCondition']['value'] >= 75)
				$this->gained(Achievement::ACCURACY_X);
			if ($acA['AchievementCondition']['value'] >= 85)
				$this->gained(Achievement::ACCURACY_XI);
			if ($acA['AchievementCondition']['value'] >= 95)
				$this->gained(Achievement::ACCURACY_XII);
			if ($acS['AchievementCondition']['value'] < 30)
				$this->gained(Achievement::SPEED_X);
			if ($acS['AchievementCondition']['value'] < 20)
				$this->gained(Achievement::SPEED_XI);
			if ($acS['AchievementCondition']['value'] < 10)
				$this->gained(Achievement::SPEED_XII);
		}

		$achievementId = 46;
		if ($acA['AchievementCondition']['value'] >= 100)
		{
			$ac100 = ClassRegistry::init('AchievementCondition')->find('all', ['conditions' => ['user_id' => Auth::getUserID(), 'category' => '%', 'value >=' => 100]]) ?: [];
			$ac100counter = 0;
			$ac100Count = count($ac100);
			for ($j = 0; $j < $ac100Count; $j++)
				if (count(TsumegoUtil::collectTsumegosFromSet($ac100[$j]['AchievementCondition']['set_id'])) >= 100)
					$ac100counter++;
			$as100 = ClassRegistry::init('AchievementStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'achievement_id' => $achievementId]]);
			if ($as100 == null)
			{
				$as100 = [];
				$as100['AchievementStatus']['user_id'] = Auth::getUserID();
				$as100['AchievementStatus']['achievement_id'] = $achievementId;
				$as100['AchievementStatus']['value'] = 0;
				ClassRegistry::init('AchievementStatus')->create();
			}
			if ($as100['AchievementStatus']['value'] != $ac100counter)
			{
				$as100['AchievementStatus']['value'] = $ac100counter;
				ClassRegistry::init('AchievementStatus')->save($as100);
			}
		}
		return $this;
	}

	public function checkAll(): void
	{
		$this->checkLevelAchievements();
		$this->checkProblemNumberAchievements();
		$this->checkRatingAchievements();
		$this->checkDanSolveAchievements();
		$this->checkNoErrorAchievements();
		$this->checkTimeModeAchievements();
	}

	public function finalize(): AchievementChecker
	{
		if (empty($this->updated))
			return $this;

		$xpBonus = 0;
		foreach ($this->updated as $achievement)
			$xpBonus += $achievement['xp'];
		if ($xpBonus == 0)
			return $this;

		$user = &Auth::getUser();
		Level::addXP($user, $xpBonus);
		Auth::saveUser();
		return $this;
	}

	private array $existingStatuses = [];
	public array $updated = [];
}

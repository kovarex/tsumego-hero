<?php

namespace App\Utility;

use App\Model\Entity\Achievement;
use App\Model\Table\AchievementConditionTable;
use App\Model\Table\AchievementTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeRankTable;
use App\Model\Table\TimeModeSessionStatusTable;
use Cake\ORM\Locator\LocatorAwareTrait;
use ClassRegistry;
use SetConnection;

class AchievementChecker
{
	use LocatorAwareTrait;

	public function __construct()
	{
		$this->fillExistingStatuses();
	}

	//The fields the client needs to render an achievement popup from data.
	//Both the /tsumegos/result response and the page-load embed use this, so
	//the popup payload stays pure data and the client renders the markup.
	public static function toPopupData(Achievement $achievement): array
	{
		return [
			'id' => $achievement->id,
			'name' => $achievement->name,
			'description' => $achievement->description,
			'xp' => (int) $achievement->xp,
			'image' => $achievement->image,
			'color' => $achievement->color,
		];
	}

	public function gained(AchievementId $achievementID): void
	{
		if ($this->existingStatuses[$achievementID->value])
			return;
		$achievementStatusTable = $this->fetchTable('AchievementStatus');
		$achievementStatusTable->saveOrFail($achievementStatusTable->newEntity([
			'achievement_id' => $achievementID->value,
			'user_id' => Auth::getUserID(),
		]));

		$this->updated[] = $this->fetchTable('Achievement')->get($achievementID->value);
	}

	//Unlocks a repeatable achievement and records how often it was earned. Earning
	//it the first time grants the XP and the popup, later ones only raise the count.
	//The count records what the player earned, so a collection it can no longer
	//recount does not lower it.
	private function gainedTimes(AchievementId $achievementID, int $times): void
	{
		if ($times < 1)
			return;
		$this->gained($achievementID);

		$achievementStatusTable = $this->fetchTable('AchievementStatus');
		$status = $achievementStatusTable->find()->where(['user_id' => Auth::getUserID(), 'achievement_id' => $achievementID->value])->first();
		if (!$status || $status->value >= $times)
			return;

		$status->value = $times;
		$achievementStatusTable->saveOrFail($status);
	}

	private function fillExistingStatuses(): void
	{
		$achievementStatuses = $this->fetchTable('AchievementStatus')->find()->where(['user_id' => Auth::getUserID()])->all();
		foreach ($achievementStatuses as $achievementStatus)
			$this->existingStatuses[$achievementStatus->achievement_id] = true;
	}

	public function unlocked(AchievementId $achievementID): bool
	{
		return isset($this->existingStatuses[$achievementID->value]);
	}

	public function checkProblemNumberAchievements(): AchievementChecker
	{
		$solvedCount = Auth::getUser()['solved'];
		if ($solvedCount >= 1000)
			$this->gained(AchievementId::PROBLEMS_1000);
		if ($solvedCount >= 2000)
			$this->gained(AchievementId::PROBLEMS_2000);
		if ($solvedCount >= 3000)
			$this->gained(AchievementId::PROBLEMS_3000);
		if ($solvedCount >= 4000)
			$this->gained(AchievementId::PROBLEMS_4000);
		if ($solvedCount >= 5000)
			$this->gained(AchievementId::PROBLEMS_5000);
		if ($solvedCount >= 6000)
			$this->gained(AchievementId::PROBLEMS_6000);
		if ($solvedCount >= 7000)
			$this->gained(AchievementId::PROBLEMS_7000);
		if ($solvedCount >= 8000)
			$this->gained(AchievementId::PROBLEMS_8000);
		if ($solvedCount >= 9000)
			$this->gained(AchievementId::PROBLEMS_9000);
		if ($solvedCount >= 10000)
			$this->gained(AchievementId::PROBLEMS_10000);

		if (AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_USER_OF_THE_DAY) !== null)
			$this->gained(AchievementId::USER_OF_THE_DAY);
		return $this;
	}

	public function checkDanSolveAchievements(): void
	{
		$conditions = AchievementConditionTable::values(Auth::getUserID(), [
			...array_values(AchievementConditionTable::DAN_SOLVE_CATEGORIES),
			AchievementConditionTable::CATEGORY_EMERALD,
			AchievementConditionTable::CATEGORY_SAPPHIRE,
			AchievementConditionTable::CATEGORY_RUBY,
			AchievementConditionTable::CATEGORY_SPRINT,
			AchievementConditionTable::CATEGORY_GOLDEN,
			AchievementConditionTable::CATEGORY_POTION]);
		$ac1 = [];
		foreach (AchievementConditionTable::DAN_SOLVE_CATEGORIES as $rank => $category)
			$ac1[$rank] = $conditions[$category] ?? 0;
		$ac1['emerald'] = $conditions[AchievementConditionTable::CATEGORY_EMERALD] ?? 0;
		$ac1['sapphire'] = $conditions[AchievementConditionTable::CATEGORY_SAPPHIRE] ?? 0;
		$ac1['ruby'] = $conditions[AchievementConditionTable::CATEGORY_RUBY] ?? 0;
		$ac1['sprint'] = $conditions[AchievementConditionTable::CATEGORY_SPRINT] ?? 0;
		$ac1['golden'] = $conditions[AchievementConditionTable::CATEGORY_GOLDEN] ?? 0;
		$ac1['potion'] = $conditions[AchievementConditionTable::CATEGORY_POTION] ?? 0;

		if ($ac1['1d'] > 0)
			$this->gained(AchievementId::SOLVE_1D);
		if ($ac1['2d'] > 0)
			$this->gained(AchievementId::SOLVE_2D);
		if ($ac1['3d'] > 0)
			$this->gained(AchievementId::SOLVE_3D);
		if ($ac1['4d'] > 0)
			$this->gained(AchievementId::SOLVE_4D);
		if ($ac1['5d'] > 0)
			$this->gained(AchievementId::SOLVE_5D);
		if ($ac1['1d'] >= AchievementTable::SOLVE_10_COUNT)
			$this->gained(AchievementId::SOLVE_10_1D);
		if ($ac1['2d'] >= AchievementTable::SOLVE_10_COUNT)
			$this->gained(AchievementId::SOLVE_10_2D);
		if ($ac1['3d'] >= AchievementTable::SOLVE_10_COUNT)
			$this->gained(AchievementId::SOLVE_10_3D);
		if ($ac1['4d'] >= AchievementTable::SOLVE_10_COUNT)
			$this->gained(AchievementId::SOLVE_10_4D);
		if ($ac1['5d'] >= AchievementTable::SOLVE_10_COUNT)
			$this->gained(AchievementId::SOLVE_10_5D);
		if ($ac1['emerald'] == 1)
			$this->gained(AchievementId::EMERALD);
		if ($ac1['sapphire'] == 1)
			$this->gained(AchievementId::SAPPHIRE);
		if ($ac1['ruby'] == 1)
			$this->gained(AchievementId::RUBY);

		if ($this->unlocked(AchievementId::EMERALD)
			&& $this->unlocked(AchievementId::SAPPHIRE)
			&& $this->unlocked(AchievementId::RUBY))
				$this->gained(AchievementId::DIAMOND);
		if ($ac1['sprint'] >= AchievementTable::SPRINT_COUNT)
			$this->gained(AchievementId::SPRINT);
		if ($ac1['golden'] >= AchievementTable::GOLD_DIGGER_COUNT)
			$this->gained(AchievementId::GOLD_DIGGER);
		if ($ac1['potion'] >= HeroPowers::$BAD_POTION_THRESHOLD)
			$this->gained(AchievementId::BAD_POTION);
	}

	public function checkNoErrorAchievements(): void
	{
		$streak = AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_NO_ERROR_STREAK) ?? 0;
		if ($streak >= AchievementTable::NO_ERROR_STREAK_I_STREAK_COUNT)
			$this->gained(AchievementId::NO_ERROR_STREAK_I);
		if ($streak >= AchievementTable::NO_ERROR_STREAK_II_STREAK_COUNT)
			$this->gained(AchievementId::NO_ERROR_STREAK_II);
		if ($streak >= AchievementTable::NO_ERROR_STREAK_III_STREAK_COUNT)
			$this->gained(AchievementId::NO_ERROR_STREAK_III);
		if ($streak >= AchievementTable::NO_ERROR_STREAK_IV_STREAK_COUNT)
			$this->gained(AchievementId::NO_ERROR_STREAK_IV);
		if ($streak >= AchievementTable::NO_ERROR_STREAK_V_STREAK_COUNT)
			$this->gained(AchievementId::NO_ERROR_STREAK_V);
		if ($streak >= AchievementTable::NO_ERROR_STREAK_VI_STREAK_COUNT)
			$this->gained(AchievementId::NO_ERROR_STREAK_VI);
	}

	public function checkTimeModeAchievements(): AchievementChecker
	{
		// select always just one session per each rank and category combination, and the best one
		$timeModeSessions = Util::query("
SELECT *
FROM (
    SELECT
        time_mode_session.*,
        ROW_NUMBER() OVER (
            PARTITION BY time_mode_rank_id, time_mode_category_id
            ORDER BY points DESC, created DESC
        ) AS rn
    FROM time_mode_session
    WHERE time_mode_session.user_id = ?
      AND time_mode_session.time_mode_session_status_id = ?
      AND time_mode_session.challenge_id IS NULL
) ranked
WHERE rn = 1;", [Auth::getUserID(), TimeModeSessionStatusTable::SOLVED]);
		foreach ($timeModeSessions as $timeModeSession)
		{
			// Compare IDs directly - no need for recursive loading
			$statusId = isset($timeModeSession['time_mode_session_status_id']) ? $timeModeSession['time_mode_session_status_id'] : 0;
			$rankId = isset($timeModeSession['time_mode_rank_id']) ? $timeModeSession['time_mode_rank_id'] : 0;
			$categoryId = isset($timeModeSession['time_mode_category_id']) ? $timeModeSession['time_mode_category_id'] : 0;

			if ($statusId == TimeModeSessionStatusTable::SOLVED)
				if ($rankId == TimeModeRankTable::RANK_5K)
				{
					if ($categoryId == TimeModeCategoryTable::SLOW)
						$this->gained(AchievementId::TIME_MODE_APPRENTICE_SLOW);
					elseif ($categoryId == TimeModeCategoryTable::FAST)
						$this->gained(AchievementId::TIME_MODE_APPRENTICE_FAST);
					elseif ($categoryId == TimeModeCategoryTable::BLITZ)
						$this->gained(AchievementId::TIME_MODE_APPRENTICE_BLITZ);
				}
				elseif ($rankId == TimeModeRankTable::RANK_4K)
				{
					if ($categoryId == TimeModeCategoryTable::SLOW)
						$this->gained(AchievementId::TIME_MODE_SCHOLAR_SLOW);
					elseif ($categoryId == TimeModeCategoryTable::FAST)
						$this->gained(AchievementId::TIME_MODE_SCHOLAR_FAST);
					elseif ($categoryId == TimeModeCategoryTable::BLITZ)
						$this->gained(AchievementId::TIME_MODE_SCHOLAR_BLITZ);

				}
				elseif ($rankId == TimeModeRankTable::RANK_3K)
				{
					if ($categoryId == TimeModeCategoryTable::SLOW)
						$this->gained(AchievementId::TIME_MODE_LABOURER_SLOW);
					elseif ($categoryId == TimeModeCategoryTable::FAST)
						$this->gained(AchievementId::TIME_MODE_LABOURER_FAST);
					elseif ($categoryId == TimeModeCategoryTable::BLITZ)
						$this->gained(AchievementId::TIME_MODE_LABOURER_BLITZ);
				}
				elseif ($rankId == TimeModeRankTable::RANK_2K)
				{
					if ($categoryId == TimeModeCategoryTable::SLOW)
						$this->gained(AchievementId::TIME_MODE_ADEPT_SLOW);
					elseif ($categoryId == TimeModeCategoryTable::FAST)
						$this->gained(AchievementId::TIME_MODE_ADEPT_FAST);
					elseif ($categoryId == TimeModeCategoryTable::BLITZ)
						$this->gained(AchievementId::TIME_MODE_ADEPT_BLITZ);
				}
				elseif ($rankId == TimeModeRankTable::RANK_1K)
				{
					if ($categoryId == TimeModeCategoryTable::SLOW)
						$this->gained(AchievementId::TIME_MODE_EXPERT_SLOW);
					elseif ($categoryId == TimeModeCategoryTable::FAST)
						$this->gained(AchievementId::TIME_MODE_EXPERT_FAST);
					elseif ($categoryId == TimeModeCategoryTable::BLITZ)
						$this->gained(AchievementId::TIME_MODE_EXPERT_BLITZ);
				}
				elseif ($rankId == TimeModeRankTable::RANK_1D)
					if ($categoryId == TimeModeCategoryTable::SLOW)
						$this->gained(AchievementId::TIME_MODE_MASTER_SLOW);
					elseif ($categoryId == TimeModeCategoryTable::FAST)
						$this->gained(AchievementId::TIME_MODE_MASTER_FAST);
					elseif ($categoryId == TimeModeCategoryTable::BLITZ)
						$this->gained(AchievementId::TIME_MODE_MASTER_BLITZ);

			// Precision achievements based on points and rank
			$points = isset($timeModeSession['points']) ? $timeModeSession['points'] : 0;
			if ($points >= 850 && $rankId >= TimeModeRankTable::RANK_4K)
				$this->gained(AchievementId::TIME_MODE_PRECISION_IV);
			if ($points >= 875 && $rankId >= TimeModeRankTable::RANK_6K)
				$this->gained(AchievementId::TIME_MODE_PRECISION_III);
			if ($points >= 900 && $rankId >= TimeModeRankTable::RANK_8K)
				$this->gained(AchievementId::TIME_MODE_PRECISION_II);
			if ($points >= 950 && $rankId >= TimeModeRankTable::RANK_10K)
				$this->gained(AchievementId::TIME_MODE_PRECISION_I);
		}
		return $this;
	}

	public function checkRatingAchievements(): AchievementChecker
	{
		$rating = Auth::getUser()['rating'];
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('6k'))
			$this->gained(AchievementId::RATING_6_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('5k'))
			$this->gained(AchievementId::RATING_5_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('4k'))
			$this->gained(AchievementId::RATING_4_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('3k'))
			$this->gained(AchievementId::RATING_3_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('2k'))
			$this->gained(AchievementId::RATING_2_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('1k'))
			$this->gained(AchievementId::RATING_1_KYU);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('1d'))
			$this->gained(AchievementId::RATING_1_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('2d'))
			$this->gained(AchievementId::RATING_2_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('3d'))
			$this->gained(AchievementId::RATING_3_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('4d'))
			$this->gained(AchievementId::RATING_4_DAN);
		if ($rating >= Rating::getRankMinimalRatingFromReadableRank('5d'))
			$this->gained(AchievementId::RATING_5_DAN);
		return $this;
	}

	public function checkLevelAchievements(): AchievementChecker
	{
		$userLevel = Auth::getUser()['level'];
		if ($userLevel >= 10)
			$this->gained(AchievementId::LEVEL_UP);
		if ($userLevel >= 20)
			$this->gained(AchievementId::FIRST_HERO_POWER);
		if ($userLevel >= 30)
			$this->gained(AchievementId::UPGRADED_INTUITION);
		if ($userLevel >= 40)
			$this->gained(AchievementId::MORE_POWER);
		if ($userLevel >= 50)
			$this->gained(AchievementId::HALF_WAY_TO_TOP);
		if ($userLevel >= 60)
			$this->gained(AchievementId::CONGRATS_MORE_PROBLEMS);
		if ($userLevel >= 70)
			$this->gained(AchievementId::NICE_LEVEL);
		if ($userLevel >= 80)
			$this->gained(AchievementId::DID_LOT_OF_TSUMEGO);
		if ($userLevel >= 90)
			$this->gained(AchievementId::STILL_DOING_TSUMEGO);
		if ($userLevel >= 100)
			$this->gained(AchievementId::THE_TOP);
		return $this;
	}

	public function checkSetCompletedAchievements(): AchievementChecker
	{
		$completedSets = AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_COMPLETED_SETS);
		if ($completedSets === null)
			return $this;
		if ($completedSets >= AchievementTable::COMPLETE_SETS_I_SETS_COUNT)
			$this->gained(AchievementId::COMPLETE_SETS_I);
		if ($completedSets >= AchievementTable::COMPLETE_SETS_II_SETS_COUNT)
			$this->gained(AchievementId::COMPLETE_SETS_II);
		if ($completedSets >= AchievementTable::COMPLETE_SETS_III_SETS_COUNT)
			$this->gained(AchievementId::COMPLETE_SETS_III);
		if ($completedSets >= AchievementTable::COMPLETE_SETS_IV_SETS_COUNT)
			$this->gained(AchievementId::COMPLETE_SETS_IV);
		if ($completedSets >= AchievementTable::COMPLETE_SETS_V_SETS_COUNT)
			$this->gained(AchievementId::COMPLETE_SETS_V);
		if ($completedSets >= AchievementTable::COMPLETE_SETS_VI_SETS_COUNT)
			$this->gained(AchievementId::COMPLETE_SETS_VI);
		return $this;
	}

	public function setAchievementSpecial(?string $s = null): AchievementChecker
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
			$uts = $tsIds ? Util::query('SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ? AND tsumego_id IN ('
				. implode(',', array_fill(0, count($tsIds), '?')) . ')', [Auth::getUserID(), ...$tsIds]) : [];
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++)
				for ($k = 0; $k < $tsCount; $k++)
					if ($uts[$j]['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['status'] == 'S'
					|| $uts[$j]['status'] == 'W' || $uts[$j]['status'] == 'C'))
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
			$uts = $tsIds ? Util::query('SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ? AND tsumego_id IN ('
				. implode(',', array_fill(0, count($tsIds), '?')) . ')', [Auth::getUserID(), ...$tsIds]) : [];
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++)
				for ($k = 0; $k < $tsCount; $k++)
					if ($uts[$j]['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['status'] == 'S'
					|| $uts[$j]['status'] == 'W' || $uts[$j]['status'] == 'C'))
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
			$uts = $tsIds ? Util::query('SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ? AND tsumego_id IN ('
				. implode(',', array_fill(0, count($tsIds), '?')) . ')', [Auth::getUserID(), ...$tsIds]) : [];
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++)
				for ($k = 0; $k < $tsCount; $k++)
					if ($uts[$j]['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['status'] == 'S'
					|| $uts[$j]['status'] == 'W' || $uts[$j]['status'] == 'C'))
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
			$uts = $tsIds ? Util::query('SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ? AND tsumego_id IN ('
				. implode(',', array_fill(0, count($tsIds), '?')) . ')', [Auth::getUserID(), ...$tsIds]) : [];
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++)
				for ($k = 0; $k < $tsCount; $k++)
					if ($uts[$j]['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['status'] == 'S'
					|| $uts[$j]['status'] == 'W' || $uts[$j]['status'] == 'C'))
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
			$uts = $tsIds ? Util::query('SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ? AND tsumego_id IN ('
				. implode(',', array_fill(0, count($tsIds), '?')) . ')', [Auth::getUserID(), ...$tsIds]) : [];
			$counter = 0;
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++)
				for ($k = 0; $k < $tsCount; $k++)
					if ($uts[$j]['tsumego_id'] == $ts[$k]['Tsumego']['id'] && ($uts[$j]['status'] == 'S'
					|| $uts[$j]['status'] == 'W' || $uts[$j]['status'] == 'C'))
						$counter++;
			if ($counter == count($ts))
				$completed = $s;
		}

		if ($completed == 'cc1')
			$this->gained(AchievementId::LIFE_DEATH_ELEMENTARY);
		if ($completed == 'cc2')
			$this->gained(AchievementId::LIFE_DEATH_INTERMEDIATE);
		if ($completed == 'cc3')
			$this->gained(AchievementId::LIFE_DEATH_ADVANCED);
		if ($completed == '1000w1')
			$this->gained(AchievementId::WEIQI_1000_FIRST_HALF);
		if ($completed == '1000w2')
			$this->gained(AchievementId::WEIQI_1000_SECOND_HALF);
		return $this;
	}

	public function checkSetAchievements(int $sid = 0, float|int $setRating = 0): AchievementChecker
	{
		$accuracy = AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_ACCURACY, $sid);
		if ($accuracy === null)
			return $this;
		if (TsumegoUtil::countTsumegosFromSet($sid) < 100)
			return $this;
		$time = AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_TIME, $sid);

		if ($setRating < 1300)
		{
			if ($accuracy >= 75)
				$this->gained(AchievementId::ACCURACY_I);
			if  ($accuracy >= 85)
				$this->gained(AchievementId::ACCURACY_II);
			if ($accuracy >= 95)
				$this->gained(AchievementId::ACCURACY_III);
			if ($time < 15)
				$this->gained(AchievementId::SPEED_I);
			if ($time < 10)
				$this->gained(AchievementId::SPEED_II);
			if ($time < 5)
				$this->gained(AchievementId::SPEED_III);
		}
		elseif ($setRating >= 1300 && $setRating < 1500)
		{
			if ($accuracy >= 75)
				$this->gained(AchievementId::ACCURACY_IV);
			if ($accuracy >= 85)
				$this->gained(AchievementId::ACCURACY_V);
			if ($accuracy >= 95)
				$this->gained(AchievementId::ACCURACY_VI);
			if ($time < 18)
				$this->gained(AchievementId::SPEED_IV);
			if ($time < 13)
				$this->gained(AchievementId::SPEED_V);
			if ($time < 8)
				$this->gained(AchievementId::SPEED_VI);
		}
		elseif ($setRating >= 1500 && $setRating < 1700)
		{
			if ($accuracy >= 75)
				$this->gained(AchievementId::ACCURACY_VII);
			if ($accuracy >= 85)
				$this->gained(AchievementId::ACCURACY_VIII);
			if ($accuracy >= 95)
				$this->gained(AchievementId::ACCURACY_IX);
			if ($time < 30)
				$this->gained(AchievementId::SPEED_VII);
			if ($time < 20)
				$this->gained(AchievementId::SPEED_VIII);
			if ($time < 10)
				$this->gained(AchievementId::SPEED_IX);
		}
		else
		{
			if ($accuracy >= 75)
				$this->gained(AchievementId::ACCURACY_X);
			if ($accuracy >= 85)
				$this->gained(AchievementId::ACCURACY_XI);
			if ($accuracy >= 95)
				$this->gained(AchievementId::ACCURACY_XII);
			if ($time < 30)
				$this->gained(AchievementId::SPEED_X);
			if ($time < 20)
				$this->gained(AchievementId::SPEED_XI);
			if ($time < 10)
				$this->gained(AchievementId::SPEED_XII);
		}

		if ($accuracy >= 100)
		{
			// Repeatable: one perfect collection of 100+ problems per count. The first
			// one pays out like any other achievement, further ones only raise the count.
			$this->gainedTimes(AchievementId::SUPERIOR_ACCURACY, $this->countPerfectCollections());
		}
		return $this;
	}

	//How many public collections the player finished at 100% accuracy, on a set of at
	//least the problems a collection achievement asks for. One query instead of loading
	//every set's problems.
	private function countPerfectCollections(): int
	{
		$rows = Util::query(
			'SELECT COUNT(*) AS total FROM achievement_condition ac '
			. 'WHERE ac.user_id = ? AND ac.achievement_condition_category_id = ? AND ac.value >= ? '
			. 'AND ac.set_id IN (' . SetConnection::publicCollectionsWithMinimumProblemsSql() . ')',
			[Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_ACCURACY, 100]
		);

		return (int) $rows[0]['total'];
	}

	public function checkFavoritesAchievement(int $setId): AchievementChecker
	{
		if ($this->unlocked(AchievementId::FAVORITES))
			return $this;

		$user = ClassRegistry::init('User')->findById(Auth::getUserID());
		if (!$user || empty($user['User']['default_set_id']) || $setId !== (int) $user['User']['default_set_id'])
			return $this;

		$count = ClassRegistry::init('SetConnection')->find('count', ['conditions' => ['set_id' => $setId]]);
		if ($count > 0)
			$this->gained(AchievementId::FAVORITES);

		return $this;
	}

	//Run the standard solve/progress-dependent achievement checks.
	//This is the canonical set that runs after a solve (PlayResultProcessor) and
	//on each page load (AppController::checkAchievements). Add new solve- or
	//progress-dependent checks here so both call sites stay in sync.
	//(checkTimeModeAchievements is intentionally separate - it runs on the time
	//mode result page via TimeModeController.)
	public function checkStandardAchievements(): AchievementChecker
	{
		$this->checkLevelAchievements();
		$this->checkProblemNumberAchievements();
		$this->checkRatingAchievements();
		$this->checkDanSolveAchievements();
		$this->checkNoErrorAchievements();
		$this->checkSetCompletedAchievements();
		return $this;
	}

	public function checkAll(): void
	{
		$this->checkLevelAchievements();
		$this->checkProblemNumberAchievements();
		$this->checkRatingAchievements();
		$this->checkDanSolveAchievements();
		$this->checkNoErrorAchievements();
		$this->checkSetCompletedAchievements();
		$this->checkTimeModeAchievements();
	}

	public function finalize(): AchievementChecker
	{
		if (empty($this->updated))
			return $this;

		$xpBonus = 0;
		foreach ($this->updated as $achievement)
			$xpBonus += $achievement->xp;
		if ($xpBonus == 0)
			return $this;

		$user = &Auth::getUser();
		Level::addXP($user, $xpBonus);
		Auth::saveUserFields([
			'xp' => $user['xp'],
			'level' => $user['level'],
		]);
		return $this;
	}

	private array $existingStatuses = [];
	/** @var Achievement[] the achievements unlocked by this check, for the popups */
	public array $updated = [];
}

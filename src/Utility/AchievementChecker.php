<?php

namespace App\Utility;

use App\Model\Entity\Set;
use App\Model\Entity\Achievement;
use App\Model\Table\AchievementConditionTable;
use App\Model\Table\AchievementTable;
use App\Model\Table\ProgressDeletionTable;
use App\Model\Table\SetTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeRankTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Model\Table\TsumegoStatusTable;
use Cake\ORM\Locator\LocatorAwareTrait;
use App\Model\Table\SetConnectionTable;

/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
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

	//How far a player got toward a solved, level or rating achievement, from their user row: the value
	//its check compares and the threshold that unlocks it. Null for the other achievements.
	/** @param UserRow $user */
	public static function userRowProgress(int $achievementID, array $user): ?array
	{
		$achievement = AchievementId::tryFrom($achievementID);
		if (($threshold = array_search($achievement, self::PROBLEMS_SOLVED, true)) !== false)
			return ['value' => self::solved($user), 'goal' => $threshold];
		if (($threshold = array_search($achievement, self::LEVEL, true)) !== false)
			return ['value' => self::level($user), 'goal' => $threshold];
		//The rating is fractional: shown rounded down, as the check unlocks it only once the rating reaches the threshold
		if (($rank = array_search($achievement, self::RATING, true)) !== false)
			return ['value' => (int) floor(self::rating($user)), 'goal' => (int) ceil(Rating::getRankMinimalRatingFromReadableRank($rank))];
		return null;
	}

	//The values of the user row the solved, level and rating achievements unlock by
	/** @param UserRow $user */
	private static function solved(array $user): int
	{
		return (int) ($user['solved'] ?? 0);
	}

	/** @param UserRow $user */
	private static function level(array $user): int
	{
		return (int) ($user['level'] ?? 0);
	}

	/** @param UserRow $user */
	private static function rating(array $user): float
	{
		return (float) ($user['rating'] ?? 0);
	}

	public function gained(AchievementId $achievementID): void
	{
		if ($this->unlocked($achievementID))
			return;
		$achievementStatusTable = $this->fetchTable('AchievementStatus');
		$achievementStatusTable->saveOrFail($achievementStatusTable->newEntity([
			'achievement_id' => $achievementID->value,
			'user_id' => Auth::getUserID(),
		]));
		// unlocked from now on, so a second reason to gain it in the same check does not insert it again
		$this->existingStatuses[$achievementID->value] = true;

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
		// only the ids, this runs on every page of a signed-in player
		foreach (Util::query('SELECT achievement_id FROM achievement_status WHERE user_id = ?', [Auth::getUserID()]) as $row)
			$this->existingStatuses[(int) $row['achievement_id']] = true;
	}

	//The value of the player's condition of a category that is not per set, null when the player has
	//none or it is NULL, like AchievementConditionTable::value(). They are read together, once per
	//check, since the checks of every page ask for several of them.
	private function condition(int $categoryID): ?float
	{
		if ($this->conditions === null)
		{
			$this->conditions = [];
			foreach (Util::query(
				'SELECT achievement_condition_category_id AS category_id, value FROM achievement_condition WHERE user_id = ? AND set_id IS NULL',
				[Auth::getUserID()]) as $row)
					$this->conditions[(int) $row['category_id']] = $row['value'] === null ? null : (float) $row['value'];
		}
		return $this->conditions[$categoryID] ?? null;
	}

	public function unlocked(AchievementId $achievementID): bool
	{
		return isset($this->existingStatuses[$achievementID->value]);
	}

	public function checkProblemNumberAchievements(): AchievementChecker
	{
		$this->gainedFrom(self::solved(Auth::getUser()), self::PROBLEMS_SOLVED);
		if ($this->condition(AchievementConditionTable::CATEGORY_USER_OF_THE_DAY) !== null)
			$this->gained(AchievementId::USER_OF_THE_DAY);
		return $this;
	}

	public function checkDanSolveAchievements(): void
	{
		$condition = fn(int $category) => $this->condition($category) ?? 0;

		foreach (AchievementConditionTable::DAN_SOLVE_CATEGORIES as $rank => $category)
			if ($condition($category) > 0)
				$this->gained(self::DAN_SOLVE[$rank]);
		foreach (AchievementConditionTable::DAN_SOLVE_CATEGORIES as $rank => $category)
			if ($condition($category) >= AchievementTable::SOLVE_10_COUNT)
				$this->gained(self::DAN_SOLVE_10[$rank]);

		foreach (self::GEMS as $category => $achievementID)
			if ($condition($category) == 1)
				$this->gained($achievementID);
		if ($this->unlocked(AchievementId::EMERALD)
			&& $this->unlocked(AchievementId::SAPPHIRE)
			&& $this->unlocked(AchievementId::RUBY))
				$this->gained(AchievementId::DIAMOND);

		if ($condition(AchievementConditionTable::CATEGORY_SPRINT) >= AchievementTable::SPRINT_COUNT)
			$this->gained(AchievementId::SPRINT);
		if ($condition(AchievementConditionTable::CATEGORY_GOLDEN) >= AchievementTable::GOLD_DIGGER_COUNT)
			$this->gained(AchievementId::GOLD_DIGGER);
		if ($condition(AchievementConditionTable::CATEGORY_POTION) >= HeroPowers::$BAD_POTION_THRESHOLD)
			$this->gained(AchievementId::BAD_POTION);
	}

	public function checkNoErrorAchievements(): void
	{
		$this->gainedFrom(
			$this->condition(AchievementConditionTable::CATEGORY_NO_ERROR_STREAK) ?? 0,
			self::NO_ERROR_STREAK);
	}

	public function checkTimeModeAchievements(): AchievementChecker
	{
		// one session per rank and category, the best one, and only the passed ones
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
			$rankID = (int) $timeModeSession['time_mode_rank_id'];
			if ($achievementID = self::TIME_MODE_PASSED[$rankID][(int) $timeModeSession['time_mode_category_id']] ?? null)
				$this->gained($achievementID);

			foreach (self::TIME_MODE_PRECISION as [$points, $minimalRankID, $achievementID])
				if ($timeModeSession['points'] >= $points && $rankID >= $minimalRankID)
					$this->gained($achievementID);
		}
		return $this;
	}

	public function checkRatingAchievements(): AchievementChecker
	{
		$rating = self::rating(Auth::getUser());
		foreach (self::RATING as $rank => $achievementID)
			if ($rating >= Rating::getRankMinimalRatingFromReadableRank($rank))
				$this->gained($achievementID);
		return $this;
	}

	public function checkLevelAchievements(): AchievementChecker
	{
		$this->gainedFrom(self::level(Auth::getUser()), self::LEVEL);
		return $this;
	}

	public function checkSetCompletedAchievements(): AchievementChecker
	{
		$completedSets = $this->condition(AchievementConditionTable::CATEGORY_COMPLETED_SETS);
		if ($completedSets !== null)
			$this->gainedFrom($completedSets, self::COMPLETED_SETS);
		return $this;
	}

	//The daily streak achievements, by the longest streak the player reached (DailyStreak), so a freeze
	//keeps counting towards them and a broken streak keeps them. It runs with the other standard checks:
	//after the result that counts a day, and on page loads, which gives them to a streak reached before
	//they existed.
	public function checkDailyStreakAchievements(): AchievementChecker
	{
		$user = Auth::getUser();
		$this->gainedFrom(max((int) ($user['streak_best'] ?? 0), (int) ($user['streak_length'] ?? 0)), self::DAILY_STREAK);
		return $this;
	}

	//Unlocks every achievement of $achievementByThreshold whose threshold $value reached, the lowest first.
	/** @param array<int, AchievementId> $achievementByThreshold */
	private function gainedFrom(float|int $value, array $achievementByThreshold): void
	{
		foreach ($achievementByThreshold as $threshold => $achievementID)
			if ($value >= $threshold)
				$this->gained($achievementID);
	}

	//Unlocks the completion achievement of the given collection (set.completion_achievement_id) when
	//the player has solved every problem, not in the trash, of the collections not in the trash that
	//share it. It is asked each time the player looks at a finished page of one of them, so once
	//unlocked nothing is counted again, and the database counts the problems still unsolved instead
	//of loading them all.
	public function checkCompletionAchievement(Set $set): AchievementChecker
	{
		if ($set->completion_achievement_id === null)
			return $this;
		$achievementID = AchievementId::from($set->completion_achievement_id);
		if ($this->unlocked($achievementID))
			return $this;
		$unsolved = Util::query('SELECT COUNT(*) AS unsolved FROM `set`'
			. ' JOIN set_connection sc ON sc.set_id = `set`.id'
			. ' JOIN tsumego ON tsumego.id = sc.tsumego_id AND tsumego.deleted IS NULL'
			. ' LEFT JOIN tsumego_status ON tsumego_status.user_id = ? AND tsumego_status.tsumego_id = sc.tsumego_id'
			. ' AND ' . TsumegoStatusTable::solvedCondition('tsumego_status.status')
			. ' WHERE `set`.completion_achievement_id = ? AND `set`.deleted IS NULL AND tsumego_status.id IS NULL',
			[Auth::getUserID(), $achievementID->value])[0]['unsolved'];
		if ((int) $unsolved === 0)
			$this->gained($achievementID);
		return $this;
	}

	//The achievements of the player's bests on a part of a collection (the parts a reset clears,
	//ProgressDeletionTable::$PARTITION_SIZE): a part of at least SetConnectionTable::ACHIEVEMENT_MINIMUM_PROBLEMS problems
	//counts as a collection does.
	//$setRating: The average rating of the part's problems
	public function checkSetAchievements(int $sid = 0, float|int $setRating = 0, int $partition = 0): AchievementChecker
	{
		$accuracy = AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_ACCURACY, $sid, $partition);
		if ($accuracy === null)
			return $this;
		if (ProgressDeletionTable::partSize(TsumegoUtil::countTsumegosFromSet($sid), $partition) < SetConnectionTable::ACHIEVEMENT_MINIMUM_PROBLEMS)
			return $this;
		$time = AchievementConditionTable::value(Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_TIME, $sid, $partition);

		$band = array_find(self::SET_BANDS, fn(array $band) => $band['below'] === null || $setRating < Rating::getRankMinimalRatingFromReadableRank($band['below']));
		foreach ($band['accuracy'] as $minimalAccuracy => $achievementID)
			if ($accuracy >= $minimalAccuracy)
				$this->gained($achievementID);
		foreach ($band['seconds'] as $secondsBelow => $achievementID)
			if ($time < $secondsBelow)
				$this->gained($achievementID);

		if ($accuracy >= 100)
		{
			// Repeatable: one perfect collection of 100+ problems per count. The first
			// one pays out like any other achievement, further ones only raise the count.
			$this->gainedTimes(AchievementId::SUPERIOR_ACCURACY, $this->countPerfectCollections());
		}
		return $this;
	}

	//How many parts of the site's collections the player finished at 100% accuracy, of at least the problems a
	//collection achievement asks for
	private function countPerfectCollections(): int
	{
		$rows = Util::query(
			'SELECT ac.set_partition_or_zero AS part, COUNT(*) AS problems FROM achievement_condition ac '
			. 'JOIN `set` st ON st.id = ac.set_id AND ' . SetTable::officialCondition('st') . ' '
			. 'JOIN set_connection sc ON sc.set_id = ac.set_id '
			. 'JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL '
			. 'WHERE ac.user_id = ? AND ac.achievement_condition_category_id = ? AND ac.value >= ? '
			. 'GROUP BY ac.id, ac.set_partition_or_zero',
			[Auth::getUserID(), AchievementConditionTable::CATEGORY_SET_ACCURACY, 100]
		);
		return count(array_filter($rows, fn(array $row) => ProgressDeletionTable::partSize((int) $row['problems'], (int) $row['part']) >= SetConnectionTable::ACHIEVEMENT_MINIMUM_PROBLEMS));
	}

	public function checkFavoritesAchievement(int $setId): AchievementChecker
	{
		if ($this->unlocked(AchievementId::FAVORITES))
			return $this;

		$defaultSetId = $this->fetchTable('User')->find()->select(['default_set_id'])->where(['id' => Auth::getUserID()])->first()?->default_set_id;
		if (empty($defaultSetId) || $setId !== $defaultSetId)
			return $this;

		if ($this->fetchTable('SetConnection')->exists(['set_id' => $setId]))
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
		$this->checkDailyStreakAchievements();
		return $this;
	}

	public function checkAll(): void
	{
		$this->checkStandardAchievements()->checkTimeModeAchievements();
	}

	//Grants the XP of the achievements gained. That XP can raise the level past the threshold of a level
	//achievement, checked before it, so the level ones are checked again, and their XP granted, until the
	//level stays.
	public function finalize(): AchievementChecker
	{
		$granted = 0;
		while ($granted < count($this->updated))
		{
			$xpBonus = 0;
			foreach (array_slice($this->updated, $granted) as $achievement)
				$xpBonus += $achievement->xp;
			$granted = count($this->updated);
			if ($xpBonus == 0)
				break;

			$user = &Auth::getUser();
			$level = $user['level'];
			Level::addXP($user, $xpBonus);
			Auth::saveUserFields([
				'xp' => $user['xp'],
				'level' => $user['level'],
			]);
			if ($user['level'] != $level)
				$this->checkLevelAchievements();
		}
		return $this;
	}

	private const array PROBLEMS_SOLVED = [
		1000 => AchievementId::PROBLEMS_1000,
		2000 => AchievementId::PROBLEMS_2000,
		3000 => AchievementId::PROBLEMS_3000,
		4000 => AchievementId::PROBLEMS_4000,
		5000 => AchievementId::PROBLEMS_5000,
		6000 => AchievementId::PROBLEMS_6000,
		7000 => AchievementId::PROBLEMS_7000,
		8000 => AchievementId::PROBLEMS_8000,
		9000 => AchievementId::PROBLEMS_9000,
		10000 => AchievementId::PROBLEMS_10000];

	private const array LEVEL = [
		10 => AchievementId::LEVEL_UP,
		20 => AchievementId::FIRST_HERO_POWER,
		30 => AchievementId::UPGRADED_INTUITION,
		40 => AchievementId::MORE_POWER,
		50 => AchievementId::HALF_WAY_TO_TOP,
		60 => AchievementId::CONGRATS_MORE_PROBLEMS,
		70 => AchievementId::NICE_LEVEL,
		80 => AchievementId::DID_LOT_OF_TSUMEGO,
		90 => AchievementId::STILL_DOING_TSUMEGO,
		100 => AchievementId::THE_TOP];

	//The rank whose minimal rating unlocks it
	private const array RATING = [
		'6k' => AchievementId::RATING_6_KYU,
		'5k' => AchievementId::RATING_5_KYU,
		'4k' => AchievementId::RATING_4_KYU,
		'3k' => AchievementId::RATING_3_KYU,
		'2k' => AchievementId::RATING_2_KYU,
		'1k' => AchievementId::RATING_1_KYU,
		'1d' => AchievementId::RATING_1_DAN,
		'2d' => AchievementId::RATING_2_DAN,
		'3d' => AchievementId::RATING_3_DAN,
		'4d' => AchievementId::RATING_4_DAN,
		'5d' => AchievementId::RATING_5_DAN];

	private const array NO_ERROR_STREAK = [
		AchievementTable::NO_ERROR_STREAK_I_STREAK_COUNT => AchievementId::NO_ERROR_STREAK_I,
		AchievementTable::NO_ERROR_STREAK_II_STREAK_COUNT => AchievementId::NO_ERROR_STREAK_II,
		AchievementTable::NO_ERROR_STREAK_III_STREAK_COUNT => AchievementId::NO_ERROR_STREAK_III,
		AchievementTable::NO_ERROR_STREAK_IV_STREAK_COUNT => AchievementId::NO_ERROR_STREAK_IV,
		AchievementTable::NO_ERROR_STREAK_V_STREAK_COUNT => AchievementId::NO_ERROR_STREAK_V,
		AchievementTable::NO_ERROR_STREAK_VI_STREAK_COUNT => AchievementId::NO_ERROR_STREAK_VI];

	private const array COMPLETED_SETS = [
		AchievementTable::COMPLETE_SETS_I_SETS_COUNT => AchievementId::COMPLETE_SETS_I,
		AchievementTable::COMPLETE_SETS_II_SETS_COUNT => AchievementId::COMPLETE_SETS_II,
		AchievementTable::COMPLETE_SETS_III_SETS_COUNT => AchievementId::COMPLETE_SETS_III,
		AchievementTable::COMPLETE_SETS_IV_SETS_COUNT => AchievementId::COMPLETE_SETS_IV,
		AchievementTable::COMPLETE_SETS_V_SETS_COUNT => AchievementId::COMPLETE_SETS_V,
		AchievementTable::COMPLETE_SETS_VI_SETS_COUNT => AchievementId::COMPLETE_SETS_VI];

	private const array DAILY_STREAK = [
		AchievementTable::DAILY_STREAK_WEEK_DAYS => AchievementId::DAILY_STREAK_WEEK,
		AchievementTable::DAILY_STREAK_TWO_WEEKS_DAYS => AchievementId::DAILY_STREAK_TWO_WEEKS,
		AchievementTable::DAILY_STREAK_MONTH_DAYS => AchievementId::DAILY_STREAK_MONTH,
		AchievementTable::DAILY_STREAK_HALF_YEAR_DAYS => AchievementId::DAILY_STREAK_HALF_YEAR,
		AchievementTable::DAILY_STREAK_YEAR_DAYS => AchievementId::DAILY_STREAK_YEAR];

	//By the keys of AchievementConditionTable::DAN_SOLVE_CATEGORIES: one problem of the rank solved, and ten
	private const array DAN_SOLVE = [
		'1d' => AchievementId::SOLVE_1D,
		'2d' => AchievementId::SOLVE_2D,
		'3d' => AchievementId::SOLVE_3D,
		'4d' => AchievementId::SOLVE_4D,
		'5d' => AchievementId::SOLVE_5D];
	private const array DAN_SOLVE_10 = [
		'1d' => AchievementId::SOLVE_10_1D,
		'2d' => AchievementId::SOLVE_10_2D,
		'3d' => AchievementId::SOLVE_10_3D,
		'4d' => AchievementId::SOLVE_10_4D,
		'5d' => AchievementId::SOLVE_10_5D];

	//By the achievement condition category of the gem
	private const array GEMS = [
		AchievementConditionTable::CATEGORY_EMERALD => AchievementId::EMERALD,
		AchievementConditionTable::CATEGORY_SAPPHIRE => AchievementId::SAPPHIRE,
		AchievementConditionTable::CATEGORY_RUBY => AchievementId::RUBY];

	//A passed run, by its time mode rank and category
	private const array TIME_MODE_PASSED = [
		TimeModeRankTable::RANK_5K => [
			TimeModeCategoryTable::SLOW => AchievementId::TIME_MODE_APPRENTICE_SLOW,
			TimeModeCategoryTable::FAST => AchievementId::TIME_MODE_APPRENTICE_FAST,
			TimeModeCategoryTable::BLITZ => AchievementId::TIME_MODE_APPRENTICE_BLITZ],
		TimeModeRankTable::RANK_4K => [
			TimeModeCategoryTable::SLOW => AchievementId::TIME_MODE_SCHOLAR_SLOW,
			TimeModeCategoryTable::FAST => AchievementId::TIME_MODE_SCHOLAR_FAST,
			TimeModeCategoryTable::BLITZ => AchievementId::TIME_MODE_SCHOLAR_BLITZ],
		TimeModeRankTable::RANK_3K => [
			TimeModeCategoryTable::SLOW => AchievementId::TIME_MODE_LABOURER_SLOW,
			TimeModeCategoryTable::FAST => AchievementId::TIME_MODE_LABOURER_FAST,
			TimeModeCategoryTable::BLITZ => AchievementId::TIME_MODE_LABOURER_BLITZ],
		TimeModeRankTable::RANK_2K => [
			TimeModeCategoryTable::SLOW => AchievementId::TIME_MODE_ADEPT_SLOW,
			TimeModeCategoryTable::FAST => AchievementId::TIME_MODE_ADEPT_FAST,
			TimeModeCategoryTable::BLITZ => AchievementId::TIME_MODE_ADEPT_BLITZ],
		TimeModeRankTable::RANK_1K => [
			TimeModeCategoryTable::SLOW => AchievementId::TIME_MODE_EXPERT_SLOW,
			TimeModeCategoryTable::FAST => AchievementId::TIME_MODE_EXPERT_FAST,
			TimeModeCategoryTable::BLITZ => AchievementId::TIME_MODE_EXPERT_BLITZ],
		TimeModeRankTable::RANK_1D => [
			TimeModeCategoryTable::SLOW => AchievementId::TIME_MODE_MASTER_SLOW,
			TimeModeCategoryTable::FAST => AchievementId::TIME_MODE_MASTER_FAST,
			TimeModeCategoryTable::BLITZ => AchievementId::TIME_MODE_MASTER_BLITZ]];

	//[the points of a passed run, the lowest time mode rank they count at, the achievement]
	private const array TIME_MODE_PRECISION = [
		[850, TimeModeRankTable::RANK_4K, AchievementId::TIME_MODE_PRECISION_IV],
		[875, TimeModeRankTable::RANK_6K, AchievementId::TIME_MODE_PRECISION_III],
		[900, TimeModeRankTable::RANK_8K, AchievementId::TIME_MODE_PRECISION_II],
		[950, TimeModeRankTable::RANK_10K, AchievementId::TIME_MODE_PRECISION_I]];

	//The accuracy and speed achievements of a finished collection of 100+ problems, by the
	//rating of the collection: below the first rating of the band's rank (the rank edges of Rating), the accuracy (%)
	//at least, and the average seconds per problem below.
	private const array SET_BANDS = [
		['below' => '10k',
			'accuracy' => [75 => AchievementId::ACCURACY_I, 85 => AchievementId::ACCURACY_II, 95 => AchievementId::ACCURACY_III],
			'seconds' => [15 => AchievementId::SPEED_I, 10 => AchievementId::SPEED_II, 5 => AchievementId::SPEED_III]],
		['below' => '7k',
			'accuracy' => [75 => AchievementId::ACCURACY_IV, 85 => AchievementId::ACCURACY_V, 95 => AchievementId::ACCURACY_VI],
			'seconds' => [18 => AchievementId::SPEED_IV, 13 => AchievementId::SPEED_V, 8 => AchievementId::SPEED_VI]],
		['below' => '4k',
			'accuracy' => [75 => AchievementId::ACCURACY_VII, 85 => AchievementId::ACCURACY_VIII, 95 => AchievementId::ACCURACY_IX],
			'seconds' => [30 => AchievementId::SPEED_VII, 20 => AchievementId::SPEED_VIII, 10 => AchievementId::SPEED_IX]],
		['below' => null,
			'accuracy' => [75 => AchievementId::ACCURACY_X, 85 => AchievementId::ACCURACY_XI, 95 => AchievementId::ACCURACY_XII],
			'seconds' => [30 => AchievementId::SPEED_X, 20 => AchievementId::SPEED_XI, 10 => AchievementId::SPEED_XII]]];

	private array $existingStatuses = [];
	/** @var array<int, ?float>|null by category, see condition() */
	private ?array $conditions = null;
	/** @var Achievement[] the achievements unlocked by this check, for the popups */
	public array $updated = [];
}

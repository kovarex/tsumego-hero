<?php

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use App\Model\Entity\Achievement;
use App\Model\Table\AchievementConditionTable;
use App\Model\Table\AchievementTable;
use App\Model\Table\UserTable;
use App\Utility\AchievementChecker;
use App\Utility\AchievementId;
use App\Utility\AchievementRarity;
use App\Utility\Auth;
use App\Utility\DailyStreak;
use App\Utility\Util;
use App\Attribute\HttpGet;
use App\Model\Table\ProgressDeletionTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\SetTable;

/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class AchievementsController extends AppController
{
	#[HttpGet]
	public function index(): void
	{
		$this->renderAchievementsPage(Auth::isLoggedIn() ? Auth::getUser() : null);
	}

	#[HttpGet]
	public function user(string $userId): void
	{
		$user = UserTable::row((int) $userId);
		if (!$user)
			throw new NotFoundException('User not found');
		$this->assertUserVisible($user);

		$this->renderAchievementsPage($user);
		$this->render('index');
	}

	//One row per achievement, the most common first (AchievementRarity). Without a viewed user (anonymous
	//visitor) every row is locked.
	/** @param UserRow|null $viewedUser */
	private function renderAchievementsPage(?array $viewedUser): void
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Achievements');

		$statusByAchievementId = [];
		if ($viewedUser)
		{
			$this->set('viewedUser', $viewedUser);
			foreach ($this->fetchTable('AchievementStatus')->find()->where(['user_id' => $viewedUser['id']])->all() as $status)
				$statusByAchievementId[$status->achievement_id] = $status;
		}

		$achievements = [];
		foreach ($this->fetchTable('Achievement')->find()->orderBy(['order' => 'ASC'])->all() as $achievement)
		{
			$status = $statusByAchievementId[$achievement->id] ?? null;
			$achievements[] = [
				'id' => $achievement->id,
				'url' => '/achievements/view/' . $achievement->id . ($viewedUser ? '/' . $viewedUser['id'] : ''),
				'name' => $achievement->name,
				'description' => $achievement->description,
				'image' => $achievement->image,
				'unlocked' => $status !== null,
				'percent' => AchievementRarity::percent($achievement->id, $status !== null),
				'unlocked_at' => $status?->created->format('Y-m-d H:i:s'),
				//Repeatable achievements are earned more than once: they show how often,
				//but still count as a single completed achievement.
				'earned_count' => $status ? $status->value : 0,
				'progress' => null,
				'progress_goal' => null,
				'progress_percent' => null,
			];
		}
		if ($viewedUser)
		{
			$this->fillProgress($achievements, $viewedUser);
			$this->fillDailyStreakProgress($achievements, $viewedUser);
		}

		// usort is stable, so equally common ones keep the order of the achievement table
		usort($achievements, fn(array $a, array $b) => $b['percent'] <=> $a['percent']);
		$this->set('achievements', $achievements);
		$this->set('unlockedCount', count(array_filter(array_column($achievements, 'unlocked'))));
	}

	//Fills progress, progress_goal and progress_percent of the locked achievements with a counter,
	//so their cards can show how far the user got: the solved, level and rating ones from the user
	//row, the others from their achievement conditions.
	/** @param UserRow $user */
	private function fillProgress(array &$achievements, array $user): void
	{
		$definitions = [];
		foreach ($achievements as $index => $achievement)
			if (!$achievement['unlocked'])
			{
				if ($userRowProgress = AchievementChecker::userRowProgress($achievement['id'], $user))
					self::setProgress($achievements[$index], $userRowProgress['value'], $userRowProgress['goal']);
				elseif ($definition = AchievementTable::progressDefinition($achievement['id']))
					$definitions[$index] = $definition;
			}
		if (!$definitions)
			return;

		$values = AchievementConditionTable::values((int) $user['id'], array_values(array_unique(array_column($definitions, 'category'))));
		foreach ($definitions as $index => $definition)
			self::setProgress($achievements[$index], (int) ($values[$definition['category']] ?? 0), $definition['goal']);
	}

	//The progress of an achievement card, of $value toward $goal, the bar full once the value reaches it
	private static function setProgress(array &$achievement, int $value, int $goal): void
	{
		$progress = max(0, min($value, $goal));
		$achievement['progress'] = $progress;
		$achievement['progress_goal'] = $goal;
		$achievement['progress_percent'] = (int) round($progress / $goal * 100);
	}

	//The locked daily streak achievements show the streak the player has now, of the days they ask in a row
	/** @param UserRow $user */
	private function fillDailyStreakProgress(array &$achievements, array $user): void
	{
		$length = DailyStreak::ofUser($user)->length;
		foreach ($achievements as $index => $achievement)
			if (!$achievement['unlocked'] && ($goal = AchievementTable::DAILY_STREAK_DAYS[$achievement['id']] ?? null))
				self::setProgress($achievements[$index], $length, $goal);
	}

	#[HttpGet]
	public function view(?int $id = null, ?int $userId = null): void
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Achievements');
		$achievement = $this->fetchTable('Achievement')->find()->where(['id' => $id])->first();
		if (!$achievement)
			throw new NotFoundException('Achievement not found');

		$viewedUser = null;
		if ($userId !== null)
		{
			$viewedUser = UserTable::row($userId);
			if (!$viewedUser)
				throw new NotFoundException('User not found');
			$this->assertUserVisible($viewedUser);
		}
		$statusUserId = $viewedUser ? (int) $viewedUser['id'] : (Auth::isLoggedIn() ? Auth::getUserID() : null);

		$achievementStatusTable = $this->fetchTable('AchievementStatus');
		$currentUserStatus = null;
		$completerConditions = [
			'achievement_id' => $id,
			'AchievementStatus.user_id NOT IN (SELECT id FROM user WHERE deleted IS NOT NULL)'];
		$completerCount = $achievementStatusTable->find()->where($completerConditions)->count();
		$completerUserIds = $achievementStatusTable->find()
			->where($completerConditions)
			->orderBy(['created' => 'DESC', 'AchievementStatus.id' => 'DESC'])
			->limit(10)
			->all()
			->extract('user_id')
			->toList();
		if ($statusUserId)
			$currentUserStatus = $achievementStatusTable->find()->where(['achievement_id' => $id, 'user_id' => $statusUserId])->first();
		$usersById = [];
		if ($completerUserIds)
			foreach ($this->fetchTable('User')->find()->where(['id IN' => $completerUserIds])->all() as $completer)
				$usersById[$completer->id] = UserTable::rowOf($completer);
		//The completers as users, null for one the users table no longer has
		$completers = array_map(fn(int $userId) => $usersById[$userId] ?? null, $completerUserIds);
		$userCount = $this->fetchTable('User')->find()->where(['deleted IS' => null])->count();
		$completionPercent = $userCount > 0 ? round($completerCount / $userCount * 100, 1) : 0;
		$rarity = AchievementTable::getRarityLabel($completionPercent);

		$progress = null;
		$progressGoal = null;
		if ($statusUserId)
		{
			$progressDefinition = AchievementTable::progressDefinition($achievement->id);
			if ($progressDefinition)
			{
				$progress = (int) AchievementConditionTable::value($statusUserId, $progressDefinition['category']);
				$progressGoal = $progressDefinition['goal'];
			}
			elseif ($progressGoal = AchievementTable::DAILY_STREAK_DAYS[$achievement->id] ?? null)
				$progress = DailyStreak::ofUser($viewedUser ?? Auth::getUser())->length;
			elseif ($userRowProgress = AchievementChecker::userRowProgress($achievement->id, $viewedUser ?? Auth::getUser()))
			{
				$progress = $userRowProgress['value'];
				$progressGoal = $userRowProgress['goal'];
			}
			if ($progressGoal !== null)
			{
				if ($currentUserStatus)
					$progress = $progressGoal;
				$progress = max(0, min($progress, $progressGoal));
			}
		}

		$this->set('achievement', $achievement);
		$this->set('viewedUser', $viewedUser);
		$this->set('collections', $this->fillEarningCollections($achievement, $statusUserId));
		$this->set('currentUserStatus', $currentUserStatus);
		$this->set('completers', $completers);
		$this->set('completerCount', $completerCount);
		$this->set('userCount', $userCount);
		$this->set('completionPercent', $completionPercent);
		$this->set('rarity', $rarity);
		$this->set('progress', $progress);
		$this->set('progressGoal', $progressGoal);
	}

	//The public collections that can earn a repeatable collection achievement, with the
	//best accuracy the player reached on each of them. The achievement page lists them so
	//a player can see which collections are still left to finish.
	private function fillEarningCollections(Achievement $achievement, ?int $userID): array
	{
		if ($achievement->id !== AchievementId::SUPERIOR_ACCURACY->value)
			return [];

		// the bests are per part of a collection (ProgressDeletionTable::$PARTITION_SIZE): each part of enough problems
		// is listed, numbered when the collection has more than one
		$rows = Util::query(
			'SELECT s.id, s.title, COUNT(*) AS problems FROM `set` s'
			. ' JOIN set_connection sc ON sc.set_id = s.id'
			. ' JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL'
			. ' WHERE ' . SetTable::officialCondition('s')
			. ' GROUP BY s.id HAVING problems >= ' . SetConnectionTable::ACHIEVEMENT_MINIMUM_PROBLEMS
			. ' ORDER BY ' . SetConnectionTable::displayOrderForSetSql('s')
		);

		$bestAccuracy = $userID ? AchievementConditionTable::bestAccuracies($userID, array_map(fn($row) => (int) $row['id'], $rows)) : [];

		$collections = [];
		foreach ($rows as $row)
		{
			$problems = (int) $row['problems'];
			$partCount = (int) ceil($problems / ProgressDeletionTable::$PARTITION_SIZE);
			for ($part = 0; $part < $partCount; $part++)
			{
				if (ProgressDeletionTable::partSize($problems, $part) < SetConnectionTable::ACHIEVEMENT_MINIMUM_PROBLEMS)
					continue;
				$accuracy = $bestAccuracy[(int) $row['id']][$part] ?? 0;
				$collections[] = [
					'id' => (int) $row['id'],
					'partition' => $part,
					'title' => $row['title'] . ($partCount > 1 ? ' #' . ($part + 1) : ''),
					'accuracy' => $accuracy,
					'earned' => $accuracy >= 100,
				];
			}
		}
		return $collections;
	}

}

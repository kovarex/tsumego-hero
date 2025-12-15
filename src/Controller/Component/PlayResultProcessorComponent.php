<?php

App::uses('TsumegoStatus', 'Model');
App::uses('SetConnection', 'Model');
App::uses('Rating', 'Utility');
App::uses('Util', 'Utility');
App::uses('Decoder', 'Utility');
App::uses('HeroPowers', 'Utility');
App::uses('TsumegoXPAndRating', 'Utility');
App::uses('Level', 'Utility');
App::uses('Progress', 'Utility');

class PlayResultProcessorComponent extends Component
{
	public $components = ['Session'];

	public function checkPreviousPlay($timeModeComponent): void
	{
		$this->checkAddFavorite();
		$this->checkRemoveFavorite();

		$previousTsumegoID = Util::clearNumericCookie('previousTsumegoID');
		if (!$previousTsumegoID)
			return;

		$previousTsumego = ClassRegistry::init('Tsumego')->findById($previousTsumegoID);
		if (!$previousTsumego)
			return;

		$result = $this->checkPreviousPlayAndGetResult($previousTsumego);

		$previousTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => [
				'tsumego_id' => (int) $previousTsumego['Tsumego']['id'],
				'user_id' => (int) Auth::getUserID(),
			],
		]);

		$this->updateTsumegoStatus($previousTsumego, $result, $previousTsumegoStatus);

		if (!isset($result['solved']))
			return;
		if (HeroPowers::getSprintRemainingSeconds() > 0)
			$result['xp-modifier'] = ($result['xp-modifier'] ?: 1) * Constants::$SPRINT_MULTIPLIER;

		$previousStatusValue = $previousTsumegoStatus ? $previousTsumegoStatus['TsumegoStatus']['status'] : 'N';

		// I need to save the original tsumego rating I calculated XP change for
		// this is to avoid that rating gets changed, and the XP change calculation would
		// be based on the changed rating, and would slightly differ from the promised change
		$originalTsumegoRating = $previousTsumego['Tsumego']['rating'];

		$this->processRatingChange($previousTsumego, $result, $previousStatusValue);
		$this->processDamage($result);
		$timeModeComponent->processPlayResult($previousTsumego, $result);
		$this->processXpChange($previousTsumego, $result, $previousStatusValue, $originalTsumegoRating);
		$this->updateTsumegoAttempt($previousTsumego, $result);
		$this->processErrorAchievement($result);
		$this->processUnsortedStuff($previousTsumego, $result);
	}

	public function checkPreviousPlayAndGetResult(&$previousTsumego): array
	{
		$result = [];
		if ($misplays = $this->checkMisplay())
		{
			$result['solved'] = false;
			$result['misplays'] = $misplays;
		}
		if (Decoder::decodeSuccess($previousTsumego['Tsumego']['id']))
			$result['solved'] = true;

		return $result;
	}

	private function getNewStatus($solved, $currentStatus, &$result)
	{
		if ($solved)
		{
			if ($currentStatus == 'W') // half xp state
			{
				$result['xp-modifier'] = ($result['xp-modifier'] ?: 1) * Constants::$SECOND_SOLVE_XP_MULTIPLIER;
				return 'C'; // double solved
			}
			if ($currentStatus == 'G')
			{
				$result['xp-modifier'] = ($result['xp-modifier'] ?: 1) * Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER;
				return 'S';
			}
			if ($currentStatus == 'V' || $currentStatus == 'N')
				return 'S';
			return $currentStatus; // failed can't be unfailed by solving, user has to wait until next day or rejuvenation
		}

		// not solved from now
		if ($currentStatus == 'V') // if it was just visited so far (so we don't overwrite solved)
		{if (Auth::getUser()['damage'] >= Util::getHealthBasedOnLevel(Auth::getUser()['level']))
			return 'F';  // only mark as failed when the user has no hearts left
			return $currentStatus;
		}
		if ($currentStatus == 'W')
		{
			if (Auth::getUser()['damage'] >= Util::getHealthBasedOnLevel(Auth::getUser()['level']))
				return 'X'; // only mark as 'stale failed' when the user has no hearts left
			return $currentStatus;
		}
		if ($currentStatus == 'G')
			return 'V'; // failed golden tsumego
		return $currentStatus;
	}

	private function updateTsumegoStatus(array $previousTsumego, array &$result, ?array $previousTsumegoStatus): void
	{
		if ($previousTsumegoStatus == null)
		{
			$previousTsumegoStatus['TsumegoStatus'] = [];
			$previousTsumegoStatus['TsumegoStatus']['user_id'] = Auth::getUserID();
			$previousTsumegoStatus['TsumegoStatus']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
			$previousTsumegoStatus['TsumegoStatus']['status'] = 'V';
		}
		$_COOKIE['previousTsumegoBuffer'] = $previousTsumegoStatus['TsumegoStatus']['status'];

		if (isset($result['solved']))
		{
			$newStatus = $this->getNewStatus($result['solved'], $previousTsumegoStatus['TsumegoStatus']['status'], $result);
			if (TsumegoUtil::isSolvedStatus($newStatus) && !TsumegoUtil::isSolvedStatus($previousTsumegoStatus['TsumegoStatus']['status']))
				Auth::getUser()['solved'] = Auth::getUser()['solved'] + 1;
			$previousTsumegoStatus['TsumegoStatus']['status'] = $newStatus;
		}
		$previousTsumegoStatus['TsumegoStatus']['created'] = date('Y-m-d H:i:s');
		ClassRegistry::init('TsumegoStatus')->save($previousTsumegoStatus);
	}

	private function checkAddFavorite(): void
	{
		if (!Auth::isLoggedIn())
			return;

		$tsumegoID = Util::clearCookie('add_favorite');
		if (empty($tsumegoID))
			return;

		$favorite = ClassRegistry::init('Favorite')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $tsumegoID]]);
		if ($favorite)
			return;

		$favorite = [];
		$favorite['user_id'] = Auth::getUserID();
		$favorite['tsumego_id'] = $tsumegoID;
		ClassRegistry::init('Favorite')->create();
		ClassRegistry::init('Favorite')->save($favorite);
	}

	private function checkRemoveFavorite(): void
	{
		if (!Auth::isLoggedIn())
			return;

		$tsumegoID = Util::clearCookie('remove_favorite');
		if (empty($tsumegoID))
			return;

		$favorite = ClassRegistry::init('Favorite')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $tsumegoID]]);
		if (!$favorite)
			return;
		ClassRegistry::init('Favorite')->delete($favorite['Favorite']['id']);
	}

	private function updateTsumegoAttempt(array $previousTsumego, array $result): void
	{
		if (Auth::isInTimeMode())
			return;
		$lastTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find(
			'first',
			['conditions'
				=> ['user_id' => Auth::getUserID(),
					'tsumego_id' => $previousTsumego['Tsumego']['id']],
				'order' => 'id DESC']
		);

		// only not solved ones are updated (misplays get accumulated)
		if (!$lastTsumegoAttempt || $lastTsumegoAttempt['TsumegoAttempt']['solved'])
		{
			$tsumegoAttempt = [];
			$tsumegoAttempt['TsumegoAttempt']['user_id'] = Auth::getUserID();
			$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = $previousTsumego['Tsumego']['id'];
			$tsumegoAttempt['TsumegoAttempt']['seconds'] = 0;
			$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
			$tsumegoAttempt['TsumegoAttempt']['tsumego_rating'] = $previousTsumego['Tsumego']['rating'];
			$tsumegoAttempt['TsumegoAttempt']['misplays'] = 0;
		}
		else
			$tsumegoAttempt = $lastTsumegoAttempt;

		$tsumegoAttempt['TsumegoAttempt']['user_rating'] = Auth::getUser()['rating'];
		$tsumegoAttempt['TsumegoAttempt']['gain'] = $result['xp-gained'] ?: 0;
		$tsumegoAttempt['TsumegoAttempt']['seconds'] += Decoder::decodeSeconds($previousTsumego);
		$tsumegoAttempt['TsumegoAttempt']['solved'] = $result['solved'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_rating'] = $previousTsumego['Tsumego']['rating'];
		$tsumegoAttempt['TsumegoAttempt']['misplays'] += $result['misplays'] ?: 0;
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
	}

	private static function processRatingChangeStep(float &$userRating, float &$tsumegoRating, bool $isWin): void
	{
		$userRatingDelta = Rating::calculateRatingChange($userRating, $tsumegoRating, $isWin ? 1 : 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER);
		$tsumegoRatingDelta = Rating::calculateRatingChange($tsumegoRating, $userRating, $isWin ? 0 : 1, Constants::$TSUMEGO_RATING_CALCULATION_MODIFIER);
		$userRating += $userRatingDelta;
		$tsumegoRating += $tsumegoRatingDelta;
	}

	private function processRatingChange(array $previousTsumego, array $result, string $previousTsumegoStatus): void
	{
		if (!Auth::ratingisGainedInCurrentMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;
		$userRating = (float) Auth::getUser()['rating'];
		$tsumegoRating = (float) $previousTsumego['Tsumego']['rating'];

		//process misplays first
		for ($i = 0; $i < $result['misplays']; $i++)
			self::processRatingChangeStep($userRating, $tsumegoRating, false);

		// lastly process the solve
		if ($result['solved'])
			self::processRatingChangeStep($userRating, $tsumegoRating, true);

		Auth::getUser()['rating'] = $userRating;
		Auth::saveUser();

		$previousTsumego['Tsumego']['rating'] = Util::clampOptional(
			$tsumegoRating,
			$previousTsumego['Tsumego']['minimum_rating'],
			$previousTsumego['Tsumego']['maximum_rating']);
		$previousTsumego['Tsumego']['activity_value']++;
		ClassRegistry::init('Tsumego')->save($previousTsumego);
	}

	private function processDamage(array $result): void
	{
		if (!$result['misplays'])
			return;
		if (!Auth::isInLevelMode())
			return;
		Auth::getUser()['damage'] += $result['misplays'];
		Auth::saveUser();
	}

	private function processXpChange(array $previousTsumego, array &$result, string $previousTsumegoStatus, $originalTsumegoRating): void
	{
		if (!Auth::XPisGainedInCurrentMode())
			return;
		if (!Level::XPAndRatingIsGainedInTsumegoStatus($previousTsumegoStatus))
			return;
		if (!$result['solved'])
			return;

		$multiplier = ($result['xp-modifier'] ?: 1);
		$multiplier *=  TsumegoXPAndRating::getProgressDeletionMultiplier(TsumegoUtil::getProgressDeletionCount($previousTsumego['Tsumego']));

		$user = & Auth::getUser();
		$result['xp-gained'] = Rating::ratingToXP($originalTsumegoRating, $multiplier);
		Level::addXPAsResultOfTsumegoSolving($user, $result['xp-gained']);
	}

	private function processErrorAchievement(array $result): void
	{
		$achievementCondition = ClassRegistry::init('AchievementCondition')->find('first', [
			'order' => 'value DESC',
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'category' => 'err',
			],
		]);
		if (!$achievementCondition)
		{
			$achievementCondition = [];
			ClassRegistry::init('AchievementCondition')->create();
		}
		$achievementCondition['AchievementCondition']['category'] = 'err';
		$achievementCondition['AchievementCondition']['user_id'] = Auth::getUserID();
		if ($result['solved'])
			$achievementCondition['AchievementCondition']['value']++;
		else
			$achievementCondition['AchievementCondition']['value'] = 0;
		ClassRegistry::init('AchievementCondition')->save($achievementCondition);
	}

	private function processUnsortedStuff(array $previousTsumego, array $result): void
	{
		if (!$result['solved'])
			return;

		$solvedTsumegoRank = Rating::getReadableRankFromRating($previousTsumego['Tsumego']['rating']);
		AppController::saveDanSolveCondition($solvedTsumegoRank, $previousTsumego['Tsumego']['id']);
		AppController::updateGems($solvedTsumegoRank);
		if ($_COOKIE['sprint'] == 1)
			AppController::updateSprintCondition(true);
		else
			AppController::updateSprintCondition();
		if ($_COOKIE['type'] == 'g')
			AppController::updateGoldenCondition(true);

		Util::clearCookie('sequence');
		Util::clearCookie('type');
	}

	/* @return The number of misplays and consumes the misplays cookie in the process */
	private function checkMisplay(): int
	{
		return (int) Util::clearCookie('misplays');
	}
}

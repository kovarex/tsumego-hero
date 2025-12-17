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

	private array $existingStatuses = [];
	public array $updated = [];
}

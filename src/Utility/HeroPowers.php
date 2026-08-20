<?php

class HeroPowers
{
	public static $SPRINT_MINIMUM_LEVEL = 20;
	public static $INTUITION_MINIMUM_LEVEL = 30;
	public static $REJUVENATION_MINIMUM_LEVEL = 40;
	public static $POTION_MINIMUM_LEVEL = 50;
	public static $POTION_CHANCE_PER_DEATH = 0.5;
	public static $BAD_POTION_THRESHOLD = 15;
	public static $REVELATION_MINIMUM_LEVEL = 80;
	public static $REFINEMENT_MINIMUM_LEVEL = 100;

	public static function getPowers(): array
	{
		return [
			['name' => 'Sprint', 'level' => self::$SPRINT_MINIMUM_LEVEL, 'description' => 'Speed solving'],
			['name' => 'Intuition', 'level' => self::$INTUITION_MINIMUM_LEVEL, 'description' => 'Shows first correct move'],
			['name' => 'Rejuvenation', 'level' => self::$REJUVENATION_MINIMUM_LEVEL, 'description' => 'Restores health and locks'],
			['name' => 'Potion', 'level' => self::$POTION_MINIMUM_LEVEL, 'description' => 'Chance to restore health'],
			['name' => 'Revelation', 'level' => self::$REVELATION_MINIMUM_LEVEL, 'description' => 'Solves a problem'],
			['name' => 'Refinement', 'level' => self::$REFINEMENT_MINIMUM_LEVEL, 'description' => 'Golden tsumego chance'],
		];
	}

	public static function isPowerUnlocked(string $name, array $user): bool
	{
		$level = (int) ($user['level'] ?? 0);
		$premium = (int) ($user['premium'] ?? 0);
		switch ($name)
		{
			case 'Sprint': return $level >= self::$SPRINT_MINIMUM_LEVEL;
			case 'Intuition': return $level >= self::$INTUITION_MINIMUM_LEVEL;
			case 'Rejuvenation': return $level >= self::$REJUVENATION_MINIMUM_LEVEL;
			case 'Potion': return $premium > 0 || $level >= self::$POTION_MINIMUM_LEVEL;
			case 'Revelation': return self::hasRevelationUse($user);
			case 'Refinement': return $premium > 0 || $level >= self::$REFINEMENT_MINIMUM_LEVEL;
			default: return false;
		}
	}

	private static function hasRevelationUse(array $user): bool
	{
		if (($user['level'] ?? 0) >= self::$REVELATION_MINIMUM_LEVEL)
			return true;
		if (!empty($user['isAdmin']))
			return true;
		$userId = (int) ($user['id'] ?? 0);
		if ($userId && ($contribution = ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => $userId]])))
			if (!empty($contribution['UserContribution']['reward3']))
				return true;
		return false;
	}

	public static function render(): void
	{
		if (!Auth::isLoggedIn())
			return;
		self::renderSprint();
		self::renderIntuition();
		self::renderRefinement();
		self::renderRejuvenation();
		self::renderRevelation();
		self::renderPotion();
	}

	public static function renderJavascript(): void
	{
		if (!Auth::isLoggedIn())
			return;
		echo self::canUseIntuition() ? "enableIntuition();" : "disableIntuition();";
		echo self::canUseRejuvanation() ? "enableRejuvenation();" : "disableRejuvenation();";
		echo self::canUseSprint() ? "enableSprint();" : "disableSprint();";
		echo self::canUseRefinement() ? "enableRefinement();" : "disableRefinement();";
	}

	public static function changeUserSoRejuvenationCanBeUsed()
	{
		Auth::getUser()['level'] = self::$REJUVENATION_MINIMUM_LEVEL;
		Auth::saveUser();
	}

	public static function canUseRejuvanation()
	{
		if (Auth::getWithDefault('level', 0) < self::$REJUVENATION_MINIMUM_LEVEL)
			return false;
		return !Auth::getUser()['used_rejuvenation'];
	}

	public static function renderRejuvenation()
	{
		echo '<img id="rejuvenation" title="Rejuvenation (Level ' . self::$REJUVENATION_MINIMUM_LEVEL . '): Restores health, Intuition and locks.">';
	}

	public static function changeUserSoIntuitionCanBeUsed()
	{
		Auth::getUser()['level'] = self::$INTUITION_MINIMUM_LEVEL;
		Auth::saveUser();
	}

	public static function canUseIntuition()
	{
		if (Auth::getWithDefault('level', 0) < self::$INTUITION_MINIMUM_LEVEL)
			return false;
		return !Auth::getUser()['used_intuition'];
	}

	public static function renderIntuition()
	{
		echo '<img id="intuition" title="Intuition (Level ' . self::$INTUITION_MINIMUM_LEVEL . ') : Shows the first correct move." alt="Intuition">';
	}

	public static function getRevelationUseCount(): int
	{
		$result = 0;
		if (Auth::isLoggedIn())
			$result++;
		if (Auth::isAdmin())
			$result++;
		if (Auth::getUser()['level'] >= self::$REVELATION_MINIMUM_LEVEL)
			$result++;
		if ($userContribution = ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]))
			if ($userContribution['UserContribution']['reward3'])
				$result++;
		return $result;
	}

	public static function canUseRevelation(): bool
	{
		return self::remainingRevelationUseCount() > 0;
	}

	public static function remainingRevelationUseCount(): int
	{
		if (!Auth::isLoggedIn())
			return 0;
		return self::getRevelationUseCount() - Auth::getUser()['used_revelation'];
	}

	public static function renderRevelation()
	{
		if (self::getRevelationUseCount() == 0)
			return;

		$image = self::canUseRevelation() ? '/img/hp6.png' : '/img/hp6x.png';
		$hoveredImage = self::canUseRevelation() ? '/img/hp6h.png' : '/img/hp6x.png';
		echo '<img';
		echo ' id="revelation" title="Revelation (' . self::remainingRevelationUseCount() . '): Solves a problem, but you don\'t get any reward."';
		echo ' src="' . $image . '" ';
		if (self::canUseRevelation())
		{
			echo ' onmouseover="this.src = \'' . $hoveredImage . '\';"';
			echo ' onmouseout="this.src = \'' . $image . '\';"';
			echo ' onclick="revelation(); return false;"';
		}
		echo ' style="cursor:' . (self::canUseRevelation() ? 'pointer' : 'auto') . '"></a>';
	}

	public static function changeUserSoSprintCanBeUsed()
	{
		Auth::getUser()['level'] = self::$SPRINT_MINIMUM_LEVEL;
		Auth::getUser()['mode'] = Constants::$LEVEL_MODE;
		Auth::saveUser();
	}

	public static function canUseSprint()
	{
		if (Auth::getWithDefault('level', 0) < self::$SPRINT_MINIMUM_LEVEL)
			return false;
		if (!Auth::isInLevelMode())
			return false;
		return !Auth::getUser()['used_sprint'];
	}

	public static function getSprintRemainingSeconds()
	{
		if (!Auth::isLoggedIn())
			return 0;
		$value = Auth::getUser()['sprint_start'];
		if (!$value)
			return 0;

		$start = new DateTime($value);
		$now   = new DateTime('now');
		$x =		Constants::$SPRINT_SECONDS - ($now->getTimestamp() - $start->getTimestamp());
		return max(0, Constants::$SPRINT_SECONDS - ($now->getTimestamp() - $start->getTimestamp()));
	}

	public static function renderSprint()
	{
		echo '<img id="sprint" title="Sprint: Double XP for 2 minutes." alt="Sprint"></a>';
	}

	public static function canPotionTrigger()
	{
		if (!Auth::isLoggedIn())
			return false;
		if (!Auth::hasPremium() && Auth::getUser()['level'] < self::$POTION_MINIMUM_LEVEL)
			return false;
		if (Auth::getUser()['used_potion'])
			return false;
		return Auth::getUser()['damage'] >= Util::getHealthBasedOnLevel(Auth::getUser()['level']);
	}

	public static function canUseRefinement()
	{
		if (!Auth::isLoggedIn())
			return false;
		if (!Auth::hasPremium() && Auth::getWithDefault('level', 0) < self::$REFINEMENT_MINIMUM_LEVEL)
			return false;
		return !Auth::getUser()['used_refinement'];
	}

	private static function renderRefinement()
	{
		echo '<img id="refinement" title="Refinement (Level ' . self::$REFINEMENT_MINIMUM_LEVEL . '): Gives you a chance to solve a golden tsumego. If you fail, it disappears.">';
	}

	private static function renderPotion()
	{
		if (self::canPotionTrigger())
			echo '<img id="potion" title="Potion (Passive): If you misplay and have no hearts left, you have a small chance to restore your health." src="/img/hp5.png">';
	}
}

<?php

class HeroPowers
{
	public static $SPRINT_MINIMUM_LEVEL = 20;
	public static $INTUITION_MINIMUM_LEVEL = 30;
	public static $REJUVENATION_MINIMUM_LEVEL = 40;
	public static $REVELATION_MINIMUM_LEVEL = 80;
	public static $REFINEMENT_MINIMUM_LEVEL = 100;

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

	public static function changeUserSoRevelationCanBeUsed()
	{
		Auth::getUser()['level'] = self::$REVELATION_MINIMUM_LEVEL;
		Auth::getUser()['mode'] = Constants::$LEVEL_MODE;
		Auth::saveUser();
	}

	public static function getRevelationUseCount(): int
	{
		$result = 0;
		if (Auth::hasPremium())
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

	public static function isPotionActive()
	{
		if (!Auth::hasPremium() && Auth::getUser()['level'] < 50)
			return false;
		if (!Auth::getUser()['used_potion'])
			return false;
		return Auth::getUser()['damage'] >= Util::getHealthBasedOnLevel(Auth::getUser()['level']);
	}

	public static function canUseRefinement()
	{
		if (!Auth::hasPremium() && Auth::getWithDefault('level', 0) < 100)
			return false;
		return !Auth::getUser()['used_refinement'];
	}

	private static function renderRefinement()
	{
		echo '<img id="refinement" title="Refinement (Level ' . self::$REFINEMENT_MINIMUM_LEVEL . ' or premum): Gives you a chance to solve a golden tsumego. If you fail, it disappears.">';
	}

	private static function renderPotion()
	{
		if (self::isPotionActive())
			echo '<img id="potion" title="Potion (Passive): If you misplay and have no hearts left, you have a small chance to restore your health." src="/img/hp5.png">';
	}
}

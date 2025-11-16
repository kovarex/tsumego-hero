<?php

class HeroPowers {
	public static $SPRINT_MINIMUM_LEVEL = 20;
	public static $INTUITION_MINIMUM_LEVEL = 30;

	public static function render(): void {
		if (!Auth::isLoggedIn()) {
			return;
		}
		self::renderSprint();
		self::renderIntuition();
		self::renderRefinement();
		self::renderRejuvenation();
		self::renderRevelation();
		self::renderPotion();
	}

	public static function canUseRejuvanation() {
		if (Auth::getWithDefault('level', 0) < 40) {
			return false;
		}
		return !Auth::getUser()['used_rejuvenation'];
	}

	public static function renderRejuvenation() {
		if (self::canUseRejuvanation()) {
			echo '<a href="#"><img id="rejuvenation" title="Rejuvenation: Restores health, Intuition and locks." src="/img/hp3.png" onmouseover="this.src = \'/img/hp3h.png\';" onmouseout="this.src = \'/img/hp3.png\';" onclick="rejuvenation(); return false;"></a>';
		} else {
			echo '<img id="rejuvenation" title="Rejuvenation (Level 40): Restores health, Intuition and locks." src="/img/hp3x.png" style="cursor: context-menu;" alt="Rejuvenation">';
		}
	}

	public static function changeUserSoIntuitionCanBeUsed() {
		Auth::getUser()['level'] = self::$INTUITION_MINIMUM_LEVEL;
		Auth::saveUser();
	}

	public static function canUseIntuition() {
		if (Auth::getWithDefault('level', 0) < self::$INTUITION_MINIMUM_LEVEL) {
			return false;
		}
		return !Auth::getUser()['used_intuition'];
	}

	public static function renderIntuition() {
		if (self::canUseIntuition()) {
			echo '<a href="#" id="intuitionLink"><img id="intuition" title="Intuition: Shows the first correct move." alt="Intuition" src="/img/hp2.png" onmouseover="this.src = \'/img/hp2h.png\'" onmouseout="this.src = \'/img/hp2.png\';" onclick="intuition(); return false;"></a>';
		} else {
			echo '<img id="intuition" title="Intuition (Level 30): Shows the first correct move." src="/img/hp2x.png" style="cursor: context-menu;" alt="Intuition">';
		}
	}

	public static function canUseRevelation() {
		if (Auth::getUser()['used_revelation']) {
			return false;
		}
		if ($userContribution = ClassRegistry::init('UserContribution')->find('first', ['conditions' => ['user_id' => Auth::getUserID()]])) {
			if ($userContribution['UserContribution']['reward3']) {
				return true;
			}
		}
		return Auth::hasPremium() && Auth::getUser()['level'] >= 100;
	}

	public static function renderRevelation() {
		if (self::canUseRevelation()) {
			echo '<a href="#" class="revelation-anchor"><img id="revelation" title="Revelation: Solves a problem, but you don\'t get any reward." src="/img/hp6x.png" onmouseover="this.src = \'/img/hp6h.png\';" onmouseout="this.src = \'/img/hp6.png\';" onclick="revelation(); return false;"></a>';
		}
	}

	public static function changeUserSoSprintCanBeUsed() {
		Auth::getUser()['level'] = self::$SPRINT_MINIMUM_LEVEL;
		Auth::getUser()['mode'] = Constants::$LEVEL_MODE;
		Auth::saveUser();
	}

	public static function canUseSprint() {
		if (Auth::getWithDefault('level', 0) < self::$SPRINT_MINIMUM_LEVEL) {
			return false;
		}
		if (!Auth::isInLevelMode()) {
			return false;
		}
		return !Auth::getUser()['used_sprint'];
	}

	public static function getSprintRemainingSeconds() {
		if (!Auth::isLoggedIn()) {
			return 0;
		}
		$value = Auth::getUser()['sprint_start'];
		if (!$value) {
			return 0;
		}

		$start = new DateTime($value);
		$now   = new DateTime('now');
		$x =		Constants::$SPRINT_SECONDS - ($now->getTimestamp() - $start->getTimestamp());
		return max(0, Constants::$SPRINT_SECONDS - ($now->getTimestamp() - $start->getTimestamp()));
	}

	public static function renderSprint() {
		if (self::canUseSprint()) {
			echo '<a href="#" id="sprintLink"><img id="sprint" title="Sprint: Double XP for 2 minutes." alt="Sprint" src="/img/hp1.png" onmouseover="this.src = \'/img/hp1h.png\';" onmouseout="this.src = \'/img/hp1.png\';" onclick="startSprint(' . Constants::$SPRINT_SECONDS . '); return false;"></a>';
		} else {
			echo '<img id="sprint" title="Sprint (Level 20): Double XP for 2 minutes." src="/img/hp1x.png" style="cursor: context-menu;" alt="Sprint">';
		}
	}

	public static function isPotionActive() {
		if (!Auth::hasPremium() && Auth::getUser()['level'] < 50) {
			return false;
		}
		if (!Auth::getUser()['used_potion']) {
			return false;
		}
		return Auth::getUser()['health'] - Auth::getUser()['damage'] <= 0;
	}

	public static function canUseRefinement() {
		if (!Auth::hasPremium() && Auth::getWithDefault('level', 0) < 100) {
			return false;
		}
		return !Auth::getUser()['used_refinement'];
	}

	private static function renderRefinement() {
		if (self::canUseRefinement()) {
			echo '<a href="/hero/refinement" id="refinementLink"><img id="refinement" title="Refinement: Gives you a chance to solve a golden tsumego. If you fail, it disappears." src="/img/hp4.png" onmouseover="this.src = \'/img/hp4h.png\';" onmouseout="this.src = \'/img/hp4.png\';"></a>';
		} else {
			echo '<img id="refinement" title="Refinement (Level 100 or Premium): Gives you a chance to solve a golden tsumego. If you fail, it disappears." src="/img/hp4x.png" style="cursor: context-menu;" alt="Refinement">';
		}
	}

	private static function renderPotion() {
		if (self::isPotionActive()) {
			echo '<img id="potion" title="Potion (Passive): If you misplay and have no hearts left, you have a small chance to restore your health." src="/img/hp5.png">';
		}
	}
}

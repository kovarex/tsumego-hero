<?php

namespace App\Policy;

use App\Utility\HeroPowers;

/**
 * The five hero powers. Whether a power is available to a player right now stays in
 * HeroPowers, which the page asks as well when it decides what to offer; this policy
 * is only the entry point the actions authorize through, so that every action in the
 * app has the same shape: $this->Authorization->authorize(...).
 *
 * The rule reads the identity itself (HeroPowers works off Auth), so the $user
 * argument is unused here.
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class HeroPolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canSprint(?array $user): bool
	{
		return HeroPowers::canUseSprint();
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canIntuition(?array $user): bool
	{
		return HeroPowers::canUseIntuition();
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canRejuvenation(?array $user): bool
	{
		return HeroPowers::canUseRejuvanation();
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canRefinement(?array $user): bool
	{
		return HeroPowers::canUseRefinement();
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canRevelation(?array $user): bool
	{
		return HeroPowers::canUseRevelation();
	}
}

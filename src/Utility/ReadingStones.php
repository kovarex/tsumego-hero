<?php

namespace App\Utility;

/**
 * How one group of stones is drawn while the reading trainer is on: with its real
 * colours, as one shared glass stone (the point reads as taken, not as whose), or
 * not at all. Two of these make up a trainer state, see the eye under the board.
 */
enum ReadingStones: int
{
	case Normal = 0;
	case Glass = 1;
	case Hidden = 2;

	/** The stored preference value, falling back to Normal for anything unknown. */
	public static function fromPreference(?int $value): self
	{
		return self::tryFrom((int) $value) ?? self::Normal;
	}

	/**
	 * What a player who never picked one gets for the stones they play: they are
	 * taken away, so the line has to be read from memory.
	 */
	public static function defaultPlayed(): self
	{
		return self::Hidden;
	}

	/**
	 * What a player who never picked one gets for the stones the problem starts
	 * with: they stay, so there is still a position to read from.
	 */
	public static function defaultSetup(): self
	{
		return self::Normal;
	}
}

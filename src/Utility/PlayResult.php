<?php

namespace App\Utility;

//What the steps of PlayResultProcessorComponent work out about one result of a play, for the
//steps after them: the XP multiplier the status and the sprint give, the XP the solve earned and
//whether the potion triggered.
class PlayResult
{
	public function __construct(public readonly bool $solved) {}

	public float $xpMultiplier = 1;
	public int $xpGained = 0;
	public bool $potionTriggered = false;
}

<?php

namespace App\Utility;

class Level
{
	// this needs to be up to date with level code in util.js
	public static function getSections(): array
	{
		static $sections = [
			[11, 10],
			[19, 25],
			[39, 50],
			[69, 100],
			[99, 150],
			[100, 50000],
			[101, 1150],
			[10000, 0]];
		return $sections;
	}

	public static function getXPForNext(int $level): int
	{
		return new XPForNextCalculator($level)->result;
	}

	private static function sectionSum(int $level, int &$from, int $to, int $jump, int|float &$result, int &$xpIncrease): bool
	{
		$steps = min($to, $level) - $from;
		$result += $steps * $xpIncrease;
		if ($steps > 1)
			$result += (($steps * ($steps - 1)) / 2) * $jump;
		$from = $to;
		$xpIncrease += $steps * $jump;
		return $level <= $to;
	}

	public static function getXpSumToGetLevel(int $level): int
	{
		$result = 0;
		$from = 1;
		$xpIncrease = 50;

		foreach (self::getSections() as $section)
			if (self::sectionSum($level, $from, $section[0], $section[1], $result, $xpIncrease))
				return $result;
		return $result;
	}

	public static function XPAndRatingIsGainedInTsumegoStatus(string $status): bool
	{
		return $status != 'S' && $status  != 'C'; // solved or doulbe solved is already rewarded, otherwise ok
	}

	public static function getOverallXPGained(array $user): int
	{
		return Level::getXpSumToGetLevel($user['level']) + $user['xp'];
	}

	public static function addXP(array &$user, int|float $value): void
	{
		$user['xp'] += $value;
		Level::checkLevelUp($user);
	}

	public static function addXPAsResultOfTsumegoSolving(array &$user, int|float $value): void
	{
		Level::addXP($user, $value);
		$user['daily_xp'] += $value;
		$user['daily_solved']++;
	}

	public static function oldXPSumCode(int $level): int
	{
		$startxp = 50;
		$sumx = 0;
		$xpJump = 10;

		for ($i = 1; $i < $level; $i++)
		{
			if ($i >= 11)
				$xpJump = 25;
			if ($i >= 19)
				$xpJump = 50;
			if ($i >= 39)
				$xpJump = 100;
			if ($i >= 69)
				$xpJump = 150;
			if ($i >= 99)
				$xpJump = 50000;
			if ($i == 100)
				$xpJump = 1150;
			if ($i >= 101)
				$xpJump = 0;
			$sumx += $startxp;
			$startxp += $xpJump;
		}
		return $sumx;
	}

	public static function checkLevelUp(array &$user): void
	{
		while (true)
		{
			$nextLevel = Level::getXPForNext($user['level']);
			if ($user['xp'] < $nextLevel)
				return;
			$user['xp'] -= $nextLevel;
			$user['level']++;
		}
	}
}

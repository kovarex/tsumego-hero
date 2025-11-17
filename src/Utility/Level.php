<?php

class Level {
	private static function getSections(): array {
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

	private static function section($level, &$from, $to, $jump, &$result): bool {
		$steps = min($to, $level) - $from;
		$result += $steps * $jump;
		$from = $to;
		return $level <= $to;
	}

	public static function getXPForNext($level): int {
		$result = 50;
		$from = 1;

		foreach (self::getSections() as $section) {
			if (self::section($level, $from, $section[0], $section[1], $result)) {
				return $result;
			}
		}
		return $result;
	}

	private static function sectionSum($level, &$from, $to, $jump, &$result, &$xpIncrease): bool {
		$steps = min($to, $level) - $from;
		$result += $steps * $xpIncrease;
		if ($steps > 1) {
			$result += (($steps * ($steps - 1)) / 2) * $jump;
		}
		$from = $to;
		$xpIncrease += $steps * $jump;
		return $level <= $to;
	}

	public static function getXpSumToGetLevel($level): int {
		$result = 0;
		$from = 1;
		$xpIncrease = 50;

		foreach (self::getSections() as $section) {
			if (self::sectionSum($level, $from, $section[0], $section[1], $result, $xpIncrease)) {
				return $result;
			}
		}
		return $result;
	}

	public static function oldXPSumCode($level): int {
		$startxp = 50;
		$sumx = 0;
		$xpJump = 10;

		for ($i = 1; $i < $level; $i++) {
			if ($i >= 11) {
				$xpJump = 25;
			}
			if ($i >= 19) {
				$xpJump = 50;
			}
			if ($i >= 39) {
				$xpJump = 100;
			}
			if ($i >= 69) {
				$xpJump = 150;
			}
			if ($i >= 99) {
				$xpJump = 50000;
			}
			if ($i == 100) {
				$xpJump = 1150;
			}
			if ($i >= 101) {
				$xpJump = 0;
			}
			$sumx += $startxp;
			$startxp += $xpJump;
		}
		return $sumx;
	}

	public static function checkLevelUp($user) {
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

<?php

namespace App\Utility;

class XPForNextCalculator
{
	public function __construct($level)
	{
		foreach (Level::getSections() as $section)
			if ($this->section($level, $section[0], $section[1]))
				return;
	}

	public function section($level, $to, $jump): bool
	{
		$steps = min($to, $level) - $this->from;
		$this->result += $steps * $jump;
		$this->from = $to;
		return $level <= $to;
	}

	public int $from = 1;
	public int $result = 50;
}

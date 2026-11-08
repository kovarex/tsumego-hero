<?php

use App\Utility\PrintSheet;
use App\Utility\ProblemGoal;
use PHPUnit\Framework\TestCase;

class ProblemGoalTest extends TestCase
{
	public function testTheDefaultIsTheColorToPlay(): void
	{
		$this->assertSame(['Black to play', 'White to play', 'Black to play', 'White to play', ''],
			[ProblemGoal::default('B'), ProblemGoal::default('W'), ProblemGoal::default('black'), ProblemGoal::default('white'), ProblemGoal::default(null)]);
	}

	//A description with nothing to read (no text, or only markup and spaces) is the default
	public function testAnEmptyDescriptionIsTheDefault(): void
	{
		$this->assertSame(['White to play', 'White to play', 'White to play', 'White to kill'],
			[ProblemGoal::orDefault(null, 'W'), ProblemGoal::orDefault('', 'W'), ProblemGoal::orDefault(' <p> </p> ', 'W'), ProblemGoal::orDefault('White to kill', 'W')]);
	}

	//The goal the print sheet shows is the same default
	public function testThePrintSheetPrintsTheDefault(): void
	{
		$this->assertSame(['Black to play', 'White to play', 'White to live'],
			[PrintSheet::goal('', 'B'), PrintSheet::goal('<p></p>', 'W'), PrintSheet::goal('<b>White</b> to live', 'W')]);
	}

	//Only the default of the color to move is stored as empty, any case, with or without its full stop
	public function testTheDefaultOfTheColorToMoveIsStoredEmpty(): void
	{
		$this->assertSame(['', '', '', 'White to play', 'Black to play and live', 'Black to play'],
			[ProblemGoal::forStorage('Black to play', 'B'), ProblemGoal::forStorage(' black to play. ', 'B'), ProblemGoal::forStorage('White to play', 'W'),
				ProblemGoal::forStorage('White to play', 'B'), ProblemGoal::forStorage('Black to play and live', 'B'), ProblemGoal::forStorage('Black to play', null)]);
	}
}

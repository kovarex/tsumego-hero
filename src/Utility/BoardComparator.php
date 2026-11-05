<?php

namespace App\Utility;

//Compares problem A with other problems, in every symmetry of the board. A is transformed once, so
//comparing it with many problems (the similar search) costs only the comparisons.
class BoardComparator
{
	//The most stones two problems can differ in and still be similar
	private const MAXIMUM_DIFFERENCE = 5;

	//The 8 symmetries of the board as the flags of BoardPosition::transformed: the identity and the
	//mirrors first, so that on a tie the simplest one is shown, then the rotations and the diagonals
	private const SYMMETRIES = [
		0,
		BoardPosition::MIRROR_X,
		BoardPosition::MIRROR_Y,
		BoardPosition::TRANSPOSE,
		BoardPosition::MIRROR_X | BoardPosition::MIRROR_Y,
		BoardPosition::MIRROR_X | BoardPosition::TRANSPOSE,
		BoardPosition::MIRROR_Y | BoardPosition::TRANSPOSE,
		BoardPosition::MIRROR_X | BoardPosition::MIRROR_Y | BoardPosition::TRANSPOSE];

	//A in each symmetry of the board: its stones, its correct moves, and the lowest corner of those
	//(of the stones when it has no correct moves) that is put onto the same corner of B
	/** @var list<array{stones: array<int, int>, correctMoves: array<int, mixed>, lowest: int}> */
	private array $symmetries = [];

	public function __construct(private array $aStones, private string $aFirstMoveColor, private array $aCorrectMoves)
	{
		foreach (self::SYMMETRIES as $symmetry)
		{
			$stones = SgfBoard::getPositionsTransformed($aStones, $symmetry);
			$correctMoves = SgfBoard::getPositionsTransformed($aCorrectMoves, $symmetry);
			$this->symmetries[] = [
				'stones' => $stones,
				'correctMoves' => $correctMoves,
				'lowest' => SgfBoard::getLowestPosition(empty($aCorrectMoves) ? $stones : $correctMoves)];
		}
	}

	public static function compare(
		array $aStones,
		string $aFirstMoveColor,
		array $aCorrectMoves,
		array $bStones,
		string $bFirstMoveColor,
		array $bCorrectMoves): ?BoardComparisonResult
	{
		return new self($aStones, $aFirstMoveColor, $aCorrectMoves)->compareWith($bStones, $bFirstMoveColor, $bCorrectMoves);
	}

	//The closest of A's symmetries, with the colors as they are and switched, that puts the correct
	//moves of A onto those of B; null when there is none or it differs in more than MAXIMUM_DIFFERENCE
	//stones. On a tie the first is taken, and a symmetry with no difference ends the search.
	public function compareWith(array $bStones, string $bFirstMoveColor, array $bCorrectMoves): ?BoardComparisonResult
	{
		if (empty($this->aStones) || empty($bStones))
			return null;

		if (($this->aFirstMoveColor == 'N') != ($bFirstMoveColor == 'N'))
			return null;

		// correct moves length was supposed to be checked long before this step as optimisation
		$withCorrectMoves = !empty($this->aCorrectMoves);
		$bLowest = SgfBoard::getLowestPosition($withCorrectMoves ? $bCorrectMoves : $bStones);
		// without correct moves, or without a first move, the problem can be either color's, so both are tried
		$anyColor = !$withCorrectMoves || $this->aFirstMoveColor == 'N';
		$colorSwitches = [];
		if ($anyColor || $this->aFirstMoveColor == $bFirstMoveColor)
			$colorSwitches[] = false;
		if ($anyColor || $this->aFirstMoveColor != $bFirstMoveColor)
			$colorSwitches[] = true;

		$closest = null;
		$smallestDiff = null;
		foreach ($this->symmetries as $symmetry)
		{
			// we always shift A to B, so we can later show diff positions relative to original B
			$shift = BoardPosition::diff($symmetry['lowest'], $bLowest);
			if ($withCorrectMoves && !self::allShiftedAreIn($symmetry['correctMoves'], $shift, $bCorrectMoves))
				continue;
			foreach ($colorSwitches as $colorsSwitched)
			{
				$diff = self::difference($symmetry['stones'], $shift, $colorsSwitched, $bStones);
				if ($smallestDiff === null || $diff < $smallestDiff)
				{
					$closest = [$symmetry['stones'], $shift, $colorsSwitched];
					$smallestDiff = $diff;
					if ($diff === 0) // no later candidate can be closer
						break 2;
				}
			}
		}
		if ($smallestDiff === null || $smallestDiff > self::MAXIMUM_DIFFERENCE)
			return null;

		[$stones, $shift, $colorsSwitched] = $closest;
		$aOnB = SgfBoard::getShiftedPositions($stones, $shift);
		if ($colorsSwitched)
			$aOnB = SgfBoard::getColorSwitchedStones($aOnB);
		return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aOnB, $bStones));
	}

	//The number of stones A shifted (its colors switched when asked) and B differ in: those of A that B
	//doesn't have or has in the other color, and those of B that A doesn't have. Counted without making
	//the shifted A, as this runs for every symmetry of every compared problem.
	private static function difference(array $aStones, int $shift, bool $colorsSwitched, array $bStones): int
	{
		$diff = 0;
		$inBoth = 0;
		foreach ($aStones as $position => $color)
		{
			$bPosition = BoardPosition::shift($position, $shift);
			if (!isset($bStones[$bPosition]))
				$diff++;
			else
			{
				$inBoth++;
				if ($bStones[$bPosition] != ($colorsSwitched ? 1 - $color : $color))
					$diff++;
			}
		}
		return $diff + count($bStones) - $inBoth;
	}

	//Whether each of the positions, shifted, is one of B's; the correct moves were checked to be as many
	private static function allShiftedAreIn(array $positions, int $shift, array $bPositions): bool
	{
		foreach ($positions as $position => $x)
			if (!isset($bPositions[BoardPosition::shift($position, $shift)]))
				return false;
		return true;
	}
}

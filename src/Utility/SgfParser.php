<?php

declare(strict_types=1);

require_once(__DIR__ . "/BoardBounds.php");
require_once(__DIR__ . "/BoardPosition.php");
require_once(__DIR__ . "/SgfBoard.php");

class SgfParser
{
	/**
	 * Parse SGF and return board, stones and info array.
	 *
	 * @param string $sgf
	 * @return SgfBoard
	 */
	public static function process(string $sgf, $correctMoves = []): SgfBoard
	{
		$boardSize = self::detectBoardSize($sgf);
		$sgfArr = str_split($sgf);

		$blackStones = self::getInitialPosition(strpos($sgf, 'AB'), $sgfArr);
		$whiteStones = self::getInitialPosition(strpos($sgf, 'AW'), $sgfArr);

		$stones = [];
		foreach ($blackStones as $blackStone)
			$stones[$blackStone] = SgfBoard::BLACK;
		foreach ($whiteStones as $whiteStone)
			$stones[$whiteStone] = SgfBoard::WHITE;

		$boardBounds = new BoardBounds();
		foreach ($stones as $position => $color)
			$boardBounds->add($position);

		self::normalizeOrientation($stones, $correctMoves, $boardBounds, $boardSize);
		$tInfo = [$boardBounds->x->max, $boardBounds->y->max];
		return new SgfBoard($stones, $tInfo, $boardSize, $correctMoves);
	}

	/**
	 * Determine the color of the first move in an SGF string.
	 *
	 * @param string $sgf
	 * @return string 'B' for black first, 'W' for white first, 'N' when the SGF has no move
	 */
	public static function firstMoveColor(string $sgf): string
	{
		$blackPos = strpos($sgf, ';B[');
		$whitePos = strpos($sgf, ';W[');

		if ($blackPos === false && $whitePos === false)
			return 'N';
		if ($blackPos === false)
			return 'W';
		if ($whitePos === false)
			return 'B';

		return $blackPos < $whitePos ? 'B' : 'W';
	}

	/**
	 * Validate that an SGF string is structurally well-formed for a Go problem.
	 *
	 * Catches malformed input that the lenient parsing used elsewhere ignores,
	 * such as a move node that is not '-'prefixed (e.g. "AB[cc]B[aa]") or a node
	 * that mixes setup stones with a move.
	 *
	 * @param string $sgf
	 * @return string|null An error description, or null when the SGF is valid.
	 */
	public static function validate(string $sgf): ?string
	{
		$maxSize = 1024 * 1024; // 1 MB
		if (strlen($sgf) > $maxSize)
			return 'SGF data exceeds maximum size of 1 MB.';

		$s = trim($sgf);
		if ($s === '')
			return 'SGF data is empty.';
		if (!str_starts_with($s, '(;'))
			return 'Invalid SGF: must start with "(;".';
		if (substr($s, -1) !== ')')
			return 'Invalid SGF: must end with ")".';
		if (substr_count($s, '[') !== substr_count($s, ']'))
			return 'Invalid SGF: unbalanced brackets.';

		$len = strlen($s);
		$i = 0;
		$nodeHasSetup = false;
		$nodeHasMove = false;
		$inValue = false;

		while ($i < $len)
		{
			$ch = $s[$i];

			if ($ch === '[')
			{
				$inValue = true;
				$i++;
				continue;
			}

			if ($ch === ']')
			{
				$inValue = false;
				$i++;
				continue;
			}

			// Everything inside a property value is opaque; skip it.
			// In SGF a backslash escapes the next character (e.g. "\[" or "\]"),
			// so skip that too, otherwise an escaped ']' would end the value.
			if ($inValue)
			{
				if ($ch === '\\')
				{
					$i += 2;
					continue;
				}
				$i++;
				continue;
			}

			if ($ch === '(' || ctype_space($ch))
			{
				$i++;
				continue;
			}

			if ($ch === ';')
			{
				if ($nodeHasSetup && $nodeHasMove)
					return 'Invalid SGF: a node cannot contain both setup stones (AB/AW/AE) and a move (B/W).';
				$nodeHasSetup = false;
				$nodeHasMove = false;
				$i++;
				continue;
			}

			if ($ch === ')')
			{
				if ($nodeHasSetup && $nodeHasMove)
					return 'Invalid SGF: a node cannot contain both setup stones (AB/AW/AE) and a move (B/W).';
				$i++;
				continue;
			}

			if (ctype_upper($ch))
			{
				$start = $i;
				while ($i < $len && ctype_upper($s[$i]))
					$i++;
				$ident = substr($s, $start, $i - $start);
				if ($i >= $len || $s[$i] !== '[')
					return "Invalid SGF: property '{$ident}' must be followed by '['.";
				if ($ident === 'AB' || $ident === 'AW' || $ident === 'AE')
					$nodeHasSetup = true;
				if ($ident === 'B' || $ident === 'W')
					$nodeHasMove = true;
				continue;
			}

			return "Invalid SGF: unexpected character '{$ch}' at position {$i}.";
		}

		return null;
	}

	/**
	 * Validate that the SGF declares a Go game.
	 *
	 * Per the SGF spec the game (GM) property defaults to 1 (Go) when omitted,
	 * so a missing GM is accepted. Only an explicitly non-Go GM is rejected.
	 *
	 * @param string $sgf
	 * @return string|null An error description, or null when the SGF is a Go game.
	 */
	public static function validateGame(string $sgf): ?string
	{
		$s = trim($sgf);
		$len = strlen($s);
		$i = 0;
		$inValue = false;

		while ($i < $len)
		{
			$ch = $s[$i];

			if ($ch === '[')
			{
				$inValue = true;
				$i++;
				continue;
			}

			if ($ch === ']')
			{
				$inValue = false;
				$i++;
				continue;
			}

			// Everything inside a property value is opaque; skip it.
			if ($inValue)
			{
				$i++;
				continue;
			}

			if ($ch === '\\')
			{
				$i += 2;
				continue;
			}

			if (ctype_upper($ch))
			{
				$start = $i;
				while ($i < $len && ctype_upper($s[$i]))
					$i++;
				$ident = substr($s, $start, $i - $start);
				if ($ident === 'GM' && $i < $len && $s[$i] === '[')
				{
					$i++; // skip '['
					$valStart = $i;
					while ($i < $len && $s[$i] !== ']')
						$i++;
					$game = substr($s, $valStart, $i - $valStart);
					if ($game !== '1')
						return 'Invalid SGF: game (GM) must be Go (1).';
				}
				continue;
			}

			$i++;
		}

		return null;
	}

	private static function detectBoardSize(string $sgf): int
	{
		$boardSizePos = strpos($sgf, 'SZ');
		if ($boardSizePos === false)
			return 19;

		$sgfArr = str_split($sgf);
		$size = $sgfArr[$boardSizePos + 3] . $sgfArr[$boardSizePos + 4];
		if (substr($size, 1) == ']')
			$size = substr($size, 0, 1);

		return (int) $size;
	}

	private static function normalizeOrientation(array &$stones, array&$correctMoves, BoardBounds $boardBounds, int $boardSize): void
	{
		if ($boardBounds->x->isCloserToEnd($boardSize))
		{
			$stones = SgfBoard::getStonesFlipedX($stones, $boardSize);
			$correctMoves = SgfBoard::getStonesFlipedX($correctMoves, $boardSize);
			$boardBounds->x->flip($boardSize);
		}
		if ($boardBounds->y->isCloserToEnd($boardSize))
		{
			$stones = SgfBoard::getStonesFlipedY($stones, $boardSize);
			$correctMoves = SgfBoard::getStonesFlipedY($correctMoves, $boardSize);
			$boardBounds->y->flip($boardSize);
		}
	}

	private static function getInitialPosition(int|bool $pos, array $sgfArr): array
	{
		if ($pos === false)
			return [];

		$coords = [];
		$end = self::getInitialPositionEnd($pos, $sgfArr);
		for ($i = $pos + 2; $i < $end; $i++)
			if ($sgfArr[$i] == '[')
			{
				$coords[] = BoardPosition::fromLetters($sgfArr[$i + 1], $sgfArr[$i + 2]);
				$i += 3;
			}
		return $coords;
	}

	private static function getInitialPositionEnd(int $pos, array $sgfArr): int
	{
		$endCondition = $pos;
		$currentPos1 = $pos + 2;
		$currentPos2 = $pos + 5;
		while (isset($sgfArr[$currentPos1], $sgfArr[$currentPos2]) && $sgfArr[$currentPos1] == '[' && $sgfArr[$currentPos2] == ']')
		{
			$endCondition = $currentPos2;
			$currentPos1 += 4;
			$currentPos2 += 4;
		}

		return $endCondition;
	}
}

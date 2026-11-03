<?php

declare(strict_types=1);

namespace App\Utility;

class SgfParser
{
	//What is wrong with the SGF, or null when it is fit to be stored. The caller decides how to refuse the request.
	public static function validationProblem(string $sgf): ?string
	{
		return self::validate($sgf) ?? self::validateGame($sgf);
	}

	//Parse SGF and return board, stones and info array.
	public static function process(string $sgf, array $correctMoves = []): SgfBoard
	{
		$boardSize = self::boardSize($sgf);
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

	//Determine the color of the first move in an SGF string.
	//Returns 'B' for black first, 'W' for white first, 'N' when the SGF has no move
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

	//Validate that an SGF string is structurally well-formed for a Go problem.
	//Catches malformed input that the lenient parsing used elsewhere ignores,
	//such as a move node that is not '-'prefixed (e.g. "AB[cc]B[aa]") or a node
	//that mixes setup stones with a move.
	//Returns an error description, or null when the SGF is valid.
	public static function validate(string $sgf): ?string
	{
		$maxSize = 1024 * 1024; // 1 MB
		if (strlen($sgf) > $maxSize)
			return 'SGF data exceeds maximum size of 1 MB.';

		$text = trim($sgf);
		if ($text === '')
			return 'SGF data is empty.';
		if (!str_starts_with($text, '(;'))
			return 'Invalid SGF: must start with "(;".';
		if (substr($text, -1) !== ')')
			return 'Invalid SGF: must end with ")".';
		if (substr_count($text, '[') !== substr_count($text, ']'))
			return 'Invalid SGF: unbalanced brackets.';

		$nodeHasSetup = false;
		$nodeHasMove = false;
		foreach (self::scan($text) as $part)
			switch ($part[0])
			{
				case 'node':
				case 'end':
					if ($nodeHasSetup && $nodeHasMove)
						return 'Invalid SGF: a node cannot contain both setup stones (AB/AW/AE) and a move (B/W).';
					if ($part[0] === 'node')
						$nodeHasSetup = $nodeHasMove = false;
					break;
				case 'property':
					[, $identifier, $value] = $part;
					if ($value === null)
						return "Invalid SGF: property '{$identifier}' must be followed by '['.";
					$nodeHasSetup = $nodeHasSetup || in_array($identifier, ['AB', 'AW', 'AE'], true);
					$nodeHasMove = $nodeHasMove || in_array($identifier, ['B', 'W'], true);
					break;
				default:
					return "Invalid SGF: unexpected character '{$part[1]}' at position {$part[2]}.";
			}
		return null;
	}

	//Validate that the SGF declares a Go game.
	//Per the SGF spec the game (GM) property defaults to 1 (Go) when omitted,
	//so a missing GM is accepted. Only an explicitly non-Go GM is rejected.
	//Returns an error description, or null when the SGF is a Go game.
	public static function validateGame(string $sgf): ?string
	{
		foreach (self::scan(trim($sgf)) as $part)
			if ($part[0] === 'property' && $part[1] === 'GM' && $part[2] !== null && $part[2] !== '1')
				return 'Invalid SGF: game (GM) must be Go (1).';
		return null;
	}

	//The parts of an SGF text in order: a node starts (';') or a variation ends (')'), a property
	//(its identifier and its first value, null when no value follows it), or a character that has
	//no place outside a value. The values are opaque: a backslash escapes the next character, so an
	//escaped ']' does not end one.
	/** @return \Generator<int, array{0: 'node'|'end'}|array{0: 'property', 1: string, 2: string|null}|array{0: 'unexpected', 1: string, 2: int}> */
	private static function scan(string $text): \Generator
	{
		$length = strlen($text);
		$i = 0;
		while ($i < $length)
		{
			$character = $text[$i];
			if ($character === '[')
				$i = self::valueEnd($text, $i) + 1;
			elseif ($character === ']' || $character === '(' || ctype_space($character))
				$i++;
			elseif ($character === ';' || $character === ')')
			{
				yield [$character === ';' ? 'node' : 'end'];
				$i++;
			}
			elseif (ctype_upper($character))
			{
				$start = $i;
				while ($i < $length && ctype_upper($text[$i]))
					$i++;
				$value = null;
				if ($i < $length && $text[$i] === '[')
					$value = stripslashes(substr($text, $i + 1, self::valueEnd($text, $i) - $i - 1));
				yield ['property', substr($text, $start, $i - $start), $value];
			}
			else
			{
				yield ['unexpected', $character, $i];
				$i++;
			}
		}
	}

	//The position of the ']' that ends the value opened at $open, the end of the text when none does
	private static function valueEnd(string $text, int $open): int
	{
		$length = strlen($text);
		for ($i = $open + 1; $i < $length; $i++)
			if ($text[$i] === '\\')
				$i++;
			elseif ($text[$i] === ']')
				return $i;
		return $length;
	}

	//The board size the SGF names (SZ), 19 when it names none
	public static function boardSize(string $sgf): int
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

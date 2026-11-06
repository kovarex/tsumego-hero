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
		$values = self::firstValues($sgf, ['SZ', 'AB', 'AW']);
		$boardSize = self::sizeOf($values['SZ']);

		$stones = [];
		foreach (['AB' => SgfBoard::BLACK, 'AW' => SgfBoard::WHITE] as $identifier => $color)
			foreach (self::points($values[$identifier]) as [$x, $y])
				$stones[BoardPosition::fromLetters($x, $y)] = $color;

		$boardBounds = new BoardBounds();
		foreach ($stones as $position => $color)
			$boardBounds->add($position);

		[$flippedX, $flippedY] = self::normalizeOrientation($stones, $correctMoves, $boardBounds, $boardSize);
		$tInfo = [$boardBounds->x->max, $boardBounds->y->max];
		$board = new SgfBoard($stones, $tInfo, $boardSize, $correctMoves);
		$board->flippedX = $flippedX;
		$board->flippedY = $flippedY;
		return $board;
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

	//The values of the first occurrence of each of the properties, an empty list for one the SGF doesn't have
	/**
	 * @param list<string> $identifiers
	 * @return array<string, list<string>>
	 */
	public static function firstValues(string $sgf, array $identifiers): array
	{
		$result = array_fill_keys($identifiers, []);
		$missing = array_fill_keys($identifiers, true);
		foreach (self::scan($sgf) as $part)
			if ($part[0] === 'property' && isset($missing[$part[1]]))
			{
				$result[$part[1]] = $part[3];
				unset($missing[$part[1]]);
				if (empty($missing))
					break;
			}
		return $result;
	}

	//The points of point values ("aa" to "ss"), as letter pairs; anything else is left out
	/**
	 * @param list<string> $values
	 * @return list<array{string, string}>
	 */
	public static function points(array $values): array
	{
		$points = [];
		foreach ($values as $value)
			if (preg_match('/^[a-s]{2}$/', $value))
				$points[] = [$value[0], $value[1]];
		return $points;
	}

	//The board size of the values of SZ, 19 when there are none
	/** @param list<string> $values */
	private static function sizeOf(array $values): int
	{
		return empty($values) ? 19 : (int) $values[0];
	}

	//The parts of an SGF text in order: a node starts (';') or a variation ends (')'), a property
	//(its identifier, its first value, null when no value follows it, and all its values), or a
	//character that has no place outside a value. The values are opaque: a backslash escapes the next
	//character, so an escaped ']' does not end one.
	/** @return \Generator<int, array{0: 'node'|'end'}|array{0: 'property', 1: string, 2: string|null, 3: list<string>}|array{0: 'unexpected', 1: string, 2: int}> */
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
				$identifier = substr($text, $start, $i - $start);
				//Values may be apart, as editors wrap long lists of points
				$values = [];
				while ($i < $length && $text[$i] === '[')
				{
					$end = self::valueEnd($text, $i);
					$values[] = stripslashes(substr($text, $i + 1, $end - $i - 1));
					$i = $end + 1;
					while ($i < $length && ctype_space($text[$i]))
						$i++;
				}
				yield ['property', $identifier, $values[0] ?? null, $values];
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
		//Jumps from one '\' or ']' to the next, as comments are long
		for ($i = $open + 1 + strcspn($text, '\\]', $open + 1); $i < $length; $i += 2 + strcspn($text, '\\]', $i + 2))
			if ($text[$i] === ']')
				return $i;
		return $length;
	}

	//The board size the SGF names (SZ), 19 when it names none
	public static function boardSize(string $sgf): int
	{
		return self::sizeOf(self::firstValues($sgf, ['SZ'])['SZ']);
	}

	//Returns whether it flipped the board along x and along y
	/** @return array{bool, bool} */
	private static function normalizeOrientation(array &$stones, array&$correctMoves, BoardBounds $boardBounds, int $boardSize): array
	{
		$flippedX = $boardBounds->x->isCloserToEnd($boardSize);
		if ($flippedX)
		{
			$stones = SgfBoard::getStonesFlipedX($stones, $boardSize);
			$correctMoves = SgfBoard::getStonesFlipedX($correctMoves, $boardSize);
			$boardBounds->x->flip($boardSize);
		}
		$flippedY = $boardBounds->y->isCloserToEnd($boardSize);
		if ($flippedY)
		{
			$stones = SgfBoard::getStonesFlipedY($stones, $boardSize);
			$correctMoves = SgfBoard::getStonesFlipedY($correctMoves, $boardSize);
			$boardBounds->y->flip($boardSize);
		}
		return [$flippedX, $flippedY];
	}
}

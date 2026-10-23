<?php

namespace App\Utility;

/**
 * Comments keep their coordinates and colours as they are in the stored SGF,
 * without the transformation (corner, rotation, colour swap) the board is
 * shown with. The browser converts the text when it shows it and when it saves
 * it (app/comments/commentCoordinates.ts); this is the same rewrite for the
 * server, used when a new SGF version reorients the problem. Keep the two in sync.
 *
 * A label is a western coordinate such as "D4": the column letter (A-T without I)
 * from the left and the row number from the bottom. A point is 1-based x from the
 * left and y from the top, as besogo uses it.
 */
class CommentCoordinates
{
	// a coordinate is not a part of a longer word or number ("d4rkm4tter", "/files/ul1/"),
	// only a colour letter can be glued in front of it ("bL18", "WQ4")
	public const COORDINATE_PATTERN = '/(?:(?<![\p{L}\p{N}])|(?<=(?<![\p{L}\p{N}])[bwBW]))([A-Ha-hJ-Tj-t])([1-9][0-9]?)(?![\p{L}\p{N}])/u';
	// links stay as written, "%C3" or "/c3/" in them is not a coordinate
	private const LINK_PATTERN = '~((?:https?://|www\.)\S+)~i';
	private const COLOR_WORD_PATTERN = '/\b(black|white)\b/i';
	// a b/w letter that is not the end of a longer word or number
	private const COLOR_LETTER_PATTERN = '/(?<![\p{L}\p{N}])[bwBW]/u';
	// what follows a colour letter that marks the colour of a coordinate: "b E2", "w-T19", "bL18"
	private const FOLLOWED_BY_COORDINATE_PATTERN = '/^[\s,:-]*[A-HJ-Ta-hj-t][1-9][0-9]?(?![\p{L}\p{N}])/u';

	/** @return array{int, int}|null */
	public static function labelToPoint(string $label, int $size): ?array
	{
		$letter = strtoupper($label[0] ?? '');
		if ($letter < 'A' || $letter > 'Z' || $letter == 'I')
			return null;
		$x = ord($letter) - ord('A') + 1;
		if ($letter > 'I')
			$x--;
		$row = (int) substr($label, 1);
		$y = $size - $row + 1;
		if ($x < 1 || $x > $size || $row < 1 || $y < 1)
			return null;
		return [$x, $y];
	}

	public static function pointToLabel(int $x, int $y, int $size): ?string
	{
		if ($x < 1 || $x > $size || $y < 1 || $y > $size)
			return null;
		$letter = chr(ord('A') + $x - 1 + ($x >= 9 ? 1 : 0));
		return $letter . ($size - $y + 1);
	}

	/** @return string[] the labels of all coordinates in the text, upper case, in order */
	public static function labels(string $text): array
	{
		preg_match_all(self::COORDINATE_PATTERN, self::withoutLinks($text), $matches, PREG_SET_ORDER);
		$labels = [];
		foreach ($matches as $match)
			if ((int) $match[2] <= 19)
				$labels[] = strtoupper($match[1]) . $match[2];
		return $labels;
	}

	/** The text with every link replaced by a space, to look at its coordinates. */
	public static function withoutLinks(string $text): string
	{
		return preg_replace(self::LINK_PATTERN, ' ', $text);
	}

	/**
	 * Rewrites every coordinate in the text through $mapping (keeping the case of
	 * its letter, a null result keeps the coordinate as written) and optionally
	 * swaps the colours. Links stay as written.
	 *
	 * @param callable(string): ?string $mapping
	 */
	public static function transformText(string $text, callable $mapping, bool $invertColors): string
	{
		// the parts at odd indexes are the links
		$parts = preg_split(self::LINK_PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($parts as $index => $part)
			if ($index % 2 == 0)
				$parts[$index] = self::transformTextOutsideLinks($part, $mapping, $invertColors);
		return implode('', $parts);
	}

	/** @param callable(string): ?string $mapping */
	private static function transformTextOutsideLinks(string $text, callable $mapping, bool $invertColors): string
	{
		$transformed = preg_replace_callback(self::COORDINATE_PATTERN, function (array $match) use ($mapping): string {
			if ((int) $match[2] > 19)
				return $match[0];
			$mapped = $mapping(strtoupper($match[1]) . $match[2]);
			if ($mapped === null)
				return $match[0];
			return ctype_lower($match[1]) ? lcfirst($mapped) : $mapped;
		}, $text);
		return $invertColors ? self::swapColors($transformed) : $transformed;
	}

	/**
	 * Swaps black and white: the words, a standalone B or W ("B kills"), and a b/w
	 * letter marking the colour of a coordinate ("b E2", "W-T19", "bL18").
	 */
	private static function swapColors(string $text): string
	{
		$text = preg_replace_callback(self::COLOR_WORD_PATTERN, function (array $match): string {
			$swapped = strtolower($match[1]) == 'black' ? 'white' : 'black';
			if ($match[1] === strtoupper($match[1]))
				return strtoupper($swapped);
			if (ctype_upper($match[1][0]))
				return ucfirst($swapped);
			return $swapped;
		}, $text);

		return preg_replace_callback(self::COLOR_LETTER_PATTERN, function (array $match) use ($text): string {
			[$letter, $offset] = $match[0];
			$rest = substr($text, $offset + 1);
			$standalone = !preg_match('/^[\p{L}\p{N}]/u', $rest);
			$isUpper = ctype_upper($letter);
			if (!($standalone && $isUpper) && !preg_match(self::FOLLOWED_BY_COORDINATE_PATTERN, $rest))
				return $letter;
			$swapped = strtolower($letter) == 'b' ? 'w' : 'b';
			return $isUpper ? strtoupper($swapped) : $swapped;
		}, $text, -1, $count, PREG_OFFSET_CAPTURE);
	}

	public static function transformTextBy(string $text, BoardTransformation $transformation, int $size): string
	{
		return self::transformText($text, function (string $label) use ($transformation, $size): ?string {
			$point = self::labelToPoint($label, $size);
			if (!$point)
				return null;
			[$x, $y] = $transformation->applyToPoint($point[0], $point[1], $size);
			return self::pointToLabel($x, $y, $size);
		}, $transformation->invertColors);
	}

	/**
	 * Applies the transformation to a comment position
	 * ("x/y/parentX/parentY/childX/childY/moveNumber/childCount/sgf|path").
	 */
	public static function transformPosition(?string $position, BoardTransformation $transformation, int $size): ?string
	{
		if ($position === null || !$transformation->movesPoints())
			return $position;
		$pieces = explode('|', $position, 2);
		$fields = explode('/', $pieces[0]);
		for ($i = 0; $i + 1 < 6 && $i + 1 < count($fields); $i += 2)
			if ((int) $fields[$i] > 0)
				[$fields[$i], $fields[$i + 1]] = $transformation->applyToPoint((int) $fields[$i], (int) $fields[$i + 1], $size);
		$result = implode('/', $fields);
		if (isset($pieces[1]))
		{
			$moves = [];
			foreach (explode('+', $pieces[1]) as $move)
			{
				$point = explode('/', $move);
				$moves[] = count($point) == 2 ? implode('/', $transformation->applyToPoint((int) $point[0], (int) $point[1], $size)) : $move;
			}
			$result .= '|' . implode('+', $moves);
		}
		return $result;
	}
}

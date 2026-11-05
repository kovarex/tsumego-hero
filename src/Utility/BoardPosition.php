<?php

namespace App\Utility;

// the current range of board position is -31 to +31
// the negative values is to support different transformations related to board comparisons
class BoardPosition
{
	public const ZERO = (32 << 6) + 32;

	public static function pack(int $x, int $y): int
	{
		return ($x + 32) << 6 | ($y + 32);
	}

	public static function fromLetters(string $x, string $y): int
	{
		return BoardPosition::pack(ord($x) - ord('a'), ord($y) - ord('a'));
	}

	public static function unpackX(int $packed): int
	{
		return ($packed >> 6) - 32;
	}
	public static function unpackY(int $packed): int
	{
		return ($packed & 63) - 32;
	}

	public static function toLetters(int $packed): string
	{
		return chr(self::unpackX($packed) + ord('a')) . chr(self::unpackY($packed) + ord('a'));
	}

	public static function flipX(int $packed, int $size): int
	{
		return self::pack($size - 1 - self::unpackX($packed), self::unpackY($packed));
	}

	public static function flipY(int $packed, int $size): int
	{
		return self::pack(self::unpackX($packed), $size - 1 - self::unpackY($packed));
	}

	//The flags of transformed, which together make the 8 symmetries of the board
	public const MIRROR_X = 1;
	public const MIRROR_Y = 2;
	public const TRANSPOSE = 4;

	//The position mirrored left to right and/or top to bottom on a board of the largest size, then with x
	//and y swapped, as the flags of $symmetry say. Where on the board it ends doesn't matter, as the
	//comparison shifts it after.
	public static function transformed(int $packed, int $symmetry): int
	{
		$x = self::unpackX($packed);
		$y = self::unpackY($packed);
		if ($symmetry & self::MIRROR_X)
			$x = 18 - $x;
		if ($symmetry & self::MIRROR_Y)
			$y = 18 - $y;
		return $symmetry & self::TRANSPOSE ? self::pack($y, $x) : self::pack($x, $y);
	}

	public static function shift(int $packed, int $shift): int
	{
		return $packed - $shift + BoardPosition::ZERO;
	}

	public static function diff(int $a, int $b): int
	{
		return $a - $b + BoardPosition::ZERO;
	}

	public static function min(int $packed, int $other): int
	{
		return self::pack(min(self::unpackX($packed), self::unpackX($other)), min(self::unpackY($packed), self::unpackY($other)));
	}
}

<?php

// the current range of board position is -31 to +31
// the negative values is to support different transformations related to board comparisons
class BoardPosition
{
	const ZERO = (32 << 6) + 32;

	public static function pack($x, $y): int
	{
		return ($x + 32) << 6 | ($y + 32);
	}

	public static function fromLetters($x, $y): int
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

	public static function mirror(int $packed): int
	{
		return self::pack(self::unpackY($packed), self::unpackX($packed));
	}

	public static function mirrorAround(int $packed, $pivot): int
	{
		$pivotX = self::unpackX($pivot);
		$pivotY = self::unpackY($pivot);
		$x = BoardPosition::unpackX($packed) - $pivotX + $pivotY;
		$y = BoardPosition::unpackY($packed) - $pivotY + $pivotX;
		return BoardPosition::pack($y, $x);
	}

	public static function horizontallyMirroredAround(int $packed, $pivot): int
	{
		$pivotX = self::unpackX($pivot);
		$x = $pivotX + $pivotX - BoardPosition::unpackX($packed);
		return BoardPosition::pack($x, BoardPosition::unpackY($packed));
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

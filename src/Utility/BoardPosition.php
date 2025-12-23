<?php

class BoardPosition
{
	public static function pack($x, $y): int
	{
		return $x << 5 | $y;
	}

	public static function fromLetters($x, $y): int
	{
		return BoardPosition::pack(ord($x) - ord('a'), ord($y) - ord('a'));
	}

	public static function unpackX(int $packed): int
	{
		return $packed >> 5;
	}
	public static function unpackY(int $packed): int
	{
		return $packed & 31;
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
		return BoardPosition::mirror($packed - $pivot) + $pivot;
	}

	public static function shift(int $packed, int $shift): int
	{
		return $packed - $shift;
	}

	public static function diff(int $a, int $b): int
	{
		return $a - $b;
	}

	public static function min(int $packed, int $other): int
	{
		return self::pack(min(self::unpackX($packed), self::unpackX($other)), min(self::unpackY($packed), self::unpackY($other)));
	}
}

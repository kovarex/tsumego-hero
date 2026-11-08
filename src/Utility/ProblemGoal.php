<?php

namespace App\Utility;

//The goal of a problem (tsumego.description, "Black to kill"). A problem without one is "<color> to play", the color
//to move: an empty description means exactly that, so that default is never stored.
class ProblemGoal
{
	//The goal shown for a problem with no description of its own, for the color to move ('B', 'W', 'black' or 'white');
	//'' when the color is unknown
	public static function default(?string $colorToMove): string
	{
		return match (strtolower((string) $colorToMove))
		{
			'b', 'black' => 'Black to play',
			'w', 'white' => 'White to play',
			default => '',
		};
	}

	public static function isEmpty(?string $description): bool
	{
		return trim(strip_tags((string) $description)) === '';
	}

	//The description, or the default goal when it has none
	public static function orDefault(?string $description, ?string $colorToMove): string
	{
		return self::isEmpty($description) ? self::default($colorToMove) : (string) $description;
	}

	//The description to store: the default goal of the color to move ("Black to play", with or without a full stop,
	//in any case) is stored as empty, any other description as it is
	public static function forStorage(?string $description, ?string $colorToMove): string
	{
		$description = (string) $description;
		$default = self::default($colorToMove);
		if ($default !== '' && strcasecmp(rtrim(trim($description), '.'), $default) === 0)
			return '';
		return $description;
	}
}

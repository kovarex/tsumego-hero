<?php

namespace App\Utility;

/**
 * Draws the board avatar of a player.
 *
 * The drawing is a 5x5 board mirrored down the middle, where every free cell is
 * empty or carries a black or a white stone. Keeping the mirror keeps the
 * drawing calm, and the second stone colour carries the information the mirror
 * would otherwise throw away: a mirrored board with black stones only has 2^15
 * drawings, which 60% of the players would end up sharing with someone.
 *
 * The drawing is a function of the account id alone, and it is served from
 * /avatar/<id>, so one url covers the 24px avatar in a list and the 64px one on
 * a profile.
 */
class Avatar
{
	/**
	 * Bump this when the drawing changes. Browsers hold the old image for a
	 * year, so a change also needs the version in the url (User::getAvatarUrl).
	 */
	public const VERSION = 1;

	public const GRID_SIZE = 5;

	/** The free cells of a mirrored board, three states each. */
	public const PATTERN_COUNT = 14348907; // 3 ** 15

	public const WOOD_COLOR = '#dcb35c';
	public const RIM_COLOR = '#a9762f';
	public const BLACK_STONE = '#1b1b1b';
	public const WHITE_STONE = '#f7f4ef';
	public const STONE_OUTLINE = '#8a7350';
	public const GRID_COLOR = '#6b4a10';

	/** The side of the drawing, in svg user units. */
	private const CANVAS = 100;

	/** The width of the frame around the playing surface. */
	private const RIM = 2;

	/**
	 * The stones of the board, row by row, as 25 characters: 0 empty, 1 black,
	 * 2 white. The same account always gets the same board.
	 *
	 * @param int $userId
	 * @return string
	 */
	public static function pattern(int $userId): string
	{
		$hash = hash('sha256', (string) $userId);
		$number = hexdec(substr($hash, 0, 12)) % self::PATTERN_COUNT;

		$cells = array_fill(0, self::GRID_SIZE * self::GRID_SIZE, '0');
		$freeColumns = intdiv(self::GRID_SIZE + 1, 2);
		for ($row = 0; $row < self::GRID_SIZE; $row++)
			for ($column = 0; $column < $freeColumns; $column++)
			{
				$cells[$row * self::GRID_SIZE + $column] = (string) ($number % 3);
				$number = intdiv($number, 3);

				$mirrored = self::GRID_SIZE - 1 - $column;
				if ($mirrored !== $column)
					$cells[$row * self::GRID_SIZE + $mirrored] = $cells[$row * self::GRID_SIZE + $column];
			}
		return implode('', $cells);
	}

	/**
	 * The board as an svg that scales to whatever size the caller shows it in.
	 *
	 * @param int $userId
	 * @return string
	 */
	public static function svg(int $userId): string
	{
		$pattern = self::pattern($userId);
		$surface = self::CANVAS - 2 * self::RIM;
		$cell = $surface / self::GRID_SIZE;

		$lines = '';
		for ($i = 0; $i < self::GRID_SIZE; $i++)
		{
			$position = self::RIM + $cell / 2 + $i * $cell;
			$lines .= '<line x1="' . $position . '" y1="' . self::RIM . '" x2="' . $position . '" y2="' . (self::CANVAS - self::RIM) . '"/>';
			$lines .= '<line x1="' . self::RIM . '" y1="' . $position . '" x2="' . (self::CANVAS - self::RIM) . '" y2="' . $position . '"/>';
		}

		$stones = '';
		for ($i = 0; $i < self::GRID_SIZE * self::GRID_SIZE; $i++)
		{
			$state = $pattern[$i];
			if ($state === '0')
				continue;

			$centerX = self::RIM + ($i % self::GRID_SIZE) * $cell + $cell / 2;
			$centerY = self::RIM + intdiv($i, self::GRID_SIZE) * $cell + $cell / 2;
			$radius = $cell * 0.34;
			$white = $state === '2';
			// A stone of a real board carries the light of the room: a gradient across the
			// stone and, on a black one, the small bright spot of the reflection.
			$stones .= '<circle cx="' . $centerX . '" cy="' . $centerY . '" r="' . $radius . '" fill="url(#stone' . ($white ? 'w' : 'b') . ')"'
				. ($white ? ' stroke="' . self::STONE_OUTLINE . '" stroke-width="1"' : '') . '/>';
			if (!$white)
				$stones .= '<circle cx="' . ($centerX - $radius * 0.3) . '" cy="' . ($centerY - $radius * 0.35) . '" r="' . $radius * 0.26 . '" fill="#ffffff" opacity="0.22"/>';
		}

		// The frame is what makes a sharp outer edge look like the edge of a board
		// rather than a misplaced crop of one. Both it and the playing surface are
		// shaded, so a board reads as a piece of wood and not as a flat sticker.
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . self::CANVAS . ' ' . self::CANVAS . '">'
			. '<defs>'
			. '<linearGradient id="wood" x1="0" y1="0" x2="1" y2="1">'
			. '<stop offset="0" stop-color="#e6c179"/><stop offset="0.5" stop-color="' . self::WOOD_COLOR . '"/><stop offset="1" stop-color="#cfa445"/>'
			. '</linearGradient>'
			. '<linearGradient id="rim" x1="0" y1="0" x2="0" y2="1">'
			. '<stop offset="0" stop-color="#b98536"/><stop offset="0.6" stop-color="' . self::RIM_COLOR . '"/><stop offset="1" stop-color="#96682a"/>'
			. '</linearGradient>'
			. '<radialGradient id="stoneb" cx="0.35" cy="0.3" r="0.85">'
			. '<stop offset="0" stop-color="#4a4a4a"/><stop offset="0.55" stop-color="' . self::BLACK_STONE . '"/><stop offset="1" stop-color="#0d0d0d"/>'
			. '</radialGradient>'
			. '<radialGradient id="stonew" cx="0.35" cy="0.3" r="0.9">'
			. '<stop offset="0" stop-color="#ffffff"/><stop offset="0.7" stop-color="' . self::WHITE_STONE . '"/><stop offset="1" stop-color="#ddd6c8"/>'
			. '</radialGradient>'
			. '</defs>'
			. '<rect width="' . self::CANVAS . '" height="' . self::CANVAS . '" fill="url(#rim)"/>'
			. '<rect x="' . self::RIM . '" y="' . self::RIM . '" width="' . $surface . '" height="' . $surface . '" fill="url(#wood)"/>'
			. '<g stroke="' . self::GRID_COLOR . '" stroke-opacity="0.32" stroke-width="1.5">' . $lines . '</g>'
			. $stones
			. '</svg>';
	}
}

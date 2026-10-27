<?php

namespace App\Utility;

use App\Utility\Drawing\Canvas;
use App\Utility\Drawing\GdCanvas;
use App\Utility\Drawing\SvgCanvas;

//Draws the board avatar of a player.
//The drawing is a 5x5 board mirrored down the middle, where every free cell is
//empty or carries a black or a white stone. Keeping the mirror keeps the
//drawing calm, and the second stone colour carries the information the mirror
//would otherwise throw away: a mirrored board with black stones only has 2^15
//drawings, which 60% of the players would end up sharing with someone.
//The drawing is a function of the display name of the account: the name is
//hashed, and the first bytes of that hash are both the number the board is
//drawn from and the url segment it is served from (/board/<key>). So one url
//covers the 24px avatar in a list and the 64px one on a profile, and the
//endpoint behind it needs no row and no name.
class Avatar
{
	//The version of the drawing: part of the etag of a board, so a browser that
	//asks again gets the new drawing. A browser that keeps the image for a year
	//without asking needs another url as well, so a drawing that has to reach
	//everybody at once changes the url and not only this number.
	public const VERSION = 1;

	//The path a board is served from, followed by the key of a name.
	public const URL_PREFIX = '/board/';

	//The hex characters of the sha256 of a name that the board is drawn from.
	public const KEY_LENGTH = 12;

	//The side of the avatar a page shares, in pixels.
	public const SHARE_SIZE = 512;

	public const GRID_SIZE = 5;

	//The free cells of a mirrored board, three states each.
	public const PATTERN_COUNT = 14348907; // 3 ** 15

	public const WOOD_COLOR = '#dcb35c';
	public const RIM_COLOR = '#a9762f';
	public const BLACK_STONE = '#1b1b1b';
	public const WHITE_STONE = '#f7f4ef';
	public const STONE_OUTLINE = '#8a7350';
	public const GRID_COLOR = '#6b4a10';

	//The shading of the wood, top to bottom.
	private const WOOD_STOPS = [[0.0, '#e6c179'], [0.5, self::WOOD_COLOR], [1.0, '#cfa445']];

	//The shading of the frame around the playing surface.
	private const RIM_STOPS = [[0.0, '#b98536'], [0.6, self::RIM_COLOR], [1.0, '#96682a']];

	//The shading across a black stone, from the light to the dark side.
	private const BLACK_STONE_STOPS = [[0.0, '#4a4a4a'], [0.55, self::BLACK_STONE], [1.0, '#0d0d0d']];

	//The shading across a white stone, from the light to the dark side.
	private const WHITE_STONE_STOPS = [[0.0, '#ffffff'], [0.7, self::WHITE_STONE], [1.0, '#ddd6c8']];

	//How much of the bright spot of the reflection a black stone keeps.
	private const STONE_HIGHLIGHT_OPACITY = 0.22;

	private const HIGHLIGHT_COLOR = '#ffffff';

	//The width of the outline a white stone needs to stand out from pale wood.
	private const STONE_OUTLINE_WIDTH = 1.0;

	//The grid is drawn thin and faint, so it reads as a guide and not as a board.
	private const GRID_WIDTH = 1.5;
	private const GRID_OPACITY = 0.32;

	//The side of the drawing, in svg user units.
	public const CANVAS = 100;

	//The width of the frame around the playing surface.
	public const RIM = 2;

	//The key of a name: the first bytes of its sha256, in hex. Whatever the name
	//is, up to 50 characters of anything the player could type, its key is short
	//and url safe, and it is the whole input of the drawing.
	public static function key(string $displayName): string
	{
		return substr(hash('sha256', $displayName), 0, self::KEY_LENGTH);
	}

	//The url of the board of a name, as the svg a page shows.
	public static function url(string $displayName): string
	{
		return self::boardPath($displayName);
	}

	//The url of the same board as the png a page shares. Sharing a preview is the
	//one place where the picture cannot be an svg, so the picture is asked for by
	//its url and not by a header.
	public static function pngUrl(string $displayName): string
	{
		return self::boardPath($displayName) . '.png';
	}

	//The path of a board, which the two urls extend: one with the svg the page
	//shows, one with the png a share preview takes.
	private static function boardPath(string $displayName): string
	{
		return self::URL_PREFIX . self::key($displayName);
	}

	//The stones of the board, row by row, as 25 characters: 0 empty, 1 black,
	//2 white. The same key always gets the same board.
	//$key: Key of a name (self::key)
	public static function pattern(string $key): string
	{
		$number = hexdec($key) % self::PATTERN_COUNT;

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

	//The middle line of every row and column of the board, in the units of the
	//drawing. Both renditions draw the grid from here.
	/** @return array<int, float> */
	private static function gridPositions(): array
	{
		$cell = (self::CANVAS - 2 * self::RIM) / self::GRID_SIZE;
		$positions = [];
		for ($i = 0; $i < self::GRID_SIZE; $i++)
			$positions[] = self::RIM + $cell / 2 + $i * $cell;

		return $positions;
	}

	//The stones of a board, in the units of the drawing. Both renditions place
	//them here, so a stone cannot move in one picture and not in the other.
	//$key: Key of a name (self::key)
	/** @return array<int, array{0: float, 1: float, 2: float, 3: bool}> x, y, radius, white */
	private static function stones(string $key): array
	{
		$pattern = self::pattern($key);
		$cell = (self::CANVAS - 2 * self::RIM) / self::GRID_SIZE;

		$stones = [];
		for ($i = 0; $i < self::GRID_SIZE * self::GRID_SIZE; $i++)
		{
			if ($pattern[$i] === '0')
				continue;

			$stones[] = [
				self::RIM + ($i % self::GRID_SIZE) * $cell + $cell / 2,
				self::RIM + intdiv($i, self::GRID_SIZE) * $cell + $cell / 2,
				$cell * 0.34,
				$pattern[$i] === '2',
			];
		}

		return $stones;
	}

	//The drawing of a board, written once against a canvas. The svg a page shows
	//and the png a share preview takes are two canvases of this one drawing, so
	//a stone, a line or a colour cannot be in one rendition and not the other.
	//$key: Key of a name (self::key)
	public static function draw(Canvas $canvas, string $key): void
	{
		// The frame is what makes a sharp outer edge look like the edge of a board
		// rather than a misplaced crop of one. Both it and the playing surface are
		// shaded, so a board reads as a piece of wood and not as a flat sticker.
		$surface = self::CANVAS - 2 * self::RIM;
		$canvas->gradientRect(0, 0, self::CANVAS, self::CANVAS, self::RIM_STOPS, 'rim');
		$canvas->gradientRect(self::RIM, self::RIM, $surface, $surface, self::WOOD_STOPS, 'wood');

		foreach (self::gridPositions() as $position)
		{
			$canvas->line($position, self::RIM, $position, self::CANVAS - self::RIM, self::GRID_COLOR, self::GRID_WIDTH, self::GRID_OPACITY);
			$canvas->line(self::RIM, $position, self::CANVAS - self::RIM, $position, self::GRID_COLOR, self::GRID_WIDTH, self::GRID_OPACITY);
		}

		foreach (self::stones($key) as [$centerX, $centerY, $radius, $white])
		{
			// A stone of a real board carries the light of the room: a shading across the
			// stone and, on a black one, the small bright spot of the reflection.
			$canvas->gradientCircle(
				$centerX,
				$centerY,
				$radius,
				$white ? self::WHITE_STONE_STOPS : self::BLACK_STONE_STOPS,
				$white ? 'stonew' : 'stoneb',
				$white ? self::STONE_OUTLINE : '',
				$white ? self::STONE_OUTLINE_WIDTH : 0.0
			);
			if (!$white)
				$canvas->circle($centerX - $radius * 0.3, $centerY - $radius * 0.35, $radius * 0.26, self::HIGHLIGHT_COLOR, self::STONE_HIGHLIGHT_OPACITY);
		}
	}

	//The board as an svg that scales to whatever size the caller shows it in.
	//$key: Key of a name (self::key)
	public static function svg(string $key): string
	{
		$canvas = new SvgCanvas(self::CANVAS);
		self::draw($canvas, $key);

		return $canvas->picture();
	}

	//The board as a png. A share preview of several networks takes a picture and
	//not an svg, so a page shares this drawing and shows the svg in its own pages.
	//$key: Key of a name (self::key)
	//$size: Side of the picture in pixels
	//Returns PNG data
	public static function png(string $key, int $size = self::SHARE_SIZE): string
	{
		assert($size > 0, 'A board picture needs a size');

		// The picture is painted at twice the size it is served at; sampling it down
		// is what gives the stones and the grid their smooth edges.
		$canvas = new GdCanvas($size * 2, self::CANVAS);
		self::draw($canvas, $key);

		return $canvas->png($size);
	}
}

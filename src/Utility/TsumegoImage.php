<?php

namespace App\Utility;

use Cake\Http\Exception\InternalErrorException;
use GdImage;

//The picture of a problem that is shared on social sites (Open Graph): the part of the board
//its stones are on, turned to landscape, with coordinates.
//When the picture changes, bump Constants::$TSUMEGO_IMAGE_VERSION so that the cached ones are dropped.
class TsumegoImage
{
	//Intersections shown around the stones, where the board goes on
	private const PADDING = 2;
	private const MARGIN = 20;
	private const FRAME_WIDTH = 4;
	//The stone radius plus its shadow beyond the outer lines, in cells
	private const STONE_OVERHANG = 0.55;
	//The room of the coordinates on the right and at the bottom, in cells
	private const LABEL_SPACE = 1.0;
	private const COLUMN_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'];

	private GdImage $image;
	private int $minX;
	private int $minY;
	private int $maxX;
	private int $maxY;
	//Portrait crops are drawn turned: the board's x goes down the picture and its y across
	private bool $rotate;
	//The size of the crop in intersections, as drawn (after the turn)
	private int $cropWidth;
	private int $cropHeight;
	private float $cellSize;
	//The pixel of the top left intersection, and of the right and bottom lines
	private float $boardX;
	private float $boardY;
	private float $boardRight;
	private float $boardBottom;
	private bool $isLeftEdge;
	private bool $isRightEdge;
	private bool $isTopEdge;
	private bool $isBottomEdge;

	//The PNG of the problem's setup stones
	public static function png(string $sgf): string
	{
		$stones = self::setupStones($sgf);
		if (empty($stones))
			throw new InternalErrorException('No stones found in SGF');
		return new self($stones, SgfParser::boardSize($sgf))->draw($stones);
	}

	/** @param list<array{x: int, y: int, color: string}> $stones */
	private function __construct(array $stones, private int $boardSize)
	{
		$this->crop($stones);
		$this->layOut();
	}

	//The stones with PADDING intersections around them, within the board
	/** @param list<array{x: int, y: int, color: string}> $stones */
	private function crop(array $stones): void
	{
		$minX = $minY = $this->boardSize - 1;
		$maxX = $maxY = 0;
		foreach ($stones as $stone)
		{
			$minX = min($minX, $stone['x']);
			$maxX = max($maxX, $stone['x']);
			$minY = min($minY, $stone['y']);
			$maxY = max($maxY, $stone['y']);
		}
		$this->minX = max(0, $minX - self::PADDING);
		$this->minY = max(0, $minY - self::PADDING);
		$this->maxX = min($this->boardSize - 1, $maxX + self::PADDING);
		$this->maxY = min($this->boardSize - 1, $maxY + self::PADDING);

		$width = $this->maxX - $this->minX + 1;
		$height = $this->maxY - $this->minY + 1;
		$this->rotate = $height > $width;
		$this->cropWidth = $this->rotate ? $height : $width;
		$this->cropHeight = $this->rotate ? $width : $height;

		$this->isLeftEdge = ($this->rotate ? $this->minY : $this->minX) === 0;
		$this->isRightEdge = ($this->rotate ? $this->maxY : $this->maxX) === $this->boardSize - 1;
		$this->isTopEdge = ($this->rotate ? $this->minX : $this->minY) === 0;
		$this->isBottomEdge = ($this->rotate ? $this->maxX : $this->maxY) === $this->boardSize - 1;
	}

	//The largest cells that fit the picture with the stones and the coordinates, the board
	//centered and shifted up and left by half the room of the coordinates
	private function layOut(): void
	{
		$effectiveCropWidth = ($this->cropWidth - 1) + 2 * self::STONE_OVERHANG + self::LABEL_SPACE;
		$effectiveCropHeight = ($this->cropHeight - 1) + 2 * self::STONE_OVERHANG + self::LABEL_SPACE;
		$availableWidth = Constants::$OG_IMAGE_WIDTH - (2 * self::MARGIN);
		$availableHeight = Constants::$OG_IMAGE_HEIGHT - (2 * self::MARGIN);
		$this->cellSize = min($availableWidth / $effectiveCropWidth, $availableHeight / $effectiveCropHeight);

		$boardPixelsWidth = ($this->cropWidth - 1) * $this->cellSize;
		$boardPixelsHeight = ($this->cropHeight - 1) * $this->cellSize;
		$labelPixels = $this->cellSize * self::LABEL_SPACE * 0.5;
		$this->boardX = (Constants::$OG_IMAGE_WIDTH - $boardPixelsWidth) / 2 - $labelPixels;
		$this->boardY = (Constants::$OG_IMAGE_HEIGHT - $boardPixelsHeight) / 2 - $labelPixels;
		$this->boardRight = $this->boardX + ($this->cropWidth - 1) * $this->cellSize;
		$this->boardBottom = $this->boardY + ($this->cropHeight - 1) * $this->cellSize;
	}

	/** @param list<array{x: int, y: int, color: string}> $stones */
	private function draw(array $stones): string
	{
		$this->image = imagecreatetruecolor(Constants::$OG_IMAGE_WIDTH, Constants::$OG_IMAGE_HEIGHT);
		imageantialias($this->image, true);
		imagefill($this->image, 0, 0, imagecolorallocate($this->image, 220, 179, 92)); // wood

		$this->drawBoard();
		$lineColor = imagecolorallocate($this->image, 0, 0, 0);
		$this->drawGrid($lineColor);
		$this->drawStarPoints($lineColor);
		// before the stones, which cover the labels at the edges
		$this->drawLabels();
		$stoneRadius = $this->cellSize * 0.45;
		foreach ($stones as $stone)
		{
			[$x, $y] = $this->pixel($stone['x'], $stone['y']);
			$this->drawStone((int) $x, (int) $y, $stoneRadius, $stone['color'] === 'B');
		}

		ob_start();
		imagepng($this->image, null, 6);
		$png = ob_get_clean();
		imagedestroy($this->image);
		return $png;
	}

	//The pixel of an intersection of the board
	/** @return array{float, float} */
	private function pixel(int $x, int $y): array
	{
		if ($this->rotate)
			return [$this->boardX + ($y - $this->minY) * $this->cellSize, $this->boardY + ($x - $this->minX) * $this->cellSize];
		return [$this->boardX + ($x - $this->minX) * $this->cellSize, $this->boardY + ($y - $this->minY) * $this->cellSize];
	}

	//The board, with its frame on the sides where it really ends
	private function drawBoard(): void
	{
		$frame = self::FRAME_WIDTH;
		$boardColor = imagecolorallocate($this->image, 210, 170, 85);
		$borderColor = imagecolorallocate($this->image, 60, 40, 10);
		$bx0 = (int) $this->boardX;
		$by0 = (int) $this->boardY;
		$bx1 = (int) $this->boardRight;
		$by1 = (int) $this->boardBottom;
		imagefilledrectangle($this->image, $bx0, $by0, $bx1, $by1, $boardColor);
		if ($this->isLeftEdge)
			imagefilledrectangle($this->image, $bx0 - $frame, $by0 - ($this->isTopEdge ? $frame : 0), $bx0 - 1, $by1 + ($this->isBottomEdge ? $frame : 0), $borderColor);
		if ($this->isRightEdge)
			imagefilledrectangle($this->image, $bx1 + 1, $by0 - ($this->isTopEdge ? $frame : 0), $bx1 + $frame, $by1 + ($this->isBottomEdge ? $frame : 0), $borderColor);
		if ($this->isTopEdge)
			imagefilledrectangle($this->image, $bx0 - ($this->isLeftEdge ? $frame : 0), $by0 - $frame, $bx1 + ($this->isRightEdge ? $frame : 0), $by0 - 1, $borderColor);
		if ($this->isBottomEdge)
			imagefilledrectangle($this->image, $bx0 - ($this->isLeftEdge ? $frame : 0), $by1 + 1, $bx1 + ($this->isRightEdge ? $frame : 0), $by1 + $frame, $borderColor);
	}

	//The lines, thicker where the crop is at the edge of the board
	private function drawGrid(int $lineColor): void
	{
		$left = (int) $this->boardX;
		$top = (int) $this->boardY;
		$right = (int) $this->boardRight;
		$bottom = (int) $this->boardBottom;
		imagesetthickness($this->image, 1);
		for ($i = 0; $i < $this->cropWidth; $i++)
		{
			$x = (int) ($this->boardX + $i * $this->cellSize);
			imageline($this->image, $x, $top, $x, $bottom, $lineColor);
		}
		for ($i = 0; $i < $this->cropHeight; $i++)
		{
			$y = (int) ($this->boardY + $i * $this->cellSize);
			imageline($this->image, $left, $y, $right, $y, $lineColor);
		}

		imagesetthickness($this->image, 3);
		if ($this->isLeftEdge)
			imageline($this->image, $left, $top, $left, $bottom, $lineColor);
		if ($this->isRightEdge)
			imageline($this->image, $right, $top, $right, $bottom, $lineColor);
		if ($this->isTopEdge)
			imageline($this->image, $left, $top, $right, $top, $lineColor);
		if ($this->isBottomEdge)
			imageline($this->image, $left, $bottom, $right, $bottom, $lineColor);
		imagesetthickness($this->image, 1);
	}

	private function drawStarPoints(int $lineColor): void
	{
		$starSize = (int) ($this->cellSize * 0.2);
		foreach (self::starPoints($this->boardSize) as [$pointX, $pointY])
			if ($pointX >= $this->minX && $pointX <= $this->maxX && $pointY >= $this->minY && $pointY <= $this->maxY)
			{
				[$x, $y] = $this->pixel($pointX, $pointY);
				imagefilledellipse($this->image, (int) $x, (int) $y, $starSize, $starSize, $lineColor);
			}
	}

	//The column letters at the bottom and the row numbers on the right; row 1 is the bottom row of the board
	private function drawLabels(): void
	{
		$labelColor = imagecolorallocate($this->image, 140, 115, 60);
		$fontPath = dirname(__DIR__, 2) . '/resources/fonts/NimbusSans-Bold.otf';
		$fontSize = max(10, $this->cellSize * 0.45);
		$gap = self::FRAME_WIDTH + $this->cellSize * 0.55; // clear of the stones at the edges

		for ($i = 0; $i < $this->cropWidth; $i++)
		{
			$column = $this->rotate ? ($this->minY + $i) : ($this->minX + $i);
			if ($column < 0 || $column >= count(self::COLUMN_LETTERS))
				continue;
			$letter = self::COLUMN_LETTERS[$column];
			[$textWidth, $textHeight] = self::textSize($fontSize, $fontPath, $letter);
			$x = $this->boardX + $i * $this->cellSize;
			imagettftext($this->image, $fontSize, 0, (int) ($x - $textWidth / 2), (int) ($this->boardBottom + $gap + $textHeight), $labelColor, $fontPath, $letter);
		}

		for ($i = 0; $i < $this->cropHeight; $i++)
		{
			$row = $this->rotate ? ($this->minX + $i) : ($this->minY + $i);
			$label = (string) ($this->boardSize - $row);
			[, $textHeight] = self::textSize($fontSize, $fontPath, $label);
			$y = $this->boardY + $i * $this->cellSize;
			imagettftext($this->image, $fontSize, 0, (int) ($this->boardRight + $gap), (int) ($y + $textHeight / 2), $labelColor, $fontPath, $label);
		}
	}

	/** @return array{int, int} */
	private static function textSize(float $fontSize, string $fontPath, string $text): array
	{
		$box = imagettfbbox($fontSize, 0, $fontPath, $text);
		return [$box[2] - $box[0], $box[1] - $box[7]];
	}

	//A stone with its shadow, shaded by concentric circles that go from the base colour at the
	//edge to the highlight up and left of the center, as light reflected from it
	private function drawStone(int $cx, int $cy, float $radius, bool $isBlack): void
	{
		$shadowOffset = max(2, (int) ($radius * 0.08));
		$shadowDiameter = (int) ($radius * 2.05);
		imagefilledellipse($this->image, $cx + $shadowOffset, $cy + $shadowOffset, $shadowDiameter, $shadowDiameter, imagecolorallocate($this->image, 160, 130, 60));

		[$base, $highlight] = $isBlack ? [[10, 10, 10], [100, 100, 100]] : [[195, 195, 200], [255, 255, 255]];
		$steps = max(8, (int) ($radius * 0.6));
		$highlightOffsetX = -$radius * 0.3;
		$highlightOffsetY = -$radius * 0.35;
		for ($i = $steps; $i >= 0; $i--)
		{
			$ratio = ($steps - $i) / $steps; // 0 at the edge, 1 at the highlight
			$x = $cx + $highlightOffsetX * $ratio;
			$y = $cy + $highlightOffsetY * $ratio;
			$color = imagecolorallocate($this->image,
				(int) ($base[0] + ($highlight[0] - $base[0]) * $ratio),
				(int) ($base[1] + ($highlight[1] - $base[1]) * $ratio),
				(int) ($base[2] + ($highlight[2] - $base[2]) * $ratio));
			$diameter = (int) ($radius * 2.0 * ($i + 1) / ($steps + 1));
			imagefilledellipse($this->image, (int) $x, (int) $y, $diameter, $diameter, $color);
		}

		if (!$isBlack)
			imageellipse($this->image, $cx, $cy, (int) ($radius * 2), (int) ($radius * 2), imagecolorallocate($this->image, 100, 100, 100));
	}

	/** @return list<array{int, int}> */
	private static function starPoints(int $boardSize): array
	{
		return match ($boardSize)
		{
			19 => [[3, 3], [9, 3], [15, 3], [3, 9], [9, 9], [15, 9], [3, 15], [9, 15], [15, 15]],
			13 => [[3, 3], [9, 3], [6, 6], [3, 9], [9, 9]],
			9 => [[2, 2], [6, 2], [4, 4], [2, 6], [6, 6]],
			default => [],
		};
	}

	//The stones of the first AB and the first AW property
	/** @return list<array{x: int, y: int, color: string}> */
	private static function setupStones(string $sgf): array
	{
		$stones = [];
		foreach (SgfParser::firstValues($sgf, ['AB', 'AW']) as $identifier => $values)
			foreach (SgfParser::points($values) as [$x, $y])
				$stones[] = ['x' => ord($x) - ord('a'), 'y' => ord($y) - ord('a'), 'color' => $identifier[1]];
		return $stones;
	}
}

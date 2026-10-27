<?php

namespace App\Utility\Drawing;

//A canvas that paints the drawing into a picture with gd, the rendition a share
//preview takes: no network accepts an svg as its preview image, and gd is the
//one graphics library a web host is sure to have.
//The picture is painted larger than it is served and sampled down on the way
//out, which is what gives the edges of the stones and of the grid their
//smoothness; gd itself draws without them.
class GdCanvas implements Canvas
{
	private \GdImage $image;

	//Pixels per unit of the drawing.
	private float $scale;

	//$side: Side of the picture the drawing is painted into, in pixels
	//$units: Side of the drawing itself, in its own units
	public function __construct(int $side, float $units)
	{
		assert($side > 0 && $units > 0, 'A canvas needs a size');

		$this->image = imagecreatetruecolor($side, $side);
		imagealphablending($this->image, true);
		$this->scale = $side / $units;
	}

	public function gradientRect(float $x, float $y, float $width, float $height, array $stops, string $name): void
	{
		$left = (int) round($x * $this->scale);
		$right = (int) round(($x + $width) * $this->scale);
		$top = (int) round($y * $this->scale);
		$rows = max(1, (int) round($height * $this->scale));

		for ($row = 0; $row < $rows; $row++)
		{
			$position = $rows > 1 ? $row / ($rows - 1) : 0.0;
			imageline($this->image, $left, $top + $row, $right - 1, $top + $row, $this->allocate($stops, $position));
		}
	}

	public function gradientCircle(float $x, float $y, float $radius, array $stops, string $name, string $outline = '', float $outlineWidth = 0.0): void
	{
		$centerX = $x * $this->scale;
		$centerY = $y * $this->scale;
		$pixelRadius = $radius * $this->scale;

		// A stroke sits on the edge of a shape, half of it inside, so the stone
		// itself ends where the stroke begins.
		$stroke = $outline === '' ? 0.0 : $outlineWidth * $this->scale;
		$edge = $pixelRadius - $stroke / 2;
		if ($stroke > 0)
			imagefilledellipse($this->image, (int) round($centerX), (int) round($centerY),
				(int) round(2 * ($edge + $stroke)), (int) round(2 * ($edge + $stroke)), $this->color($outline));

		// The light sits up and to the left of the middle, so every ring is a smaller
		// copy of the stone pulled towards it: the rings stay inside the stone and the
		// one at the light is drawn last, over the ones behind it.
		$lightX = $centerX + (self::LIGHT_X - 0.5) * 2 * $edge;
		$lightY = $centerY + (self::LIGHT_Y - 0.5) * 2 * $edge;
		$reach = 1.5 * $edge / (2 * $edge * self::LIGHT_SPREAD);

		$rings = max(8, (int) round($edge / 2));
		for ($ring = 0; $ring < $rings; $ring++)
		{
			$share = $ring / $rings;
			$diameter = max(1, (int) round(2 * $edge * (1 - $share)));
			imagefilledellipse(
				$this->image,
				(int) round($centerX + ($lightX - $centerX) * $share),
				(int) round($centerY + ($lightY - $centerY) * $share),
				$diameter,
				$diameter,
				$this->allocate($stops, min(1.0, (1 - $share) * $reach))
			);
		}
	}

	public function line(float $x1, float $y1, float $x2, float $y2, string $color, float $width, float $opacity = 1.0): void
	{
		imagesetthickness($this->image, max(1, (int) round($width * $this->scale)));
		imageline($this->image, (int) round($x1 * $this->scale), (int) round($y1 * $this->scale),
			(int) round($x2 * $this->scale), (int) round($y2 * $this->scale), $this->color($color, $opacity));
		imagesetthickness($this->image, 1);
	}

	public function circle(float $x, float $y, float $radius, string $color, float $opacity = 1.0): void
	{
		$diameter = max(2, (int) round(2 * $radius * $this->scale));
		imagefilledellipse($this->image, (int) round($x * $this->scale), (int) round($y * $this->scale),
			$diameter, $diameter, $this->color($color, $opacity));
	}

	//The drawing as png data of the requested size. The picture is painted at
	//twice that size and sampled down here, which is the whole of its smoothing.
	//The canvas is spent afterwards: a second picture needs a second drawing.
	//$size: Side of the picture in pixels
	//Returns PNG data
	public function png(int $size): string
	{
		$picture = imagecreatetruecolor($size, $size);
		imagecopyresampled($picture, $this->image, 0, 0, 0, 0, $size, $size,
			imagesx($this->image), imagesy($this->image));

		ob_start();
		imagepng($picture, null, 6);
		$data = (string) ob_get_clean();

		imagedestroy($picture);
		imagedestroy($this->image);

		return $data;
	}

	//$position: Between 0 and 1
	//Returns colour handle of the picture
	/** @param array<int, array{0: float, 1: string}> $stops */
	private function allocate(array $stops, float $position): int
	{
		$previousOffset = $stops[0][0];
		$previousColor = self::rgb($stops[0][1]);
		foreach ($stops as [$offset, $color])
		{
			if ($position <= $offset)
			{
				$span = $offset - $previousOffset;
				$share = $span > 0 ? ($position - $previousOffset) / $span : 1.0;
				$next = self::rgb($color);

				return imagecolorallocate($this->image,
					(int) round($previousColor[0] + ($next[0] - $previousColor[0]) * $share),
					(int) round($previousColor[1] + ($next[1] - $previousColor[1]) * $share),
					(int) round($previousColor[2] + ($next[2] - $previousColor[2]) * $share));
			}
			$previousOffset = $offset;
			$previousColor = self::rgb($color);
		}

		return imagecolorallocate($this->image, ...$previousColor);
	}

	//$hex: Colour as written in the drawing
	//$opacity: How much of the colour is kept
	//Returns colour handle of the picture
	private function color(string $hex, float $opacity = 1.0): int
	{
		[$red, $green, $blue] = self::rgb($hex);

		return imagecolorallocatealpha($this->image, $red, $green, $blue, (int) round((1 - $opacity) * 127));
	}

	/** @return array{0: int, 1: int, 2: int} */
	private static function rgb(string $hex): array
	{
		$value = (int) hexdec(ltrim($hex, '#'));

		return [($value >> 16) & 0xff, ($value >> 8) & 0xff, $value & 0xff];
	}
}

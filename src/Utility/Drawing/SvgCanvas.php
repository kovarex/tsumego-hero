<?php

namespace App\Utility\Drawing;

//A canvas that writes the drawing as svg, the rendition a page shows: it stays
//sharp at any size and the browser draws it without help from the server.
class SvgCanvas implements Canvas
{
	//The side of the drawing, in svg user units.
	private float $side;

	private string $definitions = '';
	private string $body = '';

	/** @var array<string, string> Shadings already defined, by name */
	private array $defined = [];

	public function __construct(float $side)
	{
		$this->side = $side;
	}

	public function gradientRect(float $x, float $y, float $width, float $height, array $stops, string $name): void
	{
		$this->define($name, '<linearGradient id="' . $name . '" x1="0" y1="0" x2="0" y2="1">' . self::stops($stops) . '</linearGradient>');
		$this->body .= '<rect' . self::point($x, $y)
			. ' width="' . self::number($width) . '" height="' . self::number($height) . '" fill="url(#' . $name . ')"/>';
	}

	public function gradientCircle(float $x, float $y, float $radius, array $stops, string $name, string $outline = '', float $outlineWidth = 0.0): void
	{
		$this->define($name, '<radialGradient id="' . $name . '" cx="' . self::LIGHT_X . '" cy="' . self::LIGHT_Y
			. '" r="' . self::LIGHT_SPREAD . '">' . self::stops($stops) . '</radialGradient>');
		$this->body .= '<circle cx="' . self::number($x) . '" cy="' . self::number($y) . '" r="' . self::number($radius) . '" fill="url(#' . $name . ')"'
			. ($outline === '' ? '' : ' stroke="' . $outline . '" stroke-width="' . self::number($outlineWidth) . '"') . '/>';
	}

	public function line(float $x1, float $y1, float $x2, float $y2, string $color, float $width, float $opacity = 1.0): void
	{
		$this->body .= '<line x1="' . self::number($x1) . '" y1="' . self::number($y1) . '" x2="' . self::number($x2)
			. '" y2="' . self::number($y2) . '" stroke="' . $color . '" stroke-width="' . self::number($width)
			. '" stroke-opacity="' . self::number($opacity) . '"/>';
	}

	public function circle(float $x, float $y, float $radius, string $color, float $opacity = 1.0): void
	{
		$this->body .= '<circle cx="' . self::number($x) . '" cy="' . self::number($y) . '" r="' . self::number($radius)
			. '" fill="' . $color . '" opacity="' . self::number($opacity) . '"/>';
	}

	//The drawing as one svg document, ready for an img tag or a response body.
	public function picture(): string
	{
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . self::number($this->side) . ' ' . self::number($this->side) . '">'
			. '<defs>' . $this->definitions . '</defs>'
			. $this->body
			. '</svg>';
	}

	//A shading is written once however many shapes use it, so a board of 25
	//stones carries two stone shadings and not 25 copies of one. One name has to
	//mean one shading: shapes of one name and different colours would otherwise
	//quietly share the first of them.
	private function define(string $name, string $markup): void
	{
		if (isset($this->defined[$name]))
		{
			assert($this->defined[$name] === $markup, 'One shading name cannot describe two shadings');
			return;
		}

		$this->defined[$name] = $markup;
		$this->definitions .= $markup;
	}

	/** @param array<int, array{0: float, 1: string}> $stops */
	private static function stops(array $stops): string
	{
		$markup = '';
		foreach ($stops as [$offset, $color])
			$markup .= '<stop offset="' . self::number($offset) . '" stop-color="' . $color . '"/>';

		return $markup;
	}

	//Where a shape sits, left out where it sits at the start of the drawing.
	private static function point(float $x, float $y): string
	{
		return ($x == 0 ? '' : ' x="' . self::number($x) . '"') . ($y == 0 ? '' : ' y="' . self::number($y) . '"');
	}

	private static function number(float $value): string
	{
		return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
	}
}

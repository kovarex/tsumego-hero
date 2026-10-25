<?php

namespace App\Utility\Drawing;

/**
 * What a picture is made of. A drawing is written once against this interface,
 * and each implementation is one rendition of it: the svg a page shows and the
 * png a share preview takes are two canvases of the same drawing, so a shape
 * cannot be in one rendition and not in the other.
 *
 * Everything here is in the units of the drawing. A canvas knows how large that
 * is in its own terms, so the drawing does not mention pixels, files or formats.
 */
interface Canvas
{
	/** Where the light of the room sits on a stone, as a share of the stone box. */
	public const LIGHT_X = 0.35;
	public const LIGHT_Y = 0.3;

	/** How far the light of a stone reaches, as a share of the stone box. */
	public const LIGHT_SPREAD = 0.85;

	/**
	 * A rectangle shaded from its top to its bottom.
	 *
	 * @param array<int, array{0: float, 1: string}> $stops Offset from 0 to 1 and colour, from the top down
	 * @param string $name Name the rendition can give this shading
	 * @return void
	 */
	public function gradientRect(float $x, float $y, float $width, float $height, array $stops, string $name): void;

	/**
	 * A filled circle shaded from the light of the room outwards. The outline, if
	 * there is one, sits just outside the circle.
	 *
	 * @param array<int, array{0: float, 1: string}> $stops Offset from 0 to 1 and colour, from the light outwards
	 * @param string $name Name the rendition can give this shading
	 * @param string $outline Colour of the outline, or none
	 * @param float $outlineWidth Width of the outline
	 * @return void
	 */
	public function gradientCircle(float $x, float $y, float $radius, array $stops, string $name, string $outline = '', float $outlineWidth = 0.0): void;

	public function line(float $x1, float $y1, float $x2, float $y2, string $color, float $width, float $opacity = 1.0): void;

	public function circle(float $x, float $y, float $radius, string $color, float $opacity = 1.0): void;
}

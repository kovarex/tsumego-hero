<?php

namespace App\Utility;

//The rank insignia of a mistake training rung, drawn on the training buttons in
//place of the wait of the rung: "1d" reads as one dan in Go, a rank does not.
//The ladder climbs like military ranks: the first half of the rungs (the larger
//half of an odd ladder) are one, two, three and four chevrons, the rest one, two,
//three and four stars, and a crown marks a problem that graduated today. With the
//ladder of eight rungs that is four chevrons and four stars.
//The insignia follow the length of the ladder, so changing the intervals needs no
//change here. Four of a kind is what fits the button (MAX_SYMBOLS), so the two
//kinds take a ladder of up to eight rungs, and a longer one gets a third kind
//between them, bars (chevrons, bars and stars, like the enlisted, the lieutenants
//and the generals), with the rungs shared out evenly, the lower kinds taking the
//remainder. Past twelve rungs the top rungs of a kind share its last badge. The
//tooltip names the rung exactly in every case.
class RungInsignia
{
	public const CHEVRON = 'chevron';
	public const BAR = 'bar';
	public const STAR = 'star';

	//How many symbols of a kind fit the button: four chevrons or stars are 46px wide,
	//which with the padding of the badge just fits the 58px button, and a fifth would
	//stick out of it.
	public const MAX_SYMBOLS = 4;

	//The kind and the number of symbols of a rung in a ladder of $rungCount rungs.
	//A rung above the ladder, left by a ladder that got shorter, shows the top rung,
	//which is also how the next clean solve treats it: it graduates.
	/** @return array{0: string, 1: int} */
	public static function of(int $rung, int $rungCount): array
	{
		$kinds = $rungCount > 2 * self::MAX_SYMBOLS ? [self::CHEVRON, self::BAR, self::STAR] : [self::CHEVRON, self::STAR];
		$rung = max(0, min($rung, $rungCount - 1));
		foreach ($kinds as $index => $kind)
		{
			$size = intdiv($rungCount, count($kinds)) + ($index < $rungCount % count($kinds) ? 1 : 0);
			if ($rung < $size)
				return [$kind, min($rung + 1, self::MAX_SYMBOLS)];
			$rung -= $size;
		}
		return [self::STAR, 1]; // only for an empty ladder, which has no rungs to show
	}

	//The symbols of a rung in a row, as inline SVG painted in the text colour, so the
	//stylesheet picks the colour and the shapes stay sharp at any zoom. The badge
	//around it carries the accessible name, so the drawing is hidden from readers.
	public static function render(string $kind, int $count): string
	{
		[$width, $path] = self::SHAPES[$kind];
		$symbols = '';
		for ($i = 0; $i < $count; $i++)
			$symbols .= '<path' . ($i > 0 ? ' transform="translate(' . ($i * ($width + self::GAP)) . ' 0)"' : '') . ' d="' . $path . '"/>';
		return self::svg($count * $width + ($count - 1) * self::GAP, $symbols);
	}

	//The crown of a problem that graduated today, the rank above the last star.
	public static function renderCrown(): string
	{
		return self::svg(self::CROWN_WIDTH, '<path d="' . self::CROWN . '"/>');
	}

	private static function svg(int $width, string $content): string
	{
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . self::HEIGHT . '"'
			. ' width="' . $width . '" height="' . self::HEIGHT . '" fill="currentColor" aria-hidden="true" focusable="false">'
			. $content . '</svg>';
	}

	//The shapes, each with its width, on a grid of one unit to a CSS pixel at the
	//size the badge draws them: an upward chevron, a bar and a five-pointed star.
	private const SHAPES = [
		self::CHEVRON => [10, 'M0 5.5L5 .5L10 5.5V9.5L5 4.5L0 9.5Z'],
		self::BAR => [3, 'M0 1H3V10H0Z'],
		self::STAR => [10, 'M5 .2L6.2 3.7L9.9 3.8L7 6L8.1 9.6L5 7.5L1.9 9.6L3 6L.1 3.8L3.8 3.7Z'],
	];
	private const CROWN = 'M.5 2L3.5 5L6.5 0L9.5 5L12.5 2L11.2 7.5H1.8ZM1.8 8.5H11.2V10H1.8Z';
	private const CROWN_WIDTH = 13;
	private const HEIGHT = 10;
	private const GAP = 2;
}

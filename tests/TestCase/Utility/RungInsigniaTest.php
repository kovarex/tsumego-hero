<?php

use App\Utility\RungInsignia;

class RungInsigniaTest extends CakeTestCase
{
	/**
	 * The insignia the owner chose for the ladder of six rungs, like military ranks.
	 */
	public function testTheLadderOfSixRungsIsThreeChevronsThenThreeStars(): void
	{
		$this->assertSame([
			[RungInsignia::CHEVRON, 1],
			[RungInsignia::CHEVRON, 2],
			[RungInsignia::CHEVRON, 3],
			[RungInsignia::STAR, 1],
			[RungInsignia::STAR, 2],
			[RungInsignia::STAR, 3],
		], self::insignia(6));
	}

	public function testTheInsigniaFollowTheLengthOfTheLadder(): void
	{
		$this->assertSame([[RungInsignia::CHEVRON, 1]], self::insignia(1));
		$this->assertSame([[RungInsignia::CHEVRON, 1], [RungInsignia::STAR, 1]], self::insignia(2));
		$this->assertSame([[RungInsignia::CHEVRON, 1], [RungInsignia::CHEVRON, 2], [RungInsignia::STAR, 1]], self::insignia(3),
			'An odd ladder has one chevron rung more than star rungs');
		$this->assertSame([
			[RungInsignia::CHEVRON, 1],
			[RungInsignia::CHEVRON, 2],
			[RungInsignia::CHEVRON, 3],
			[RungInsignia::STAR, 1],
			[RungInsignia::STAR, 2],
		], self::insignia(5));
	}

	/**
	 * Three of a kind is what can be counted at a glance, so a ladder that would need
	 * a fourth chevron gets bars between the chevrons and the stars.
	 */
	public function testALadderOfMoreThanSixRungsGetsBarsBetweenTheChevronsAndTheStars(): void
	{
		$this->assertSame([
			[RungInsignia::CHEVRON, 1],
			[RungInsignia::CHEVRON, 2],
			[RungInsignia::CHEVRON, 3],
			[RungInsignia::BAR, 1],
			[RungInsignia::BAR, 2],
			[RungInsignia::STAR, 1],
			[RungInsignia::STAR, 2],
		], self::insignia(7));
		$this->assertSame([
			[RungInsignia::CHEVRON, 1],
			[RungInsignia::CHEVRON, 2],
			[RungInsignia::CHEVRON, 3],
			[RungInsignia::BAR, 1],
			[RungInsignia::BAR, 2],
			[RungInsignia::BAR, 3],
			[RungInsignia::STAR, 1],
			[RungInsignia::STAR, 2],
		], self::insignia(8));
	}

	public function testUpToNineRungsEveryRungHasABadgeOfItsOwnOfAtMostThreeSymbols(): void
	{
		for ($length = 1; $length <= 9; $length++)
		{
			$insignia = self::insignia($length);
			$this->assertSame($insignia, array_values(array_unique($insignia, SORT_REGULAR)), "No two rungs of a ladder of $length share a badge");
			$this->assertLessThanOrEqual(RungInsignia::MAX_OF_A_KIND, max(array_column($insignia, 1)), "A ladder of $length needs no more than three of a kind");
			$this->assertSame([RungInsignia::CHEVRON, 1], $insignia[0], 'The bottom rung is one chevron');
			if ($length > 1)
				$this->assertSame(RungInsignia::STAR, $insignia[$length - 1][0], 'The top rung is a star');
		}
	}

	/**
	 * Past nine rungs the three kinds draw more symbols, as many as fit the button,
	 * and past twelve the top rungs of a kind share its last badge.
	 */
	public function testALadderTooLongForThreeOfAKindDrawsAtMostWhatFits(): void
	{
		$insignia = self::insignia(12);
		$this->assertSame($insignia, array_values(array_unique($insignia, SORT_REGULAR)));
		$this->assertSame(RungInsignia::MAX_SYMBOLS, max(array_column(self::insignia(12), 1)));
		$this->assertSame(RungInsignia::MAX_SYMBOLS, max(array_column(self::insignia(30), 1)));
		$this->assertSame([RungInsignia::STAR, RungInsignia::MAX_SYMBOLS], RungInsignia::of(29, 30));

		// the 58px button less its border and the 4px padding on each side of the badge
		foreach ([RungInsignia::CHEVRON, RungInsignia::BAR, RungInsignia::STAR] as $kind)
		{
			preg_match('/width="(\d+)"/', RungInsignia::render($kind, RungInsignia::MAX_SYMBOLS), $match);
			$this->assertLessThanOrEqual(58 - 2 - 8, (int) $match[1], "The widest $kind badge fits the button");
		}
	}

	/**
	 * A ladder made shorter leaves rungs above its top in the pool, which the next
	 * clean solve graduates, so they show the top rung until then.
	 */
	public function testARungAboveTheLadderShowsTheTopRung(): void
	{
		$this->assertSame([RungInsignia::STAR, 3], RungInsignia::of(7, 6));
	}

	public function testTheBadgeDrawsOneShapePerSymbolInTheTextColour(): void
	{
		foreach ([RungInsignia::CHEVRON, RungInsignia::BAR, RungInsignia::STAR] as $kind)
		{
			$widths = [];
			foreach ([1, 2, 3] as $count)
			{
				$svg = RungInsignia::render($kind, $count);
				$this->assertSame($count, substr_count($svg, '<path'), "$count $kind symbols are $count shapes");
				$this->assertStringContainsString('fill="currentColor"', $svg, 'The stylesheet picks the colour');
				$this->assertStringContainsString('aria-hidden="true"', $svg, 'The badge around the drawing names the rung');
				$this->assertMatchesRegularExpression('/^<svg [^>]*viewBox="0 0 (\d+) 10" width="\1" height="10"/', $svg);
				preg_match('/width="(\d+)"/', $svg, $match);
				$widths[] = (int) $match[1];
			}
			$this->assertTrue($widths[0] < $widths[1] && $widths[1] < $widths[2], "More $kind symbols take more room");
		}
		$this->assertNotSame(RungInsignia::render(RungInsignia::CHEVRON, 1), RungInsignia::render(RungInsignia::STAR, 1));
		$this->assertSame(1, substr_count(RungInsignia::renderCrown(), '<path'));
		$this->assertStringContainsString('fill="currentColor"', RungInsignia::renderCrown());
	}

	/** @return array<int, array{0: string, 1: int}> */
	private static function insignia(int $length): array
	{
		$insignia = [];
		for ($rung = 0; $rung < $length; $rung++)
			$insignia[] = RungInsignia::of($rung, $length);
		return $insignia;
	}
}

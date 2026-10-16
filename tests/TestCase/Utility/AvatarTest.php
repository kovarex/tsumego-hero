<?php

use App\Utility\Avatar;

class AvatarTest extends CakeTestCase
{
	/**
	 * The drawing is a 5x5 board mirrored down the middle, so 15 of the 25 cells
	 * are free and every one of them is empty, a black stone or a white stone.
	 */
	public function testPatternIsAWholeBoard()
	{
		foreach (['Anna', 'kovarex', 'Gonuzul', 'a name with spaces'] as $name)
			$this->assertMatchesRegularExpression('/^[012]{25}$/', Avatar::pattern(Avatar::key($name)));
	}

	public function testPatternIsMirroredDownTheMiddle()
	{
		foreach (range(1, 50) as $i)
		{
			$pattern = Avatar::pattern(Avatar::key('player ' . $i));
			for ($row = 0; $row < 5; $row++)
				for ($column = 0; $column < 5; $column++)
					$this->assertSame(
						$pattern[$row * 5 + $column],
						$pattern[$row * 5 + 4 - $column],
						'Row ' . $row . ' of player ' . $i . ' is not mirrored'
					);
		}
	}

	/**
	 * A player recognises a name by the board it draws, so the drawing must not
	 * change when the code that computes it is refactored: another drawing here
	 * repaints the avatar of every name in this list.
	 */
	public function testTheDrawingOfANameNeverChanges()
	{
		$this->assertSame('0212010101012102202221112', Avatar::pattern(Avatar::key('Anna')));
		$this->assertSame('2111202020220221010102020', Avatar::pattern(Avatar::key('kovarex')));
		$this->assertSame('2111221012210122121220102', Avatar::pattern(Avatar::key('Gonuzul')));
	}

	/**
	 * A name is whatever the player typed, up to 50 characters of it, so the key
	 * of that name has to survive being a url segment: hex and short.
	 */
	public function testTheKeyOfANameSurvivesBeingAUrl()
	{
		foreach (['Anna', 'a name with spaces', 'Šíleně žluťoučký', '名字', str_repeat('x', 50)] as $name)
			$this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', Avatar::key($name));
	}

	public function testDifferentNamesGetDifferentBoards()
	{
		$boards = [];
		foreach (range(1, 500) as $i)
			$boards[Avatar::pattern(Avatar::key('player ' . $i))] = true;

		$this->assertSame(500, count($boards), 'Some of the 500 names share a board');
	}

	public function testBoardsUseBothStoneColours()
	{
		$black = 0;
		$white = 0;
		foreach (range(1, 100) as $i)
			foreach (str_split(Avatar::pattern(Avatar::key('player ' . $i))) as $cell)
			{
				if ($cell === '1')
					$black++;
				elseif ($cell === '2')
					$white++;
			}

		$this->assertGreaterThan(0, $black, 'No account has a black stone');
		$this->assertGreaterThan(0, $white, 'No account has a white stone');
	}

	public function testSvgIsOneBoard()
	{
		$svg = Avatar::svg(Avatar::key('player 7'));

		$this->assertStringStartsWith('<svg', $svg);
		$this->assertStringEndsWith('</svg>', $svg);
		// An svg in an img tag is a document of its own, so it needs the namespace.
		$this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svg);
		$this->assertStringContainsString('viewBox="0 0 100 100"', $svg);
		$this->assertStringContainsString(Avatar::WOOD_COLOR, $svg);
		// The frame is what makes a sharp outer edge look like a board edge, and it is a
		// plain square: rounding the corners would cut the pattern off.
		$this->assertStringContainsString('<rect width="100" height="100" fill="url(#rim)"/>', $svg);
		$this->assertStringContainsString(Avatar::RIM_COLOR, $svg);
	}

	public function testSvgDrawsAStonePerFilledCell()
	{
		foreach (['Anna', 'player 7', 'player 123', 'Gonuzul'] as $name)
		{
			$key = Avatar::key($name);
			$pattern = Avatar::pattern($key);
			$stones = substr_count($pattern, '1') + substr_count($pattern, '2');
			$this->assertSame(
				$stones,
				substr_count(Avatar::svg($key), 'fill="url(#stone'),
				'Name ' . $name . ' did not get one stone per filled cell'
			);
		}
	}

	public function testSvgColoursTheStonesTheWayThePatternSays()
	{
		foreach (['Anna', 'player 7', 'player 123', 'Gonuzul'] as $name)
		{
			$key = Avatar::key($name);
			$pattern = Avatar::pattern($key);
			$svg = Avatar::svg($key);

			$this->assertSame(
				substr_count($pattern, '1'),
				substr_count($svg, 'fill="url(#stoneb)"'),
				'Wrong number of black stones for ' . $name
			);
			$this->assertSame(
				substr_count($pattern, '2'),
				substr_count($svg, 'fill="url(#stonew)"'),
				'Wrong number of white stones for ' . $name
			);
		}
	}
}

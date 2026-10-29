<?php

use App\Utility\Avatar;

class AvatarTest extends AppTestCase
{
	//The drawing is a 5x5 board mirrored down the middle, so 15 of the 25 cells
	//are free and every one of them is empty, a black stone or a white stone.
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

	//A player recognises a name by the board it draws, so the drawing must not
	//change when the code that computes it is refactored: another drawing here
	//repaints the avatar of every name in this list.
	public function testTheDrawingOfANameNeverChanges()
	{
		$this->assertSame('0212010101012102202221112', Avatar::pattern(Avatar::key('Anna')));
		$this->assertSame('2111202020220221010102020', Avatar::pattern(Avatar::key('kovarex')));
		$this->assertSame('2111221012210122121220102', Avatar::pattern(Avatar::key('Gonuzul')));
	}

	//A name is whatever the player typed, up to 50 characters of it, so the key
	//of that name has to survive being a url segment: hex and short.
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

	//Several networks drop an svg from a share preview, so the board is drawn as a
	//png as well. The drawing stays the same, only the picture of it changes.
	public function testPngIsABoardOfTheRequestedSize()
	{
		$png = Avatar::png(Avatar::key('Anna'), 128);
		$size = getimagesizefromstring($png);

		$this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
		$this->assertSame(128, $size[0]);
		$this->assertSame(128, $size[1]);
		$this->assertSame('image/png', $size['mime']);
	}

	public function testTheSameKeyGetsTheSamePng()
	{
		$this->assertSame(Avatar::png(Avatar::key('Anna')), Avatar::png(Avatar::key('Anna')));
		$this->assertNotSame(Avatar::png(Avatar::key('Anna')), Avatar::png(Avatar::key('kovarex')));
	}

	//The two renditions are two pictures of one drawing, so a cell that holds a
	//stone in the pattern holds the same stone in the png, in the same place.
	public function testPngDrawsThePatternWhereTheSvgDrawsIt()
	{
		$size = 512;
		$cell = (Avatar::CANVAS - 2 * Avatar::RIM) / Avatar::GRID_SIZE;
		$scale = $size / Avatar::CANVAS;

		foreach (['Anna', 'kovarex', 'player 7', 'Gonuzul'] as $name)
		{
			$key = Avatar::key($name);
			$pattern = Avatar::pattern($key);
			$image = imagecreatefromstring(Avatar::png($key, $size));
			$this->assertNotFalse($image, 'The board of ' . $name . ' is not a picture');

			for ($index = 0; $index < Avatar::GRID_SIZE * Avatar::GRID_SIZE; $index++)
			{
				// Down and to the right of the middle of the cell: inside a stone and
				// clear of the lines of the board, which meet at the middle of a cell.
				$x = (int) round((Avatar::RIM + ($index % Avatar::GRID_SIZE) * $cell + $cell * 0.7) * $scale);
				$y = (int) round((Avatar::RIM + intdiv($index, Avatar::GRID_SIZE) * $cell + $cell * 0.7) * $scale);
				$colour = imagecolorsforindex($image, imagecolorat($image, $x, $y));
				$light = $colour['red'] + $colour['green'] + $colour['blue'];

				if ($pattern[$index] === '1')
					$this->assertLessThan(250, $light, 'No black stone at cell ' . $index . ' of ' . $name);
				elseif ($pattern[$index] === '2')
					$this->assertGreaterThan(600, $light, 'No white stone at cell ' . $index . ' of ' . $name);
				else
				{
					$this->assertGreaterThan(420, $light, 'Cell ' . $index . ' of ' . $name . ' is not wood');
					$this->assertLessThan(560, $light, 'Cell ' . $index . ' of ' . $name . ' is not wood');
				}
			}

			imagedestroy($image);
		}
	}

	//The frame runs all the way round the playing surface. A frame on two sides
	//only reads as a mistake at 24px, where nobody sees which side is missing.
	public function testPngFramesTheWholeBoard()
	{
		$size = 512;
		$image = imagecreatefromstring(Avatar::png(Avatar::key('Anna'), $size));
		$this->assertNotFalse($image);

		$middle = intdiv($size, 2);
		foreach ([[3, $middle], [$size - 4, $middle], [$middle, 3], [$middle, $size - 4]] as [$x, $y])
		{
			$colour = imagecolorsforindex($image, imagecolorat($image, $x, $y));
			$light = $colour['red'] + $colour['green'] + $colour['blue'];

			$this->assertGreaterThan(280, $light, 'No frame at ' . $x . ',' . $y);
			$this->assertLessThan(400, $light, 'No frame at ' . $x . ',' . $y);
		}

		imagedestroy($image);
	}
}

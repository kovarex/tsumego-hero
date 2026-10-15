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
		foreach ([1, 2, 4242, 30142] as $userId)
			$this->assertMatchesRegularExpression('/^[012]{25}$/', Avatar::pattern($userId));
	}

	public function testPatternIsMirroredDownTheMiddle()
	{
		foreach (range(1, 50) as $userId)
		{
			$pattern = Avatar::pattern($userId);
			for ($row = 0; $row < 5; $row++)
				for ($column = 0; $column < 5; $column++)
					$this->assertSame(
						$pattern[$row * 5 + $column],
						$pattern[$row * 5 + 4 - $column],
						'Row ' . $row . ' of account ' . $userId . ' is not mirrored'
					);
		}
	}

	/**
	 * A player recognises an account by this drawing, so it must not change when
	 * the code that computes it is refactored: a different drawing here redraws
	 * the avatar of every account that ever played.
	 */
	public function testDrawingForAnAccountNeverChanges()
	{
		$this->assertSame('1000100100022201000112121', Avatar::pattern(1));
		$this->assertSame('2222211111011102202211211', Avatar::pattern(2));
		$this->assertSame('0222001210100010212011211', Avatar::pattern(4242));
	}

	public function testDifferentAccountsGetDifferentBoards()
	{
		$boards = [];
		foreach (range(1, 500) as $userId)
			$boards[Avatar::pattern($userId)] = true;

		$this->assertSame(500, count($boards), 'Some of the 500 accounts share a board');
	}

	public function testBoardsUseBothStoneColours()
	{
		$black = 0;
		$white = 0;
		foreach (range(1, 100) as $userId)
			foreach (str_split(Avatar::pattern($userId)) as $cell)
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
		$svg = Avatar::svg(7);

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
		foreach ([1, 7, 123, 4242] as $userId)
		{
			$pattern = Avatar::pattern($userId);
			$stones = substr_count($pattern, '1') + substr_count($pattern, '2');
			$this->assertSame(
				$stones,
				substr_count(Avatar::svg($userId), 'fill="url(#stone'),
				'Account ' . $userId . ' did not get one stone per filled cell'
			);
		}
	}

	public function testSvgColoursTheStonesTheWayThePatternSays()
	{
		foreach ([1, 7, 123, 4242] as $userId)
		{
			$pattern = Avatar::pattern($userId);
			$svg = Avatar::svg($userId);

			$this->assertSame(
				substr_count($pattern, '1'),
				substr_count($svg, 'fill="url(#stoneb)"'),
				'Wrong number of black stones for account ' . $userId
			);
			$this->assertSame(
				substr_count($pattern, '2'),
				substr_count($svg, 'fill="url(#stonew)"'),
				'Wrong number of white stones for account ' . $userId
			);
		}
	}
}

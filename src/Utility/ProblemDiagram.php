<?php

namespace App\Utility;

//The board of a problem as a black-and-white SVG for paper (the print page, PrintController): the
//part of the board the stones are on with a little room around them, reaching the edge of the board
//where it is close, a plain grid, solid stones with outlines and no wood. The answer is the same
//part of the board with the first correct line on it as numbered stones. The same SGF always gives
//the same picture.
class ProblemDiagram
{
	//The lines shown around the stones, and the gap to the edge of the board that is closed up
	private const int PADDING = 1;
	private const int SNAP = 2;
	//The longest line of the answer that is drawn
	private const int MAXIMUM_ANSWER_MOVES = 30;
	//In SVG units: the room between two lines, around the outer lines, and the radius of a stone
	private const int CELL = 20;
	private const int MARGIN = 13;
	private const float STONE_RADIUS = 9.5;
	private const array COLUMN_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'];

	private int $minX;
	private int $minY;
	private int $maxX;
	private int $maxY;

	//$stones: 'x/y' => 'B' or 'W', 0-based from the top left
	//$answer: The moves of the first correct line in their order, each [color, x, y]
	/**
	 * @param array<string, string> $stones
	 * @param list<array{string, int, int}> $answer
	 */
	private function __construct(public readonly int $boardSize, private array $stones, public readonly array $answer)
	{
		$this->crop();
	}

	//The diagram of a stored SGF, whose first correct moves are $correctMoves (sgf.correct_moves) and
	//whose correct variations come first at every level, as the site stores every SGF; null when it
	//has no stones to show.
	public static function fromSgf(string $sgf, string $correctMoves): ?self
	{
		$root = SgfTree::parse($sgf);
		if (!$root)
			return null;
		$size = $root->boardSize();
		$stones = [];
		foreach ($root->setupStones($size) as $point => $color)
		{
			[$x, $y] = explode('/', $point);
			$stones[((int) $x - 1) . '/' . ((int) $y - 1)] = $color;
		}
		if (empty($stones))
			return null;
		return new self($size, $stones, self::firstCorrectLine($root, $size, $correctMoves));
	}

	//The first of the correct first moves, then the first variation at every move after it
	/** @return list<array{string, int, int}> */
	private static function firstCorrectLine(SgfTree $root, int $size, string $correctMoves): array
	{
		$first = SgfTree::pointFromLetters(substr($correctMoves, 0, 2), $size);
		$node = $first ? $root->childWithMoveAt($first[0], $first[1], $size) : null;
		$line = [];
		while ($node && count($line) < self::MAXIMUM_ANSWER_MOVES)
		{
			$move = $node->move($size);
			if (!$move)
				break;
			$line[] = [$move[0], $move[1] - 1, $move[2] - 1];
			$node = $node->children[0] ?? null;
		}
		return $line;
	}

	public function hasAnswer(): bool
	{
		return !empty($this->answer);
	}

	//The color of the first move of the answer, null without an answer
	public function colorToPlay(): ?string
	{
		return $this->answer[0][0] ?? null;
	}

	//The stones and the moves of the answer with PADDING lines around them, reaching the edge where
	//it is at most SNAP lines further, so the answer fits the same frame
	private function crop(): void
	{
		$xs = $ys = [];
		foreach (array_keys($this->stones) as $point)
		{
			[$x, $y] = explode('/', $point);
			$xs[] = (int) $x;
			$ys[] = (int) $y;
		}
		foreach ($this->answer as [, $x, $y])
		{
			$xs[] = $x;
			$ys[] = $y;
		}
		$last = $this->boardSize - 1;
		$this->minX = self::snapLow(min($xs) - self::PADDING);
		$this->minY = self::snapLow(min($ys) - self::PADDING);
		$this->maxX = self::snapHigh(max($xs) + self::PADDING, $last);
		$this->maxY = self::snapHigh(max($ys) + self::PADDING, $last);
	}

	private static function snapLow(int $line): int
	{
		return $line <= self::SNAP ? 0 : $line;
	}

	private static function snapHigh(int $line, int $last): int
	{
		return $line >= $last - self::SNAP ? $last : $line;
	}

	//The problem as it is set up
	public function problemSvg(string $label): string
	{
		return $this->svg($label, $this->stones, []);
	}

	//The answer: the problem with the moves of the first correct line numbered from 1. A move on a
	//point that already has one of them is left out of the board and told in answerNotes().
	public function answerSvg(string $label): string
	{
		$numbers = [];
		foreach ($this->answer as $index => [$color, $x, $y])
		{
			$point = $x . '/' . $y;
			if (!isset($this->stones[$point]) && !isset($numbers[$point]))
				$numbers[$point] = [$color, $index + 1];
		}
		return $this->svg($label, $this->stones, $numbers);
	}

	//The moves of the answer that are not on the board, as "7 at 3", or "7 at C17" on a point of a stone of the problem
	/** @return list<string> */
	public function answerNotes(): array
	{
		$notes = [];
		$numbers = [];
		foreach ($this->answer as $index => [, $x, $y])
		{
			$point = $x . '/' . $y;
			if (isset($numbers[$point]))
				$notes[] = ($index + 1) . ' at ' . $numbers[$point];
			elseif (isset($this->stones[$point]))
				$notes[] = ($index + 1) . ' at ' . $this->coordinate($x, $y);
			else
				$numbers[$point] = $index + 1;
		}
		return $notes;
	}

	//The name of a point as the board shows it: the column letter without I and the row counted from the bottom
	public function coordinate(int $x, int $y): string
	{
		return (self::COLUMN_LETTERS[$x] ?? '?') . ($this->boardSize - $y);
	}

	/**
	 * @param array<string, string> $stones
	 * @param array<string, array{string, int}> $numbers 'x/y' => [color, number]
	 */
	private function svg(string $label, array $stones, array $numbers): string
	{
		$columns = $this->maxX - $this->minX;
		$rows = $this->maxY - $this->minY;
		$width = $columns * self::CELL + 2 * self::MARGIN;
		$height = $rows * self::CELL + 2 * self::MARGIN;
		$isLeftEdge = $this->minX === 0;
		$isTopEdge = $this->minY === 0;
		$isRightEdge = $this->maxX === $this->boardSize - 1;
		$isBottomEdge = $this->maxY === $this->boardSize - 1;

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" class="print-board" viewBox="0 0 ' . $width . ' ' . $height . '"'
			. ' role="img" aria-label="' . h($label) . '">'
			. '<rect width="' . $width . '" height="' . $height . '" fill="#fff"/>';

		//The lines run out of the picture where the board goes on, and stop at its edge where it ends
		$left = $isLeftEdge ? self::MARGIN : 0;
		$right = $isRightEdge ? $width - self::MARGIN : $width;
		$top = $isTopEdge ? self::MARGIN : 0;
		$bottom = $isBottomEdge ? $height - self::MARGIN : $height;
		$svg .= '<g stroke="#000" stroke-width="1" stroke-linecap="square">';
		for ($column = 0; $column <= $columns; $column++)
		{
			$x = self::MARGIN + $column * self::CELL;
			$isEdge = ($column === 0 && $isLeftEdge) || ($column === $columns && $isRightEdge);
			$svg .= '<line x1="' . $x . '" y1="' . $top . '" x2="' . $x . '" y2="' . $bottom . '"' . ($isEdge ? ' stroke-width="2.5"' : '') . '/>';
		}
		for ($row = 0; $row <= $rows; $row++)
		{
			$y = self::MARGIN + $row * self::CELL;
			$isEdge = ($row === 0 && $isTopEdge) || ($row === $rows && $isBottomEdge);
			$svg .= '<line x1="' . $left . '" y1="' . $y . '" x2="' . $right . '" y2="' . $y . '"' . ($isEdge ? ' stroke-width="2.5"' : '') . '/>';
		}
		$svg .= '</g>';

		foreach (self::starPoints($this->boardSize) as [$x, $y])
			if ($this->contains($x, $y) && !isset($stones[$x . '/' . $y]) && !isset($numbers[$x . '/' . $y]))
				$svg .= '<circle cx="' . $this->pixelX($x) . '" cy="' . $this->pixelY($y) . '" r="2.5" fill="#000"/>';

		foreach ($stones as $point => $color)
		{
			[$x, $y] = array_map('intval', explode('/', $point));
			$svg .= $this->stone($x, $y, $color);
		}
		foreach ($numbers as $point => [$color, $number])
		{
			[$x, $y] = array_map('intval', explode('/', $point));
			$svg .= $this->stone($x, $y, $color)
				. '<text x="' . $this->pixelX($x) . '" y="' . $this->pixelY($y) . '" dy="0.35em" text-anchor="middle"'
				. ' font-family="Arial, Helvetica, sans-serif" font-weight="bold" font-size="' . ($number < 10 ? 11 : 9) . '"'
				. ' fill="' . ($color === 'B' ? '#fff' : '#000') . '">' . $number . '</text>';
		}
		return $svg . '</svg>';
	}

	private function stone(int $x, int $y, string $color): string
	{
		return '<circle class="print-board__stone print-board__stone--' . ($color === 'B' ? 'black' : 'white') . '"'
			. ' cx="' . $this->pixelX($x) . '" cy="' . $this->pixelY($y) . '" r="' . self::STONE_RADIUS . '"'
			. ' fill="' . ($color === 'B' ? '#000' : '#fff') . '" stroke="#000" stroke-width="' . ($color === 'B' ? 1 : 1.3) . '"/>';
	}

	private function contains(int $x, int $y): bool
	{
		return $x >= $this->minX && $x <= $this->maxX && $y >= $this->minY && $y <= $this->maxY;
	}

	private function pixelX(int $x): int
	{
		return self::MARGIN + ($x - $this->minX) * self::CELL;
	}

	private function pixelY(int $y): int
	{
		return self::MARGIN + ($y - $this->minY) * self::CELL;
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
}

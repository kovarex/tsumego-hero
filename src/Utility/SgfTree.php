<?php

namespace App\Utility;

/**
 * A node of an SGF game tree with its properties, for code that needs the moves
 * of all variations (SgfParser only reads the setup stones and correct moves).
 * Points are 1-based (x from the left, y from the top), as besogo uses them.
 */
class SgfTree
{
	/** @var array<string, string[]> */
	public array $properties = [];
	/** @var SgfTree[] */
	public array $children = [];

	public function __construct(public ?SgfTree $parent = null) {}

	/** Parses the first game of the SGF, null when there is no node. */
	public static function parse(string $sgf): ?SgfTree
	{
		$root = null;
		$current = null;
		$stack = [];
		$length = strlen($sgf);
		$i = 0;
		while ($i < $length)
		{
			$character = $sgf[$i];
			if ($character == '(')
			{
				$stack[] = $current;
				$i++;
			}
			elseif ($character == ')')
			{
				if (empty($stack))
					break;
				$current = array_pop($stack);
				$i++;
				if (empty($stack) && $root)
					break; // the end of the first game
			}
			elseif ($character == ';')
			{
				$node = new SgfTree($current);
				if ($current)
					$current->children[] = $node;
				else
					$root ??= $node;
				$current = $node;
				$i++;
			}
			elseif (ctype_alpha($character) && $current)
			{
				$start = $i;
				while ($i < $length && ctype_alpha($sgf[$i]))
					$i++;
				// old SGF versions allow lower case letters inside identifiers (e.g. "AddBlack")
				$name = preg_replace('/[a-z]/', '', substr($sgf, $start, $i - $start));
				while ($i < $length && ctype_space($sgf[$i]))
					$i++;
				while ($i < $length && $sgf[$i] == '[')
				{
					[$value, $i] = self::readValue($sgf, $i + 1);
					$current->properties[$name][] = $value;
					while ($i < $length && ctype_space($sgf[$i]))
						$i++;
				}
			}
			else
				$i++;
		}
		return $root;
	}

	/** @return array{string, int} the value and the position after its closing bracket */
	private static function readValue(string $sgf, int $i): array
	{
		$value = '';
		$length = strlen($sgf);
		while ($i < $length && $sgf[$i] != ']')
		{
			if ($sgf[$i] == '\\' && $i + 1 < $length)
				$i++;
			$value .= $sgf[$i];
			$i++;
		}
		return [$value, $i + 1];
	}

	public function boardSize(): int
	{
		$size = (int) ($this->properties['SZ'][0] ?? 19);
		return $size >= 2 && $size <= 52 ? $size : 19;
	}

	/** @return array{int, int}|null the point of SGF letters such as "cd", null for a pass */
	public static function pointFromLetters(string $letters, int $size): ?array
	{
		if (strlen($letters) != 2)
			return null;
		$x = ord($letters[0]) - ord('a') + 1;
		$y = ord($letters[1]) - ord('a') + 1;
		if ($x < 1 || $x > $size || $y < 1 || $y > $size)
			return null;
		return [$x, $y];
	}

	/** @return array{string, int, int}|null the colour ('B' or 'W') and point of the move of this node */
	public function move(int $size): ?array
	{
		foreach (['B', 'W'] as $color)
			if (isset($this->properties[$color]))
			{
				$point = self::pointFromLetters($this->properties[$color][0], $size);
				return $point ? [$color, $point[0], $point[1]] : null;
			}
		return null;
	}

	/** @return array<string, string> the setup stones of this node, "x/y" => 'B' or 'W' */
	public function setupStones(int $size): array
	{
		$stones = [];
		foreach (['AB' => 'B', 'AW' => 'W'] as $property => $color)
			foreach ($this->properties[$property] ?? [] as $value)
			{
				// a compressed list "aa:cc" is the rectangle between the two points
				$corners = explode(':', $value);
				$from = self::pointFromLetters($corners[0], $size);
				$to = isset($corners[1]) ? self::pointFromLetters($corners[1], $size) : $from;
				if (!$from || !$to)
					continue;
				for ($x = min($from[0], $to[0]); $x <= max($from[0], $to[0]); $x++)
					for ($y = min($from[1], $to[1]); $y <= max($from[1], $to[1]); $y++)
						$stones[$x . '/' . $y] = $color;
			}
		return $stones;
	}

	/** @return SgfTree[] this node and all nodes below it */
	public function allNodes(): array
	{
		$nodes = [$this];
		foreach ($this->children as $child)
			array_push($nodes, ...$child->allNodes());
		return $nodes;
	}

	/**
	 * The child whose move is at the point, looking through children without a
	 * move (setup nodes) the way besogo's comment anchors do.
	 */
	public function childWithMoveAt(int $x, int $y, int $size): ?SgfTree
	{
		foreach ($this->children as $child)
		{
			$move = $child->move($size);
			if (!$move && !isset($child->properties['B']) && !isset($child->properties['W']))
			{
				if ($result = $child->childWithMoveAt($x, $y, $size))
					return $result;
			}
			elseif ($move && $move[1] == $x && $move[2] == $y)
				return $child;
		}
		return null;
	}

	public function moveNumber(): int
	{
		$number = 0;
		for ($node = $this; $node; $node = $node->parent)
			if (isset($node->properties['B']) || isset($node->properties['W']))
				$number++;
		return $number;
	}
}

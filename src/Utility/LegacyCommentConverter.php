<?php

namespace App\Utility;

/**
 * Converts a comment written before comments were stored in the coordinates of
 * the SGF. Such a comment was typed while looking at the board as its author saw
 * it: normalised to the top-left, then mirrored into the author's corner (random
 * or chosen), maybe rotated, maybe with the colours swapped, and nothing of that
 * was saved with the text. The board used to guess the orientation every time
 * the comment was shown; this makes the same kind of guess once, so the comment
 * can be stored in the coordinates of the SGF and shown exactly from then on.
 *
 * - The anchor (position) records the author's corner, so it is converted
 *   exactly when its moves are still in the tree; otherwise the orientation in
 *   which its moves are found is used.
 * - The coordinates in the text get the orientation in which most of them are
 *   moves of the tree, then setup stones, then points near the stones. A tie is
 *   decided by the anchor's orientation when there is one, otherwise by the
 *   problem as shown in the top-left corner (the "Original" orientation).
 * - Black and white are swapped only when the colours written next to the
 *   coordinates contradict the colours of those moves in the tree.
 * - The "[current position]" marker is removed, the anchor alone marks it now.
 */
class LegacyCommentConverter
{
	public const MARKER = '[current position]';
	// how far from the stones a coordinate still counts as near the problem
	private const NEAR_STONES_MARGIN = 3;

	private int $size;
	/** @var array<string, string[]> "x/y" => the colours of the moves played there */
	private array $moves = [];
	/** @var array<string, string> "x/y" => the colour of the setup stone there */
	private array $stones = [];
	/** @var array{int, int, int, int} lowest x, lowest y, highest x, highest y of all stones and moves */
	private array $bounds;
	// the flips normalising the problem to the top-left corner, as besogo does on load
	private BoardTransformation $normalization;
	private bool $fullBoard;

	public function __construct(private SgfTree $tree)
	{
		$this->size = $tree->boardSize();
		$lowestX = $lowestY = $this->size;
		$highestX = $highestY = 0;
		foreach ($tree->allNodes() as $node)
		{
			$points = [];
			foreach ($node->setupStones($this->size) as $key => $color)
			{
				$this->stones[$key] = $color;
				$points[] = array_map('intval', explode('/', $key));
			}
			if ($move = $node->move($this->size))
			{
				$this->moves[$move[1] . '/' . $move[2]][] = $move[0];
				$points[] = [$move[1], $move[2]];
			}
			foreach ($points as [$x, $y])
			{
				$lowestX = min($lowestX, $x);
				$lowestY = min($lowestY, $y);
				$highestX = max($highestX, $x);
				$highestY = max($highestY, $y);
			}
		}
		$this->bounds = [$lowestX, $lowestY, $highestX, $highestY];
		// besogo's scaleParameters.normalizeToTopLeft and checkFullBoard
		$flipX = $lowestX - 1 > $this->size - $highestX;
		$flipY = $lowestY - 1 > $this->size - $highestY;
		$this->normalization = new BoardTransformation(false, $flipX, $flipY);
		[$normalizedHighestX, $normalizedHighestY] = [$flipX ? $this->size + 1 - $lowestX : $highestX, $flipY ? $this->size + 1 - $lowestY : $highestY];
		$this->fullBoard = !($this->size - $normalizedHighestX >= 10 || $this->size - $normalizedHighestY >= 10);
	}

	public function convert(string $message, ?string $position = null): LegacyCommentConversion
	{
		$result = new LegacyCommentConversion();
		$result->originalMessage = $message;
		$result->originalPosition = $position;

		$this->convertPosition($position, $result);

		$text = str_replace(self::MARKER, '', $message);
		$labels = CommentCoordinates::labels($text);
		$result->labels = $labels;
		if (empty($labels))
		{
			$result->textMethod = 'no coordinates';
			$result->message = $text;
			return $result;
		}

		$this->chooseTextOrientation($labels, $result);
		$result->colorEvidence = $this->colorEvidence($text, $result->textTransformation);
		$result->colorsSwapped = $result->colorEvidence['swapped'] > $result->colorEvidence['same'];
		$transformation = $result->textTransformation;
		if ($result->colorsSwapped)
			$transformation = new BoardTransformation($transformation->transpose, $transformation->flipX, $transformation->flipY, true);
		$result->message = CommentCoordinates::transformTextBy($text, $transformation, $this->size);
		return $result;
	}

	/** The transformation from the author's view to the SGF the old board assumed for an anchor in this corner. */
	private function expectedAnchorTransformation(string $orientation): BoardTransformation
	{
		if ($orientation == 'full-board' || $this->fullBoard)
			return BoardTransformation::identity();
		// the view was the corner's flips applied to the normalised problem
		$corner = new BoardTransformation(false, str_ends_with($orientation, 'right'), str_starts_with($orientation, 'bottom'));
		return $this->normalization->then($corner)->inverse();
	}

	private function convertPosition(?string $position, LegacyCommentConversion $result): void
	{
		$result->position = null;
		if ($position === null || $position === '')
		{
			$result->positionMethod = 'none';
			return;
		}
		$pieces = explode('|', $position, 2);
		$fields = explode('/', $pieces[0]);
		$path = [];
		foreach (explode('+', $pieces[1] ?? '') as $move)
		{
			$point = explode('/', $move);
			if (count($point) == 2 && (int) $point[0] > 0)
				$path[] = [(int) $point[0], (int) $point[1]];
		}
		if (empty($path) && (int) ($fields[0] ?? -1) > 0)
			$path[] = [(int) $fields[0], (int) $fields[1]]; // only the move itself
		if (empty($path))
		{
			$result->positionMethod = 'none';
			return;
		}

		$expected = $this->expectedAnchorTransformation($fields[8] ?? '');
		$candidates = [$expected];
		foreach (BoardTransformation::all() as $candidate)
			if (!$candidate->invertColors && !$candidate->equals($expected))
				$candidates[] = $candidate;
		foreach ($candidates as $index => $candidate)
			foreach ([$path, array_reverse($path)] as $order)
				if ($node = $this->follow($order, $candidate))
				{
					$result->anchorTransformation = $candidate;
					$result->positionMethod = $index == 0 ? 'exact' : 'found in another orientation';
					$result->position = $this->positionOf($node);
					return;
				}

		// the moves are not in the tree any more, so the anchor can not be resolved either way
		$result->positionMethod = 'moves not in the tree';
		$result->position = CommentCoordinates::transformPosition(
			implode('/', array_slice($fields, 0, 8)) . '/sgf|' . implode('+', array_map(fn($point) => implode('/', $point), $path)),
			$expected, $this->size);
	}

	/** @param array<array{int, int}> $path */
	private function follow(array $path, BoardTransformation $transformation): ?SgfTree
	{
		$node = $this->tree;
		foreach ($path as [$x, $y])
		{
			[$x, $y] = $transformation->applyToPoint($x, $y, $this->size);
			$node = $node->childWithMoveAt($x, $y, $this->size);
			if (!$node)
				return null;
		}
		return $node;
	}

	/** The position string of the node, in SGF coordinates (see besogo's makeCommentPosition). */
	private function positionOf(SgfTree $node): string
	{
		$moveOf = function (?SgfTree $node): string
		{
			$move = $node?->move($this->size);
			return $move ? $move[1] . '/' . $move[2] : '-1/-1';
		};
		$path = [];
		for ($current = $node; $current; $current = $current->parent)
			if ($current->move($this->size))
				array_unshift($path, $moveOf($current));
		$parent = $node->parent && $node->parent->move($this->size) ? $node->parent : null;
		$child = $node->children[0] ?? null;
		return $moveOf($node) . '/' . $moveOf($parent) . '/' . $moveOf($child) . '/'
			. $node->moveNumber() . '/' . count($node->children) . '/sgf|' . implode('+', $path);
	}

	/** @param string[] $labels */
	private function chooseTextOrientation(array $labels, LegacyCommentConversion $result): void
	{
		$preferences = [];
		if ($result->anchorTransformation)
			$preferences[] = $result->anchorTransformation;
		$preferences[] = $this->fullBoard ? BoardTransformation::identity() : $this->normalization;
		$preferences[] = BoardTransformation::identity();
		foreach (BoardTransformation::all() as $candidate)
			if (!$candidate->invertColors)
				$preferences[] = $candidate;

		$best = null;
		$scores = [];
		foreach ($preferences as $candidate)
		{
			$key = $candidate->toJson();
			if (isset($scores[$key]))
				continue;
			$scores[$key] = $this->score($labels, $candidate);
			if (!$best || $scores[$key] > $scores[$best->toJson()])
				$best = $candidate;
		}
		$result->textTransformation = $best;
		$result->textScore = $scores[$best->toJson()];

		// candidates scoring the same but putting the coordinates elsewhere make the choice a guess
		$bestPoints = $this->mappedPoints($labels, $best);
		$alternatives = 0;
		foreach ($scores as $key => $score)
			if ($score == $result->textScore && $this->mappedPoints($labels, BoardTransformation::fromJson($key)) != $bestPoints)
				$alternatives++;
		if ($alternatives == 0)
			$result->textMethod = 'unique';
		elseif ($result->anchorTransformation && $best->equals($result->anchorTransformation))
			$result->textMethod = 'tie, decided by the anchor';
		else
			$result->textMethod = 'tie, decided by the top-left orientation';
		$result->textAlternatives = $alternatives;
	}

	/**
	 * @param string[] $labels
	 * @return array{int, int, int} the coordinates landing on moves, on setup stones and near the stones
	 */
	private function score(array $labels, BoardTransformation $transformation): array
	{
		[$lowestX, $lowestY, $highestX, $highestY] = $this->bounds;
		$score = [0, 0, 0];
		foreach ($this->mappedPoints($labels, $transformation) as $key)
		{
			if ($key === null)
				continue;
			[$x, $y] = array_map('intval', explode('/', $key));
			if (isset($this->moves[$key]))
				$score[0]++;
			if (isset($this->stones[$key]))
				$score[1]++;
			if ($x >= $lowestX - self::NEAR_STONES_MARGIN && $x <= $highestX + self::NEAR_STONES_MARGIN
				&& $y >= $lowestY - self::NEAR_STONES_MARGIN && $y <= $highestY + self::NEAR_STONES_MARGIN)
				$score[2]++;
		}
		return $score;
	}

	/**
	 * @param string[] $labels
	 * @return array<?string> "x/y" of each label in the SGF, null when off the board
	 */
	private function mappedPoints(array $labels, BoardTransformation $transformation): array
	{
		return array_map(function (string $label) use ($transformation): ?string
		{
			$point = CommentCoordinates::labelToPoint($label, $this->size);
			if (!$point)
				return null;
			return implode('/', $transformation->applyToPoint($point[0], $point[1], $this->size));
		}, $labels);
	}

	/**
	 * Compares the colour written right before a coordinate ("black D4", "w-T19")
	 * with the colour of the moves at that point.
	 *
	 * @return array{same: int, swapped: int}
	 */
	private function colorEvidence(string $text, BoardTransformation $transformation): array
	{
		$evidence = ['same' => 0, 'swapped' => 0];
		preg_match_all(CommentCoordinates::COORDINATE_PATTERN, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
		foreach ($matches as $match)
		{
			$before = substr($text, max(0, $match[0][1] - 12), min(12, $match[0][1]));
			if (preg_match('/(black|white)\s*[,:-]?\s*$/i', $before, $word))
				$color = strtolower($word[1]) == 'black' ? 'B' : 'W';
			elseif (preg_match('/(?:^|[^\p{L}\p{N}])([bwBW])\s*[,:-]?\s*$/u', $before, $letter))
				$color = strtoupper($letter[1]);
			else
				continue;
			$point = CommentCoordinates::labelToPoint(strtoupper($match[1][0]) . $match[2][0], $this->size);
			if (!$point)
				continue;
			[$x, $y] = $transformation->applyToPoint($point[0], $point[1], $this->size);
			$colors = array_values(array_unique($this->moves[$x . '/' . $y] ?? []));
			if (count($colors) != 1)
				continue; // no move there, or both colours play there in different lines
			$evidence[$colors[0] == $color ? 'same' : 'swapped']++;
		}
		return $evidence;
	}
}

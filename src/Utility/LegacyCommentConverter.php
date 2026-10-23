<?php

namespace App\Utility;

/**
 * Converts a comment written before comments were stored in the coordinates of
 * the SGF. Such a comment was typed while looking at the board as its author saw
 * it: normalised to the top-left, then mirrored into the author's corner (random
 * or chosen), maybe rotated, maybe with the colours swapped, and nothing of that
 * was saved with the text. This finds the orientation once, so the comment can be
 * stored in the coordinates of the SGF and shown exactly from then on.
 *
 * - The anchor (position) records the author's corner, so it is converted
 *   exactly. Its moves are looked up in the tree in that orientation first, then
 *   in the others (the board could have been rotated, or the SGF replaced).
 * - The coordinates in the text follow the anchor when there is one: the text
 *   was written on the same board. Otherwise the orientation is chosen in which
 *   most of them are moves of the tree, where a board the site could show (one
 *   of the four corners, a full board as it is, rotated when it is tall) counts
 *   as one more: any other orientation has to explain two more moves, as only an
 *   SGF replaced since could have turned it. Then the orientation with more of
 *   them on setup stones and near the stones wins. A remaining tie is decided by
 *   the problem as shown in the top-left corner (the "Original" orientation).
 * - Black and white are swapped only when the colours the text names contradict
 *   the problem: the colour written next to a coordinate against the colour of
 *   the move there in the tree (in the line of the tree, when it is one of three
 *   or more coordinates in a row playing one), and "white to kill" against the
 *   description.
 *   A hypothetical ("if w D4", "should this be white to play?") does not count.
 *   A caller that knows the colours (a comment checked by hand) gives them instead.
 * - The "[current position]" marker is removed, the anchor alone marks it now.
 */
class LegacyCommentConverter
{
	public const MARKER = '[current position]';
	// how far from the stones a coordinate still counts as near the problem
	private const NEAR_STONES_MARGIN = 3;
	// how many coordinates in a row playing a line of the tree tell the colours of that line, two meet by chance
	private const LINE_LENGTH = 3;
	// "Black to kill", "w to live": the side to play, as in the description of the problem
	private const SIDE_TO_PLAY_PATTERN = '/\b(black|white|b|w)\s+to\s+(?:play|move|kill|live|capture|win|save|escape|connect|cut)\b/i';
	// "if w D4", "should this be white to play?": a hypothetical, not the colours the author saw
	private const HYPOTHETICAL_PATTERN = '/\b(?:if|should|would|could|suppose|add|adding|added|suggest\w*|chang\w*|instead|rather|consider\w*|label\w*)\b/i';

	private int $size;
	/** @var array<string, SgfTree[]> "x/y" => the nodes with a move there */
	private array $moves = [];
	/** @var array<string, string> "x/y" => the colour of the setup stone there */
	private array $stones = [];
	/** @var array{int, int, int, int} lowest x, lowest y, highest x, highest y of all stones and moves */
	private array $bounds;
	// the flips normalising the problem to the top-left corner, as besogo does on load
	private BoardTransformation $normalization;
	private bool $fullBoard;
	// besogo turns a tall and narrow board a quarter counter-clockwise (since January 2026)
	private bool $tall;
	/** @var BoardTransformation[] from the boards the site could show to the SGF */
	private array $siteViews;

	/** @param ?string $description of the problem, in the colours of the SGF */
	public function __construct(private SgfTree $tree, private ?string $description = null)
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
				$this->moves[$move[1] . '/' . $move[2]][] = $node;
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
		$normalizedHighestX = $flipX ? $this->size + 1 - $lowestX : $highestX;
		$normalizedHighestY = $flipY ? $this->size + 1 - $lowestY : $highestY;
		$this->fullBoard = !($this->size - $normalizedHighestX >= 10 || $this->size - $normalizedHighestY >= 10);

		// besogo's parseAndLoad (the area shown) and boardDisplay (the rotation of a tall board)
		if ($this->fullBoard)
			$this->tall = $normalizedHighestX - $normalizedHighestY < -4;
		else
		{
			$shownX = $normalizedHighestX >= 10 ? 19 : $normalizedHighestX + 3;
			$shownY = $normalizedHighestY >= 14 ? 19 : $normalizedHighestY + 3;
			$verticalHalfBoard = $shownX != 19 && $shownY == 19;
			$regularBoard = !$verticalHalfBoard && !($shownX == 19 && $shownY != 19);
			$this->tall = $regularBoard && $shownX - $shownY < -4 || $verticalHalfBoard && $shownX - $shownY < -10;
		}

		$this->siteViews = [];
		foreach (['top-left', 'top-right', 'bottom-left', 'bottom-right'] as $corner)
		{
			$view = $this->cornerView($corner);
			foreach ($this->tall ? [$view, $this->rotated($view)] : [$view] as $candidate)
				if (!$this->contains($this->siteViews, $candidate))
					$this->siteViews[] = $candidate;
		}
	}

	/** @param ?bool $colorsSwapped known from elsewhere, null lets the colours the text names decide */
	public function convert(string $message, ?string $position = null, ?bool $colorsSwapped = null): LegacyCommentConversion
	{
		$result = new LegacyCommentConversion();
		$result->originalMessage = $message;
		$result->originalPosition = $position;

		$anchorViews = $this->convertPosition($position, $result);

		$text = self::withoutMarker($message);
		$labels = CommentCoordinates::labels($text);
		$result->labels = $labels;
		if (empty($labels))
			$result->textMethod = 'no coordinates';
		else
		{
			$this->chooseTextOrientation($labels, $anchorViews, $result);
			if ($result->positionMethod == 'moves not in the tree' && !$result->textTransformation->equals($result->anchorTransformation))
			{
				// a tall board was shown rotated, which the text tells
				$result->anchorTransformation = $result->textTransformation;
				$result->position = $this->unresolvedPosition($position, $result->anchorTransformation);
			}
		}

		$transformation = $result->textTransformation ?? BoardTransformation::identity();
		$result->colorEvidence = $this->colorEvidence($text, $transformation);
		$result->colorsSwapped = $colorsSwapped ?? $result->colorEvidence['swapped'] > $result->colorEvidence['same'];
		if ($result->colorsSwapped)
			$transformation = new BoardTransformation($transformation->transpose, $transformation->flipX, $transformation->flipY, true);
		$result->message = CommentCoordinates::transformTextBy($text, $transformation, $this->size);
		return $result;
	}

	/** The message without the "[current position]" marker and the spaces it leaves behind. */
	public static function withoutMarker(string $message): string
	{
		if (!str_contains($message, self::MARKER))
			return $message;
		return trim(preg_replace('/[ \t]*' . preg_quote(self::MARKER, '/') . '[ \t]*/', ' ', $message));
	}

	/** From the board shown in the corner (normalised, then mirrored into it) to the SGF. */
	private function cornerView(string $corner): BoardTransformation
	{
		if ($corner == 'full-board' || $this->fullBoard)
			return BoardTransformation::identity();
		$mirror = new BoardTransformation(false, str_ends_with($corner, 'right'), str_starts_with($corner, 'bottom'));
		return $this->normalization->then($mirror)->inverse();
	}

	/** The same view after besogo turned the board a quarter counter-clockwise. */
	private function rotated(BoardTransformation $view): BoardTransformation
	{
		$counterClockwise = new BoardTransformation(true, false, true);
		return $view->inverse()->then($counterClockwise)->inverse();
	}

	/** @param BoardTransformation[] $transformations */
	private function contains(array $transformations, BoardTransformation $transformation): bool
	{
		foreach ($transformations as $candidate)
			if ($candidate->equals($transformation))
				return true;
		return false;
	}

	/**
	 * @return BoardTransformation[] the orientations the text can be in according to the anchor,
	 *   empty when there is no anchor
	 */
	private function convertPosition(?string $position, LegacyCommentConversion $result): array
	{
		$result->position = null;
		[$fields, $path] = self::parsePosition($position);
		if (empty($path))
		{
			$result->positionMethod = 'none';
			return [];
		}

		// the recorded corner, then the same corner rotated, then the other boards the site
		// could show, then any orientation (an older SGF version may have been turned)
		$expected = $this->cornerView($fields[8] ?? '');
		$known = $this->tall ? [$expected, $this->rotated($expected)] : [$expected];
		$candidates = $known;
		foreach ([...$this->siteViews, ...BoardTransformation::all()] as $candidate)
			if (!$candidate->invertColors && !$this->contains($candidates, $candidate))
				$candidates[] = $candidate;
		foreach ($candidates as $index => $candidate)
			foreach ([$path, array_reverse($path)] as $order)
				if ($node = $this->follow($order, $candidate))
				{
					$result->anchorTransformation = $candidate;
					$result->positionMethod = $index < count($known) ? 'exact' : 'found in another orientation';
					$result->position = $this->positionOf($node);
					return [$candidate];
				}

		// the moves are not in the tree, the recorded corner still says where they were played
		$result->anchorTransformation = $expected;
		$result->positionMethod = 'moves not in the tree';
		$result->position = $this->unresolvedPosition($position, $expected);
		return $known;
	}

	/**
	 * Splits a position ("x/y/parentX/parentY/childX/childY/moveNumber/childCount/corner|path")
	 * into its fields and the moves of its path, from the root or (the old PHP form) to the root.
	 *
	 * @return array{string[], array<array{int, int}>}
	 */
	private static function parsePosition(?string $position): array
	{
		if ($position === null || $position === '')
			return [[], []];
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
		return [$fields, $path];
	}

	/** The position with its moves (not in the tree) moved by the transformation, as written. */
	private function unresolvedPosition(?string $position, BoardTransformation $transformation): string
	{
		[$fields, $path] = self::parsePosition($position);
		return CommentCoordinates::transformPosition(
			implode('/', array_slice($fields, 0, 8)) . '/sgf|' . implode('+', array_map(fn($point) => implode('/', $point), $path)),
			$transformation, $this->size);
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
		$moveOf = function (?SgfTree $node): string {
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

	/**
	 * @param string[] $labels
	 * @param BoardTransformation[] $anchorViews the orientations the anchor allows, empty without one
	 */
	private function chooseTextOrientation(array $labels, array $anchorViews, LegacyCommentConversion $result): void
	{
		// the order deciding a tie
		$preferences = $anchorViews;
		foreach ([$this->cornerView('top-left'), BoardTransformation::identity(), ...$this->siteViews, ...BoardTransformation::all()] as $candidate)
			if (!$candidate->invertColors && !$this->contains($preferences, $candidate))
				$preferences[] = $candidate;

		$best = null;
		foreach ($preferences as $candidate)
		{
			$scored = [
				'transformation' => $candidate,
				'score' => $this->score($labels, $candidate),
				'siteView' => $this->contains($this->siteViews, $candidate),
				'points' => $this->mappedPoints($labels, $candidate)];
			$result->candidates[] = $scored;
			// the anchor was set on the board the text was written on, so only its orientations count
			if ((empty($anchorViews) || $this->contains($anchorViews, $candidate)) && (!$best || self::rank($scored) > self::rank($best)))
				$best = $scored;
		}
		$result->textTransformation = $best['transformation'];
		$result->textScore = $best['score'];

		// candidates ranking the same but putting the coordinates elsewhere make the choice a guess
		$alternatives = 0;
		foreach ($result->candidates as $candidate)
			if (self::rank($candidate) == self::rank($best) && $candidate['points'] != $best['points'])
				$alternatives++;
		$result->textAlternatives = $alternatives;

		if (!empty($anchorViews))
			$result->textMethod = 'the anchor';
		elseif ($alternatives == 0)
			$result->textMethod = 'unique';
		else
			$result->textMethod = 'tie, decided by the top-left orientation';
	}

	/**
	 * Coordinates on moves first, a board the site could show counting as one more;
	 * then a board the site could show; then coordinates on setup stones and near the stones.
	 *
	 * @param array{score: array{int, int, int}, siteView: bool} $candidate
	 * @return array{int, int, int, int}
	 */
	private static function rank(array $candidate): array
	{
		$siteView = $candidate['siteView'] ? 1 : 0;
		return [$candidate['score'][0] + $siteView, $siteView, $candidate['score'][1], $candidate['score'][2]];
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
		return array_map(function (string $label) use ($transformation): ?string {
			$point = CommentCoordinates::labelToPoint($label, $this->size);
			if (!$point)
				return null;
			return implode('/', $transformation->applyToPoint($point[0], $point[1], $this->size));
		}, $labels);
	}

	/**
	 * Compares the colour written right before a coordinate ("black D4", "w-T19")
	 * with the colour of the moves at that point, and the side to play the text
	 * names ("in a white to kill problem") with the one in the description,
	 * leaving out hypotheticals.
	 *
	 * @return array{same: int, swapped: int}
	 */
	private function colorEvidence(string $text, BoardTransformation $transformation): array
	{
		$evidence = ['same' => 0, 'swapped' => 0];
		$text = CommentCoordinates::withoutLinks($text);
		preg_match_all(CommentCoordinates::COORDINATE_PATTERN, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
		$points = array_map(function (array $match) use ($transformation): ?array {
			$point = CommentCoordinates::labelToPoint(strtoupper($match[1][0]) . $match[2][0], $this->size);
			return $point ? $transformation->applyToPoint($point[0], $point[1], $this->size) : null;
		}, $matches);
		$lineColors = $this->colorsInLines($points);
		foreach ($matches as $index => $match)
		{
			$before = self::sentenceBefore($text, $match[0][1]);
			if (preg_match(self::HYPOTHETICAL_PATTERN, $before))
				continue;
			// "black D4", "w-T19", "bL18", but not "kills white - D4"
			if (preg_match('/(black|white)(?:\s+|[-:])$/i', $before, $word))
				$color = strtolower($word[1]) == 'black' ? 'B' : 'W';
			elseif (preg_match('/(?:^|[^\p{L}\p{N}])([bwBW])(?:\s*|[-:])$/u', $before, $letter))
				$color = strtoupper($letter[1]);
			else
				continue;
			if (!$points[$index])
				continue;
			$colors = $lineColors[$index] ?? $this->colorsAt($points[$index]);
			if (count($colors) != 1)
				continue; // no move there, or both colours play there in different lines
			$evidence[$colors[0] == $color ? 'same' : 'swapped']++;
		}

		if ($this->description !== null && preg_match(self::SIDE_TO_PLAY_PATTERN, $this->description, $side))
		{
			preg_match_all(self::SIDE_TO_PLAY_PATTERN, $text, $mentions, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
			foreach ($mentions as $mention)
				if (!preg_match(self::HYPOTHETICAL_PATTERN, self::sentenceBefore($text, $mention[0][1])))
					$evidence[strtoupper($mention[1][0][0]) == strtoupper($side[1][0]) ? 'same' : 'swapped']++;
		}
		return $evidence;
	}

	/**
	 * @param array{int, int} $point
	 * @return string[] the colours of all moves at the point
	 */
	private function colorsAt(array $point): array
	{
		$colors = array_map(fn(SgfTree $node) => $node->move($this->size)[0], $this->moves[$point[0] . '/' . $point[1]] ?? []);
		return array_values(array_unique($colors));
	}

	/**
	 * The colours of the coordinates that play a line of the tree, LINE_LENGTH or
	 * more one after the other ("W B1, B C2, W A2"), by their index.
	 *
	 * @param array<?array{int, int}> $points of the coordinates of the text, in order
	 * @return array<int, string[]>
	 */
	private function colorsInLines(array $points): array
	{
		$colors = [];
		foreach ($points as $start => $point)
			foreach ($point ? $this->moves[$point[0] . '/' . $point[1]] ?? [] : [] as $node)
			{
				$line = [$start => $node];
				for ($index = $start + 1; isset($points[$index]) && ($node = $node->childWithMoveAt($points[$index][0], $points[$index][1], $this->size)); $index++)
					$line[$index] = $node;
				if (count($line) >= self::LINE_LENGTH)
					foreach ($line as $index => $lineNode)
						$colors[$index][] = $lineNode->move($this->size)[0];
			}
		return array_map(fn(array $lineColors) => array_values(array_unique($lineColors)), $colors);
	}

	/** The part of the sentence that comes before the offset. */
	private static function sentenceBefore(string $text, int $offset): string
	{
		$sentences = preg_split('/[.!?\n]/', substr($text, 0, $offset));
		return end($sentences);
	}
}

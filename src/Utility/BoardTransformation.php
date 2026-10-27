<?php

namespace App\Utility;

//One of the 16 ways a square board can be reoriented: an optional transpose
//(x and y swapped), followed by the optional flips of x and y, and an optional
//swap of black and white. Points are 1-based (x from the left, y from the top),
//as besogo uses them. The JSON form matches besogo's getTransformationFromSgf.
class BoardTransformation
{
	public function __construct(
		public readonly bool $transpose = false,
		public readonly bool $flipX = false,
		public readonly bool $flipY = false,
		public readonly bool $invertColors = false) {}

	public static function identity(): BoardTransformation
	{
		return new BoardTransformation();
	}

	public static function fromArray(array $data): BoardTransformation
	{
		return new BoardTransformation(
			!empty($data['transpose']),
			!empty($data['flipX']),
			!empty($data['flipY']),
			!empty($data['invertColors']));
	}

	//Parses the JSON form; anything that is not an object reads as the identity.
	public static function fromJson(?string $json): BoardTransformation
	{
		$data = $json ? json_decode($json, true) : null;
		return is_array($data) ? self::fromArray($data) : self::identity();
	}

	public function toJson(): string
	{
		return json_encode([
			'transpose' => $this->transpose,
			'flipX' => $this->flipX,
			'flipY' => $this->flipY,
			'invertColors' => $this->invertColors]);
	}

	//All 16 transformations, the identity first.
	public static function all(): array
	{
		$result = [];
		foreach ([false, true] as $invertColors)
			foreach ([false, true] as $transpose)
				foreach ([false, true] as $flipY)
					foreach ([false, true] as $flipX)
						$result[] = new BoardTransformation($transpose, $flipX, $flipY, $invertColors);
		return $result;
	}

	public function isIdentity(): bool
	{
		return !$this->transpose && !$this->flipX && !$this->flipY && !$this->invertColors;
	}

	public function movesPoints(): bool
	{
		return $this->transpose || $this->flipX || $this->flipY;
	}

	public function equals(BoardTransformation $other): bool
	{
		return $this->transpose == $other->transpose
			&& $this->flipX == $other->flipX
			&& $this->flipY == $other->flipY
			&& $this->invertColors == $other->invertColors;
	}

	/** @return array{int, int} */
	public function applyToPoint(int $x, int $y, int $size): array
	{
		if ($this->transpose)
			[$x, $y] = [$y, $x];
		if ($this->flipX)
			$x = $size + 1 - $x;
		if ($this->flipY)
			$y = $size + 1 - $y;
		return [$x, $y];
	}

	public function inverse(): BoardTransformation
	{
		// undoing flips then a transpose is the transpose followed by the flips of the swapped axes
		if (!$this->transpose)
			return $this;
		return new BoardTransformation(true, $this->flipY, $this->flipX, $this->invertColors);
	}

	//The transformation applying this one first and then $next.
	public function then(BoardTransformation $next): BoardTransformation
	{
		// (1, 2) lies on no symmetry axis, so its image identifies the result
		[$x, $y] = $this->applyToPoint(1, 2, 19);
		[$x, $y] = $next->applyToPoint($x, $y, 19);
		$invertColors = $this->invertColors != $next->invertColors;
		foreach (self::all() as $candidate)
			if ($candidate->invertColors == $invertColors && $candidate->applyToPoint(1, 2, 19) === [$x, $y])
				return $candidate;
		throw new \LogicException('Board transformations are closed under composition.');
	}
}

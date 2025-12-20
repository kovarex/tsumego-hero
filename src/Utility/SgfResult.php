<?php

declare(strict_types=1);

class SgfResult
{
	/**
	 * @param array<int,BoardPosition> $blackStones
	 * @param array<int,BoardPosition> $whiteStones
	 * @param array{int,int} $info
	 * @param int $size
	 */
	public function __construct(array $blackStones, array $whiteStones, array $info, int $size)
	{
		$this->blackStones = $blackStones;
		$this->whiteStones = $whiteStones;
		$this->info = $info;
		$this->size = $size;
	}

	public function getStoneCount(): int
	{
		return count($this->blackStones) + count($this->whiteStones);
	}

	public function getMirrored(): SgfResult
	{
		$result = new SgfResult([], [], $this->info, $this->size);
		foreach ($this->blackStones as $blackStone)
			$result->blackStones[] = $blackStone->getMirrored();
		foreach ($this->whiteStones as $whiteStone)
			$result->whiteStones[] = $whiteStone->getMirrored();
		return $result;
	}

	public function getShifted(BoardPosition $shift): SgfResult
	{
		$result = new SgfResult([], [], $this->info, $this->size);
		foreach ($this->blackStones as $blackStone)
			$result->blackStones[] = $blackStone->getShifted($shift);
		foreach ($this->whiteStones as $whiteStone)
			$result->whiteStones[] = $whiteStone->getShifted($shift);
		return $result;
	}

	public function getLowest(): BoardPosition
	{
		$result = new BoardPosition($this->size, $this->size);
		foreach ($this->blackStones as $blackStone)
			$result->minEqual($blackStone);
		foreach ($this->whiteStones as $whiteStone)
			$result->minEqual($whiteStone);
		return $result;
	}

	public array $blackStones;
	public array $whiteStones;
	public array $info;
	public int $size;
}

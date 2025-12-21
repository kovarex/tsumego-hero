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
			$result->blackStones[] = BoardPosition::mirror($blackStone);
		foreach ($this->whiteStones as $whiteStone)
			$result->whiteStones[] = BoardPosition::mirror($whiteStone);
		return $result;
	}

	public function getColorSwitched(): SgfResult
	{
		return new SgfResult($this->whiteStones, $this->blackStones, $this->info, $this->size);
	}

	public function getShifted(int $shift): SgfResult
	{
		$result = new SgfResult([], [], $this->info, $this->size);
		foreach ($this->blackStones as $blackStone)
			$result->blackStones[] = BoardPosition::shift($blackStone, $shift);
		foreach ($this->whiteStones as $whiteStone)
			$result->whiteStones[] = BoardPosition::shift($whiteStone, $shift);
		return $result;
	}

	public function getLowest(): int
	{
		$result = BoardPosition::pack($this->size, $this->size);
		foreach ($this->blackStones as $blackStone)
			$result = BoardPosition::min($result, $blackStone);
		foreach ($this->whiteStones as $whiteStone)
			$result = BoardPosition::min($result, $whiteStone);
		return $result;
	}

	public array $blackStones;
	public array $whiteStones;
	public array $info;
	public int $size;
}

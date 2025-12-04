<?php

declare(strict_types=1);

readonly class SgfResult
{
	/**
	 * @param array<int,BoardPosition> $blackStones
	 * @param array<int,BoardPosition> $whiteStones
	 * @param array{int,int} $info
	 * @param int $size
	 */
	public function __construct(
		public array $blackStones,
		public array $whiteStones,
		public array $info,
		public int $size
	) {}

	public function getStoneCount(): int
	{
		return count($this->blackStones) + count($this->whiteStones);
	}
}

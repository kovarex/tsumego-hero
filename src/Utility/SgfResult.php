<?php

declare(strict_types=1);

readonly class SgfResult
{
	/**
	 * @param array<int,array{int,int,string}> $blackStones
	 * @param array<int,array{int,int,string}> $whiteStones
	 * @param array{int,int} $info
	 * @param int $size
	 */
	public function __construct(
		public array $blackStones,
		public array $whiteStones,
		public array $info,
		public int $size
	) {}
}

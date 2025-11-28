<?php

declare(strict_types=1);

readonly class SgfResult
{
	/**
	 * @param array<int,array<int,string>> $board
	 * @param array<int,array{int,int,string}> $stones
	 * @param array{int,int} $info
	 * @param int $size
	 */
	public function __construct(
		public array $board,
		public array $stones,
		public array $info,
		public int $size
	) {}
}

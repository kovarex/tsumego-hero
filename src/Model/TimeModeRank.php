<?php

class TimeModeRank extends AppModel
{
	// Time mode rank IDs
	public const RANK_15K = 1;
	public const RANK_14K = 2;
	public const RANK_13K = 3;
	public const RANK_12K = 4;
	public const RANK_11K = 5;
	public const RANK_10K = 6;
	public const RANK_9K = 7;
	public const RANK_8K = 8;
	public const RANK_7K = 9;
	public const RANK_6K = 10;
	public const RANK_5K = 11;
	public const RANK_4K = 12;
	public const RANK_3K = 13;
	public const RANK_2K = 14;
	public const RANK_1K = 15;
	public const RANK_1D = 16;
	public const RANK_2D = 17;
	public const RANK_3D = 18;
	public const RANK_4D = 19;
	public const RANK_5D = 20;

	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] = 'time_mode_rank';
		parent::__construct($id, $table, $ds);
	}
}

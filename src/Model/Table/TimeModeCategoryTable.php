<?php

namespace App\Model\Table;

//How much time a time mode run gives for a problem: Blitz, Fast or Slow.
class TimeModeCategoryTable extends AppTable
{
	// the rows of time_mode_category
	public const BLITZ = 1;
	public const FAST = 2;
	public const SLOW = 3;
}

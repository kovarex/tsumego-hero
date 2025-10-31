<?php

class TimeModeRank {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'time_mode_rank';
		parent::__construct($id, $table, $ds);
	}
}

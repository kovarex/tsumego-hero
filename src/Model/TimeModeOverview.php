<?php

class TimeModeOverview extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'time_mode_overview';
		parent::__construct($id, $table, $ds);
	}
}

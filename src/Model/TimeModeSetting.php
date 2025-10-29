<?php

class TimeModeSetting extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'time_mode_setting';
		parent::__construct($id, $table, $ds);
	}
}

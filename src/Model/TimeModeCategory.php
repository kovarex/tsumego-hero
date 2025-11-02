<?php

class TimeModeCategory extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'time_mode_category';
		parent::__construct($id, $table, $ds);
	}
}

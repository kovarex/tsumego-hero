<?php

class TimeModeAttempt extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'time_mode_attempt';
		parent::__construct($id, $table, $ds);
	}
}

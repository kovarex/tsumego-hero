<?php

class TsumegoStatus extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'tsumego_status';
		parent::__construct($id, $table, $ds);
	}
}

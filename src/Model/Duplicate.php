<?php

class Duplicate extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'duplicate';
		parent::__construct($id, $table, $ds);
	}
}

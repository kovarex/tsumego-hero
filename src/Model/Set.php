<?php

class Set extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'set';
		parent::__construct($id, $table, $ds);
	}

	public $hasMany = ['SetConnection'];
}

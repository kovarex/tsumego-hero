<?php

class Reputation extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'reputation';
		parent::__construct($id, $table, $ds);
	}
}

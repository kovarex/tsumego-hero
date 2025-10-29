<?php

class Joseki extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'joseki';
		parent::__construct($id, $table, $ds);
	}
}

<?php

class Activate extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'activate';
		parent::__construct($id, $table, $ds);

	}
}

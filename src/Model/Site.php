<?php

class Site extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'site';
		parent::__construct($id, $table, $ds);

	}
}

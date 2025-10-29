<?php

class Purge extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'purge';
		parent::__construct($id, $table, $ds);
	}
}

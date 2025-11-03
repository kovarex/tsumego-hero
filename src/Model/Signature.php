<?php

class Signature extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'signature';
		parent::__construct($id, $table, $ds);
	}
}

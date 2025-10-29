<?php

class Reject extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'reject';
		parent::__construct($id, $table, $ds);
	}
	public $name = 'Reject';

}

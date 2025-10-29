<?php

class UserBoard extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'user_board';
		parent::__construct($id, $table, $ds);
	}
}

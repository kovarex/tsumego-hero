<?php

class AdminActivity extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'admin_activity';
		parent::__construct($id, $table, $ds);

	}
}

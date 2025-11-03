<?php

class Answer extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'answer';
		parent::__construct($id, $table, $ds);

	}
}

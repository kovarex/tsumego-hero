<?php

class TsumegoVariant extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'tsumego_variant';
		parent::__construct($id, $table, $ds);
	}
}

<?php

class Tsumego extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] = 'tsumego';
		parent::__construct($id, $table, $ds);
	}

	public $belongsTo = ['Set'];
	public $hasMany = ['SetConnection'];

	public $validate = [
		'title' => [
			'rule' => 'notBlank',
		],
		'sgf1' => [
			'rule' => 'notBlank',
		],
	];
}

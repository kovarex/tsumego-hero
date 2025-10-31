<?php

class Tsumego extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] = 'tsumego';
		parent::__construct($id, $table, $ds);
	}

	public function initialize(array $config) {
		parent::initialize($config);
		$this->belongsToMany('SetConnection');
	}

	public $hasMany = ['ContainedInSetConnections' => ['className' => 'SetConnection']];

	public $validate = [
		'title' => [
			'rule' => 'notBlank',
		],
		'sgf1' => [
			'rule' => 'notBlank',
		],
	];
}

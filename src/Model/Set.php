<?php

class Set extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'set';
		parent::__construct($id, $table, $ds);
	}

	public function initialize(array $config) {
		parent::initialize($config);
		$this->hasMany('SetConnection');
	}

	public $hasMany = ['ContainedSetConnections' => ['className' => 'SetConnection']];
}

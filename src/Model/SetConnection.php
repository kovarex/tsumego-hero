<?php

class SetConnection extends AppModel {
	public function __construct($id = false, $table = null, $ds = null) {
		$id['table'] =  'set_connection';
		parent::__construct($id, $table, $ds);
	}

	public function initialize(array $config) {
		parent::initialize($config);
		$this->hasOne('Set');
		$this->hasOne('Tsumego');
	}

	public $hasOne = [
		'tsumego' => ['className' => 'Tsumego'],
        'set' => ['className' => 'Set']];
}

<?php

class Tsumego extends AppModel {
	public function __construct() {
		parent::__construct(false, 'tsumego');
	}
	public $validate = [
		'title' => [
			'rule' => 'notBlank',
		],
		'sgf1' => [
			'rule' => 'notBlank',
		],
	];
}

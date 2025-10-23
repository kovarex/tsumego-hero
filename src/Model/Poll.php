<?php

class Poll extends AppModel {
	public $validate = [
		'img' => [
			'rule' => 'notBlank',
		],
	];

}

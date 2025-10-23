<?php

class Post extends AppModel {
	public $validate = [
		'title' => [
			'rule' => 'notBlank',
		],
		'sgf1' => [
			'rule' => 'notBlank',
		],
	];

}

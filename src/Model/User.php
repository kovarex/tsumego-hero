<?php

class User extends AppModel {
	public function __construct() {
		parent::__construct(false, 'user');
	}

	public $validate = [
		'name' => [

			'notempty' => [
				'rule' => 'notBlank',
				'required' => true,
				'message' => 'Please Insert Name',
			],

			'minmax' => [
				'rule' => ['between', 3, 30],
				'message' => 'The length of the name should have at least 3 characters',
			],

			'checkUnique' => [
				'rule' => ['checkUnique', 'name'],
				'message' => 'Name already exists',
			],
		],

		'email' => [
			'notempty' => [
				'rule' => 'notBlank',
				'message' => 'Insert Email',
			],

			'email' => [
				'rule' => ['email', false],
				'message' => 'Enter a valid email-address',
			],

			'checkUnique' => [
				'rule' => ['checkUnique', 'email'],
				'message' => 'Email already exists',
			],
		],
	];
}

<?php
class User extends AppModel {

	public $name = 'User';

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

		'pw' => [
			'notempty' => [
				'rule' => 'notBlank',
				'message' => 'Please insert Password',
			],

			'minmax' => [
				'rule' => ['between', 4, 40],
				'message' => 'The password should have at least 4 characters',
			],
			'match' => [
				'rule' => 'checkPasswords',
				'message' => 'Passwords do not match',
			],
		],
	];

	public function checkUnique($data, $field) {
		$valid = false;
		if ($this->hasField($field)) {
			$valid = $this->isUnique([$field => $data]);
		}

		return $valid;
	}

	public function checkPasswords() {
		if (isset($this->data['User']['pw2'])) {
			return $this->data['User']['pw'] == $this->data['User']['pw2'];
		}

			return true;
	}

}

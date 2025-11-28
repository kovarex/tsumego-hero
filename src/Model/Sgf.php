<?php

class Sgf extends AppModel
{
	public $validate = [
		'tsumego_id' => [
			'required' => [
				'rule' => 'numeric',
				'message' => 'Tsumego id is required',
				'allowEmpty' => false,
				'required' => true,
			],
		],
		'sgf' => [
			'notBlank' => [
				'rule' => 'notBlank',
				'message' => 'SGF content is required',
				'allowEmpty' => false,
				'required' => true,
			],
		],
	];

	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'sgf';
		parent::__construct($id, $table, $ds);
	}
}

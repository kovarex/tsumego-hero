<?php

class AdminActivity extends AppModel
{
	public $useTable = 'admin_activity';
	public $actsAs = ['Containable'];

	public $belongsTo = [
		'AdminActivityType' => [
			'className' => 'AdminActivityType',
			'foreignKey' => 'type',
			'fields' => ['name']
		]
	];
}

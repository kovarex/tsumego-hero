<?php

class TimeModeSession extends AppModel
{
	public $belongsTo = [
		'TimeModeSessionStatus' => [
			'className' => 'TimeModeSessionStatus',
			'foreignKey' => 'time_mode_session_status_id',
		],
	];

	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'time_mode_session';
		parent::__construct($id, $table, $ds);
	}
}

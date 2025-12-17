<?php

class TimeModeSession extends AppModel
{
	public $belongsTo = [
		'TimeModeCategory' => [
			'className' => 'TimeModeCategory',
			'foreignKey' => 'time_mode_category_id',
		],
		'TimeModeSessionStatus' => [
			'className' => 'TimeModeSessionStatus',
			'foreignKey' => 'time_mode_session_status_id',
		],
		'TimeModeRank' => [
			'className' => 'TimeModeRank',
			'foreignKey' => 'time_mode_rank_id',
		],
	];

	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'time_mode_session';
		parent::__construct($id, $table, $ds);
	}
}

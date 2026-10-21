<?php

class TimeModeAttempt extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'time_mode_attempt';
		parent::__construct($id, $table, $ds);
	}
}

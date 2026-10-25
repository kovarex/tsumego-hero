<?php

class TsumegoAttempt extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'tsumego_attempt';
		parent::__construct($id, $table, $ds);
	}
}

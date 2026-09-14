<?php

class ProgressDeletion extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'progress_deletion';
		parent::__construct($id, $table, $ds);
	}
}

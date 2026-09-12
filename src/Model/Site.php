<?php

class Site extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'site';
		parent::__construct($id, $table, $ds);

	}
}

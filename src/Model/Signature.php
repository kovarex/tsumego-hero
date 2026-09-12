<?php

class Signature extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'signature';
		parent::__construct($id, $table, $ds);
	}
}

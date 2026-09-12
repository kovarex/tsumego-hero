<?php

class TsumegoVariant extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'tsumego_variant';
		parent::__construct($id, $table, $ds);
	}
}

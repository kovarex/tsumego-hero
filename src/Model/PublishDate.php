<?php

class PublishDate extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'publish_date';
		parent::__construct($id, $table, $ds);
	}
}

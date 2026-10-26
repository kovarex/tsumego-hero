<?php

class UserContribution extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'user_contribution';
		parent::__construct($id, $table, $ds);
	}
}

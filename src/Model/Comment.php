<?php

class Comment extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'comment';
		parent::__construct($id, $table, $ds);
	}
	public $name = 'Comment';
}

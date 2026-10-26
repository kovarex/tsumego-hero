<?php

class Reject extends AppModel
{
	// the rows of reject_type, what was rejected
	public const TYPE_PROPOSAL = 1; // an SGF proposal, sgf_id is the rejected version
	public const TYPE_TAG = 2; // a tag on a problem, tag_name keeps its name should the tag be deleted
	public const TYPE_TAG_NAME = 3; // a new tag, deleted with the rejection, so only tag_name is left

	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'reject';
		parent::__construct($id, $table, $ds);
	}
	public $name = 'Reject';

}

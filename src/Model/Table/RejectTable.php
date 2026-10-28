<?php

namespace App\Model\Table;

//A proposal or tag an admin rejected, with what the admin wrote the proposer. The class name gives
//the table, reject, and the entity, Reject.
class RejectTable extends AppTable
{
	// the rows of reject_type, what was rejected
	public const TYPE_PROPOSAL = 1; // an SGF proposal, sgf_id is the rejected version
	public const TYPE_TAG = 2; // a tag on a problem, tag_name keeps its name should the tag be deleted
	public const TYPE_TAG_NAME = 3; // a new tag, deleted with the rejection, so only tag_name is left
}

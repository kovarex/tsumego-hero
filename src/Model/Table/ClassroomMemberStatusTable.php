<?php

namespace App\Model\Table;

//Where a membership in a class stands. The side that did not start it accepts it: the player
//accepts an invitation of the teacher, the teacher accepts a request of the player.
class ClassroomMemberStatusTable extends AppTable
{
	// the rows of classroom_member_status
	public const INVITED = 1;
	public const REQUESTED = 2;
	public const ACCEPTED = 3;
}

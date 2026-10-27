<?php

//Where a membership in a class stands. The side that did not start it accepts it: the player
//accepts an invitation of the teacher, the teacher accepts a request of the player.
class ClassroomMemberStatus extends AppModel
{
	public const INVITED = 1;
	public const REQUESTED = 2;
	public const ACCEPTED = 3;

	public $useTable = 'classroom_member_status';
}

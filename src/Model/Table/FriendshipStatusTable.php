<?php

namespace App\Model\Table;

//Where a friendship stands: asked and waiting for the answer of the asked player, or accepted.
class FriendshipStatusTable extends AppTable
{
	// the rows of friendship_status
	public const PENDING = 1;
	public const ACCEPTED = 2;
}

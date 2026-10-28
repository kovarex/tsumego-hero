<?php

namespace App\Model\Table;

use Cake\Validation\Validator;

//A comment on a tsumego problem, standalone or in a TsumegoIssue (tsumego_issue_id, NULL when
//standalone). position is the node the comment is anchored to, see besogo's makeCommentPosition,
//and deleted is when the comment was deleted, NULL when it is not.
//The coordinates in message and position are those of the current SGF version, without the
//transformation the board is shown with (see App\Utility\CommentCoordinates).
class TsumegoCommentTable extends AppTable
{
	public const MAX_MESSAGE_LENGTH = 2048;

	public function validationDefault(Validator $validator): Validator
	{
		return $validator
			->notBlank('message', 'Comment message is required')
			->maxLength('message', self::MAX_MESSAGE_LENGTH, 'Comment is too long (maximum 2048 characters)');
	}
}

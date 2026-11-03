<?php

namespace App\Model\Table;

use App\Model\Entity\TsumegoComment;
use App\Model\Entity\TsumegoIssue;
use Cake\ORM\TableRegistry;
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

	//A comment as the comment section shows it (app/comments/commentTypes.ts).
	/**
	 * @param array<string, mixed>|null $author The user row of its writer, see authorsOf()
	 * @return array<string, mixed>
	 */
	public static function payload(TsumegoComment $comment, ?array $author): array
	{
		return [
			'id' => $comment->id,
			'text' => $comment->message,
			'user_id' => $comment->user_id,
		] + self::authorPayload($author) + [
			'created' => $comment->created->format('c'),
			'position' => $comment->position,
		];
	}

	//The writer of a comment or an issue as the comment section shows them; a deleted account has no name nor avatar.
	/**
	 * @param array<string, mixed>|null $author A user row
	 * @return array{user_display_name: ?string, user_avatar_url: ?string, user_rating: mixed, isAdmin: bool}
	 */
	public static function authorPayload(?array $author): array
	{
		return [
			'user_display_name' => UserTable::displayNameOrNull($author),
			'user_avatar_url' => $author === null ? null : UserTable::avatarOrNull($author),
			'user_rating' => $author['rating'] ?? null,
			'isAdmin' => UserTable::isAdmin($author),
		];
	}

	//The users who wrote the comments and issues, by id, as the rows of the user table.
	/**
	 * @param list<TsumegoComment|TsumegoIssue> $writings
	 * @return array<int, array<string, mixed>>
	 */
	public static function authorsOf(array $writings): array
	{
		if (empty($writings))
			return [];
		$authors = [];
		$authorIds = array_values(array_unique(array_map(fn($writing) => $writing->user_id, $writings)));
		foreach (TableRegistry::getTableLocator()->get('User')->find()->where(['id IN' => $authorIds])->all() as $user)
			$authors[$user->id] = UserTable::rowOf($user);
		return $authors;
	}
}

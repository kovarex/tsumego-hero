<?php

namespace App\Utility;

use Cake\ORM\TableRegistry;
use App\Model\Table\MessageTable;
use Cake\Routing\Router;
use App\Model\Table\UserTable;
use App\Model\Table\SetConnectionTable;

//Tells a player what an admin decided about their proposal: a new version of a problem, a tag
//on a problem or a new tag. It goes as a message from the admin, so the player sees who decided
//and can answer them.
class ProposalFeedback
{
	//Longest comment an admin can send with a decision, in characters.
	public const COMMENT_MAX_LENGTH = 2000;

	//The comment the admin sent with the decision, '' when none was written (cut to the longest that fits).
	public static function commentFromRequest(mixed $comment): string
	{
		$comment = trim(str_replace(["\r\n", "\r"], "\n", is_string($comment) ? $comment : ''));
		return mb_substr($comment, 0, self::COMMENT_MAX_LENGTH);
	}

	//The name of the problem as the player knows it, the set and the number in it.
	public static function problemName(int $tsumegoID): string
	{
		$setConnection = SetConnectionTable::findDisplaySetConnection($tsumegoID);
		if (!$setConnection)
			return 'problem ' . $tsumegoID;
		$set = TableRegistry::getTableLocator()->get('Set')->find()->where(['id' => $setConnection->set_id])->first();
		return trim($set->title . ' ' . $set->title2) . ' ' . $setConnection->num;
	}

	//Sends the decision from the signed in admin to the proposer. Nothing is sent when the admin decided
	//about their own proposal or the proposer's account is gone.
	//$decision: The first line, e.g. "Your proposal for X was rejected by Y."
	//$tsumegoID: The problem, linked at the end
	//$proposalDescription: What the proposer wrote about their proposal, quoted so they know which one it was
	//$comment: The admin's comment
	//$emailer: CakeEmail from AppController::_getEmailer()
	//$diffUrl: The proposed version compared with the one it would replace, linked after the problem
	//Returns whether the message was sent
	public static function send(int $proposerUserID, string $decision, ?int $tsumegoID, ?string $proposalDescription, string $comment, mixed $emailer, ?string $diffUrl = null): bool
	{
		$paragraphs = [$decision];
		if ($proposalDescription !== null && $proposalDescription !== '')
			$paragraphs[] = 'You wrote: ' . $proposalDescription;
		if ($comment !== '')
			$paragraphs[] = self::adminName() . ': ' . $comment;
		if ($tsumegoID !== null)
		{
			$setConnection = SetConnectionTable::findDisplaySetConnection($tsumegoID);
			if ($setConnection)
				$paragraphs[] = Router::url('/' . $setConnection->id, true);
		}
		if ($diffUrl !== null)
			$paragraphs[] = 'Your changes: ' . $diffUrl;
		$text = implode("\n\n", $paragraphs);

		if (MessageTable::problemWith(Auth::getUserID(), $proposerUserID, $text) !== null)
			return false;
		MessageTable::send(Auth::getUserID(), $proposerUserID, $text, $emailer);
		return true;
	}

	//The editor showing the proposed version compared with the one it would replace, the version alone when there is none.
	public static function diffUrl(int $proposalSgfID, ?int $replacedSgfID): string
	{
		$query = 'sgfID=' . $proposalSgfID;
		if ($replacedSgfID !== null)
			$query .= '&diffID=' . $replacedSgfID;
		return Router::url('/editor/?' . $query, true);
	}

	//The name of the signed in admin, for the decision line.
	public static function adminName(): string
	{
		return UserTable::displayName(Auth::getUser());
	}
}

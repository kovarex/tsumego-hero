<?php

namespace App\Utility;

use ClassRegistry;
use Message;
use Router;
use User;

/**
 * Tells a player what an admin decided about their proposal: a new version of a problem, a tag
 * on a problem or a new tag. It goes as a message from the admin, so the player sees who decided
 * and can answer them.
 */
class ProposalFeedback
{
	/** Longest comment an admin can send with a decision, in characters. */
	public const COMMENT_MAX_LENGTH = 2000;

	/** The comment the admin sent with the decision, '' when none was written (cut to the longest that fits). */
	public static function commentFromRequest(mixed $comment): string
	{
		$comment = trim(str_replace(["\r\n", "\r"], "\n", is_string($comment) ? $comment : ''));
		return mb_substr($comment, 0, self::COMMENT_MAX_LENGTH);
	}

	/** The name of the problem as the player knows it, the set and the number in it. */
	public static function problemName(int $tsumegoID): string
	{
		$setConnection = ClassRegistry::init('SetConnection')->findDisplaySetConnection($tsumegoID);
		if (!$setConnection)
			return 'problem ' . $tsumegoID;
		$set = ClassRegistry::init('Set')->findById($setConnection['SetConnection']['set_id']);
		return trim($set['Set']['title'] . ' ' . $set['Set']['title2']) . ' ' . $setConnection['SetConnection']['num'];
	}

	/**
	 * Sends the decision from the signed in admin to the proposer. Nothing is sent when the admin decided
	 * about their own proposal or the proposer's account is gone.
	 *
	 * @param string $decision The first line, e.g. "Your proposal for X was rejected by Y."
	 * @param int|null $tsumegoID The problem, linked at the end
	 * @param string|null $proposalDescription What the proposer wrote about their proposal, quoted so they know which one it was
	 * @param string $comment The admin's comment
	 * @param mixed $emailer CakeEmail from AppController::_getEmailer()
	 * @return bool Whether the message was sent
	 */
	public static function send(int $proposerUserID, string $decision, ?int $tsumegoID, ?string $proposalDescription, string $comment, mixed $emailer): bool
	{
		$paragraphs = [$decision];
		if ($proposalDescription !== null && $proposalDescription !== '')
			$paragraphs[] = 'You wrote: ' . $proposalDescription;
		if ($comment !== '')
			$paragraphs[] = self::adminName() . ': ' . $comment;
		if ($tsumegoID !== null)
		{
			$setConnection = ClassRegistry::init('SetConnection')->findDisplaySetConnection($tsumegoID);
			if ($setConnection)
				$paragraphs[] = Router::url('/' . $setConnection['SetConnection']['id'], true);
		}
		$text = implode("\n\n", $paragraphs);

		if (Message::problemWith(Auth::getUserID(), $proposerUserID, $text) !== null)
			return false;
		Message::send(Auth::getUserID(), $proposerUserID, $text, $emailer);
		return true;
	}

	/** The name of the signed in admin, for the decision line. */
	public static function adminName(): string
	{
		return User::displayName(Auth::getUser());
	}
}

<?php

namespace App\Utility;

//The Accept and Reject buttons of a proposal in the admin panel, with the comment the proposer
//gets in a message (see ProposalFeedback). Rejecting needs the comment, accepting doesn't.
class ProposalDecisionForm
{
	public static function render(string $acceptAction, string $rejectAction, string $acceptID, string $rejectID, string $commentID): void
	{
		echo '<form method="post" class="proposal-decision" action="' . h($rejectAction) . '">';
		echo '<input type="text" name="comment" class="proposal-decision__comment" id="' . h($commentID) . '" maxlength="' . ProposalFeedback::COMMENT_MAX_LENGTH . '" placeholder="Comment to the proposer" title="Sent to the proposer as a message from you, needed to reject" required>';
		echo '<button type="submit" class="btn btn--small" id="' . h($acceptID) . '" formaction="' . h($acceptAction) . '" formnovalidate>Accept</button>';
		echo '<button type="submit" class="btn btn--small" id="' . h($rejectID) . '">Reject</button>';
		echo '</form>';
	}
}

<?php

namespace App\Controller;

use App\Attribute\HttpPost;
use App\Model\Entity\Sgf;
use App\Model\Entity\TagConnection;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\RejectTable;
use App\Model\Table\SgfTable;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\ProposalFeedback;
use App\Utility\SgfCommentReorientation;
use Cake\Http\Response;

//The decisions of an admin on what players proposed: a new version of a problem's SGF and a tag
//on a problem. The proposals are listed on the pages of the admin area, see AdminController.
class ProposalsController extends AppController
{
	private const string SGF_PROPOSALS_PAGE = '/admin/sgf-proposals';
	private const string TAG_PROPOSALS_PAGE = '/admin/tag-proposals';

	#[HttpPost]
	public function acceptSgf(int $sgfID): Response
	{
		$this->Authorization->authorize('Admin');

		$proposal = $this->undecidedSgfProposal($sgfID);
		if (!$proposal)
			return $this->redirect(self::SGF_PROPOSALS_PAGE);

		$replacedSgfID = SgfTable::findCurrentVersionID($proposal->tsumego_id);
		SgfTable::accept($proposal->id, Auth::getUserID());
		SgfCommentReorientation::afterAccepted($proposal->id);
		CookieFlash::set('Sgf proposal accepted', 'success');
		AdminActivityLogger::log(AdminActivityTypeTable::ACCEPT_PROPOSAL, $proposal->tsumego_id, null, null, $this->displayName($proposal->user_id));

		ProposalFeedback::send(
			(int) $proposal->user_id,
			'Your proposal for ' . ProposalFeedback::problemName($proposal->tsumego_id) . ' was accepted by ' . ProposalFeedback::adminName() . ', it is the current version of the problem now.',
			$proposal->tsumego_id,
			$proposal->description,
			ProposalFeedback::commentFromRequest($this->input('comment')),
			$this->_getEmailer(),
			ProposalFeedback::diffUrl($proposal->id, $replacedSgfID));

		return $this->redirect(self::SGF_PROPOSALS_PAGE);
	}

	#[HttpPost]
	public function rejectSgf(int $sgfID): Response
	{
		$this->Authorization->authorize('Admin');

		$proposal = $this->undecidedSgfProposal($sgfID);
		if (!$proposal)
			return $this->redirect(self::SGF_PROPOSALS_PAGE);

		$comment = $this->requiredComment('Write the proposer why the proposal is rejected.');
		if ($comment === null)
			return $this->redirect(self::SGF_PROPOSALS_PAGE);

		$this->saveReject([
			'user_id' => $proposal->user_id,
			'comment' => $comment,
			'tsumego_id' => $proposal->tsumego_id,
			'sgf_id' => $proposal->id,
			'reject_type_id' => RejectTable::TYPE_PROPOSAL]);
		// kept, so the proposer can still see what they proposed
		SgfTable::setStatus($proposal->id, SgfTable::STATUS_REJECTED);
		CookieFlash::set('Sgf proposal rejected', 'success');
		AdminActivityLogger::log(AdminActivityTypeTable::REJECT_PROPOSAL, $proposal->tsumego_id, null, null, $this->displayName($proposal->user_id));

		ProposalFeedback::send(
			(int) $proposal->user_id,
			'Your proposal for ' . ProposalFeedback::problemName($proposal->tsumego_id) . ' was rejected by ' . ProposalFeedback::adminName() . '.',
			$proposal->tsumego_id,
			$proposal->description,
			$comment,
			$this->_getEmailer(),
			ProposalFeedback::diffUrl($proposal->id, SgfTable::findCurrentVersionID($proposal->tsumego_id)));

		return $this->redirect(self::SGF_PROPOSALS_PAGE);
	}

	//Puts a rejected SGF proposal back among the ones waiting for a decision.
	#[HttpPost]
	public function reopenSgf(int $sgfID): Response
	{
		$this->Authorization->authorize('Admin');

		/** @var Sgf|null $proposal */
		$proposal = $this->fetchTable('Sgf')->find()->where(['id' => $sgfID])->first();
		if (!$proposal || $proposal->sgf_status_id != SgfTable::STATUS_REJECTED)
		{
			CookieFlash::set('There is no rejected sgf proposal to reopen.', 'fail');
			return $this->redirect(self::SGF_PROPOSALS_PAGE);
		}
		SgfTable::setStatus($proposal->id, SgfTable::STATUS_PROPOSED);
		$this->fetchTable('Reject')->deleteAll(['sgf_id' => $proposal->id]);
		CookieFlash::set('Sgf proposal reopened', 'success');
		AdminActivityLogger::log(AdminActivityTypeTable::REOPEN_PROPOSAL, $proposal->tsumego_id, null, null, $this->displayName($proposal->user_id));

		return $this->redirect(self::SGF_PROPOSALS_PAGE);
	}

	#[HttpPost]
	public function acceptTag(int $tagConnectionID): Response
	{
		$this->Authorization->authorize('Admin');

		$proposal = $this->unapprovedTagProposal($tagConnectionID);
		if (!$proposal)
			return $this->redirect(self::TAG_PROPOSALS_PAGE);

		$proposal->approved = true;
		$tag = $this->fetchTable('Tag')->get($proposal->tag_id);
		AdminActivityLogger::log(AdminActivityTypeTable::ACCEPT_TAG, $proposal->tsumego_id, null, null, $tag->name);
		$this->fetchTable('TagConnection')->saveOrFail($proposal);

		// tags are accepted by the dozen, so the proposer only hears about it when the admin had something to say
		$comment = ProposalFeedback::commentFromRequest($this->input('comment'));
		if ($comment !== '')
			ProposalFeedback::send(
				(int) $proposal->user_id,
				'Your tag ' . $tag->name . ' on ' . ProposalFeedback::problemName($proposal->tsumego_id) . ' was accepted by ' . ProposalFeedback::adminName() . '.',
				$proposal->tsumego_id,
				null,
				$comment,
				$this->_getEmailer());
		return $this->redirect(self::TAG_PROPOSALS_PAGE);
	}

	#[HttpPost]
	public function rejectTag(int $tagConnectionID): Response
	{
		$this->Authorization->authorize('Admin');

		$proposal = $this->unapprovedTagProposal($tagConnectionID);
		if (!$proposal)
			return $this->redirect(self::TAG_PROPOSALS_PAGE);

		$comment = $this->requiredComment('Write the proposer why the tag is rejected.');
		if ($comment === null)
			return $this->redirect(self::TAG_PROPOSALS_PAGE);

		$tag = $this->fetchTable('Tag')->get($proposal->tag_id);
		AdminActivityLogger::log(AdminActivityTypeTable::REJECT_TAG, $proposal->tsumego_id, null, $tag->name, null);
		$this->saveReject([
			'user_id' => $proposal->user_id,
			'comment' => $comment,
			'tsumego_id' => $proposal->tsumego_id,
			'reject_type_id' => RejectTable::TYPE_TAG,
			'tag_id' => $tag->id,
			'tag_name' => $tag->name]);
		$this->fetchTable('TagConnection')->deleteOrFail($proposal);

		ProposalFeedback::send(
			(int) $proposal->user_id,
			'Your tag ' . $tag->name . ' on ' . ProposalFeedback::problemName($proposal->tsumego_id) . ' was rejected by ' . ProposalFeedback::adminName() . '.',
			$proposal->tsumego_id,
			null,
			$comment,
			$this->_getEmailer());

		CookieFlash::set('Tag proposal rejected', 'success');
		return $this->redirect(self::TAG_PROPOSALS_PAGE);
	}

	//The SGF proposal still waiting for a decision, or null with the reason flashed
	private function undecidedSgfProposal(int $sgfID): ?Sgf
	{
		/** @var Sgf|null $proposal */
		$proposal = $this->fetchTable('Sgf')->find()->where(['id' => $sgfID])->first();
		if (!$proposal)
			CookieFlash::set('Sgf proposal doesn\'t exist.', 'fail');
		elseif ($proposal->sgf_status_id != SgfTable::STATUS_PROPOSED)
			CookieFlash::set('Sgf proposal was already decided.', 'fail');
		else
			return $proposal;
		return null;
	}

	//The tag proposal not accepted yet, or null with the reason flashed
	private function unapprovedTagProposal(int $tagConnectionID): ?TagConnection
	{
		/** @var TagConnection|null $proposal */
		$proposal = $this->fetchTable('TagConnection')->find()->where(['id' => $tagConnectionID])->first();
		if (!$proposal)
			CookieFlash::set('Tag proposal doesn\'t exist.', 'fail');
		elseif ($proposal->approved)
			CookieFlash::set('Tag proposal was already accepted.', 'fail');
		else
			return $proposal;
		return null;
	}

	//The comment of a rejection, which the proposer has to get, or null with the reason flashed
	private function requiredComment(string $whenMissing): ?string
	{
		$comment = ProposalFeedback::commentFromRequest($this->input('comment'));
		if ($comment !== '')
			return $comment;
		CookieFlash::set($whenMissing, 'fail');
		return null;
	}

	/** @param array<string, mixed> $fields */
	private function saveReject(array $fields): void
	{
		$rejectTable = $this->fetchTable('Reject');
		$rejectTable->saveOrFail($rejectTable->newEntity(['rejected_by_user_id' => Auth::getUserID()] + $fields));
	}

	private function displayName(int $userID): ?string
	{
		return $this->fetchTable('User')->find()->select(['display_name'])->where(['id' => $userID])->first()?->display_name;
	}
}

<?php

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response as CakeResponse;
use App\Model\Table\SgfTable;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Model\Table\AdminOverviewTypeTable;
use App\Model\Table\SgfReviewTable;
use App\Model\Table\TsumegoIssueTable;
use App\Utility\AdminActivityRenderer;
use App\Utility\Auth;
use App\Utility\RejectedSGFProposalsRenderer;
use App\Utility\SGFProposalsRenderer;
use App\Utility\SgfChangesRenderer;
use App\Utility\TagConnectionProposalsRenderer;
use App\Utility\TagProposalsRenderer;
use App\Utility\Util;

//The admin area: one page with a link to everything an admin does, each with a count of what
//waits there, and the pages of the proposals and of the admin activity.
class AdminController extends AppController
{
	#[HttpGet]
	public function index(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'Admin');
		$userID = Auth::getUserID();
		$this->set('newActivityCount', AdminOverviewTypeTable::countNewActivity($userID));
		$this->set('sgfProposalCount', SGFProposalsRenderer::total());
		$this->set('tagProposalCount', TagConnectionProposalsRenderer::total());
		$this->set('newTagCount', TagProposalsRenderer::total());
		$this->set('openIssueCount', TsumegoIssueTable::getIndexCounts()['open']);
		$this->set('newSgfChangeCount', AdminOverviewTypeTable::countNewSgfChanges($userID));
		$this->set('deletedAccountCount', $this->fetchTable('User')->find()->where(['deleted IS NOT' => null])->count());
	}

	#[HttpGet]
	public function activity(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'Admin activity');
		$newSince = AdminOverviewTypeTable::markSeen(Auth::getUserID(), AdminOverviewTypeTable::ACTIVITY);
		$this->set('adminActivityRenderer', new AdminActivityRenderer($this->queries(), $newSince));
	}

	#[HttpGet]
	public function sgfProposals(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'SGF proposals');
		$this->set('sgfProposalsRenderer', new SGFProposalsRenderer($this->queries()));
		$this->set('rejectedSgfProposalsRenderer', new RejectedSGFProposalsRenderer($this->queries()));
	}

	//Every version that became part of a problem, for the admins to review the changes of each other
	#[HttpGet]
	public function sgfChanges(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'SGF changes');
		$newSince = AdminOverviewTypeTable::markSeen(Auth::getUserID(), AdminOverviewTypeTable::SGF_CHANGES);
		$this->set('sgfChangesRenderer', new SgfChangesRenderer($this->queries(), $newSince));
	}

	//Ticks an SGF change as reviewed by the admin (reviewed=1), or takes the tick back (reviewed=0).
	//What the admin made part of a problem, their own change or a proposal they accepted, is
	//reviewed by the others.
	#[HttpPost]
	public function reviewSgfChange(string $sgfID): CakeResponse
	{
		$this->Authorization->authorize('Admin', 'reviewSgfChange');
		$sgf = $this->fetchTable('Sgf')->find()->select(['id', 'sgf_status_id', 'accepted_by_user_id'])->where(['id' => (int) $sgfID])->first();
		if (!$sgf)
			throw new NotFoundException('SGF change not found');
		if ($sgf->sgf_status_id !== SgfTable::STATUS_ACCEPTED)
			throw new BadRequestException('A proposal is not a change yet, it waits for the decision of an admin');
		if ($sgf->accepted_by_user_id === Auth::getUserID())
			throw new BadRequestException('What you accepted is reviewed by the other admins');

		$reviewed = !empty($this->input('reviewed'));
		SgfReviewTable::mark((int) $sgfID, Auth::getUserID(), $reviewed);

		return $this->respondJson(['reviewed' => $reviewed]);
	}

	//Existing tags players want on a problem
	#[HttpGet]
	public function tagProposals(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'Tag proposals');
		$this->set('tagConnectionProposalsRenderer', new TagConnectionProposalsRenderer($this->queries()));
	}

	//Tags players want to be added
	#[HttpGet]
	public function newTags(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'New tags');
		$this->set('tagProposalsRenderer', new TagProposalsRenderer($this->queries()));
	}

	#[HttpGet]
	public function deletedAccounts(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'Deleted accounts');
		$this->set('deletedUsers', Util::query('SELECT id, deleted_name, deleted FROM user WHERE deleted IS NOT NULL ORDER BY deleted DESC'));
	}
}

<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Utility\AdminActivityRenderer;
use App\Utility\Auth;
use App\Utility\RejectedSGFProposalsRenderer;
use App\Utility\SGFProposalsRenderer;
use App\Utility\SgfChangesRenderer;
use App\Utility\TagConnectionProposalsRenderer;
use App\Utility\TagProposalsRenderer;

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
		$this->set('newActivityCount', AdminPage::countNewActivity($userID));
		$this->set('sgfProposalCount', SGFProposalsRenderer::total());
		$this->set('tagProposalCount', TagConnectionProposalsRenderer::total());
		$this->set('newTagCount', TagProposalsRenderer::total());
		$this->set('openIssueCount', ClassRegistry::init('TsumegoIssue')->getIndexCounts()['open']);
		$this->set('newSgfChangeCount', AdminPage::countNewSgfChanges($userID));
		$this->set('deletedAccountCount', ClassRegistry::init('User')->find('count', ['conditions' => ['deleted IS NOT NULL']]));
	}

	#[HttpGet]
	public function activity(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'Admin activity');
		$newSince = AdminPage::markViewed(Auth::getUserID(), AdminPage::ACTIVITY);
		$this->set('adminActivityRenderer', new AdminActivityRenderer($this->params['url'], $newSince));
	}

	#[HttpGet]
	public function sgfProposals(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'SGF proposals');
		$this->set('sgfProposalsRenderer', new SGFProposalsRenderer($this->params['url']));
		$this->set('rejectedSgfProposalsRenderer', new RejectedSGFProposalsRenderer($this->params['url']));
	}

	//Every version that became part of a problem, for the admins to review the changes of each other
	#[HttpGet]
	public function sgfChanges(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'SGF changes');
		$newSince = AdminPage::markViewed(Auth::getUserID(), AdminPage::SGF_CHANGES);
		$this->set('sgfChangesRenderer', new SgfChangesRenderer($this->params['url'], $newSince));
	}

	//Ticks an SGF change as reviewed by the admin (reviewed=1), or takes the tick back (reviewed=0).
	//What the admin made part of a problem, their own change or a proposal they accepted, is
	//reviewed by the others.
	#[HttpPost]
	public function reviewSgfChange(string $sgfID): CakeResponse
	{
		$this->Authorization->authorize('Admin', 'reviewSgfChange');
		$sgf = ClassRegistry::init('Sgf')->find('first', ['fields' => ['id', 'sgf_status_id', 'accepted_by_user_id'], 'conditions' => ['id' => (int) $sgfID]]);
		if (!$sgf)
			throw new NotFoundException('SGF change not found');
		if ((int) $sgf['Sgf']['sgf_status_id'] !== Sgf::STATUS_ACCEPTED)
			throw new BadRequestException('A proposal is not a change yet, it waits for the decision of an admin');
		if ((int) $sgf['Sgf']['accepted_by_user_id'] === Auth::getUserID())
			throw new BadRequestException('What you accepted is reviewed by the other admins');

		$reviewed = !empty($this->request->data['reviewed']);
		SgfReview::mark((int) $sgfID, Auth::getUserID(), $reviewed);

		$this->autoRender = false;
		$this->response->type('json');
		$this->response->body(json_encode(['reviewed' => $reviewed]));
		return $this->response;
	}

	//Existing tags players want on a problem
	#[HttpGet]
	public function tagProposals(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'Tag proposals');
		$this->set('tagConnectionProposalsRenderer', new TagConnectionProposalsRenderer($this->params['url']));
	}

	//Tags players want to be added
	#[HttpGet]
	public function newTags(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'New tags');
		$this->set('tagProposalsRenderer', new TagProposalsRenderer($this->params['url']));
	}

	#[HttpGet]
	public function deletedAccounts(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'Deleted accounts');
		$this->set('deletedUsers', ClassRegistry::init('User')->find('all', [
			'conditions' => ['deleted IS NOT NULL'],
			'order' => 'deleted DESC']) ?: []);
	}
}

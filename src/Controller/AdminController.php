<?php

use App\Attribute\HttpGet;
use App\Utility\AdminActivityRenderer;
use App\Utility\Auth;
use App\Utility\RejectedSGFProposalsRenderer;
use App\Utility\SGFProposalsRenderer;
use App\Utility\TagConnectionProposalsRenderer;
use App\Utility\TagProposalsRenderer;

/**
 * The admin area: one page with a link to everything an admin does, each with a count of what
 * waits there, and the pages of the proposals and of the admin activity.
 */
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
		$this->set('newUploadCount', AdminPage::countNewUploads($userID));
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

	/**
	 * Existing tags players want on a problem
	 */
	#[HttpGet]
	public function tagProposals(): void
	{
		$this->Authorization->authorize('Admin', 'viewAdminArea');
		$this->set('_page', 'admin');
		$this->set('_title', 'Tag proposals');
		$this->set('tagConnectionProposalsRenderer', new TagConnectionProposalsRenderer($this->params['url']));
	}

	/**
	 * Tags players want to be added
	 */
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

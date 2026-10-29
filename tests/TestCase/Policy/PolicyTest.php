<?php

//Authorization policies decide whether an identity may perform an action.
//They read only the computed `permissions` list attached by Auth::getIdentity().
use App\Model\Entity\Set;
use App\Model\Entity\TagConnection;
use App\Model\Entity\TsumegoComment;
use App\Model\Entity\TsumegoIssue;
use App\Model\Entity\Tsumego;
use App\Model\Table\UserTable;
use App\Policy\AdminPolicy;
use App\Policy\OwnerPolicy;
use App\Policy\SetPolicy;
use App\Policy\SgfPolicy;
use App\Policy\TagConnectionPolicy;
use App\Policy\TagPolicy;
use App\Policy\TsumegoCommentPolicy;
use App\Policy\TsumegoIssuePolicy;
use App\Policy\TsumegoPolicy;
use App\Utility\Auth;
use App\Utility\Constants;

class PolicyTest extends AppTestCase
{
	//Builds an identity array (like Auth::getIdentity() returns) for the given role.
	private function identity($isAdmin, int $premium = 0, ?int $id = null): ?array
	{
		if ($isAdmin === null)
			return null;
		$identity = ['user_role_id' => $isAdmin ? UserTable::ROLE_ADMIN : UserTable::ROLE_PLAYER, 'premium_id' => $premium ? UserTable::PREMIUM_BASIC : null, 'level' => 1, 'rating' => 1000];
		// a player who is logged in always has an id
		$identity['id'] = $id ?? 999;
		return $identity;
	}

	public function testAdminPolicyAllowsOnlyAdmins()
	{
		$policy = new AdminPolicy();
		foreach (['canViewAdminArea', 'canReviewSgfChange', 'canUserstats', 'canUserstats3'] as $method)
		{
			$this->assertTrue($policy->{$method}($this->identity(true)), $method . ' allows admin');
			$this->assertFalse($policy->{$method}($this->identity(false)), $method . ' blocks regular user');
			$this->assertFalse($policy->{$method}($this->identity(null)), $method . ' blocks anonymous');
		}
	}

	public function testOwnerHasTheRightsOfAnAdmin()
	{
		$owner = ['user_role_id' => UserTable::ROLE_OWNER, 'premium_id' => null, 'level' => 1, 'rating' => 1000];
		$this->assertTrue(AdminPolicy::canViewAdminArea($owner));
		$this->assertTrue(SgfPolicy::canView($owner));
	}

	public function testOwnerPolicyAllowsOnlyOwners()
	{
		$this->assertTrue(OwnerPolicy::canManageRoles(['user_role_id' => UserTable::ROLE_OWNER]));
		$this->assertFalse(OwnerPolicy::canManageRoles($this->identity(true)));
		$this->assertFalse(OwnerPolicy::canManageRoles($this->identity(false)));
		$this->assertFalse(OwnerPolicy::canManageRoles($this->identity(null)));
	}

	public function testSgfPolicyAllowsOnlyAdmins()
	{
		$policy = new SgfPolicy();
		$this->assertTrue($policy->canView($this->identity(true)));
		$this->assertFalse($policy->canView($this->identity(false)));
		$this->assertFalse($policy->canView($this->identity(null)));
	}

	public function testTagPolicyAllowsOnlyAdmins()
	{
		$policy = new TagPolicy();
		foreach (['canEdit', 'canEditAction', 'canIndex', 'canProblems'] as $method)
		{
			$this->assertTrue($policy->{$method}($this->identity(true)), $method . ' allows admin');
			$this->assertFalse($policy->{$method}($this->identity(false)), $method . ' blocks regular user');
			$this->assertFalse($policy->{$method}($this->identity(null)), $method . ' blocks anonymous');
		}
	}

	public function testTagDeleteReservedForOwners()
	{
		$policy = new TagPolicy();
		$this->assertTrue($policy->canDelete(['user_role_id' => UserTable::ROLE_OWNER]), 'owner can delete');
		$this->assertFalse($policy->canDelete($this->identity(true, 0, 72)), 'admin blocked, whatever the id');
		$this->assertFalse($policy->canDelete($this->identity(false)), 'regular user blocked');
		$this->assertFalse($policy->canDelete($this->identity(null)), 'anonymous blocked');
	}

	public function testSetPolicyAllowsAdminOrPremium()
	{
		$policy = new SetPolicy();
		$this->assertTrue($policy->canSandbox($this->identity(true)), 'admin allowed');
		$this->assertTrue($policy->canSandbox($this->identity(false, 1)), 'premium allowed');
		$this->assertFalse($policy->canSandbox($this->identity(false)), 'regular blocked');
		$this->assertFalse($policy->canSandbox($this->identity(null)), 'anonymous blocked');
	}

	public function testSetPolicyAllowsViewingPublicSetsForEveryone()
	{
		$policy = new SetPolicy();
		$publicSet = new Set(['public' => 1, 'user_id' => null]);

		$this->assertTrue($policy->canView($this->identity(null), $publicSet));
		$this->assertTrue($policy->canView($this->identity(false), $publicSet));
	}

	public function testSetPolicyRequiresLoginForPrivateSandboxSets()
	{
		$policy = new SetPolicy();
		$sandboxSet = new Set(['public' => 0, 'user_id' => null]);

		$this->assertTrue($policy->canView($this->identity(false), $sandboxSet), 'logged-in can view sandbox');
		$this->assertFalse($policy->canView($this->identity(null), $sandboxSet), 'anonymous blocked');
	}

	public function testSetPolicyRestrictsPrivateUserOwnedSets()
	{
		$policy = new SetPolicy();
		$privateSet = new Set(['public' => 0, 'user_id' => 7]);
		$owner = $this->identity(false);
		$owner['id'] = 7;

		$this->assertTrue($policy->canView($owner, $privateSet), 'owner can view');
		$this->assertTrue($policy->canView($this->identity(true), $privateSet), 'admin can view');
		$this->assertFalse($policy->canView($this->identity(false), $privateSet), 'other user blocked');
		$this->assertFalse($policy->canView($this->identity(null), $privateSet), 'anonymous blocked');
	}

	public function testSetPolicyAllowsViewingSharedPlayerCollections()
	{
		$policy = new SetPolicy();
		$sharedSet = new Set(['public' => 1, 'user_id' => 7]);

		$this->assertTrue($policy->canView($this->identity(null), $sharedSet), 'anyone with the link');
		$this->assertTrue($policy->canView($this->identity(false), $sharedSet));
	}

	public function testSetPolicyLetsOnlyTheOwnerDecideOnSharing()
	{
		$policy = new SetPolicy();
		$playerSet = new Set(['public' => 0, 'user_id' => 7]);
		$siteSet = new Set(['public' => 1, 'user_id' => null]);
		$owner = $this->identity(false);
		$owner['id'] = 7;
		$other = $this->identity(false);
		$other['id'] = 8;

		$this->assertTrue($policy->canShare($owner, $playerSet), 'the owner decides');
		$this->assertFalse($policy->canShare($other, $playerSet), 'another player cannot share it');
		$this->assertFalse($policy->canShare($this->identity(true), $playerSet), 'not even an admin decides for the owner');
		$this->assertFalse($policy->canShare($this->identity(null), $playerSet), 'anonymous blocked');
		$this->assertFalse($policy->canShare($this->identity(true), $siteSet), 'site collections are public already');
	}

	public function testSetPolicyAllowsPlayingSandboxOnlyWithPermission()
	{
		$policy = new SetPolicy();
		$sandboxSet = new Set(['public' => 0, 'user_id' => null]);
		$publicSet = new Set(['public' => 1, 'user_id' => null]);

		$this->assertTrue($policy->canPlay($this->identity(true), $sandboxSet), 'admin can play sandbox');
		$this->assertTrue($policy->canPlay($this->identity(false, 1), $sandboxSet), 'premium can play sandbox');
		$this->assertFalse($policy->canPlay($this->identity(false), $sandboxSet), 'regular blocked from sandbox');
		$this->assertFalse($policy->canPlay($this->identity(null), $sandboxSet), 'anonymous blocked from sandbox');
		$this->assertTrue($policy->canPlay($this->identity(null), $publicSet), 'anyone can play public sets');
	}

	public function testTsumegoCommentPolicyAllowsOwnerOrAdmin()
	{
		$policy = new TsumegoCommentPolicy();
		$comment = new TsumegoComment(['user_id' => 7]);
		$owner = $this->identity(false);
		$owner['id'] = 7;
		$other = $this->identity(false);
		$other['id'] = 8;

		$this->assertTrue($policy->canDelete($owner, $comment), 'owner can delete');
		$this->assertTrue($policy->canDelete($this->identity(true), $comment), 'admin can delete');
		$this->assertFalse($policy->canDelete($other, $comment), 'other user blocked');
		$this->assertFalse($policy->canDelete($this->identity(null), $comment), 'anonymous blocked');
	}

	public function testTsumegoIssuePolicyCloseAllowsOwnerOrAdmin()
	{
		$policy = new TsumegoIssuePolicy();
		$issue = new TsumegoIssue(['user_id' => 7]);
		$owner = $this->identity(false);
		$owner['id'] = 7;

		$this->assertTrue($policy->canClose($owner, $issue));
		$this->assertTrue($policy->canClose($this->identity(true), $issue));
		$this->assertFalse($policy->canClose($this->identity(false), $issue), 'other user blocked');
	}

	public function testTsumegoIssuePolicyReopenAllowsOwnerOrAdmin()
	{
		$policy = new TsumegoIssuePolicy();
		$issue = new TsumegoIssue(['user_id' => 7]);
		$owner = $this->identity(false);
		$owner['id'] = 7;

		$this->assertTrue($policy->canReopen($owner, $issue), 'owner can reopen');
		$this->assertTrue($policy->canReopen($this->identity(true), $issue), 'admin can reopen');
		$this->assertFalse($policy->canReopen($this->identity(false), $issue), 'other user blocked');
		$this->assertFalse($policy->canReopen($this->identity(null), $issue), 'anonymous blocked');
	}

	public function testTsumegoIssuePolicyMoveCommentIsAdminOnly()
	{
		$policy = new TsumegoIssuePolicy();
		$this->assertTrue($policy->canMoveComment($this->identity(true)));
		$this->assertFalse($policy->canMoveComment($this->identity(false)));
	}

	public function testTagConnectionPolicyAddRequiresContributionAndASolve()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'contributor', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumegos' => [
				['set_order' => 1, 'status' => 'S'],
				['set_order' => 2, 'status' => 'W'],
				['set_order' => 3, 'status' => 'F'],
				['set_order' => 4]]]);
		$policy = new TagConnectionPolicy();
		$contributor = $this->identity(false, 0, $context->user['id']);
		$contributor['rating'] = Constants::$MINIMUM_RATING_TO_CONTRIBUTE;
		[$solved, $solvedLongAgo, $failed, $notVisited] = array_map(fn($tsumego) => new Tsumego(['id' => $tsumego['id']]), $context->tsumegos);

		$this->assertTrue($policy->canAdd($contributor, $solved), 'contributor tags a solved problem');
		$this->assertTrue($policy->canAdd($contributor, $solvedLongAgo), 'a problem solved long ago is solved too');
		$this->assertFalse($policy->canAdd($contributor, $failed), 'tags are hints, an unsolved problem gets none');
		$this->assertFalse($policy->canAdd($contributor, $notVisited), 'nor one never opened');
		$this->assertTrue($policy->canAdd($this->identity(true, 0, $context->user['id']), $notVisited), 'admin tags any problem');
		$this->assertFalse($policy->canAdd($this->identity(false, 0, $context->user['id']), $solved), 'non-contributor blocked');
		$this->assertFalse($policy->canAdd($this->identity(null), $solved), 'anonymous blocked');
	}

	public function testTagConnectionPolicyRemoveAllowsOwnerOfUnapprovedOrAdmin()
	{
		$policy = new TagConnectionPolicy();
		$conn = new TagConnection(['user_id' => 7, 'approved' => false]);
		$approvedConn = new TagConnection(['user_id' => 7, 'approved' => true]);
		$owner = $this->identity(false);
		$owner['id'] = 7;

		$this->assertTrue($policy->canRemove($owner, $conn), 'owner removes own unapproved tag');
		$this->assertFalse($policy->canRemove($owner, $approvedConn), 'owner cannot remove approved tag');
		$this->assertTrue($policy->canRemove($this->identity(true), $approvedConn), 'admin can remove approved tag');
		$this->assertFalse($policy->canRemove($this->identity(false), $conn), 'other user blocked');
	}

	public function testTsumegoPolicyAllowsOnlyAdmins()
	{
		$policy = new TsumegoPolicy();
		foreach (['canEdit', 'canEditSettings', 'canMergeForm', 'canMergeFinalForm', 'canSetupSgf', 'canSetupSgfStep2', 'canPerformMerge'] as $method)
		{
			$this->assertTrue($policy->{$method}($this->identity(true)), $method . ' allows admin');
			$this->assertFalse($policy->{$method}($this->identity(false)), $method . ' blocks regular user');
			$this->assertFalse($policy->{$method}($this->identity(null)), $method . ' blocks anonymous');
		}
	}

	public function testSetPolicyEditAllowsOwnerOrAdmin()
	{
		$policy = new SetPolicy();
		$set = new Set(['user_id' => 7, 'public' => 1]);
		$owner = $this->identity(false);
		$owner['id'] = 7;

		$this->assertTrue($policy->canEdit($owner, $set), 'owner can edit');
		$this->assertTrue($policy->canEdit($this->identity(true), $set), 'admin can edit');
		$this->assertFalse($policy->canEdit($this->identity(false), $set), 'other user blocked');
		$this->assertFalse($policy->canEdit($this->identity(null), $set), 'anonymous blocked');
	}

	public function testSetPolicyDeleteAllowsOwnerOrAdminForSiteSets()
	{
		$policy = new SetPolicy();
		$ownedSet = new Set(['user_id' => 7, 'public' => 1]);
		$sandboxSet = new Set(['user_id' => null, 'public' => 0]);
		$owner = $this->identity(false);
		$owner['id'] = 7;

		$this->assertTrue($policy->canDelete($owner, $ownedSet), 'owner can delete own set');
		$this->assertTrue($policy->canDelete($this->identity(true), $sandboxSet), 'admin can delete sandbox set');
		$this->assertFalse($policy->canDelete($this->identity(false), $sandboxSet), 'regular cannot delete sandbox');
		$this->assertFalse($policy->canDelete($this->identity(true), $ownedSet), 'admin cannot delete other user set');
		$this->assertFalse($policy->canDelete($this->identity(null), $ownedSet), 'anonymous cannot delete');
		$publicSet = new Set(['user_id' => null, 'public' => 1]);
		$this->assertTrue($policy->canDelete($this->identity(true), $publicSet), 'admin can delete public site set (to the trash)');
		$this->assertFalse($policy->canDelete($this->identity(false), $publicSet), 'regular cannot delete public site set');
	}

	public function testSetPolicyCreateAndAddTsumegoAllowsOnlyAdmins()
	{
		$policy = new SetPolicy();
		$this->assertTrue($policy->canCreateAndAddTsumego($this->identity(true)));
		$this->assertFalse($policy->canCreateAndAddTsumego($this->identity(false)));
		$this->assertFalse($policy->canCreateAndAddTsumego($this->identity(null)));
	}

	public function testSetPolicyEditSettingsAllowsOnlyAdmins()
	{
		$policy = new SetPolicy();
		$this->assertTrue($policy->canEditSettings($this->identity(true)));
		$this->assertFalse($policy->canEditSettings($this->identity(false)));
		$this->assertFalse($policy->canEditSettings($this->identity(null)));
	}

	public function testTagPolicyAddRequiresContribution()
	{
		$policy = new TagPolicy();
		$contributor = $this->identity(false);
		$contributor['rating'] = Constants::$MINIMUM_RATING_TO_CONTRIBUTE;

		$this->assertTrue($policy->canAdd($contributor), 'contributor allowed');
		$this->assertFalse($policy->canAdd($this->identity(false)), 'non-contributor blocked');
		$this->assertFalse($policy->canAdd($this->identity(null)), 'anonymous blocked');
	}

	public function testTagPolicyProposalActionsAllowOnlyAdmins()
	{
		$policy = new TagPolicy();
		foreach (['canAcceptTagProposal', 'canRejectTagProposal'] as $method)
		{
			$this->assertTrue($policy->{$method}($this->identity(true)), $method . ' allows admin');
			$this->assertFalse($policy->{$method}($this->identity(false)), $method . ' blocks regular user');
			$this->assertFalse($policy->{$method}($this->identity(null)), $method . ' blocks anonymous');
		}
	}

	public function testAdminPolicyProposalActionsAllowOnlyAdmins()
	{
		$policy = new AdminPolicy();
		foreach (['canAcceptSGFProposal', 'canRejectSGFProposal', 'canReopenSGFProposal', 'canAcceptTagConnectionProposal', 'canRejectTagConnectionProposal'] as $method)
		{
			$this->assertTrue($policy->{$method}($this->identity(true)), $method . ' allows admin');
			$this->assertFalse($policy->{$method}($this->identity(false)), $method . ' blocks regular user');
			$this->assertFalse($policy->{$method}($this->identity(null)), $method . ' blocks anonymous');
		}
	}
}

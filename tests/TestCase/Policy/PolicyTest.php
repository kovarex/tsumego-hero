<?php

App::uses('AdminPolicy', 'Policy');
App::uses('SgfPolicy', 'Policy');
App::uses('TagPolicy', 'Policy');
App::uses('SetPolicy', 'Policy');
App::uses('TsumegoCommentPolicy', 'Policy');
App::uses('TsumegoIssuePolicy', 'Policy');
App::uses('TagConnectionPolicy', 'Policy');
App::uses('TsumegoPolicy', 'Policy');
App::uses('Constants', 'Utility');

/**
 * Authorization policies decide whether an identity may perform an action.
 * They read only the computed `permissions` list attached by Auth::getIdentity().
 */
class PolicyTest extends CakeTestCase
{
	/**
	 * Builds an identity array (like Auth::getIdentity() returns) for the given role.
	 */
	private function identity($isAdmin, int $premium = 0): ?array
	{
		if ($isAdmin === null)
			return null;
		return ['isAdmin' => $isAdmin, 'premium' => $premium, 'level' => 1, 'rating' => 1000];
	}

	public function testAdminPolicyAllowsOnlyAdmins()
	{
		$policy = new AdminPolicy();
		foreach (['canAdminstats', 'canUploads', 'canUserstats', 'canUserstats3', 'canShowPublishSchedule', 'canData'] as $method)
		{
			$this->assertTrue($policy->{$method}($this->identity(true)), $method . ' allows admin');
			$this->assertFalse($policy->{$method}($this->identity(false)), $method . ' blocks regular user');
			$this->assertFalse($policy->{$method}($this->identity(null)), $method . ' blocks anonymous');
		}
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
		foreach (['canDelete', 'canEdit', 'canEditAction'] as $method)
		{
			$this->assertTrue($policy->{$method}($this->identity(true)), $method . ' allows admin');
			$this->assertFalse($policy->{$method}($this->identity(false)), $method . ' blocks regular user');
			$this->assertFalse($policy->{$method}($this->identity(null)), $method . ' blocks anonymous');
		}
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
		$publicSet = ['public' => 1, 'user_id' => null];

		$this->assertTrue($policy->canView($this->identity(null), $publicSet));
		$this->assertTrue($policy->canView($this->identity(false), $publicSet));
	}

	public function testSetPolicyRequiresLoginForPrivateSandboxSets()
	{
		$policy = new SetPolicy();
		$sandboxSet = ['public' => 0, 'user_id' => null];

		$this->assertTrue($policy->canView($this->identity(false), $sandboxSet), 'logged-in can view sandbox');
		$this->assertFalse($policy->canView($this->identity(null), $sandboxSet), 'anonymous blocked');
	}

	public function testSetPolicyRestrictsPrivateUserOwnedSets()
	{
		$policy = new SetPolicy();
		$privateSet = ['public' => 0, 'user_id' => 7];
		$owner = $this->identity(false);
		$owner['id'] = 7;

		$this->assertTrue($policy->canView($owner, $privateSet), 'owner can view');
		$this->assertTrue($policy->canView($this->identity(true), $privateSet), 'admin can view');
		$this->assertFalse($policy->canView($this->identity(false), $privateSet), 'other user blocked');
		$this->assertFalse($policy->canView($this->identity(null), $privateSet), 'anonymous blocked');
	}

	public function testSetPolicyAllowsPlayingSandboxOnlyWithPermission()
	{
		$policy = new SetPolicy();
		$sandboxSet = ['public' => 0, 'user_id' => null];
		$publicSet = ['public' => 1, 'user_id' => null];

		$this->assertTrue($policy->canPlay($this->identity(true), $sandboxSet), 'admin can play sandbox');
		$this->assertTrue($policy->canPlay($this->identity(false, 1), $sandboxSet), 'premium can play sandbox');
		$this->assertFalse($policy->canPlay($this->identity(false), $sandboxSet), 'regular blocked from sandbox');
		$this->assertFalse($policy->canPlay($this->identity(null), $sandboxSet), 'anonymous blocked from sandbox');
		$this->assertTrue($policy->canPlay($this->identity(null), $publicSet), 'anyone can play public sets');
	}

	public function testTsumegoCommentPolicyAllowsOwnerOrAdmin()
	{
		$policy = new TsumegoCommentPolicy();
		$comment = ['user_id' => 7];
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
		$issue = ['user_id' => 7];
		$owner = $this->identity(false);
		$owner['id'] = 7;

		$this->assertTrue($policy->canClose($owner, $issue));
		$this->assertTrue($policy->canClose($this->identity(true), $issue));
		$this->assertFalse($policy->canClose($this->identity(false), $issue), 'other user blocked');
	}

	public function testTsumegoIssuePolicyReopenAllowsOwnerOrAdmin()
	{
		$policy = new TsumegoIssuePolicy();
		$issue = ['user_id' => 7];
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

	public function testTagConnectionPolicyAddRequiresContribution()
	{
		$policy = new TagConnectionPolicy();
		$contributor = $this->identity(false);
		$contributor['rating'] = Constants::$MINIMUM_RATING_TO_CONTRIBUTE;

		$this->assertTrue($policy->canAdd($contributor), 'contributor allowed');
		$this->assertFalse($policy->canAdd($this->identity(false)), 'non-contributor blocked');
		$this->assertFalse($policy->canAdd($this->identity(null)), 'anonymous blocked');
	}

	public function testTagConnectionPolicyRemoveAllowsOwnerOfUnapprovedOrAdmin()
	{
		$policy = new TagConnectionPolicy();
		$conn = ['user_id' => 7, 'approved' => 0];
		$approvedConn = ['user_id' => 7, 'approved' => 1];
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
		$set = ['user_id' => 7, 'public' => 1];
		$owner = $this->identity(false);
		$owner['id'] = 7;

		$this->assertTrue($policy->canEdit($owner, $set), 'owner can edit');
		$this->assertTrue($policy->canEdit($this->identity(true), $set), 'admin can edit');
		$this->assertFalse($policy->canEdit($this->identity(false), $set), 'other user blocked');
		$this->assertFalse($policy->canEdit($this->identity(null), $set), 'anonymous blocked');
	}

	public function testSetPolicyDeleteAllowsOwnerOrAdminForSandbox()
	{
		$policy = new SetPolicy();
		$ownedSet = ['user_id' => 7, 'public' => 1];
		$sandboxSet = ['user_id' => null, 'public' => 0];
		$owner = $this->identity(false);
		$owner['id'] = 7;

		$this->assertTrue($policy->canDelete($owner, $ownedSet), 'owner can delete own set');
		$this->assertTrue($policy->canDelete($this->identity(true), $sandboxSet), 'admin can delete sandbox set');
		$this->assertFalse($policy->canDelete($this->identity(false), $sandboxSet), 'regular cannot delete sandbox');
		$this->assertFalse($policy->canDelete($this->identity(true), $ownedSet), 'admin cannot delete other user set');
		$this->assertFalse($policy->canDelete($this->identity(null), $ownedSet), 'anonymous cannot delete');
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
		foreach (['canAcceptSGFProposal', 'canRejectSGFProposal', 'canAcceptTagConnectionProposal', 'canRejectTagConnectionProposal'] as $method)
		{
			$this->assertTrue($policy->{$method}($this->identity(true)), $method . ' allows admin');
			$this->assertFalse($policy->{$method}($this->identity(false)), $method . ' blocks regular user');
			$this->assertFalse($policy->{$method}($this->identity(null)), $method . ' blocks anonymous');
		}
	}
}

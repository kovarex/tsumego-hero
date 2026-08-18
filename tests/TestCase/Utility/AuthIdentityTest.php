<?php

App::uses('Auth', 'Utility');
App::uses('BasePolicy', 'Policy');

/**
 * Auth::identity() returns the user with a computed permissions list,
 * or null when not logged in.
 */
class AuthIdentityTest extends CakeTestCase
{
	public function testIdentityIsNullWhenLoggedOut()
	{
		new ContextPreparator(['user' => null]);

		$this->assertNull(Auth::identity());
	}

	public function testRegularUserHasNoPermissions()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false, 'premium' => 0]]);

		$identity = Auth::identity();
		$this->assertNotNull($identity);
		$this->assertSame([], $identity['permissions']);
	}

	public function testAdminHasAdminAndSandboxPermissions()
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		$identity = Auth::identity();
		$this->assertContains('admin', $identity['permissions']);
		$this->assertContains('sandbox', $identity['permissions']);
	}

	public function testPremiumUserHasSandboxPermission()
	{
		new ContextPreparator(['user' => ['name' => 'premium', 'admin' => false, 'premium' => 1]]);

		$identity = Auth::identity();
		$this->assertContains('sandbox', $identity['permissions']);
		$this->assertNotContains('admin', $identity['permissions']);
	}

	public function testLowRatingUserCannotContribute()
	{
		new ContextPreparator(['user' => ['name' => 'low', 'level' => 1, 'rating' => 1000, 'admin' => false]]);

		$this->assertFalse(BasePolicy::canPropose(Auth::identity()));
	}

	public function testContributorRatingGrantsContribution()
	{
		new ContextPreparator(['user' => ['name' => 'contributor', 'level' => 1, 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE, 'admin' => false]]);

		$this->assertTrue(BasePolicy::canPropose(Auth::identity()));
	}

	public function testHighLevelGrantsContribution()
	{
		new ContextPreparator(['user' => ['name' => 'highlevel', 'level' => 40, 'rating' => 100, 'admin' => false]]);

		$this->assertTrue(BasePolicy::canPropose(Auth::identity()));
	}

	public function testAdminCanContribute()
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		$this->assertTrue(BasePolicy::canPropose(Auth::identity()));
	}

	public function testAnonymousUserCannotContribute()
	{
		new ContextPreparator(['user' => null]);

		$this->assertFalse(BasePolicy::canPropose(null));
	}
}

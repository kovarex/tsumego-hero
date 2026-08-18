<?php

App::uses('Auth', 'Utility');
App::uses('BasePolicy', 'Policy');

/**
 * Auth::getIdentity() returns the user array, or null when not logged in.
 * Policies check user fields directly (isAdmin, premium, level, rating).
 */
class AuthIdentityTest extends CakeTestCase
{
	public function testIdentityIsNullWhenLoggedOut()
	{
		new ContextPreparator(['user' => null]);

		$this->assertNull(Auth::getIdentity());
	}

	public function testIdentityContainsUserFields()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false, 'premium' => 0]]);

		$identity = Auth::getIdentity();
		$this->assertNotNull($identity);
		$this->assertSame('regular', $identity['name']);
		$this->assertFalse((bool) $identity['isAdmin']);
	}

	public function testAdminIsDetected()
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		$identity = Auth::getIdentity();
		$this->assertTrue(BasePolicy::canPropose($identity));
	}

	public function testPremiumUserIsDetected()
	{
		new ContextPreparator(['user' => ['name' => 'premium', 'admin' => false, 'premium' => 1]]);

		$identity = Auth::getIdentity();
		$this->assertTrue((bool) $identity['premium']);
		$this->assertFalse((bool) $identity['isAdmin']);
	}

	public function testLowRatingUserCannotContribute()
	{
		new ContextPreparator(['user' => ['name' => 'low', 'level' => 1, 'rating' => 1000, 'admin' => false]]);

		$this->assertFalse(BasePolicy::canPropose(Auth::getIdentity()));
	}

	public function testContributorRatingGrantsContribution()
	{
		new ContextPreparator(['user' => ['name' => 'contributor', 'level' => 1, 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE, 'admin' => false]]);

		$this->assertTrue(BasePolicy::canPropose(Auth::getIdentity()));
	}

	public function testHighLevelGrantsContribution()
	{
		new ContextPreparator(['user' => ['name' => 'highlevel', 'level' => 40, 'rating' => 100, 'admin' => false]]);

		$this->assertTrue(BasePolicy::canPropose(Auth::getIdentity()));
	}

	public function testAdminCanContribute()
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		$this->assertTrue(BasePolicy::canPropose(Auth::getIdentity()));
	}

	public function testAnonymousUserCannotContribute()
	{
		new ContextPreparator(['user' => null]);

		$this->assertFalse(BasePolicy::canPropose(null));
	}
}

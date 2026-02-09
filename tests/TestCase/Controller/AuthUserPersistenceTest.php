<?php

/**
 * Tests for Auth user persistence integrity.
 *
 * These tests ensure that making HTTP requests doesn't corrupt user data.
 * The user data stored in Auth should never be modified except through
 * explicit Auth methods like saveUser() with intentional changes.
 */
class AuthUserPersistenceTest extends ControllerTestCase
{
	/**
	 * Test that user data remains unchanged after making requests.
	 *
	 * When a logged-in user makes any request, their database record
	 * should not be modified unless they explicitly change something.
	 */
	public function testUserDataUnchangedAfterSimpleRequest()
	{
		// Arrange: Create a user and capture their initial state
		$context = new ContextPreparator([
			'user' => [
				'name' => 'TestUser123',
				'rating' => 1500,
				'xp' => 100,
			],
		]);

		$userId = $context->user['id'];
		$initialUser = ClassRegistry::init('User')->findById($userId)['User'];

		// Act: Make a simple request (triggers beforeFilter, view rendering, saveUser)
		$this->testAction('/sites/index', ['method' => 'get', 'return' => 'view']);

		// Assert: User data in database should be identical
		$userAfterRequest = ClassRegistry::init('User')->findById($userId)['User'];

		$this->assertEquals($initialUser['name'], $userAfterRequest['name'],
			'User name should not change after making a request');
		$this->assertEquals($initialUser['rating'], $userAfterRequest['rating'],
			'User rating should not change after making a request');
	}

	/**
	 * Test that users with special name prefixes maintain their names.
	 *
	 * Some authentication providers (like Google OAuth) use name prefixes
	 * to distinguish users. These prefixes must be preserved in the database
	 * even if they're stripped for display purposes.
	 */
	public function testSpecialPrefixedUsernamesPreservedInDatabase()
	{
		// Arrange: Create a user with a special prefix (simulating OAuth user)
		$context = new ContextPreparator([
			'user' => [
				'name' => 'g__OAuthUser',
				'external_id' => '12345', // Indicates OAuth authentication
			],
		]);

		$userId = $context->user['id'];

		// Act: Make a request
		$this->testAction('/sites/index', ['method' => 'get', 'return' => 'view']);

		// Assert: The prefixed name must be preserved
		$userAfterRequest = ClassRegistry::init('User')->findById($userId);
		$this->assertEquals(
			'g__OAuthUser',
			$userAfterRequest['User']['name'],
			'Prefixed usernames must be preserved in database (stripping is for display only)'
		);
	}

	/**
	 * Test that users with overlapping display names can coexist.
	 *
	 * When display name processing (like prefix stripping) is done correctly,
	 * users like "g__Alex" and "Alex" can both exist because the database
	 * stores their actual unique names.
	 */
	public function testUsersWithOverlappingDisplayNamesCanCoexist()
	{
		// Arrange: Create two users whose display names would be the same
		// g__SharedName (OAuth) displays as "SharedName"
		// SharedName (regular) displays as "SharedName"
		$context = new ContextPreparator([
			'user' => [
				'name' => 'g__SharedName',
				'external_id' => '99999',
			],
			'other-users' => [
				['name' => 'SharedName'],
			],
		]);

		$oauthUserId = $context->user['id'];

		// Act: Make a request as the OAuth user
		// This should NOT cause a database conflict
		$exceptionThrown = false;
		$exceptionMessage = '';
		try
		{
			$this->testAction('/sites/index', ['method' => 'get', 'return' => 'view']);
		}
		catch (Exception $e)
		{
			$exceptionThrown = true;
			$exceptionMessage = $e->getMessage();
		}

		// Assert: No exception, both users maintain their identities
		$this->assertFalse($exceptionThrown,
			"Users with overlapping display names should coexist. Exception: $exceptionMessage");

		$oauthUser = ClassRegistry::init('User')->findById($oauthUserId);
		$this->assertEquals('g__SharedName', $oauthUser['User']['name'],
			'OAuth user must keep prefixed name');
	}

	/**
	 * Test that checkPicture produces correct display names.
	 *
	 * The checkPicture function should transform usernames for display:
	 * - OAuth users (g__ prefix + external_id): strip prefix
	 * - Regular users: keep name as-is
	 */
	public function testDisplayNameTransformation()
	{
		// OAuth user: prefix should be stripped for display
		$oauthUser = ['name' => 'g__GoogleUser', 'external_id' => '12345'];
		$this->assertEquals('GoogleUser', AppController::checkPicture($oauthUser),
			'OAuth users should have g__ prefix stripped for display');

		// Regular user: name unchanged
		$regularUser = ['name' => 'RegularUser', 'external_id' => null];
		$this->assertEquals('RegularUser', AppController::checkPicture($regularUser),
			'Regular users keep their name unchanged');

		// Edge case: g__ prefix but no external_id (shouldn't happen, but be safe)
		$ambiguousUser = ['name' => 'g__Ambiguous', 'external_id' => null];
		$this->assertEquals('g__Ambiguous', AppController::checkPicture($ambiguousUser),
			'Users with g__ prefix but no external_id keep the prefix');
	}
}

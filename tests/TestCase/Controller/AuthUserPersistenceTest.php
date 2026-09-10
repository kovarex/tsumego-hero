<?php

App::uses('User', 'Model');

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
	 * Google OAuth users have name=NULL and display_name set to their
	 * visible name. After a request, these fields must be preserved.
	 */
	public function testGoogleUserFieldsPreservedInDatabase()
	{
		// Arrange: Create a Google OAuth user (name=NULL, display_name set)
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'OAuthUser',
				'external_id' => '12345', // Indicates OAuth authentication
			],
		]);

		$userId = $context->user['id'];

		// Act: Make a request
		$this->testAction('/sites/index', ['method' => 'get', 'return' => 'view']);

		// Assert: Google user fields must be preserved
		$userAfterRequest = ClassRegistry::init('User')->findById($userId);
		$this->assertNull(
			$userAfterRequest['User']['name'],
			'Google users should have name=NULL (they use display_name instead)'
		);
		$this->assertEquals(
			'OAuthUser',
			$userAfterRequest['User']['display_name'],
			'display_name must be preserved after request'
		);
	}

	/**
	 * Test that a Google user and a regular user with similar names can coexist.
	 *
	 * Google user: name=NULL, display_name='SharedName'
	 * Regular user: name='SharedName2', display_name='SharedName2'
	 * Both should work without conflicts.
	 */
	public function testGoogleAndRegularUsersCanCoexist()
	{
		// Arrange: Create a Google user and a regular user
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'SharedName',
				'external_id' => '99999',
			],
			'other-users' => [
				['name' => 'SharedName2'],
			],
		]);

		$oauthUserId = $context->user['id'];

		// Act: Make a request as the OAuth user
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

		// Assert: No exception, Google user maintains identity
		$this->assertFalse($exceptionThrown,
			"Users should coexist without conflict. Exception: $exceptionMessage");

		$oauthUser = ClassRegistry::init('User')->findById($oauthUserId);
		$this->assertNull($oauthUser['User']['name'],
			'Google user should have name=NULL');
		$this->assertEquals('SharedName', $oauthUser['User']['display_name'],
			'Google user must keep their display_name');
	}

	/**
	 * Test that User::isGoogleUser correctly identifies Google users.
	 *
	 * Google users are identified by having a non-empty external_id.
	 * Regular users have external_id = null.
	 */
	public function testIsGoogleUserDetection()
	{
		// Google user: has external_id
		$googleUser = ['name' => null, 'external_id' => '12345', 'display_name' => 'GoogleUser'];
		$this->assertTrue(User::isGoogleUser($googleUser),
			'User with external_id should be detected as Google user');

		// Regular user: no external_id
		$regularUser = ['name' => 'RegularUser', 'external_id' => null, 'display_name' => 'RegularUser'];
		$this->assertFalse(User::isGoogleUser($regularUser),
			'User without external_id should not be detected as Google user');

		// Edge case: empty external_id
		$ambiguousUser = ['name' => 'Someone', 'external_id' => '', 'display_name' => 'Someone'];
		$this->assertFalse(User::isGoogleUser($ambiguousUser),
			'User with empty external_id should not be detected as Google user');
	}
}

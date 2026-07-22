<?php

App::uses('User', 'Model');
App::uses('Auth', 'Utility');

/**
 * Tests for display name update functionality.
 *
 * Tests that:
 * - Google users can change their display name (it's only set once on first login)
 * - Regular users can change their display name
 * - Display names must be unique
 * - Display names must meet length requirements
 */
class UpdateNameTest extends ControllerTestCase
{
	/**
	 * Test that logged out users cannot update display name.
	 */
	public function testUpdateNameRequiresLogin(): void
	{
		// Create context but don't log in (pass null for user)
		new ContextPreparator(['user' => null]);

		$result = $this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'testname_new']],
			'return' => 'result',
		]);

		// Should redirect to home
		$this->assertStringContainsString('/', $this->headers['Location']);
	}

	/**
	 * Test that Google users CAN change their display name.
	 * (Display name is only set once on first login, not synced on every login)
	 */
	public function testGoogleUsersCanChangeName(): void
	{
		// Create a Google user using ContextPreparator
		$context = new ContextPreparator([
			'user' => [
				'name' => 'GoogleUser',
				'display_name' => 'Google User',
				'email' => 'google@gmail.com',
				'external_id' => 'google12345',  // Makes it a Google user
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'New Name']],
			'return' => 'result',
		]);

		// Display name SHOULD have changed (Google users can update it)
		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('New Name', $updatedUser['User']['display_name']);
	}

	/**
	 * Test that regular users CAN change their display name.
	 */
	public function testRegularUsersCanChangeName(): void
	{
		// Create a regular user (no external_id)
		$context = new ContextPreparator([
			'user' => [
				'name' => 'RegularUser',
				'display_name' => 'Regular User',
				'email' => 'regular@example.com',
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'Updated Display']],
			'return' => 'result',
		]);

		// Display name SHOULD have changed
		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('Updated Display', $updatedUser['User']['display_name']);
	}

	/**
	 * Test that display name must be unique.
	 */
	public function testDisplayNameMustBeUnique(): void
	{
		// Create two users - one logged in, one with the display name we want
		$context = new ContextPreparator([
			'user' => [
				'name' => 'MyUser',
				'display_name' => 'My Name',
				'email' => 'my@example.com',
			],
			'other-users' => [
				[
					'name' => 'OtherUser',
					'display_name' => 'Taken Name',
					'email' => 'other@example.com',
				],
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'Taken Name']],
			'return' => 'result',
		]);

		// Display name should NOT have changed (uniqueness enforced)
		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('My Name', $updatedUser['User']['display_name']);
	}

	/**
	 * Test that display name must be at least 3 characters.
	 */
	public function testNameMinimumLength(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'ShortUser',
				'display_name' => 'Original Name',
				'email' => 'short@example.com',
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'ab']], // Too short
			'return' => 'result',
		]);

		// Display name should NOT have changed
		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('Original Name', $updatedUser['User']['display_name']);
	}

	/**
	 * Test that empty display name is rejected.
	 */
	public function testEmptyNameRejected(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'EmptyUser',
				'display_name' => 'My Display',
				'email' => 'empty@example.com',
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => '']],
			'return' => 'result',
		]);

		// Display name should NOT have changed
		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('My Display', $updatedUser['User']['display_name']);
	}
}

<?php

/**
 * Tests for the forced display-name change flow.
 *
 * Free renaming is disabled; the display name can only be changed by a user
 * who was flagged with needs_display_name_change (migration rename or a
 * duplicate Google name). On success the flag is cleared.
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
	 * Test that a user NOT flagged cannot change their display name.
	 */
	public function testNonForcedUserCannotChangeName(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Original Name',
				'email' => 'regular@example.com',
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'Should Not Change']],
			'return' => 'result',
		]);

		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('Original Name', $updatedUser['User']['display_name']);
		$this->assertEquals(false, $updatedUser['User']['needs_display_name_change']);
	}

	/**
	 * Test that a forced Google user can change their display name and the flag is cleared.
	 */
	public function testForcedGoogleUserCanChangeName(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Google User',
				'email' => 'google@gmail.com',
				'external_id' => 'google12345',
				'needs_display_name_change' => true,
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'New Name']],
			'return' => 'result',
		]);

		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('New Name', $updatedUser['User']['display_name']);
		$this->assertEquals(false, $updatedUser['User']['needs_display_name_change']);
	}

	/**
	 * Test that a forced regular user can change their display name and the flag is cleared.
	 */
	public function testForcedRegularUserCanChangeName(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Regular User',
				'email' => 'regular@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'Updated Display']],
			'return' => 'result',
		]);

		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('Updated Display', $updatedUser['User']['display_name']);
		$this->assertEquals(false, $updatedUser['User']['needs_display_name_change']);
	}

	/**
	 * Test that display name must be unique even when forced.
	 */
	public function testDisplayNameMustBeUnique(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'My Name',
				'email' => 'my@example.com',
				'needs_display_name_change' => true,
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

		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('My Name', $updatedUser['User']['display_name']);
	}

	/**
	 * Test that an unchanged display name is rejected even when forced.
	 */
	public function testUnchangedNameIsRejected(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Andrey (2)',
				'email' => 'andrey@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'Andrey (2)']],
			'return' => 'result',
		]);

		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('Andrey (2)', $updatedUser['User']['display_name']);
		$this->assertEquals(true, $updatedUser['User']['needs_display_name_change']);
	}

	/**
	 * Test that the length limit counts characters, not bytes.
	 */
	public function testNameLengthIsCountedInCharacters(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Natalya (2)',
				'email' => 'natalya@example.com',
				'needs_display_name_change' => true,
			],
		]);

		// 26 cyrillic characters, which is 52 bytes
		$newName = str_repeat("\u{042F}", 26);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => $newName]],
			'return' => 'result',
		]);

		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals($newName, $updatedUser['User']['display_name']);
		$this->assertEquals(false, $updatedUser['User']['needs_display_name_change']);
	}

	/**
	 * Test that display name must be at least 3 characters even when forced.
	 */
	public function testNameMinimumLength(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Original Name',
				'email' => 'short@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'ab']],
			'return' => 'result',
		]);

		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('Original Name', $updatedUser['User']['display_name']);
	}

	/**
	 * Test that empty display name is rejected even when forced.
	 */
	public function testEmptyNameRejected(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'My Display',
				'email' => 'empty@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => '']],
			'return' => 'result',
		]);

		$updatedUser = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertEquals('My Display', $updatedUser['User']['display_name']);
	}

	/**
	 * Test that the changename page is only reachable by a forced user.
	 */
	public function testChangenamePageRequiresForcedUser(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Not Forced',
				'email' => 'notforced@example.com',
			],
		]);

		$this->testAction('/users/changename', ['method' => 'get', 'return' => 'result']);
		$this->assertStringContainsString('/', $this->headers['Location']);
	}

	/**
	 * Test that the changename page renders for a forced user.
	 */
	public function testChangenamePageRendersForForcedUser(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Forced Name',
				'email' => 'forced@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$result = $this->testAction('/users/changename', ['method' => 'get', 'return' => 'view']);
		$this->assertStringContainsString('Choose your display name', $result);
	}

	/**
	 * Test that a forced rename is recorded in the activity trail.
	 */
	public function testRenamingIsRecordedInTheActivityTrail(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Name From Migration',
				'email' => 'renamed@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'My Chosen Name']],
			'return' => 'result',
		]);

		$activities = ClassRegistry::init('AdminActivity')->find('all');
		$this->assertCount(1, $activities);
		$this->assertEquals(AdminActivityType::DISPLAY_NAME_CHANGE, $activities[0]['AdminActivity']['type']);
		$this->assertEquals('Name From Migration', $activities[0]['AdminActivity']['old_value']);
		$this->assertEquals('My Chosen Name', $activities[0]['AdminActivity']['new_value']);
		$this->assertEquals($context->user['id'], $activities[0]['AdminActivity']['user_id']);
	}

	/**
	 * Test that a rejected rename is not recorded in the activity trail.
	 */
	public function testRejectedRenameIsNotRecorded(): void
	{
		new ContextPreparator([
			'user' => [
				'display_name' => 'My Name',
				'email' => 'rejectedrename@example.com',
				'needs_display_name_change' => true,
			],
			'other-users' => [
				[
					'name' => 'OtherUser',
					'display_name' => 'Taken Name',
					'email' => 'takenname@example.com',
				],
			],
		]);

		$this->testAction('/users/updatename', [
			'method' => 'post',
			'data' => ['User' => ['display_name' => 'Taken Name']],
			'return' => 'result',
		]);

		$this->assertCount(0, ClassRegistry::init('AdminActivity')->find('all'));
	}
}

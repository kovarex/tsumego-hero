<?php

App::uses('User', 'Model');

class UserTest extends CakeTestCase
{
	// =====================================================================
	// getAvatarUrl Tests
	// =====================================================================

	/**
	 * Test that Google users get their Google picture URL (resized).
	 */
	public function testGetAvatarUrlReturnsGooglePictureForGoogleUser()
	{
		$user = [
			'external_id' => '123456789',
			'picture' => 'https://lh3.googleusercontent.com/a/abc123',
			'email' => 'test@gmail.com',
		];

		$url = User::getAvatarUrl($user, 40);
		$this->assertStringContainsString('https://lh3.googleusercontent.com', $url);
		$this->assertStringContainsString('s40', $url);
	}

	/**
	 * Test that non-Google users get Gravatar URL.
	 */
	public function testGetAvatarUrlReturnsGravatarForRegularUser()
	{
		$user = [
			'external_id' => null,
			'picture' => null,
			'email' => 'test@example.com',
		];

		$url = User::getAvatarUrl($user, 40);
		$this->assertStringContainsString('gravatar.com/avatar/', $url);
		$this->assertStringContainsString('s=40', $url);
	}

	/**
	 * Test that Google user without picture falls back to Gravatar.
	 */
	public function testGetAvatarUrlFallsBackToGravatarWhenNoPicture()
	{
		$user = [
			'external_id' => '123456789',
			'picture' => null,
			'email' => 'test@gmail.com',
		];

		$url = User::getAvatarUrl($user, 40);
		$this->assertStringContainsString('gravatar.com/avatar/', $url);
	}

	/**
	 * Test that avatar size can be customized.
	 */
	public function testGetAvatarUrlCustomSize()
	{
		$user = [
			'external_id' => null,
			'picture' => null,
			'email' => 'test@example.com',
		];

		$url = User::getAvatarUrl($user, 80);
		$this->assertStringContainsString('s=80', $url);
	}

	// =====================================================================
	// renderLink Tests - Avatar Display
	// =====================================================================

	/**
	 * Test that renderLink includes an avatar image.
	 */
	public function testRenderLinkIncludesAvatar()
	{
		$html = User::renderLink([
			'id' => 123,
			'display_name' => 'TestUser',
			'external_id' => null,
			'picture' => null,
			'rating' => 1500.0,
			'email' => 'test@example.com',
		]);

		$this->assertStringContainsString('<img', $html);
		$this->assertStringContainsString('user-avatar', $html);
		$this->assertStringContainsString('gravatar.com/avatar/', $html);
	}

	/**
	 * Test that renderLink shows Google picture for Google users.
	 */
	public function testRenderLinkShowsGooglePictureForGoogleUser()
	{
		$html = User::renderLink([
			'id' => 123,
			'display_name' => 'GoogleUser',
			'external_id' => 'google_ext_123',
			'picture' => 'https://lh3.googleusercontent.com/a/abc123',
			'rating' => 1500.0,
			'email' => 'google@gmail.com',
		]);

		$this->assertStringContainsString('lh3.googleusercontent.com', $html);
		$this->assertStringContainsString('s20', $html); // 20px size
	}

	/**
	 * Test that renderLink handles deleted users (null id).
	 */
	public function testRenderLinkHandlesDeletedUser()
	{
		$html = User::renderLink(null);
		$this->assertStringContainsString('[deleted user]', $html);
		$this->assertStringNotContainsString('<a href', $html);

		// Test with empty array
		$html = User::renderLink(['id' => null, 'display_name' => 'Ghost']);
		$this->assertStringContainsString('[deleted user]', $html);
	}

	/**
	 * Test that renderLink works with array input (containable result format).
	 */
	public function testRenderLinkAcceptsArrayInput()
	{
		$user = [
			'id' => 456,
			'display_name' => 'ArrayUser',
			'external_id' => null,
			'picture' => null,
			'rating' => 1800.0,
			'email' => 'array@example.com',
		];

		$html = User::renderLink($user);

		$this->assertStringContainsString('/users/view/456', $html);
		$this->assertStringContainsString('ArrayUser', $html);
		$this->assertStringContainsString('<img', $html);
		$this->assertStringContainsString('gravatar.com/avatar/', $html);
	}

	/**
	 * Test that renderLink includes rank from rating.
	 */
	public function testRenderLinkIncludesRankFromRating()
	{
		$html = User::renderLink([
			'id' => 123,
			'display_name' => 'RankedUser',
			'external_id' => null,
			'picture' => null,
			'rating' => 1500.0, // About 5 kyu
			'email' => 'ranked@example.com',
		]);

		// Should have some rank indicator (e.g., "5k" or similar)
		$this->assertMatchesRegularExpression('/\d+[kd]/i', $html);
	}

	// =====================================================================
	// updateDisplayName Tests
	// =====================================================================

	/**
	 * Test that updateDisplayName updates the display_name in the main database.
	 */
	public function testUpdateDisplayNameUpdatesMainDatabase()
	{
		// Create a user via ContextPreparator
		$context = new ContextPreparator([
			'user' => [
				'name' => 'DisplayTest',
				'display_name' => 'Display Test',
				'email' => 'display@example.com',
			],
		]);

		// Update the display name
		$result = User::updateDisplayName($context->user['id'], 'New Display Name');
		$this->assertTrue($result);

		// Verify the display_name changed in database
		$userModel = ClassRegistry::init('User');
		$user = $userModel->findById($context->user['id']);
		$this->assertEquals('New Display Name', $user['User']['display_name']);
	}

	/**
	 * Test that updateDisplayName gracefully handles missing forum database.
	 * (Forum DB is not configured in test environment)
	 */
	public function testUpdateDisplayNameHandlesMissingForumDatabase()
	{
		// Create a user via ContextPreparator
		$context = new ContextPreparator([
			'user' => [
				'name' => 'ForumTest',
				'display_name' => 'Forum Test',
				'email' => 'forum@example.com',
			],
		]);

		// This should not throw an exception even though forum DB doesn't exist
		$result = User::updateDisplayName($context->user['id'], 'Forum Test Name');
		$this->assertTrue($result);

		// Main DB should still be updated
		$userModel = ClassRegistry::init('User');
		$user = $userModel->findById($context->user['id']);
		$this->assertEquals('Forum Test Name', $user['User']['display_name']);
	}

	// =====================================================================
	// Email Uniqueness Validation Tests
	// =====================================================================
	// NOTE: The User model's checkUnique validation does NOT work because
	// the email column has no UNIQUE constraint in the database. Email
	// uniqueness is enforced at the CONTROLLER level (UsersController::add).
	// See tests/TestCase/Controller/RegistrationEmailTest.php for those tests.

	// =====================================================================
	// isGoogleUser Tests
	// =====================================================================

	/**
	 * Test isGoogleUser returns true for Google users.
	 */
	public function testIsGoogleUserReturnsTrueForGoogleUser()
	{
		$user = ['external_id' => 'google123', 'email' => 'test@example.com'];
		$this->assertTrue(User::isGoogleUser($user));
	}

	/**
	 * Test isGoogleUser returns false for regular users.
	 */
	public function testIsGoogleUserReturnsFalseForRegularUser()
	{
		$user = ['external_id' => null, 'email' => 'test@example.com'];
		$this->assertFalse(User::isGoogleUser($user));

		$user2 = ['external_id' => '', 'email' => 'test@example.com'];
		$this->assertFalse(User::isGoogleUser($user2));
	}

	// =====================================================================
	// isUsingGravatar Tests
	// =====================================================================

	/**
	 * Test isUsingGravatar returns false for Google users with pictures.
	 */
	public function testIsUsingGravatarReturnsFalseForGoogleUserWithPicture()
	{
		$user = [
			'external_id' => 'google123',
			'picture' => 'https://lh3.googleusercontent.com/a/test',
			'email' => 'test@gmail.com',
		];
		$this->assertFalse(User::isUsingGravatar($user));
	}

	/**
	 * Test isUsingGravatar returns true for Google users without pictures.
	 */
	public function testIsUsingGravatarReturnsTrueForGoogleUserWithoutPicture()
	{
		$user = [
			'external_id' => 'google123',
			'picture' => null,
			'email' => 'test@gmail.com',
		];
		$this->assertTrue(User::isUsingGravatar($user));
	}

	/**
	 * Test isUsingGravatar returns true for regular users.
	 */
	public function testIsUsingGravatarReturnsTrueForRegularUser()
	{
		$user = [
			'external_id' => null,
			'picture' => null,
			'email' => 'test@example.com',
		];
		$this->assertTrue(User::isUsingGravatar($user));
	}
}

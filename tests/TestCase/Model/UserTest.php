<?php

use App\Utility\Avatar;
use App\Utility\Util;

class UserTest extends CakeTestCase
{
	// =====================================================================
	// Characters the forum cannot store
	// =====================================================================

	/**
	 * The rule guards the name of a new account, not every save of the row: an account that
	 * already carries such a character keeps working.
	 */
	public function testAnExistingNameTheForumCannotStoreDoesNotBlockSaving(): void
	{
		$context = new ContextPreparator(['user' => ['display_name' => 'Player']]);
		Util::query('UPDATE user SET display_name = ? WHERE id = ?', ['Player😀', $context->user['id']]);

		$User = ClassRegistry::init('User');
		$user = $User->findById($context->user['id']);
		$user['User']['highscore_page'] = 5;

		$this->assertNotFalse($User->save($user), 'Saving an account that already has such a name must work');
	}

	// =====================================================================
	// getAvatarUrl Tests
	// =====================================================================

	/**
	 * Test that Google users get their Google picture URL (resized).
	 */
	public function testGetAvatarUrlReturnsGooglePictureForGoogleUser()
	{
		$user = [
			'pref_avatar' => User::AVATAR_GOOGLE,
			'external_id' => '123456789',
			'picture' => 'https://lh3.googleusercontent.com/a/abc123',
			'email' => 'test@gmail.com',
		];

		$url = User::getAvatarUrl($user, 40);
		$this->assertStringContainsString('https://lh3.googleusercontent.com', $url);
		$this->assertStringContainsString('s40', $url);
	}

	/**
	 * Everyone without a Google picture gets the board the site draws for them, so
	 * no request leaves for an avatar host.
	 */
	public function testGetAvatarUrlReturnsTheBoardForAPlayerWithoutAPicture()
	{
		$user = [
			'id' => 4242,
			'display_name' => 'Anna',
			'external_id' => null,
			'picture' => null,
			'email' => 'test@example.com',
		];

		$this->assertSame(Avatar::url('Anna'), User::getAvatarUrl($user, 20));
	}

	/**
	 * A board belongs to a name: the same name always draws the same board, a rename
	 * draws another one, and the account behind it does not come into it.
	 */
	public function testTheBoardFollowsTheNameAndNotTheAccount()
	{
		$board = fn(string $name, int $id) => User::getAvatarUrl([
			'id' => $id,
			'display_name' => $name,
			'email' => 'board@example.com',
		]);

		$this->assertSame($board('Anna', 1), $board('Anna', 4242));
		$this->assertNotSame($board('Anna', 1), $board('Anna (2)', 1));
	}

	/**
	 * A Google account that has no picture to show gets a board too.
	 */
	public function testGetAvatarUrlReturnsTheBoardWhenAGoogleAccountHasNoPicture()
	{
		$user = [
			'id' => 77,
			'display_name' => 'Somebody',
			'external_id' => '123456789',
			'picture' => null,
			'email' => 'test@gmail.com',
		];

		$this->assertSame(Avatar::url('Somebody'), User::getAvatarUrl($user, 20));
	}

	public function testTheGooglePictureIsAskedForInTheSizeTheAvatarIsDrawnIn()
	{
		foreach (['', '=s96', '=s96-c', '=s96-c-rw', '=w100-h100'] as $suffix)
		{
			$user = [
				'id' => 1,
				'external_id' => '123',
				'picture' => 'https://lh3.googleusercontent.com/a/x' . $suffix,
				'pref_avatar' => User::AVATAR_GOOGLE,
			];

			$this->assertSame(
				'https://lh3.googleusercontent.com/a/x=s128',
				User::getAvatarUrl($user, 128),
				'The picture with the suffix ' . $suffix . ' was not asked for in 128 pixels'
			);
		}
	}

	/**
	 * A picture url that carries its own parameters is served as it is, so its
	 * signature or query can not be broken by a size that is appended to it.
	 */
	public function testAGooglePictureWithItsOwnParametersIsLeftAlone()
	{
		$user = [
			'id' => 1,
			'external_id' => '123',
			'picture' => 'https://lh3.googleusercontent.com/a/x?sz=50',
			'pref_avatar' => User::AVATAR_GOOGLE,
		];

		$this->assertSame('https://lh3.googleusercontent.com/a/x?sz=50', User::getAvatarUrl($user, 128));
	}

	/**
	 * A picture of another host is served as it is as well.
	 */
	public function testAPictureOfAnotherHostIsLeftAlone()
	{
		$user = [
			'id' => 1,
			'external_id' => '123',
			'picture' => 'https://example.com/photo.png',
			'pref_avatar' => User::AVATAR_GOOGLE,
		];

		$this->assertSame('https://example.com/photo.png', User::getAvatarUrl($user, 128));
	}

	public function testAnAvatarFromAnotherHostFallsBackToTheBoardOfTheAccount()
	{
		$withPicture = User::renderLink([
			'id' => 4242,
			'display_name' => 'Pic',
			'external_id' => '123',
			'picture' => 'https://lh3.googleusercontent.com/a/x',
			'pref_avatar' => User::AVATAR_GOOGLE,
		]);
		$this->assertStringContainsString(
			"onerror=\"this.onerror=null;this.classList.add('user-avatar--board');this.src='" . Avatar::url('Pic') . "'\"",
			$withPicture
		);

		$withBoard = User::renderLink(['id' => 4242, 'display_name' => 'Pic', 'email' => 'pic@example.com']);
		$this->assertStringNotContainsString('onerror', $withBoard, 'A board avatar never fails, so it needs no fallback');
	}

	/**
	 * One svg covers every size, so the board url does not change with it.
	 */
	public function testTheBoardUrlIsTheSameForEverySize()
	{
		$user = ['id' => 4242, 'display_name' => 'Anna', 'external_id' => null, 'picture' => null, 'email' => 'test@example.com'];

		$this->assertSame(User::getAvatarUrl($user, 20), User::getAvatarUrl($user, 48));
	}

	/**
	 * Every account starts on the board, a Google picture is only shown once the
	 * player picks it.
	 */
	public function testTheBoardIsTheDefaultForAGoogleAccount()
	{
		$user = ['id' => 1, 'display_name' => 'Anna', 'external_id' => '123', 'picture' => 'https://lh3.googleusercontent.com/a/x'];

		$this->assertSame(Avatar::url('Anna'), User::getAvatarUrl($user, 24));
	}

	public function testChoosingTheBoardHidesTheGooglePicture()
	{
		$user = [
			'id' => 1,
			'display_name' => 'Anna',
			'pref_avatar' => User::AVATAR_BOARD,
			'external_id' => '123',
			'picture' => 'https://lh3.googleusercontent.com/a/x',
		];

		$this->assertSame(Avatar::url('Anna'), User::getAvatarUrl($user, 24));
	}

	public function testChoosingTheGooglePictureUsesIt()
	{
		$user = [
			'id' => 1,
			'pref_avatar' => User::AVATAR_GOOGLE,
			'external_id' => '123',
			'picture' => 'https://lh3.googleusercontent.com/a/x',
		];

		$this->assertSame('https://lh3.googleusercontent.com/a/x=s24', User::getAvatarUrl($user, 24));
	}

	public function testAGoogleChoiceWithoutAPictureFallsBackToTheBoard()
	{
		$user = ['id' => 7, 'display_name' => 'Anna', 'pref_avatar' => User::AVATAR_GOOGLE, 'external_id' => '123', 'picture' => null];

		$this->assertSame(Avatar::url('Anna'), User::getAvatarUrl($user, 24));
	}

	public function testChoosingGravatarUsesTheSha256OfTheEmail()
	{
		$user = ['id' => 1, 'pref_avatar' => User::AVATAR_GRAVATAR, 'email' => ' MyEmailAddress@example.com '];

		$this->assertSame(
			'https://www.gravatar.com/avatar/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee?s=24&d=identicon',
			User::getAvatarUrl($user, 24)
		);
	}

	/**
	 * The hash is the one from the Gravatar documentation, so a change back to the
	 * older md5 fails here.
	 */
	public function testTheGravatarHashIsNotMd5()
	{
		$this->assertSame(
			'84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee',
			User::gravatarHash(' MyEmailAddress@example.com ')
		);
		$this->assertNotSame(md5('myemailaddress@example.com'), User::gravatarHash('myemailaddress@example.com'));
	}

	public function testAnUnknownChoiceFallsBackToTheBoard()
	{
		$user = ['id' => 8, 'display_name' => 'Anna', 'pref_avatar' => 99, 'email' => 'test@example.com'];

		$this->assertSame(Avatar::url('Anna'), User::getAvatarUrl($user, 24));
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
		$this->assertStringContainsString('src="' . Avatar::url('TestUser') . '"', $html);
		// A board is a picture of a thing, so it keeps its corners.
		$this->assertStringContainsString('class="user-avatar user-avatar--board"', $html);
		// A page shows up to a thousand of these, so they load lazily and decode
		// off the main thread.
		$this->assertStringContainsString('loading="lazy"', $html);
		$this->assertStringContainsString('decoding="async"', $html);
		$this->assertStringContainsString('referrerpolicy="no-referrer"', $html);
	}

	/**
	 * Test that renderLink shows Google picture for Google users.
	 */
	public function testRenderLinkShowsGooglePictureForGoogleUser()
	{
		$html = User::renderLink([
			'id' => 123,
			'display_name' => 'GoogleUser',
			'pref_avatar' => User::AVATAR_GOOGLE,
			'external_id' => 'google_ext_123',
			'picture' => 'https://lh3.googleusercontent.com/a/abc123',
			'rating' => 1500.0,
			'email' => 'google@gmail.com',
		]);

		$this->assertStringContainsString('lh3.googleusercontent.com', $html);
		// The list shows 24px of it, so twice that is requested for a sharp screen.
		$this->assertStringContainsString('s48', $html);
		// A picture of a face is round, which is how its owner sees it in Google.
		$this->assertStringContainsString('class="user-avatar"', $html);
	}

	/**
	 * Test that renderLink handles deleted users (null id).
	 */
	public function testRenderLinkHandlesDeletedUser()
	{
		$html = User::renderLink(null);
		$this->assertStringContainsString('[account deleted]', $html);
		$this->assertStringNotContainsString('<a href', $html);

		// Test with empty array
		$html = User::renderLink(['id' => null, 'display_name' => 'Ghost']);
		$this->assertStringContainsString('[account deleted]', $html);
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
		$this->assertStringContainsString('src="' . Avatar::url('ArrayUser') . '"', $html);
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

	/**
	 * Test that normalizeDisplayName trims surrounding whitespace and collapses
	 * internal runs of whitespace into single spaces.
	 */
	public function testNormalizeDisplayNameTrimsAndCollapsesWhitespace()
	{
		$this->assertSame('Hello World', User::normalizeDisplayName('  Hello   World  '));
		$this->assertSame('admin', User::normalizeDisplayName('admin '));
		$this->assertSame('John Doe', User::normalizeDisplayName("\tJohn\nDoe\t"));
	}

	/**
	 * Test that generateUniqueDisplayName normalizes the requested name, so a
	 * padded input cannot collide with a different existing display name.
	 */
	public function testGenerateUniqueDisplayNameNormalizesInput()
	{
		$context = new ContextPreparator(['user' => ['name' => 'DisplayTest', 'display_name' => 'Display Test']]);

		// " Display   Test " normalizes to "Display Test" (already taken -> gets a suffix)
		$unique = User::generateUniqueDisplayName(' Display   Test ');
		$this->assertStringStartsWith('Display Test', $unique);
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

	/**
	 * A display name has to be one the site can store: not empty, and without a character above
	 * U+FFFF, which the utf8mb3 columns of the forum cannot hold. Three-byte letters from any
	 * alphabet fit and stay allowed.
	 */
	public function testDisplayNameMustBeOneTheSiteCanStore()
	{
		$Model = ClassRegistry::init('User');
		foreach ([
			'empty' => ['', false],
			'with an emoji' => ['Player😀', false],
			'with a rare kanji above U+FFFF' => ['𠮷', false],
			'Cyrillic' => ['Лисёнок', true],
			'Japanese' => ['日本語', true]] as $case => [$displayName, $valid])
		{
			$Model->create();
			$Model->set([
				'name' => 'SomeLoginName',
				'display_name' => $displayName,
				'email' => 'blank@example.com',
			]);
			$this->assertSame($valid, $Model->validates(), 'A display name ' . $case . ($valid ? ' must validate' : ' must be refused'));
		}
	}

	/**
	 * An e-mail address can have 254 characters, the user table stores them all.
	 */
	public function testAnEmailAddressCanHaveUpTo254Characters(): void
	{
		$Model = ClassRegistry::init('User');
		foreach ([57 => true, 58 => false] as $lastLabelLength => $valid)
		{
			// 64 + 1 + 63 + 1 + 63 + 1 + 57 + 4 = 254 characters
			$email = str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', $lastLabelLength) . '.com';
			$Model->create();
			$Model->set(['name' => 'SomeLoginName', 'display_name' => 'Player', 'email' => $email]);
			$this->assertSame($valid, $Model->validates(), 'An address of ' . strlen($email) . ' characters' . ($valid ? ' must validate' : ' must be refused'));
		}
	}

	/**
	 * Test that a generated display name fits the column.
	 */
	public function testGenerateUniqueDisplayNameFitsTheColumn()
	{
		new ContextPreparator(['user' => null]);

		$generated = User::generateUniqueDisplayName(str_repeat('A', 70));

		$this->assertSame(str_repeat('A', 50), $generated);
	}
}

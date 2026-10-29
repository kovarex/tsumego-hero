<?php

use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\UserTable;
use App\Utility\Avatar;
use App\Utility\Util;

//Tests for the forced display-name change flow.
//Free renaming is disabled; the display name can only be changed by a user
//who was flagged with needs_display_name_change (migration rename or a
//duplicate Google name). On success the flag is cleared.
class UpdateNameTest extends IntegrationTestCase
{
	//Test that logged out users cannot update display name.
	public function testUpdateNameRequiresLogin(): void
	{
		// Create context but don't log in (pass null for user)
		new ContextPreparator(['user' => null]);

		$this->post('/users/updatename', ['User' => ['display_name' => 'testname_new']]);

		// Should redirect to home
		$this->assertStringContainsString('/', (string) $this->headerValue('Location'));
	}

	//Test that a user NOT flagged cannot change their display name.
	public function testNonForcedUserCannotChangeName(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Original Name',
				'email' => 'regular@example.com',
			],
		]);

		$this->post('/users/updatename', ['User' => ['display_name' => 'Should Not Change']]);

		$updatedUser = UserTable::row((int) $context->user['id']);
		$this->assertEquals('Original Name', $updatedUser['display_name']);
		$this->assertEquals(false, $updatedUser['needs_display_name_change']);
	}

	//Test that a forced Google user can change their display name and the flag is cleared.
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

		$this->post('/users/updatename', ['User' => ['display_name' => 'New Name']]);

		$updatedUser = UserTable::row((int) $context->user['id']);
		$this->assertEquals('New Name', $updatedUser['display_name']);
		$this->assertEquals(false, $updatedUser['needs_display_name_change']);
	}

	//Test that a forced regular user can change their display name and the flag is cleared.
	public function testForcedRegularUserCanChangeName(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Regular User',
				'email' => 'regular@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->post('/users/updatename', ['User' => ['display_name' => 'Updated Display']]);

		$updatedUser = UserTable::row((int) $context->user['id']);
		$this->assertEquals('Updated Display', $updatedUser['display_name']);
		$this->assertEquals(false, $updatedUser['needs_display_name_change']);
	}

	//Test that display name must be unique even when forced.
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

		$this->post('/users/updatename', ['User' => ['display_name' => 'Taken Name']]);

		$updatedUser = UserTable::row((int) $context->user['id']);
		$this->assertEquals('My Name', $updatedUser['display_name']);
	}

	//An emoji is above U+FFFF, which the utf8mb3 columns the forum shares cannot hold, so a rename cannot bring one in.
	public function testRenameToANameWithAnEmojiIsRejected(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'My Name',
				'email' => 'my@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->post('/users/updatename', ['User' => ['display_name' => 'My Name 😀']]);

		$updatedUser = UserTable::row((int) $context->user['id']);
		$this->assertEquals('My Name', $updatedUser['display_name']);
		$this->assertEquals(true, $updatedUser['needs_display_name_change']);
	}

	//Test that an unchanged display name is rejected even when forced.
	public function testUnchangedNameIsRejected(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Andrey (2)',
				'email' => 'andrey@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->post('/users/updatename', ['User' => ['display_name' => 'Andrey (2)']]);

		$updatedUser = UserTable::row((int) $context->user['id']);
		$this->assertEquals('Andrey (2)', $updatedUser['display_name']);
		$this->assertEquals(true, $updatedUser['needs_display_name_change']);
	}

	//Test that the length limit counts characters, not bytes.
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

		$this->post('/users/updatename', ['User' => ['display_name' => $newName]]);

		$updatedUser = UserTable::row((int) $context->user['id']);
		$this->assertEquals($newName, $updatedUser['display_name']);
		$this->assertEquals(false, $updatedUser['needs_display_name_change']);
	}

	//Test that display name must be at least 3 characters even when forced.
	public function testNameMinimumLength(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Original Name',
				'email' => 'short@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->post('/users/updatename', ['User' => ['display_name' => 'ab']]);

		$updatedUser = UserTable::row((int) $context->user['id']);
		$this->assertEquals('Original Name', $updatedUser['display_name']);
	}

	//Test that empty display name is rejected even when forced.
	public function testEmptyNameRejected(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'My Display',
				'email' => 'empty@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->post('/users/updatename', ['User' => ['display_name' => '']]);

		$updatedUser = UserTable::row((int) $context->user['id']);
		$this->assertEquals('My Display', $updatedUser['display_name']);
	}

	//Test that the changename page is only reachable by a forced user.
	public function testChangenamePageRequiresForcedUser(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Not Forced',
				'email' => 'notforced@example.com',
			],
		]);

		$this->get('/users/changename');
		$this->assertStringContainsString('/', (string) $this->headerValue('Location'));
	}

	//Test that a visitor who is not signed in gets sent to the login page.
	public function testChangenamePageSendsAnonymousToLogin(): void
	{
		new ContextPreparator(['user' => null]);

		$this->get('/users/changename');
		$this->assertStringContainsString('/users/login', $this->headerValue('Location'));
	}

	public function testChangenamePageRendersForForcedUser(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Forced Name',
				'email' => 'forced@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->get('/users/changename');
		$result = $this->view;
		$this->assertStringContainsString('Choose your display name', $result);
	}

	//Test that a forced rename is recorded in the activity trail.
	public function testRenamingIsRecordedInTheActivityTrail(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Name From Migration',
				'email' => 'renamed@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->post('/users/updatename', ['User' => ['display_name' => 'My Chosen Name']]);

		$activities = $this->fetchTable('AdminActivity')->find()->orderBy('id')->toArray();
		$this->assertCount(1, $activities);
		$this->assertEquals(AdminActivityTypeTable::DISPLAY_NAME_CHANGE, $activities[0]->type);
		$this->assertEquals('Name From Migration', $activities[0]->old_value);
		$this->assertEquals('My Chosen Name', $activities[0]->new_value);
		$this->assertEquals($context->user['id'], $activities[0]->user_id);
	}

	//Test that a rejected rename is not recorded in the activity trail.
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

		$this->post('/users/updatename', ['User' => ['display_name' => 'Taken Name']]);

		$this->assertCount(0, $this->fetchTable('AdminActivity')->find()->orderBy('id')->toArray());
	}

	//Test that renaming updates the linked phpBB forum user.
	public function testRenamingKeepsTheForumUsernameInSync(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Old Forum Name',
				'email' => 'forumsync@example.com',
				'needs_display_name_change' => true,
			],
		]);

		Util::execute(
			"INSERT INTO phpbb_users (user_id, username, username_clean, user_permissions, user_sig, external_id)"
			. " VALUES (?, ?, ?, '', '', ?)",
			[700001, 'Old Forum Name', 'old forum name', $context->user['id']]
		);

		$this->post('/users/updatename', ['User' => ['display_name' => 'New Forum Name']]);

		$forumUser = Util::query("SELECT username, username_clean FROM phpbb_users WHERE user_id = ?", [700001]);
		$this->assertEquals('New Forum Name', $forumUser[0]['username']);
		$this->assertEquals('new forum name', $forumUser[0]['username_clean']);
	}

	//Test that renaming updates the poster names cached in the forum topics.
	public function testRenamingKeepsForumTopicNamesInSync(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Old Poster',
				'email' => 'postersync@example.com',
				'needs_display_name_change' => true,
			],
		]);

		Util::execute(
			"INSERT INTO phpbb_users (user_id, username, username_clean, user_permissions, user_sig, external_id)"
			. " VALUES (?, ?, ?, '', '', ?)",
			[700002, 'Old Poster', 'old poster', $context->user['id']]
		);

		// A topic stores its poster by phpBB user id, and the forum account is the one
		// linked to this site account by external_id.
		Util::execute(
			"INSERT INTO phpbb_topics (topic_id, topic_poster, topic_first_poster_name, topic_last_poster_id,"
			. " topic_last_poster_name) VALUES (?, ?, ?, ?, ?)",
			[700002, 700002, 'Old Poster', 700002, 'Old Poster']
		);

		$this->post('/users/updatename', ['User' => ['display_name' => 'New Poster']]);

		$topic = Util::query(
			"SELECT topic_first_poster_name, topic_last_poster_name FROM phpbb_topics WHERE topic_id = ?",
			[700002]
		);
		$this->assertEquals('New Poster', $topic[0]['topic_first_poster_name']);
		$this->assertEquals('New Poster', $topic[0]['topic_last_poster_name']);
	}

	//Test that the forum username key is the one phpBB computes for the display name.
	public function testForumUsernameKeyUsesPhpbbCleaning(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Old Key Name',
				'email' => 'cleankey@example.com',
				'needs_display_name_change' => true,
			],
		]);

		Util::execute(
			"INSERT INTO phpbb_users (user_id, username, username_clean, user_permissions, user_sig, external_id)"
			. " VALUES (?, ?, ?, '', '', ?)",
			[700003, 'Old Key Name', 'old key name', $context->user['id']]
		);

		$this->post('/users/updatename', ['User' => ['display_name' => 'Yiğit Aydın']]);

		$forumUser = Util::query("SELECT username, username_clean FROM phpbb_users WHERE user_id = ?", [700003]);
		$this->assertEquals('Yiğit Aydın', $forumUser[0]['username']);
		$this->assertEquals('yiğit aydin', $forumUser[0]['username_clean']);
	}

	//Test that a flagged user browsing the site is sent to the rename page.
	public function testForcedUserIsSentToTheRenamePage(): void
	{
		new ContextPreparator([
			'user' => [
				'display_name' => 'Forced Name',
				'email' => 'forced@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->get('/sets/index');
		$this->assertStringContainsString('/users/changename', $this->headerValue('Location') ?? '');
	}

	//Test that a flagged user can open the password reset link from the e-mail.
	public function testForcedUserCanOpenThePasswordResetLink(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Forced Name',
				'email' => 'forced@example.com',
				'needs_display_name_change' => true,
			],
		]);
		Util::execute("UPDATE user SET password_reset_token = ? WHERE id = ?", ['reset-checksum', $context->user['id']]);

		$this->get('/users/newpassword/reset-checksum');

		$this->assertResponseCode(200);
		$this->assertStringNotContainsString('/users/changename', $this->headerValue('Location') ?? '');
	}

	//Test that the link in the account deletion e-mail is not sent to the rename page.
	public function testForcedUserCanConfirmTheAccountDeletion(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Forced Name',
				'email' => 'forced@example.com',
				'needs_display_name_change' => true,
			],
		]);
		// A token is only honoured while it is fresh, see UsersController::$DELETION_LINK_VALID_HOURS.
		Util::execute('UPDATE user SET deletion_token = ?, deletion_token_created = ? WHERE id = ?', ['delete-token', date('Y-m-d H:i:s'), $context->user['id']]);

		$this->post('/users/confirm_delete_account/delete-token');

		$this->assertResponseCode(302);
		$this->assertStringNotContainsString('/users/changename', $this->headerValue('Location') ?? '');
		$this->assertNotNull(UserTable::row((int) $context->user['id'])['deleted'], 'the account went through the deletion');
	}

	//Test that a flagged user can open the page that starts the account deletion.
	public function testForcedUserCanOpenTheDeleteAccountPage(): void
	{
		new ContextPreparator([
			'user' => [
				'display_name' => 'Forced Name',
				'email' => 'forced@example.com',
				'needs_display_name_change' => true,
			],
		]);

		$this->get('/users/delete_account');

		$this->assertResponseCode(200);
		$this->assertStringNotContainsString('/users/changename', $this->headerValue('Location') ?? '');
	}

	//The rename page shows the board of the player, so the board itself must not be sent
	//to the rename page either. The request has to be a real one: a controller test runs
	//the action even when the gate redirected it, so it cannot tell the two apart.
	public function testForcedUserStillGetsTheirAvatarImage(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Forced Name',
				'email' => 'forced@example.com',
				'needs_display_name_change' => true,
			],
		]);
		$browser = Browser::instance();
		$browser->get(ltrim(Avatar::url($context->user['display_name']), '/'));

		$this->assertStringContainsString(Avatar::url($context->user['display_name']), $browser->driver->getCurrentURL());
		$this->assertStringContainsString('<svg', $browser->driver->getPageSource());
	}

	//A list falls back on the board of an account when the picture of an avatar is gone,
	//so the board has to be answered while the player still has to pick a name too. The
	//gate would otherwise send that request to the rename page and leave the list with a
	//broken image.
	public function testForcedUserStillGetsTheirBoardByUserId(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Forced Name',
				'email' => 'forced@example.com',
				'needs_display_name_change' => true,
			],
		]);
		$browser = Browser::instance();
		$browser->get('avatar/' . $context->user['id']);

		$this->assertStringContainsString(Avatar::url('Forced Name'), $browser->driver->getCurrentURL());
	}

	//Test that a forum which can not be written to does not fail the rename. The
	//next forum sign in repairs the name (jwtauth::sync_rename).
	public function testRenameSurvivesAForumFailure(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'display_name' => 'Old Forum Name',
				'email' => 'forumfail@example.com',
				'needs_display_name_change' => true,
			],
		]);

		Util::execute('RENAME TABLE phpbb_users TO phpbb_users_unreachable');
		try
		{
			$this->post('/users/updatename', ['User' => ['display_name' => 'New Forum Name']]);
		}
		finally
		{
			Util::execute('RENAME TABLE phpbb_users_unreachable TO phpbb_users');
		}

		$user = UserTable::row((int) $context->user['id']);
		$this->assertEquals('New Forum Name', $user['display_name']);
		$this->assertEquals(false, $user['needs_display_name_change']);
	}
}

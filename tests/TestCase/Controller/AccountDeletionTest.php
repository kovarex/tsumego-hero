<?php

use App\Model\Table\AdminActivityTypeTable;
use App\Utility\Auth;
use App\Utility\CookieFlash;

class AccountDeletionTest extends IntegrationTestCase
{
	private function userRow(int $userID): array
	{
		return ClassRegistry::init('User')->findById($userID)['User'];
	}

	private function deletedOf(int $userID): ?string
	{
		return $this->userRow($userID)['deleted'];
	}

	private function requestDeletion(): void
	{
		$this->registerTestEmailer();
		$this->post('/users/delete_account');
	}

	private function confirmDeletion(string $token): void
	{
		$this->post('/users/confirm_delete_account/' . $token);
	}

	// the whole deletion of the logged-in user: the emailed link, then confirming it
	private function deleteOwnAccount(): void
	{
		$userID = Auth::getUserID();
		$this->requestDeletion();
		$this->confirmDeletion($this->userRow($userID)['deletion_token']);
	}

	private function restoreAccount(int $userID): void
	{
		$this->post('/users/restore_account/' . $userID);
	}

	public function testRequestingDeletionEmailsConfirmationLink(): void
	{
		$context = new ContextPreparator(['user' => ['email' => 'kovarex@example.com']]);
		$this->requestDeletion();

		$user = $this->userRow($context->user['id']);
		$this->assertNull($user['deleted'], 'Nothing is deleted before the emailed link is confirmed');
		$this->assertNotEmpty($user['deletion_token']);
		$this->assertSame('kovarex@example.com', TestEmailer::$lastEmail['to']);
		$this->assertTextContains('Deletion of your Tsumego Hero account', TestEmailer::$lastEmail['subject']);
		$this->assertTextContains('https://' . $_SERVER['HTTP_HOST'] . '/users/confirm_delete_account/' . $user['deletion_token'], TestEmailer::$lastEmail['body']);
	}

	public function testDeletionEmailGoesToTheUsersEmail(): void
	{
		new ContextPreparator(['user' => ['name' => 'Google User', 'email' => 'google@example.com', 'external_id' => '123']]);
		$this->requestDeletion();
		$this->assertSame('google@example.com', TestEmailer::$lastEmail['to']);
	}

	//The deletion happens through the mailed link, so a mail that never left leaves no
	//token behind and the request can be repeated.
	public function testNoLinkIsKeptWhenTheMailCannotBeSent(): void
	{
		$context = new ContextPreparator();
		$this->registerTestEmailer();

		try
		{
			TestEmailer::$unreachable = true;
			$this->post('/users/delete_account');
		}
		finally
		{
			TestEmailer::$unreachable = false;
		}

		$this->assertNull($this->userRow($context->user['id'])['deletion_token']);

		$this->requestDeletion();
		$this->assertNotEmpty($this->userRow($context->user['id'])['deletion_token']);
	}

	public function testConfirmingEmailedLinkDeletesAccount(): void
	{
		$context = new ContextPreparator();
		$this->deleteOwnAccount();

		$this->assertNotNull($this->deletedOf($context->user['id']));
		$this->assertFalse(Auth::isLoggedIn());
		$this->assertNull($this->userRow($context->user['id'])['deletion_token']);
		$adminActivity = $this->fetchTable('AdminActivity')->find()->firstOrFail();
		$this->assertSame(AdminActivityTypeTable::DELETE_USER, $adminActivity->type);
		$this->assertSame($context->user['id'], $adminActivity->user_id);
		$this->assertSame('DN_kovarex', $adminActivity->old_value, 'The activity log names the account the way players see it');
	}

	public function testEmailedLinkWorksWithoutBeingSignedIn(): void
	{
		$context = new ContextPreparator();
		$this->requestDeletion();
		$token = $this->userRow($context->user['id'])['deletion_token'];

		$this->logout();
		$this->confirmDeletion($token);
		$this->assertNotNull($this->deletedOf($context->user['id']));
		$this->assertSame($context->user['id'], $this->fetchTable('AdminActivity')->find()->firstOrFail()->user_id);
	}

	public function testOpeningEmailedLinkOnlyAsksForConfirmation(): void
	{
		$context = new ContextPreparator();
		$this->requestDeletion();
		$token = $this->userRow($context->user['id'])['deletion_token'];

		// email scanners open links, so just opening it must not delete anything
		$this->get('/users/confirm_delete_account/' . $token);
		$result = $this->view;
		$this->assertStringContainsString('confirm-deletion-button', $result);
		$this->assertNull($this->deletedOf($context->user['id']));
	}

	public function testWrongOrExpiredLinkDoesNotDeleteAccount(): void
	{
		$context = new ContextPreparator();
		$this->requestDeletion();
		$token = $this->userRow($context->user['id'])['deletion_token'];

		$this->confirmDeletion('wrong' . $token);
		$this->assertNull($this->deletedOf($context->user['id']));

		ClassRegistry::init('User')->save(['User' => ['id' => $context->user['id'], 'deletion_token_created' => date('Y-m-d H:i:s', strtotime('-25 hours'))]], false);
		$this->post('/users/confirm_delete_account/' . $token);
		$result = $this->view;
		$this->assertStringContainsString('deletion-link-invalid', $result);
		$this->assertNull($this->deletedOf($context->user['id']));
	}

	public function testDeletedAccountCannotLogIn(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'kovarex', 'deleted' => true]]]);
		$this->post('users/login/', ['username' => 'kovarex', 'password' => 'test']);
		$this->assertFalse(Auth::isLoggedIn());
	}

	public function testSessionOfDeletedAccountEnds(): void
	{
		// the login cookie is still there, but the account was deleted
		new ContextPreparator(['user' => ['deleted' => true]]);
		$this->get('/sites/index');
		$this->assertFalse(Auth::isLoggedIn());
	}

	public function testDeletedAccountIsLeftOutOfLeaderboards(): void
	{
		new ContextPreparator(['other-users' => [
			['name' => 'ghost', 'level' => 50, 'rating' => 2500, 'deleted' => true],
			['name' => 'alive', 'level' => 40, 'rating' => 2400]]]);

		foreach (['/users/highscore', '/users/rating'] as $url)
		{
			$this->get($url);
			$result = $this->view;
			$this->assertStringContainsString('alive', $result, $url);
			$this->assertStringNotContainsString('ghost', $result, $url);
			$this->assertStringNotContainsString(User::DELETED_NAME, $result, $url);
		}
	}

	public function testNameOfDeletedAccountIsReplaced(): void
	{
		$context = new ContextPreparator(['other-users' => [['name' => 'ghost', 'deleted' => true], ['name' => 'alive']]]);
		$ghost = $context->otherUsers[0];
		$alive = $context->otherUsers[1];

		$this->assertSame(h(User::DELETED_NAME), User::renderLink($ghost));
		$this->assertSame(User::DELETED_NAME, User::displayName($ghost));
		$this->assertNull(User::displayNameOrNull($ghost));
		$this->assertStringContainsString('alive', User::renderLink($alive));
		$this->assertSame($alive['display_name'], User::displayName($alive));
	}

	public function testCommentOfDeletedAccountShowsPlaceholder(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'ghost', 'deleted' => true]],
			'tsumego' => ['set_order' => 1, 'comments' => [['message' => 'comment by a deleted account']]]]);
		ClassRegistry::init('TsumegoComment')->updateAll(['TsumegoComment.user_id' => $context->otherUsers[0]['id']], []);

		$this->get('/comments');
		$result = $this->view;
		$this->assertStringContainsString('comment by a deleted account', $result);
		$this->assertStringContainsString(h(User::DELETED_NAME), $result);
		$this->assertStringNotContainsString('ghost', $result);
	}

	public function testProfileOfDeletedAccountIsNotFound(): void
	{
		$context = new ContextPreparator(['other-users' => [['name' => 'ghost', 'deleted' => true]]]);
		$this->get('/users/view/' . $context->otherUsers[0]['id']);

		$this->assertResponseCode(404);
	}

	public function testAdminCanSeeProfileOfDeletedAccount(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'other-users' => [['name' => 'ghost', 'deleted' => true]]]);
		$this->get('/users/view/' . $context->otherUsers[0]['id']);
		$result = $this->view;
		$this->assertStringContainsString('ghost', $result);
	}

	public function testAdminRestoresAccount(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'other-users' => [['name' => 'ghost', 'email' => 'ghost@example.com', 'deleted' => true]]]);
		$this->restoreAccount($context->otherUsers[0]['id']);
		$ghost = $this->userRow($context->otherUsers[0]['id']);
		$this->assertNull($ghost['deleted']);
		$this->assertSame('ghost', $ghost['name']);
		$this->assertSame('ghost@example.com', $ghost['email']);
		$this->assertSame('Account ghost restored', CookieFlash::get());
	}

	public function testRestoreWorksWhenAnotherAccountHasTheSameEmail(): void
	{
		// emails don't need to be unique, people have several accounts with one email
		$context = new ContextPreparator([
			'user' => ['admin' => true, 'email' => 'shared@example.com'],
			'other-users' => [['name' => 'ghost', 'email' => 'shared@example.com', 'deleted' => true]]]);
		$this->restoreAccount($context->otherUsers[0]['id']);
		$this->assertNull($this->deletedOf($context->otherUsers[0]['id']));
	}

	public function testAGuestCannotRestoreAccount(): void
	{
		$context = new ContextPreparator(['other-users' => [['name' => 'ghost', 'deleted' => true]]]);

		$this->post('/users/restore_account/' . $context->otherUsers[0]['id']);

		$this->assertResponseCode(401);
	}

	public function testOnlyAdminCanRestoreAccount(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'other-users' => [['name' => 'ghost', 'deleted' => true]]]);

		$this->post('/users/restore_account/' . $context->otherUsers[0]['id']);

		$this->assertResponseCode(403);
	}

	public function testDeletingFreesNameAndGoogleAccount(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'kovarex', 'email' => 'kovarex@example.com', 'external_id' => '123']]);
		$this->deleteOwnAccount();

		$user = $this->userRow($context->user['id']);
		$this->assertSame('[deleted ' . $context->user['id'] . ']', $user['name']);
		$this->assertSame('kovarex@example.com', $user['email'], 'The email of a deleted account stays');
		$this->assertNull($user['external_id']);
		$this->assertNull($user['deleted_name'], 'A Google account has no login name to keep');
		$this->assertSame('123', $user['deleted_external_id']);
	}

	public function testDeletingKeepsTheLoginNameForRestoring(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'kovarex', 'email' => 'kovarex@example.com']]);
		$this->deleteOwnAccount();

		$this->assertSame('kovarex', $this->userRow($context->user['id'])['deleted_name']);
	}

	public function testNewAccountCanTakeNameOfDeletedOneWhichThenCantBeRestored(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'email' => 'kovarex@example.com'],
			'other-users' => [['name' => 'admin', 'admin' => true]]]);
		$oldAccountID = $context->user['id'];
		$this->deleteOwnAccount();

		// a new account can take the name; the email stays with the deleted account
		$this->logout();
		$this->post('/users/add', ['User' => [
			'name' => 'kovarex',
			'email' => 'newkovarex@example.com',
			'password1' => 'secretChest42',
			'password2' => 'secretChest42']]);
		$newAccount = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		$this->assertNotEmpty($newAccount);
		$this->assertNotEquals($oldAccountID, $newAccount['User']['id']);

		// the old account can't be restored, as its name is taken
		$this->login('admin');
		$this->restoreAccount($oldAccountID);
		$this->assertNotNull($this->deletedOf($oldAccountID));
		$this->assertSame('Account kovarex can\'t be restored: The name kovarex is used by another account now.', CookieFlash::get());
		$this->assertSame('kovarex', $this->userRow($newAccount['User']['id'])['name']);

		// once the new account is deleted as well, the old one can be restored
		User::deleteAccount($newAccount['User']['id']);
		$this->restoreAccount($oldAccountID);
		$oldAccount = $this->userRow($oldAccountID);
		$this->assertNull($oldAccount['deleted']);
		$this->assertSame('kovarex', $oldAccount['name']);
		$this->assertSame('kovarex@example.com', $oldAccount['email']);
		$this->assertNull($oldAccount['deleted_name']);
	}

	public function testRestoreFailsWhenGoogleAccountIsLinkedToAnotherAccount(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [
				['display_name' => 'Google User', 'email' => 'google@example.com', 'external_id' => '123', 'deleted' => true],
				['display_name' => 'Google User 2', 'external_id' => '456', 'email' => 'other@example.com']]]);
		// signing in with the same Google account created a new account
		ClassRegistry::init('User')->save(['User' => ['id' => $context->otherUsers[1]['id'], 'external_id' => '123']], false);

		$this->restoreAccount($context->otherUsers[0]['id']);
		$this->assertNotNull($this->deletedOf($context->otherUsers[0]['id']));
		$this->assertStringContainsString('The Google account is linked to another account now.', CookieFlash::get());
	}
}

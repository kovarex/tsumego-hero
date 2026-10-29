<?php

use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Util;

//Changing the address of an account: the new address waits for its confirmation
//link, the old address gets a link that cancels the change.
class EmailChangeTest extends IntegrationTestCase
{
	private const PASSWORD = 'test';

	private function prepareUser(string $name): array
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => $name, 'email' => 'old@example.com']]);
		$this->login($name);
		return $context->user;
	}

	private function requestChange(array $data): void
	{
		$this->post('/users/emailPreference', ['User' => $data]);
	}

	private function storedUser(int $userID): array
	{
		return UserTable::row((int) $userID);
	}

	private function requestNewAddress(int $userID, string $address = 'new@example.com'): string
	{
		$this->requestChange(['email' => $address, 'password' => self::PASSWORD]);
		return (string) $this->storedUser($userID)['unconfirmed_email_token'];
	}

	private function confirm(string $token, string $method = 'post'): void
	{
		$this->{$method}('/users/confirmEmailChange/' . $token);
	}

	public function testRequestKeepsTheAddressUntilItIsConfirmed(): void
	{
		$user = $this->prepareUser('emailRequest');

		$this->requestChange(['email' => 'new@example.com', 'password' => self::PASSWORD]);

		$stored = $this->storedUser($user['id']);
		$this->assertSame('old@example.com', $stored['email']);
		$this->assertSame('new@example.com', $stored['unconfirmed_email']);
		$this->assertNotEmpty($stored['unconfirmed_email_token']);
		$this->assertNotEmpty($stored['unconfirmed_email_cancel_token']);
	}

	public function testRequestNeedsTheCurrentPassword(): void
	{
		$user = $this->prepareUser('emailWrongPassword');

		$this->requestChange(['email' => 'new@example.com', 'password' => 'not-my-password']);

		$this->assertNull($this->storedUser($user['id'])['unconfirmed_email']);
	}

	//The change is confirmed by the mailed link alone, so a mail that never left leaves
	//nothing to confirm: no address is kept waiting and the request can be repeated.
	public function testNothingIsKeptWaitingWhenTheMailCannotBeSent(): void
	{
		$user = $this->prepareUser('emailNoMail');
		$this->registerTestEmailer();

		try
		{
			TestEmailer::$unreachable = true;
			$this->requestChange(['email' => 'new@example.com', 'password' => self::PASSWORD]);
		}
		finally
		{
			TestEmailer::$unreachable = false;
		}

		$this->assertNull($this->storedUser($user['id'])['unconfirmed_email']);

		$this->requestChange(['email' => 'new@example.com', 'password' => self::PASSWORD]);
		$this->assertSame('new@example.com', $this->storedUser($user['id'])['unconfirmed_email']);
	}

	public function testRequestRefusesAnAddressOfAnotherAccount(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'emailTaken', 'email' => 'old@example.com'],
			'other-users' => [['name' => 'emailOwner', 'email' => 'taken@example.com']]]);
		$this->login('emailTaken');

		$this->requestChange(['email' => 'taken@example.com', 'password' => self::PASSWORD]);

		$this->assertNull($this->storedUser($context->user['id'])['unconfirmed_email']);
	}

	public function testConfirmingMovesTheNewAddressIntoTheAccount(): void
	{
		$user = $this->prepareUser('emailConfirmed');
		$token = $this->requestNewAddress($user['id']);

		$this->confirm($token);

		$stored = $this->storedUser($user['id']);
		$this->assertSame('new@example.com', $stored['email']);
		$this->assertNull($stored['unconfirmed_email']);
		$this->assertNull($stored['unconfirmed_email_token']);
	}

	public function testALongAddressIsKeptWhole(): void
	{
		$user = $this->prepareUser('emailLong');
		$address = str_repeat('a', 64) . '@' . str_repeat('b', 60) . '.example.com';
		$token = $this->requestNewAddress($user['id'], $address);

		$this->confirm($token);

		$this->assertSame($address, $this->storedUser($user['id'])['email']);
	}

	public function testAnAddressLongerThanAnyEmailIsRefused(): void
	{
		$user = $this->prepareUser('emailTooLong');
		$address = str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 58) . '.com';

		$this->requestChange(['email' => $address, 'password' => self::PASSWORD]);

		$this->assertNull($this->storedUser($user['id'])['unconfirmed_email']);
	}

	public function testTheConfirmationLinkWorksOnce(): void
	{
		$user = $this->prepareUser('emailOnce');
		$token = $this->requestNewAddress($user['id']);
		$this->confirm($token);

		$this->requestNewAddress($user['id'], 'third@example.com');
		$this->confirm($token);

		$this->assertSame('new@example.com', $this->storedUser($user['id'])['email']);
	}

	public function testAnExpiredLinkLeavesTheAddressAlone(): void
	{
		$user = $this->prepareUser('emailExpired');
		$token = $this->requestNewAddress($user['id']);
		Util::execute('UPDATE user SET unconfirmed_email_created = ? WHERE id = ?', [
			date('Y-m-d H:i:s', strtotime('-2 days')), $user['id']]);

		$this->confirm($token);

		$this->assertSame('old@example.com', $this->storedUser($user['id'])['email']);
	}

	//The address was free when it was asked for, but the link works for a day.
	public function testAnAddressThatWasTakenInTheMeantimeIsRefused(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'emailTakenLater', 'email' => 'old@example.com'],
			'other-users' => [['name' => 'tookTheAddress', 'email' => 'early@example.com']],
		]);
		$this->login('emailTakenLater');
		$user = $context->user;

		$token = $this->requestNewAddress($user['id']);
		// the other account takes the address while the confirmation link waits
		Util::execute('UPDATE user SET email = ? WHERE id = ?', ['new@example.com', $context->otherUsers[0]['id']]);

		$this->confirm($token);

		$stored = $this->storedUser($user['id']);
		$this->assertSame('old@example.com', $stored['email']);
		$this->assertNull($stored['unconfirmed_email']);
		$this->assertNull($stored['unconfirmed_email_token']);
	}

	public function testCancellingFromTheOldAddressKeepsTheAddress(): void
	{
		$user = $this->prepareUser('emailCancelled');
		$this->requestNewAddress($user['id']);
		$cancelToken = $this->storedUser($user['id'])['unconfirmed_email_cancel_token'];

		$this->post('/users/cancelEmailChange/' . $cancelToken);

		$stored = $this->storedUser($user['id']);
		$this->assertSame('old@example.com', $stored['email']);
		$this->assertNull($stored['unconfirmed_email']);
	}

	public function testTheAddressThatIsGivenUpIsToldAboutTheChange(): void
	{
		$user = $this->prepareUser('emailTold');
		$token = $this->requestNewAddress($user['id']);
		$this->registerTestEmailer();

		$this->confirm($token);

		$this->assertSame('old@example.com', TestEmailer::$lastEmail['to']);
	}

	public function testTheAddressThatIsGivenUpIsAskedToCancel(): void
	{
		$user = $this->prepareUser('emailCancelMail');
		$this->registerTestEmailer();

		$this->requestNewAddress($user['id']);

		$this->assertSame('old@example.com', TestEmailer::$lastEmail['to']);
		$this->assertStringContainsString(
			$this->storedUser($user['id'])['unconfirmed_email_cancel_token'],
			TestEmailer::$lastEmail['body']);
	}

	//A Google account signs in with the address of its Google account.
	public function testGoogleAccountKeepsItsAddress(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['external_id' => 'google-555666']]);
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();

		$this->requestChange(['email' => 'new@example.com', 'password' => self::PASSWORD]);

		$this->assertNull($this->storedUser($context->user['id'])['unconfirmed_email']);
	}
}

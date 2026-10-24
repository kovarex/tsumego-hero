<?php

use App\Utility\Auth;

class AvatarPreferenceTest extends TestCaseWithAuth
{
	public function testStoresTheChosenAvatar(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'avatarStores']]);
		$this->login('avatarStores');

		$result = $this->testAction('/users/avatarPreference', [
			'method' => 'post',
			'data' => ['avatar' => User::AVATAR_GRAVATAR],
			'return' => 'contents',
		]);

		$this->assertSame('{"status":"ok"}', $result);
		$row = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::AVATAR_GRAVATAR, (int) $row['User']['pref_avatar']);
	}

	public function testSwitchingBackToTheBoardStoresTheBoard(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'avatarBack']]);
		$this->login('avatarBack');
		$this->storeAvatar($context->user['id'], User::AVATAR_GRAVATAR);

		$this->testAction('/users/avatarPreference', [
			'method' => 'post',
			'data' => ['avatar' => User::AVATAR_BOARD],
		]);

		$row = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::AVATAR_BOARD, (int) $row['User']['pref_avatar']);
	}

	public function testTheGooglePictureIsStoredForAnAccountWithOne(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'avatarGoogle']]);
		$this->storeGooglePicture($context->user['id']);
		$this->login('avatarGoogle');

		$this->testAction('/users/avatarPreference', [
			'method' => 'post',
			'data' => ['avatar' => User::AVATAR_GOOGLE],
		]);

		$row = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::AVATAR_GOOGLE, (int) $row['User']['pref_avatar']);
	}

	/**
	 * An account without a Google picture can not pick one, so the request is
	 * ignored and the avatar stays on the board.
	 */
	public function testTheGooglePictureIsIgnoredForAnAccountWithoutOne(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'avatarNoGoogle']]);
		$this->login('avatarNoGoogle');

		$this->testAction('/users/avatarPreference', [
			'method' => 'post',
			'data' => ['avatar' => User::AVATAR_GOOGLE],
		]);

		$row = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::AVATAR_BOARD, (int) $row['User']['pref_avatar']);
	}

	public function testAnUnknownValueIsIgnored(): void
	{
		foreach ([99, -5, 3] as $invalid)
		{
			Auth::logout();
			$name = 'avatarBad' . $invalid;
			$context = new ContextPreparator(['user' => ['name' => $name]]);
			$this->login($name);

			$this->testAction('/users/avatarPreference', [
				'method' => 'post',
				'data' => ['avatar' => $invalid],
			]);

			$row = ClassRegistry::init('User')->findById($context->user['id']);
			$this->assertSame(User::AVATAR_BOARD, (int) $row['User']['pref_avatar'], 'The value ' . $invalid . ' was stored');
		}
	}

	public function testRequiresLogin(): void
	{
		Auth::logout();

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/users/avatarPreference', [
			'method' => 'post',
			'data' => ['avatar' => User::AVATAR_BOARD],
		]);
	}

	public function testTheSettingsPageOffersTheAvatarsTheAccountCanUse(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'avatarOffers']]);
		$this->login('avatarOffers');

		$html = $this->testAction('/users/settings', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('name="avatar" value="' . User::AVATAR_BOARD . '"', $html);
		$this->assertStringContainsString('name="avatar" value="' . User::AVATAR_GRAVATAR . '"', $html);
		$this->assertStringNotContainsString('name="avatar" value="' . User::AVATAR_GOOGLE . '"', $html);
	}

	public function testTheSettingsPageOffersTheGooglePictureToAnAccountWithOne(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'avatarOffersGoogle']]);
		$this->storeGooglePicture($context->user['id']);
		$this->login('avatarOffersGoogle');

		$html = $this->testAction('/users/settings', ['method' => 'get', 'return' => 'contents']);

		$this->assertStringContainsString('name="avatar" value="' . User::AVATAR_GOOGLE . '"', $html);
	}

	private function storeAvatar(int $userId, int $avatar): void
	{
		ClassRegistry::init('User')->save(
			['User' => ['id' => $userId, 'pref_avatar' => $avatar]],
			['validate' => false, 'fieldList' => ['pref_avatar']]
		);
	}

	private function storeGooglePicture(int $userId): void
	{
		$user = ClassRegistry::init('User');
		$user->id = $userId;
		$user->saveField('external_id', '123456789012345678901');
		$user->saveField('picture', 'https://lh3.googleusercontent.com/a/photo');
	}
}

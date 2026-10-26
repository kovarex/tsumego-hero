<?php

use App\Utility\CookieFlash;
use App\Utility\UploadedAvatar;

class AvatarUploadTest extends IntegrationTestCase
{
	/** @var array<int, string> Files a test made, removed afterwards */
	private array $files = [];

	public function tearDown(): void
	{
		foreach ($this->files as $file)
			@unlink($file);
		$this->files = [];
		parent::tearDown();
	}

	/**
	 * An upload is the avatar right away: the player who picks a picture wants to see it.
	 */
	public function testAnUploadedPictureBecomesTheAvatar(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');

		$this->upload($this->picture(400, 300));

		$row = $this->userRow($context->user['id']);
		$this->assertSame(User::AVATAR_UPLOADED, (int) $row['avatar_setting_id']);
		$this->assertMatchesRegularExpression('#^avatars/' . $context->user['id'] . '-[0-9a-f]{16}\.webp$#', $row['uploaded_avatar']);
		$this->files[] = UploadedAvatar::pathOf($row['uploaded_avatar']);
		$this->assertSame('WEBP', substr((string) file_get_contents(UploadedAvatar::pathOf($row['uploaded_avatar'])), 8, 4));
		$this->assertRedirect('/users/settings');
		$this->assertSame('Your picture is your avatar now.', CookieFlash::get());
		$this->assertSame(UploadedAvatar::url($row['uploaded_avatar']), User::getAvatarUrl($row));
	}

	/**
	 * An account keeps one uploaded picture, so the picture a new one replaces is gone.
	 */
	public function testANewPictureReplacesTheOldOne(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100, [255, 0, 0]));
		$first = $this->userRow($context->user['id'])['uploaded_avatar'];
		$this->files[] = UploadedAvatar::pathOf($first);

		$this->upload($this->picture(100, 100, [0, 0, 255]));

		$second = $this->userRow($context->user['id'])['uploaded_avatar'];
		$this->files[] = UploadedAvatar::pathOf($second);
		$this->assertNotSame($first, $second);
		$this->assertFileDoesNotExist(UploadedAvatar::pathOf($first));
		$this->assertFileExists(UploadedAvatar::pathOf($second));
	}

	public function testAFileThatIsNotAPictureChangesNothing(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader', 'avatar_setting_id' => User::AVATAR_GRAVATAR]]);
		$this->login('uploader');
		$path = $this->file('<?php echo "hello"; ?>');

		$this->upload($path, 'avatar.png');

		$row = $this->userRow($context->user['id']);
		$this->assertNull($row['uploaded_avatar']);
		$this->assertSame(User::AVATAR_GRAVATAR, (int) $row['avatar_setting_id']);
		$this->assertSame('The file is not a JPEG, PNG, WebP or GIF picture.', CookieFlash::get());
		$this->assertSame([], glob(WWW_ROOT . 'img' . DS . 'avatars' . DS . $context->user['id'] . '-*'));
	}

	public function testUploadRequiresLogin(): void
	{
		$this->upload($this->picture(100, 100));

		$this->assertResponseCode(401);
	}

	/**
	 * Without the picture the account shows the board again, the avatar it always has.
	 */
	public function testRemovingThePictureBringsTheBoardBack(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100));
		$name = $this->userRow($context->user['id'])['uploaded_avatar'];
		$this->files[] = UploadedAvatar::pathOf($name);

		$this->post('/users/avatarRemove');

		$row = $this->userRow($context->user['id']);
		$this->assertNull($row['uploaded_avatar']);
		$this->assertSame(User::AVATAR_BOARD, (int) $row['avatar_setting_id']);
		$this->assertFileDoesNotExist(UploadedAvatar::pathOf($name));
		$this->assertRedirect('/users/settings');
	}

	/**
	 * A player who went back to the Gravatar and then removes the picture keeps the Gravatar.
	 */
	public function testRemovingAPictureThatIsNotShownKeepsTheAvatar(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100));
		$this->files[] = UploadedAvatar::pathOf($this->userRow($context->user['id'])['uploaded_avatar']);
		$this->post('/users/avatarPreference', ['avatar' => User::AVATAR_GRAVATAR]);

		$this->post('/users/avatarRemove');

		$row = $this->userRow($context->user['id']);
		$this->assertNull($row['uploaded_avatar']);
		$this->assertSame(User::AVATAR_GRAVATAR, (int) $row['avatar_setting_id']);
	}

	/**
	 * The uploaded picture is one of the avatars to pick from, so a player can go to the
	 * board and back without uploading it again.
	 */
	public function testThePictureCanBePickedAgainAfterAnotherAvatar(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100));
		$this->files[] = UploadedAvatar::pathOf($this->userRow($context->user['id'])['uploaded_avatar']);
		$this->post('/users/avatarPreference', ['avatar' => User::AVATAR_BOARD]);

		$this->post('/users/avatarPreference', ['avatar' => User::AVATAR_UPLOADED]);

		$this->assertSame(User::AVATAR_UPLOADED, (int) $this->userRow($context->user['id'])['avatar_setting_id']);
	}

	public function testThePictureCanNotBePickedWithoutOne(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');

		$this->post('/users/avatarPreference', ['avatar' => User::AVATAR_UPLOADED]);

		$this->assertSame(User::AVATAR_BOARD, (int) $this->userRow($context->user['id'])['avatar_setting_id']);
	}

	public function testTheSettingsPageOffersTheUploadAndThePicture(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');

		$this->get('/users/settings');
		$this->assertStringContainsString('action="/users/avatarUpload" enctype="multipart/form-data"', $this->view);
		$this->assertStringNotContainsString('name="avatar" value="' . User::AVATAR_UPLOADED . '"', $this->view);
		$this->assertStringNotContainsString('id="avatar-remove"', $this->view);

		$this->upload($this->picture(100, 100));
		$this->files[] = UploadedAvatar::pathOf($this->userRow($context->user['id'])['uploaded_avatar']);
		$this->get('/users/settings');

		$this->assertStringContainsString('name="avatar" value="' . User::AVATAR_UPLOADED . '" onclick="avatarPreference(this.value);" checked="checked"', $this->view);
		$this->assertStringContainsString('id="avatar-remove"', $this->view);
	}

	/**
	 * The picture is shown wherever the name of the player is, the same way the other avatars are.
	 */
	public function testTheProfileShowsAndSharesThePicture(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100));
		$name = $this->userRow($context->user['id'])['uploaded_avatar'];
		$this->files[] = UploadedAvatar::pathOf($name);

		$this->get('/users/view/' . $context->user['id']);

		$this->assertStringContainsString('<img src="' . UploadedAvatar::url($name) . '"', $this->view);
		$this->assertStringContainsString('og:image" content="' . Router::url(UploadedAvatar::url($name), true) . '"', $this->view);
		$this->assertStringContainsString('<meta property="og:image:type" content="image/webp">', $this->view);
		$this->assertStringContainsString('<meta property="og:image:width" content="' . UploadedAvatar::SIZE . '">', $this->view);
	}

	/**
	 * A deleted account asked to be forgotten, so its picture is not shown, the same as its board.
	 */
	public function testADeletedAccountDoesNotShowThePicture(): void
	{
		$this->assertNull(User::avatarOrNull([
			'id' => 5,
			'display_name' => 'gone',
			'deleted' => '2026-10-26 12:00:00',
			'avatar_setting_id' => User::AVATAR_UPLOADED,
			'uploaded_avatar' => 'avatars/5-0123456789abcdef.webp',
		]));
	}

	private function upload(string $path, string $name = 'avatar.png'): void
	{
		$_FILES['avatar'] = [
			'name' => $name,
			'type' => 'image/png',
			'tmp_name' => $path,
			'error' => UPLOAD_ERR_OK,
			'size' => filesize($path),
		];
		$this->post('/users/avatarUpload');
		$_FILES = [];
	}

	/** @return array<string, mixed> */
	private function userRow(int $id): array
	{
		return ClassRegistry::init('User')->findById($id)['User'];
	}

	/** @param array{int, int, int} $colour */
	private function picture(int $width, int $height, array $colour = [200, 120, 40]): string
	{
		$image = imagecreatetruecolor($width, $height);
		imagefill($image, 0, 0, imagecolorallocate($image, ...$colour));
		$path = $this->file('');
		imagepng($image, $path);
		imagedestroy($image);
		return $path;
	}

	private function file(string $content): string
	{
		$path = (string) tempnam(sys_get_temp_dir(), 'avatar');
		file_put_contents($path, $content);
		$this->files[] = $path;
		return $path;
	}
}

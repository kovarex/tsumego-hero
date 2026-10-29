<?php

use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\UploadedAvatar;
use App\Utility\Util;

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

	//An upload is the avatar right away: the player who picks a picture wants to see it.
	public function testAnUploadedPictureBecomesTheAvatar(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');

		$this->upload($this->picture(400, 300));

		$row = $this->userRow($context->user['id']);
		$this->assertSame(UserTable::AVATAR_UPLOADED, (int) $row['avatar_setting_id']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{' . UploadedAvatar::HASH_LENGTH . '}$/', $row['uploaded_avatar_hash']);
		$picture = UploadedAvatar::picture($context->user['id'], $row['uploaded_avatar_hash']);
		$this->assertSame('WEBP', substr((string) $picture, 8, 4));
		$this->assertSame(UploadedAvatar::hash((string) $picture), $row['uploaded_avatar_hash']);
		$this->assertRedirect('/users/settings');
		$this->assertSame('Your picture is your avatar now.', CookieFlash::get());
		$this->assertSame(UploadedAvatar::url($context->user['id'], $row['uploaded_avatar_hash']), UserTable::getAvatarUrl($row));
	}

	//An account keeps one uploaded picture, and a new one gets a new url.
	public function testANewPictureReplacesTheOldOne(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100, [255, 0, 0]));
		$first = $this->userRow($context->user['id'])['uploaded_avatar_hash'];

		$this->upload($this->picture(100, 100, [0, 0, 255]));

		$second = $this->userRow($context->user['id'])['uploaded_avatar_hash'];
		$this->assertNotSame($first, $second);
		$this->assertNull(UploadedAvatar::picture($context->user['id'], $first));
		$this->assertNotNull(UploadedAvatar::picture($context->user['id'], $second));
		$this->assertSame(1, $this->pictureRows($context->user['id']));
	}

	public function testAFileThatIsNotAPictureChangesNothing(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader', 'avatar_setting_id' => UserTable::AVATAR_GRAVATAR]]);
		$this->login('uploader');
		$path = $this->file('<?php echo "hello"; ?>');

		$this->upload($path, 'avatar.png');

		$row = $this->userRow($context->user['id']);
		$this->assertNull($row['uploaded_avatar_hash']);
		$this->assertSame(UserTable::AVATAR_GRAVATAR, (int) $row['avatar_setting_id']);
		$this->assertSame('The file is not a JPEG, PNG, WebP or GIF picture.', CookieFlash::get());
		$this->assertSame(0, $this->pictureRows($context->user['id']));
	}

	public function testUploadRequiresLogin(): void
	{
		$this->upload($this->picture(100, 100));

		$this->assertResponseCode(401);
	}

	//Without the picture the account shows the board again, the avatar it always has.
	public function testRemovingThePictureBringsTheBoardBack(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100));

		$this->post('/users/avatarRemove');

		$row = $this->userRow($context->user['id']);
		$this->assertNull($row['uploaded_avatar_hash']);
		$this->assertSame(UserTable::AVATAR_BOARD, (int) $row['avatar_setting_id']);
		$this->assertSame(0, $this->pictureRows($context->user['id']));
		$this->assertRedirect('/users/settings');
	}

	//A player who went back to the Gravatar and then removes the picture keeps the Gravatar.
	public function testRemovingAPictureThatIsNotShownKeepsTheAvatar(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100));
		$this->post('/users/avatarPreference', ['avatar' => UserTable::AVATAR_GRAVATAR]);

		$this->post('/users/avatarRemove');

		$row = $this->userRow($context->user['id']);
		$this->assertNull($row['uploaded_avatar_hash']);
		$this->assertSame(UserTable::AVATAR_GRAVATAR, (int) $row['avatar_setting_id']);
	}

	//The uploaded picture is one of the avatars to pick from, so a player can go to the
	//board and back without uploading it again.
	public function testThePictureCanBePickedAgainAfterAnotherAvatar(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100));
		$this->post('/users/avatarPreference', ['avatar' => UserTable::AVATAR_BOARD]);

		$this->post('/users/avatarPreference', ['avatar' => UserTable::AVATAR_UPLOADED]);

		$this->assertSame(UserTable::AVATAR_UPLOADED, (int) $this->userRow($context->user['id'])['avatar_setting_id']);
	}

	public function testThePictureCanNotBePickedWithoutOne(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');

		$this->post('/users/avatarPreference', ['avatar' => UserTable::AVATAR_UPLOADED]);

		$this->assertSame(UserTable::AVATAR_BOARD, (int) $this->userRow($context->user['id'])['avatar_setting_id']);
	}

	public function testTheSettingsPageOffersTheUploadAndThePicture(): void
	{
		new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');

		$this->get('/users/settings');
		$this->assertStringContainsString('action="/users/avatarUpload" enctype="multipart/form-data"', $this->view);
		$this->assertStringNotContainsString('name="avatar" value="' . UserTable::AVATAR_UPLOADED . '"', $this->view);
		$this->assertStringNotContainsString('id="avatar-remove"', $this->view);

		$this->upload($this->picture(100, 100));
		$this->get('/users/settings');

		$this->assertStringContainsString('name="avatar" value="' . UserTable::AVATAR_UPLOADED . '" onclick="avatarPreference(this.value);" checked="checked"', $this->view);
		$this->assertStringContainsString('id="avatar-remove"', $this->view);
	}

	//The picture is shown wherever the name of the player is, the same way the other avatars are.
	public function testTheProfileShowsAndSharesThePicture(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100));
		$url = UploadedAvatar::url($context->user['id'], $this->userRow($context->user['id'])['uploaded_avatar_hash']);

		$this->get('/users/view/' . $context->user['id']);

		$this->assertStringContainsString('<img src="' . $url . '"', $this->view);
		$this->assertStringContainsString('og:image" content="' . Router::url($url, true) . '"', $this->view);
		$this->assertStringContainsString('<meta property="og:image:type" content="image/webp">', $this->view);
		$this->assertStringContainsString('<meta property="og:image:width" content="' . UploadedAvatar::SIZE . '">', $this->view);
	}

	//The url carries the hash of the picture, so the answer never changes and the browser
	//keeps it; an img tag asks for it without a session.
	public function testThePictureIsServedFromItsUrl(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100));
		$hash = $this->userRow($context->user['id'])['uploaded_avatar_hash'];
		$this->logout();
		Auth::logout();

		$this->get(UploadedAvatar::url($context->user['id'], $hash));

		$this->assertResponseCode(200);
		$this->assertContentType('image/webp');
		$this->assertSame(UploadedAvatar::picture($context->user['id'], $hash), $this->view);
		$this->assertSame(UploadedAvatar::SIZE, getimagesizefromstring($this->view)[0]);
		$this->assertHeaderContains('Cache-Control', 'max-age=31536000');
		$this->assertHeaderContains('Etag', $hash);
	}

	public function testTheUrlOfAReplacedPictureAnswersNothing(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100, [255, 0, 0]));
		$first = $this->userRow($context->user['id'])['uploaded_avatar_hash'];
		$this->upload($this->picture(100, 100, [0, 0, 255]));

		$this->get(UploadedAvatar::url($context->user['id'], $first));

		$this->assertResponseCode(404);
	}

	//A deleted account asked to be forgotten, so its picture is neither shown nor served.
	public function testADeletedAccountDoesNotShowThePicture(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'uploader']]);
		$this->login('uploader');
		$this->upload($this->picture(100, 100));
		UserTable::deleteAccount($context->user['id']);
		$row = $this->userRow($context->user['id']);

		$this->assertNull(UserTable::avatarOrNull($row));
		$this->get(UploadedAvatar::url($context->user['id'], $row['uploaded_avatar_hash']));
		$this->assertResponseCode(404);
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
		return UserTable::row((int) $id);
	}

	private function pictureRows(int $userID): int
	{
		return (int) Util::query('SELECT COUNT(*) AS count FROM user_avatar WHERE user_id = ?', [$userID])[0]['count'];
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

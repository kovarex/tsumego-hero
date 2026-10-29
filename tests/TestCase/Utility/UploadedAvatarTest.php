<?php

use App\Model\Table\UserTable;
use App\Utility\UploadedAvatar;
use App\Utility\Util;

class UploadedAvatarTest extends CakeTestCase
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

	//Whatever its shape, a picture becomes a square of one size, so every avatar is the
	//same small file and a share preview knows its size.
	public function testAPictureBecomesASquareWebp(): void
	{
		foreach ([[400, 200], [200, 400], [50, 50], [3000, 1000]] as [$width, $height])
		{
			$avatar = UploadedAvatar::process($this->picture('png', $width, $height));
			$size = getimagesizefromstring($avatar);

			$this->assertSame('WEBP', substr($avatar, 8, 4), 'A ' . $width . 'x' . $height . ' picture is not a WebP');
			$this->assertSame(UploadedAvatar::SIZE, $size[0]);
			$this->assertSame(UploadedAvatar::SIZE, $size[1]);
		}
	}

	public function testEveryTypeABrowserShowsIsTaken(): void
	{
		foreach (['png', 'jpeg', 'webp', 'gif'] as $type)
		{
			$avatar = UploadedAvatar::process($this->picture($type, 120, 80));

			$this->assertSame('WEBP', substr($avatar, 8, 4), 'A ' . $type . ' was not made into an avatar');
		}
	}

	//A face is usually in the middle of a photo, so the middle square is kept and the
	//edges of a wide picture are cut off.
	public function testTheMiddleSquareIsKept(): void
	{
		// Red, green and blue thirds of a picture three times as wide as high.
		$image = imagecreatetruecolor(300, 100);
		imagefilledrectangle($image, 0, 0, 99, 99, imagecolorallocate($image, 255, 0, 0));
		imagefilledrectangle($image, 100, 0, 199, 99, imagecolorallocate($image, 0, 255, 0));
		imagefilledrectangle($image, 200, 0, 299, 99, imagecolorallocate($image, 0, 0, 255));

		$avatar = imagecreatefromstring(UploadedAvatar::process($this->save($image, 'png')));

		foreach ([[5, 5], [UploadedAvatar::SIZE - 6, UploadedAvatar::SIZE - 6], [128, 128]] as [$x, $y])
		{
			$colour = imagecolorsforindex($avatar, imagecolorat($avatar, $x, $y));
			$this->assertGreaterThan(200, $colour['green'], 'The avatar at ' . $x . ',' . $y . ' is not the middle of the picture');
			$this->assertLessThan(60, $colour['red']);
			$this->assertLessThan(60, $colour['blue']);
		}
	}

	public function testTransparencyIsKept(): void
	{
		$image = imagecreatetruecolor(100, 100);
		imagealphablending($image, false);
		imagesavealpha($image, true);
		imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

		$avatar = imagecreatefromstring(UploadedAvatar::process($this->save($image, 'png')));

		$this->assertSame(127, imagecolorsforindex($avatar, imagecolorat($avatar, 50, 50))['alpha']);
	}

	//A phone stores its photo as the sensor saw it and writes how to turn it into the EXIF
	//data. That data is not kept, so the picture is turned before it is stored.
	public function testAPhotoIsTurnedTheWayItsCameraSays(): void
	{
		if (!function_exists('exif_read_data'))
			$this->markTestSkipped('The exif extension is not installed');

		// Red left, blue right; orientation 6 says the photo needs a quarter turn clockwise
		// to stand upright, which brings the left side to the top.
		$image = imagecreatetruecolor(100, 100);
		imagefilledrectangle($image, 0, 0, 49, 99, imagecolorallocate($image, 255, 0, 0));
		imagefilledrectangle($image, 50, 0, 99, 99, imagecolorallocate($image, 0, 0, 255));
		$path = $this->save($image, 'jpeg');
		file_put_contents($path, self::withOrientation((string) file_get_contents($path), 6));

		$avatar = imagecreatefromstring(UploadedAvatar::process($path));

		$top = imagecolorsforindex($avatar, imagecolorat($avatar, 128, 20));
		$bottom = imagecolorsforindex($avatar, imagecolorat($avatar, 128, UploadedAvatar::SIZE - 20));
		$this->assertGreaterThan(200, $top['red'], 'The left side of the photo is not at the top');
		$this->assertGreaterThan(200, $bottom['blue'], 'The right side of the photo is not at the bottom');
	}

	public function testWhatTheFileCarriedBesidesThePictureIsNotKept(): void
	{
		$image = imagecreatetruecolor(100, 100);
		$path = $this->save($image, 'jpeg');
		file_put_contents($path, self::withOrientation((string) file_get_contents($path), 1));

		$avatar = UploadedAvatar::process($path);

		$this->assertStringNotContainsString('Exif', $avatar);
	}

	//The type is read from the content, so a script named like a picture is not one.
	public function testAFileThatIsNotAPictureIsRefused(): void
	{
		$path = $this->file('<?php echo "hello"; ?>');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The file is not a JPEG, PNG, WebP or GIF picture.');
		UploadedAvatar::process($path);
	}

	public function testAPictureOfAnotherTypeIsRefused(): void
	{
		$image = imagecreatetruecolor(20, 20);
		$path = $this->file('');
		imagebmp($image, $path);

		$this->expectException(InvalidArgumentException::class);
		UploadedAvatar::process($path);
	}

	//A few bytes can claim a picture that takes gigabytes to unpack, so the size in the
	//header is checked before a pixel is decoded.
	public function testAPictureWithTooManyPixelsIsRefusedBeforeItIsDecoded(): void
	{
		$header = pack('N', 13) . 'IHDR' . pack('NN', 20000, 20000) . "\x08\x02\x00\x00\x00";
		$path = $this->file("\x89PNG\r\n\x1a\n" . $header . pack('N', crc32(substr($header, 4))));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('megapixels');
		UploadedAvatar::process($path);
	}

	public function testABrokenPictureIsRefused(): void
	{
		$picture = (string) file_get_contents($this->picture('png', 100, 100));
		$path = $this->file(substr($picture, 0, 40));

		$this->expectException(InvalidArgumentException::class);
		UploadedAvatar::process($path);
	}

	public function testTheUploadFormNeedsAPicture(): void
	{
		$this->assertUploadRefused(null, 'Pick a picture');
		$this->assertUploadRefused(['error' => UPLOAD_ERR_NO_FILE], 'Pick a picture');
	}

	public function testAFileAboveTheLimitIsRefused(): void
	{
		$path = $this->picture('png', 10, 10);
		$this->assertUploadRefused(['tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => UploadedAvatar::maxBytes() + 1], 'too large, the limit is ' . UploadedAvatar::maxSize());
		$this->assertUploadRefused(['error' => UPLOAD_ERR_INI_SIZE], 'too large');
		$this->assertUploadRefused(['error' => UPLOAD_ERR_FORM_SIZE], 'too large');
	}

	//PHP refuses a file above its own limit before the upload sees it, so that limit is the one the player is told.
	public function testTheLimitIsTheLowerOneOfThisAndOfPhp(): void
	{
		$this->assertLessThanOrEqual(UploadedAvatar::MAX_BYTES, UploadedAvatar::maxBytes());
		$this->assertLessThanOrEqual(ini_parse_quantity((string) ini_get('upload_max_filesize')), UploadedAvatar::maxBytes());
		$this->assertMatchesRegularExpression('/^\d+(\.\d)? MB$/', UploadedAvatar::maxSize());
	}

	public function testAnUploadThatBrokeOffIsRefused(): void
	{
		$this->assertUploadRefused(['error' => UPLOAD_ERR_PARTIAL], 'The upload failed');
	}

	public function testAnUploadedPictureBecomesAnAvatar(): void
	{
		$path = $this->picture('jpeg', 300, 200);

		$avatar = UploadedAvatar::fromUpload(['tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)]);

		$this->assertSame('WEBP', substr($avatar, 8, 4));
	}

	//A picture is bytes and not text, so it has to come back from the database exactly as it went in.
	public function testAStoredPictureComesBackByteForByte(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'pictured']]);
		$avatar = UploadedAvatar::process($this->picture('jpeg', 300, 200));

		$hash = $this->store($context->user['id'], $avatar);

		$this->assertMatchesRegularExpression('/^[0-9a-f]{' . UploadedAvatar::HASH_LENGTH . '}$/', $hash);
		$this->assertSame($avatar, UploadedAvatar::picture($context->user['id'], $hash));
		$this->assertSame('/avatar/' . $context->user['id'] . '/' . $hash . '.webp', UploadedAvatar::url($context->user['id'], $hash));
	}

	//An account has one uploaded picture; the url of the one it replaced answers nothing.
	public function testANewPictureTakesThePlaceOfTheOldOne(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'pictured']]);
		$first = $this->store($context->user['id'], UploadedAvatar::process($this->picture('png', 100, 100)));
		$second = $this->store($context->user['id'], UploadedAvatar::process($this->picture('png', 100, 100, [0, 0, 255])));

		$this->assertNotSame($first, $second);
		$this->assertNull(UploadedAvatar::picture($context->user['id'], $first));
		$this->assertNotNull(UploadedAvatar::picture($context->user['id'], $second));
		$this->assertSame(1, (int) Util::query('SELECT COUNT(*) AS count FROM user_avatar WHERE user_id = ?', [$context->user['id']])[0]['count']);
	}

	public function testAPictureIsOnlyServedForItsOwnAccount(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'pictured'], 'other-users' => [['name' => 'other']]]);
		$hash = $this->store($context->user['id'], UploadedAvatar::process($this->picture('png', 100, 100)));

		$this->assertNull(UploadedAvatar::picture($context->otherUsers[0]['id'], $hash));
	}

	public function testRemoveDeletesThePicture(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'pictured']]);
		$hash = $this->store($context->user['id'], UploadedAvatar::process($this->picture('png', 100, 100)));

		UploadedAvatar::remove($context->user['id']);

		$this->assertNull(UploadedAvatar::picture($context->user['id'], $hash));
		$this->assertSame([], Util::query('SELECT user_id FROM user_avatar WHERE user_id = ?', [$context->user['id']]));
	}

	//A deleted account asked to be forgotten, so its picture is not served even to someone
	//who kept its url. The row stays, so a restored account has it back.
	public function testADeletedAccountServesNoPicture(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'pictured']]);
		$hash = $this->store($context->user['id'], UploadedAvatar::process($this->picture('png', 100, 100)));

		UserTable::deleteAccount($context->user['id']);
		$this->assertNull(UploadedAvatar::picture($context->user['id'], $hash));

		UserTable::restoreAccount($context->user['id']);
		$this->assertNotNull(UploadedAvatar::picture($context->user['id'], $hash));
	}

	//Store a picture the way an upload does: the row and the hash of the account.
	private function store(int $userID, string $avatar): string
	{
		$hash = UploadedAvatar::store($userID, $avatar);
		Util::execute('UPDATE user SET uploaded_avatar_hash = ?, avatar_setting_id = ? WHERE id = ?', [$hash, UserTable::AVATAR_UPLOADED, $userID]);
		return $hash;
	}

	private function assertUploadRefused(?array $file, string $message): void
	{
		try
		{
			UploadedAvatar::fromUpload($file);
			$this->fail('The upload ' . json_encode($file) . ' was taken');
		}
		catch (InvalidArgumentException $e)
		{
			$this->assertStringContainsString($message, $e->getMessage());
		}
	}

	//A JPEG with an EXIF block that holds nothing but the orientation.
	private static function withOrientation(string $jpeg, int $orientation): string
	{
		$tiff = 'MM' . pack('n', 42) . pack('N', 8)
			. pack('n', 1) . pack('nnN', 0x0112, 3, 1) . pack('nn', $orientation, 0)
			. pack('N', 0);
		$exif = "Exif\x00\x00" . $tiff;
		return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2);
	}

	/** @param array{int, int, int} $colour */
	private function picture(string $type, int $width, int $height, array $colour = [200, 120, 40]): string
	{
		$image = imagecreatetruecolor($width, $height);
		imagefill($image, 0, 0, imagecolorallocate($image, ...$colour));
		return $this->save($image, $type);
	}

	private function save(GdImage $image, string $type): string
	{
		$path = $this->file('');
		match ($type)
		{
			'png' => imagepng($image, $path),
			'jpeg' => imagejpeg($image, $path, 95),
			'webp' => imagewebp($image, $path, 90),
			'gif' => imagegif($image, $path),
		};
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

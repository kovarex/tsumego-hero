<?php


use App\Utility\SetImage;

class SetImageTest extends AppTestCase
{
	public function testProcessConvertsPngToWebp(): void
	{
		$source = $this->_createImage('png', 400, 200);

		$result = SetImage::process($source, 'png');

		$this->assertSame('WEBP', substr($result['data'], 8, 4));
		$this->assertSame(400, $result['width']);
		$this->assertSame(200, $result['height']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result['hash']);
		@unlink($source);
	}

	public function testProcessCapsLongestEdgeAt1600(): void
	{
		$source = $this->_createImage('jpeg', 3000, 1000);

		$result = SetImage::process($source, 'jpg');

		$this->assertSame('WEBP', substr($result['data'], 8, 4));
		$this->assertSame(1600, $result['width']);
		$this->assertSame(533, $result['height']);
		@unlink($source);
	}

	public function testProcessConvertsWebpToWebp(): void
	{
		$source = $this->_createImage('webp', 300, 150);

		$result = SetImage::process($source, 'webp');

		$this->assertSame('WEBP', substr($result['data'], 8, 4));
		$this->assertSame(300, $result['width']);
		$this->assertSame(150, $result['height']);
		@unlink($source);
	}

	public function testProcessLeavesSmallImagesUntouched(): void
	{
		$source = $this->_createImage('png', 100, 50);

		$result = SetImage::process($source, 'png');

		$this->assertSame(100, $result['width']);
		$this->assertSame(50, $result['height']);
		@unlink($source);
	}

	public function testProcessRejectsUnsupportedExtension(): void
	{
		$source = $this->_createImage('png', 10, 10);

		$this->expectException(InvalidArgumentException::class);
		SetImage::process($source, 'gif');
		@unlink($source);
	}

	private function _createImage(string $type, int $width, int $height): string
	{
		$img = imagecreatetruecolor($width, $height);
		$color = imagecolorallocate($img, 255, 0, 0);
		imagefill($img, 0, 0, $color);
		$path = tempnam(sys_get_temp_dir(), 'setimg');
		if ($type === 'png')
			imagepng($img, $path);
		elseif ($type === 'webp')
			imagewebp($img, $path, 90);
		else
			imagejpeg($img, $path, 90);
		imagedestroy($img);
		return $path;
	}

	//A cover is stored under the set it belongs to, and the name of the file is a
	//part of the hash of the picture, so two sets never share one picture by name.
	public function testStoreWritesTheCoverUnderItsSet(): void
	{
		$picture = $this->_picture('cover of a set');
		$image = SetImage::store(9001, $picture);

		$this->assertSame('sets/9001/' . substr($picture['hash'], 0, 16) . '.webp', $image);
		$this->assertSame($picture['data'], file_get_contents($this->_pathOf($image)));
		SetImage::removeFolder(9001);
	}

	public function testTheSamePictureKeepsTheSameName(): void
	{
		$picture = $this->_picture('cover of a set');

		$this->assertSame(SetImage::store(9002, $picture), SetImage::store(9002, $picture));
		SetImage::removeFolder(9002);
	}

	//Only covers live under sets/, so a shared image of the site is never taken with one.
	public function testRemoveTakesOnlyAnUploadedCover(): void
	{
		$shared = WWW_ROOT . 'img' . DS . 'shared-test.png';
		file_put_contents($shared, 'a shared image of the site');
		SetImage::remove('shared-test.png');
		$this->assertFileExists($shared);
		@unlink($shared);

		$image = SetImage::store(9003, $this->_picture('cover of a set'));
		SetImage::remove($image);
		$this->assertFileDoesNotExist($this->_pathOf($image));
		SetImage::removeFolder(9003);
	}

	public function testRemoveFolderTakesThePicturesWithIt(): void
	{
		$image = SetImage::store(9004, $this->_picture('cover of a set'));

		SetImage::removeFolder(9004);

		$this->assertFileDoesNotExist($this->_pathOf($image));
		$this->assertDirectoryDoesNotExist(WWW_ROOT . 'img' . DS . 'sets' . DS . '9004');
	}

	/** @return array{data: string, hash: string, width: int, height: int} */
	private function _picture(string $data): array
	{
		return ['data' => $data, 'hash' => hash('sha256', $data), 'width' => 4, 'height' => 4];
	}

	private function _pathOf(string $image): string
	{
		return WWW_ROOT . 'img' . DS . str_replace('/', DS, $image);
	}
}

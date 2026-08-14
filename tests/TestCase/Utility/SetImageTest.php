<?php

App::uses('SetImage', 'Utility');

class SetImageTest extends CakeTestCase
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
}

<?php


use App\Utility\SetImage;
use Cake\ORM\TableRegistry;

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

	//The cover is a row of set_cover, and set.cover_hash is the start of the hash of its picture
	public function testStoreKeepsThePictureWithItsSet(): void
	{
		$setId = $this->_set();
		$picture = $this->_picture('cover of a set', 4, 6);

		$hash = SetImage::store($setId, $picture);

		$this->assertSame(substr($picture['hash'], 0, SetImage::HASH_LENGTH), $hash);
		$this->assertSame($hash, TableRegistry::getTableLocator()->get('Set')->get($setId)->cover_hash);
		$this->assertSame($picture['data'], SetImage::picture($setId, $hash));
		$this->assertSame(['width' => 4, 'height' => 6], SetImage::size($setId));
		$this->assertSame('/set-cover/' . $setId . '/' . $hash . '.webp', SetImage::url($setId, $hash));
	}

	public function testTheSamePictureKeepsTheSameHash(): void
	{
		$setId = $this->_set();
		$picture = $this->_picture('cover of a set', 4, 4);

		$this->assertSame(SetImage::store($setId, $picture), SetImage::store($setId, $picture));
	}

	//A hash that isn't the one of the cover answers nothing, so an old url doesn't show a new cover
	public function testOnlyTheHashOfTheCoverGetsThePicture(): void
	{
		$setId = $this->_set();
		$old = SetImage::store($setId, $this->_picture('old cover', 4, 4));
		$new = SetImage::store($setId, $this->_picture('new cover', 4, 4));

		$this->assertNull(SetImage::picture($setId, $old));
		$this->assertSame('new cover', SetImage::picture($setId, $new));
	}

	public function testRemoveTakesTheCover(): void
	{
		$setId = $this->_set();
		$hash = SetImage::store($setId, $this->_picture('cover of a set', 4, 4));

		SetImage::remove($setId);

		$this->assertNull(SetImage::picture($setId, $hash));
		$this->assertNull(SetImage::size($setId));
		$this->assertNull(TableRegistry::getTableLocator()->get('Set')->get($setId)->cover_hash);
	}

	//The covers of the hero power collections are much taller than wide and sit lower on the page
	public function testATallCoverIsOneMuchTallerThanWide(): void
	{
		$this->assertTrue(SetImage::isTall(['width' => 236, 'height' => 389]));
		$this->assertFalse(SetImage::isTall(['width' => 434, 'height' => 617]));
		$this->assertFalse(SetImage::isTall(null));
	}

	private function _set(): int
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'covered set', 'num' => 1]]]]);
		return (int) $context->tsumegos[0]['sets'][0]['id'];
	}

	/** @return array{data: string, hash: string, width: int, height: int} */
	private function _picture(string $data, int $width, int $height): array
	{
		return ['data' => $data, 'hash' => hash('sha256', $data), 'width' => $width, 'height' => $height];
	}
}

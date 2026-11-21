<?php

use App\Utility\Asset;
use App\Utility\ViteManifest;

class AssetTest extends AppTestCase
{
	private string $image;

	public function setUp(): void
	{
		parent::setUp();
		$this->image = 'img/asset-test-' . getmypid() . '.png';
		Asset::reset();
	}

	public function tearDown(): void
	{
		@unlink(WWW_ROOT . $this->image);
		Asset::reset();
		parent::tearDown();
	}

	//The version of an image is the one the build saw (scripts/image-versions.mjs)
	public function testAnImageGetsTheVersionOfTheBuild(): void
	{
		$version = ViteManifest::imageVersions()['besogo/img/black0.png'] ?? null;
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', (string) $version, 'The build gave the stone its version');
		$this->assertSame('/besogo/img/black0.png?v=' . $version, Asset::url('/besogo/img/black0.png'));
	}

	//An image the build didn't see, as in a checkout never built, gets the time it changed
	public function testTheVersionOfAnImageTheBuildDidNotSeeChangesWithTheImage(): void
	{
		file_put_contents(WWW_ROOT . $this->image, 'first');
		touch(WWW_ROOT . $this->image, 1700000000);
		$first = Asset::url('/' . $this->image);
		$this->assertMatchesRegularExpression('#^/' . preg_quote($this->image, '#') . '\?v=\w+$#', $first);

		file_put_contents(WWW_ROOT . $this->image, 'second');
		touch(WWW_ROOT . $this->image, 1700000060);
		Asset::reset();
		$this->assertNotSame($first, Asset::url('/' . $this->image));
	}

	public function testAMissingImageKeepsItsAddress(): void
	{
		$this->assertSame('/img/no-such-image.png', Asset::url('/img/no-such-image.png'));
		$this->assertSame('', Asset::preload('/img/no-such-image.png'));
	}

	//The texture of the board names its picture relative to the stylesheet, which the page gets inline with the version
	public function testAnInlineStyleGivesItsImagesTheirVersions(): void
	{
		$style = Asset::inlineStyle('/besogo/css/board-texture4.css');
		$this->assertStringStartsWith('<style>', $style);
		$this->assertStringContainsString('url("' . Asset::url('/besogo/img/texture4.jpg') . '")', $style);
		$this->assertStringContainsString('?v=', $style);
	}

	public function testAPreloadAsksForTheVersionedImage(): void
	{
		$this->assertSame('<link rel="preload" as="image" href="' . Asset::url('/besogo/img/black0.png') . '">', Asset::preload('/besogo/img/black0.png'));
	}
}

<?php

use App\Utility\ViteManifest;

class ViteManifestTest extends AppTestCase
{
	//A mistyped entry would leave the page without its script, so it is an error
	public function testAnUnknownEntryIsAnError(): void
	{
		$this->expectException(RuntimeException::class);
		ViteManifest::legacyScript('plya');
	}

	public function testTheTagsPointToTheBuiltFiles(): void
	{
		$this->assertMatchesRegularExpression('#^<script src="/dist/legacy-play-[0-9a-f]+\.js"></script>$#', ViteManifest::legacyScript('play'));
		$this->assertMatchesRegularExpression('#^<script type="module" src="/dist/app-[^"]+\.js"></script>$#', ViteManifest::script('app'));
		$this->assertMatchesRegularExpression('#^<link rel="stylesheet" href="/dist/app-theme-[^"]+\.css" />$#', ViteManifest::css('app-theme'));
	}
}

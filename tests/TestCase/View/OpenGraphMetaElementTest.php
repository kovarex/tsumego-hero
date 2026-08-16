<?php

App::uses('View', 'View');

/**
 * Tests for the generic Open Graph meta element renderer.
 */
class OpenGraphMetaElementTest extends CakeTestCase
{
	public function testRendersAllTagsFromOgArray(): void
	{
		$og = [
			'title' => 'Life & Death - Intermediate #2',
			'description' => '100 go problems',
			'image' => 'https://tsumego.com/img/cover.jpg',
			'image_type' => 'image/jpeg',
			'image_width' => 1200,
			'image_height' => 630,
			'image_alt' => 'Board diagram',
			'url' => 'https://tsumego.com/sets/view/117',
			'type' => 'website',
			'site_name' => 'Tsumego',
			'locale' => 'en_US',
			'profile_username' => 'sorcererontherocks',
			'twitter_card' => 'summary_large_image',
		];

		$view = new View(null);
		$view->element('open_graph_meta', ['og' => $og]);
		$result = $view->fetch('og_meta');

		$this->assertStringContainsString('<meta property="og:title" content="Life &amp; Death - Intermediate #2">', $result);
		$this->assertStringContainsString('<meta property="og:description" content="100 go problems">', $result);
		$this->assertStringContainsString('<meta property="og:image" content="https://tsumego.com/img/cover.jpg">', $result);
		$this->assertStringContainsString('<meta property="og:image:type" content="image/jpeg">', $result);
		$this->assertStringContainsString('<meta property="og:image:width" content="1200">', $result);
		$this->assertStringContainsString('<meta property="og:image:height" content="630">', $result);
		$this->assertStringContainsString('<meta property="og:image:alt" content="Board diagram">', $result);
		$this->assertStringContainsString('<meta property="og:url" content="https://tsumego.com/sets/view/117">', $result);
		$this->assertStringContainsString('<meta property="og:type" content="website">', $result);
		$this->assertStringContainsString('<meta property="og:site_name" content="Tsumego">', $result);
		$this->assertStringContainsString('<meta property="og:locale" content="en_US">', $result);
		$this->assertStringContainsString('<meta property="profile:username" content="sorcererontherocks">', $result);
		$this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $result);
		$this->assertStringContainsString('<meta name="twitter:title" content="Life &amp; Death - Intermediate #2">', $result);
		$this->assertStringContainsString('<meta name="twitter:description" content="100 go problems">', $result);
		$this->assertStringContainsString('<meta name="twitter:image" content="https://tsumego.com/img/cover.jpg">', $result);
	}

	public function testOmitsOptionalTagsWhenNotProvided(): void
	{
		$og = [
			'title' => 'Tsumego',
			'url' => 'https://tsumego.com/',
		];

		$view = new View(null);
		$view->element('open_graph_meta', ['og' => $og]);
		$result = $view->fetch('og_meta');

		$this->assertStringContainsString('<meta property="og:title" content="Tsumego">', $result);
		$this->assertStringContainsString('<meta property="og:url" content="https://tsumego.com/">', $result);
		$this->assertStringContainsString('<meta property="og:site_name" content="Tsumego">', $result);
		$this->assertStringContainsString('<meta name="twitter:card" content="summary">', $result);
		$this->assertStringNotContainsString('og:description', $result);
		$this->assertStringNotContainsString('og:image', $result);
		$this->assertStringNotContainsString('twitter:description', $result);
		$this->assertStringNotContainsString('twitter:image', $result);
		$this->assertStringNotContainsString('profile:username', $result);
	}

	public function testRendersNothingWhenOgMissing(): void
	{
		$view = new View(null);
		$view->element('open_graph_meta', []);
		$this->assertSame('', $view->fetch('og_meta'));
	}
}

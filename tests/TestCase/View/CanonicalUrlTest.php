<?php

/**
 * Every page declares the one address a search engine should keep for it.
 */
class CanonicalUrlTest extends ControllerTestCase
{
	/**
	 * A listing and its first page are one page, however it was asked for
	 */
	public function testFirstPageIsTheListing(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => '1', 'public' => 1]]]
		]);
		$setID = $context->tsumegos[0]['set-connections'][0]['set_id'];
		$userID = $context->user['id'];
		$cases = [
			'/sets/' => '/sets',
			'/sets/view/' . $setID . '/1' => '/sets/' . $setID,
			'/tags/user/' . $userID . '?page=1' => '/tags/user/' . $userID
		];

		foreach ($cases as $url => $listing)
			$this->assertContains(Router::url($listing, true), $this->canonicalAddresses($url), $url);
	}

	/**
	 * Asking a listing for a page does not move it
	 */
	public function testPagingIsNotPartOfTheAddress(): void
	{
		$context = new ContextPreparator();

		foreach (['/tags/user/', '/users/solveHistory/'] as $listing)
		{
			$url = $listing . $context->user['id'];
			foreach (['?page=2', '?activity_page=2'] as $askedFor)
				$this->assertContains(Router::url($url, true), $this->canonicalAddresses($url . $askedFor), $url . $askedFor);
		}
	}

	/**
	 * A page that describes itself in more detail still shares the address
	 * search engines keep
	 */
	public function testOpenGraphUrlIsTheCanonicalAddress(): void
	{
		$context = new ContextPreparator([
			'set' => ['title' => 'test set', 'public' => 1],
			'tsumego' => ['set_order' => 1]
		]);

		foreach (['/sets/view/' . $context->set['id'], '/users/view/' . $context->user['id']] as $url)
			$this->assertSame(
				$this->canonicalAddresses($url)[0],
				$this->metaContent('og:url', $this->page($url)),
				$url);
	}

	/**
	 * Every page the site publishes names one address, spelled out in full, in
	 * the link and in the shared metadata alike
	 */
	public function testEveryPublishedPageNamesTheSameAddress(): void
	{
		$context = new ContextPreparator([
			'set' => ['title' => 'test set', 'public' => 1],
			'tsumego' => ['set_order' => 1],
			'tags' => [['name' => 'Test Tag']]
		]);

		Cache::delete('sitemap_xml', 'long');
		$sitemap = (string) $this->testAction('/sitemap.xml', ['method' => 'get', 'return' => 'contents']);
		preg_match_all('~<loc>([^<]+)</loc>~', $sitemap, $matches);
		$this->assertNotEmpty($matches[1]);

		foreach ($matches[1] as $url)
		{
			$path = (string) parse_url($url, PHP_URL_PATH);
			$html = $this->page($path);
			$addresses = $this->canonicalAddressesOf($html);
			$this->assertCount(1, $addresses, $path);
			$this->assertStringStartsWith(Router::url('/', true), $addresses[0], $path);
			$this->assertSame($addresses[0], $this->metaContent('og:url', $html), $path);
		}
	}

	private function page(string $url): string
	{
		return (string) $this->testAction($url, ['method' => 'get', 'return' => 'contents']);
	}

	private function metaContent(string $property, string $html): string
	{
		preg_match('~<meta property="' . $property . '" content="([^"]*)">~', $html, $matches);
		return $matches[1] ?? '';
	}

	private function canonicalAddresses(string $url): array
	{
		return $this->canonicalAddressesOf($this->page($url));
	}

	private function canonicalAddressesOf(string $html): array
	{
		preg_match_all('~<link rel="canonical" href="([^"]*)">~', $html, $matches);
		return $matches[1];
	}
}

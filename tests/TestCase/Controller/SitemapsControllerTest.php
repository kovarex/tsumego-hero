<?php

/**
 * SitemapsController Test
 *
 * Tests XML sitemap generation
 */
class SitemapsControllerTest extends ControllerTestCase
{
	/**
	 * Test sitemap includes public sets
	 */
	public function testSitemapIncludesPublicSets()
	{
		// Create test context with a public set
		$context = new ContextPreparator([
			'set' => ['public' => 1],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]SZ[19];B[aa];W[ab])']
		]);

		// Clear sitemap cache
		Cache::delete('sitemap_xml', 'long');

		// Request sitemap
		$result = $this->testAction('/sitemap.xml', [
			'method' => 'get',
			'return' => 'contents'
		]);

		// Verify the set is in the sitemap
		$this->assertStringContainsString('/sets/view/' . $context->set['id'], $result);

		// Verify it has proper sitemap elements
		$this->assertStringContainsString('<changefreq>weekly</changefreq>', $result);
		$this->assertStringContainsString('<priority>0.8</priority>', $result);
	}

	/**
	 * Test sitemap includes tags
	 */
	public function testSitemapIncludesTags()
	{
		// Create test context with a tag
		$context = new ContextPreparator([
			'tags' => [['name' => 'Test Tag']],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]SZ[19];B[aa];W[ab])']
		]);

		// Request sitemap
		$result = $this->testAction('/sitemap.xml', [
			'method' => 'get',
			'return' => 'contents'
		]);

		// Verify the tag is in the sitemap
		$this->assertStringContainsString('/sets/tag/Test%20Tag', $result);
		$this->assertStringContainsString('<priority>0.7</priority>', $result);
	}

	/**
	 * Test sitemap includes static pages
	 */
	public function testSitemapIncludesStaticPages()
	{
		// Request sitemap
		$result = $this->testAction('/sitemap.xml', [
			'method' => 'get',
			'return' => 'contents'
		]);

		// Verify static pages are included
		$this->assertStringContainsString('/hero', $result);
		$this->assertStringContainsString('/time_mode/play', $result);
		$this->assertStringContainsString('<changefreq>monthly</changefreq>', $result);
		$this->assertStringContainsString('<priority>0.6</priority>', $result);
	}

	/**
	 * Tear down
	 */
	public function tearDown(): void
	{
		parent::tearDown();
		// Clear cache after tests
		Cache::delete('sitemap_xml', 'long');
	}
}

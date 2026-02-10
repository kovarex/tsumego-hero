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
		$context = new ContextPreparator([
			'set' => ['public' => 1],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]SZ[19];B[aa];W[ab])']
		]);

		Cache::delete('sitemap_xml', 'long');

		$result = $this->testAction('/sitemap.xml', [
			'method' => 'get',
			'return' => 'contents'
		]);

		$this->assertStringContainsString('/sets/view/' . $context->set['id'], $result);
		$this->assertStringContainsString('<urlset', $result);
		$this->assertStringContainsString('<loc>', $result);
	}

	/**
	 * Test sitemap includes tags
	 */
	public function testSitemapIncludesTags()
	{
		$context = new ContextPreparator([
			'tags' => [['name' => 'Test Tag']],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]SZ[19];B[aa];W[ab])']
		]);

		Cache::delete('sitemap_xml', 'long');

		$result = $this->testAction('/sitemap.xml', [
			'method' => 'get',
			'return' => 'contents'
		]);

		$this->assertStringContainsString('/sets/tag/Test%20Tag', $result);
	}

	/**
	 * Test sitemap includes static pages
	 */
	public function testSitemapIncludesStaticPages()
	{
		Cache::delete('sitemap_xml', 'long');

		$result = $this->testAction('/sitemap.xml', [
			'method' => 'get',
			'return' => 'contents'
		]);

		$this->assertStringContainsString('/hero', $result);
		$this->assertStringContainsString('/time_mode/play', $result);
	}

	/**
	 * Test sitemap includes individual puzzles
	 */
	public function testSitemapIncludesIndividualPuzzles()
	{
		$context = new ContextPreparator([
			'set' => ['public' => 1],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]SZ[19];B[aa];W[ab])']
		]);

		Cache::delete('sitemap_xml', 'long');

		$result = $this->testAction('/sitemap.xml', [
			'method' => 'get',
			'return' => 'contents'
		]);

		// Verify individual puzzle URL is in sitemap (uses set_connection ID)
		$this->assertStringContainsString('/' . $context->setConnections[0]['id'] . '</loc>', $result);
	}

	public function tearDown(): void
	{
		parent::tearDown();
		Cache::delete('sitemap_xml', 'long');
	}
}

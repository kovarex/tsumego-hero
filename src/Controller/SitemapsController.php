<?php

/**
 * Sitemaps Controller
 *
 * Generates XML sitemap for search engines
 *
 * @link https://www.sitemaps.org/protocol.html
 */
class SitemapsController extends AppController
{
	/**
	 * Models
	 */
	public $uses = ['Set', 'Tag'];

	/**
	 * Generate XML sitemap
	 *
	 * Outputs sitemap.xml with all public URLs
	 * Caches result for 1 hour to improve performance
	 *
	 * @return void
	 */
	public function index()
	{
		// Check cache first
		$cacheKey = 'sitemap_xml';
		$urls = Cache::read($cacheKey, 'long');

		if ($urls === false)
		{
			// Cache miss - generate sitemap
			$urls = $this->_generateUrls();

			// Cache for 1 hour
			Cache::write($cacheKey, $urls, 'long');
		}

		// Set view vars
		$this->set('urls', $urls);

		// Configure response
		$this->response->type('xml');
		$this->layout = 'xml/default';
	}

	/**
	 * Generate all URLs for sitemap
	 *
	 * @return array
	 */
	private function _generateUrls()
	{
		$urls = [];

		// Homepage (highest priority)
		$urls[] = [
			'loc' => Router::url('/', true),
			'priority' => '1.0',
			'changefreq' => 'daily'
		];

		// All public puzzle sets (collections)
		$sets = $this->Set->find('all', [
			'conditions' => ['Set.public' => 1],
			'fields' => ['Set.id', 'Set.title', 'Set.created'],
			'order' => ['Set.id' => 'ASC']
		]);

		foreach ($sets as $set)
		{
			$urls[] = [
				'loc' => Router::url(['controller' => 'sets', 'action' => 'view', $set['Set']['id']], true),
				'lastmod' => date('Y-m-d', strtotime($set['Set']['created'])),
				'priority' => '0.8',
				'changefreq' => 'weekly'
			];
		}

		// All tags
		$tags = $this->Tag->find('all', [
			'fields' => ['Tag.name'],
			'order' => ['Tag.name' => 'ASC']
		]);

		foreach ($tags as $tag)
		{
			$urls[] = [
				'loc' => Router::url(['controller' => 'sets', 'action' => 'tag', $tag['Tag']['name']], true),
				'priority' => '0.7',
				'changefreq' => 'weekly'
			];
		}

		// Static pages
		$staticPages = [
			['controller' => 'hero', 'action' => 'index', 'priority' => '0.6'],
			['controller' => 'time_mode', 'action' => 'play', 'priority' => '0.6']
		];

		foreach ($staticPages as $page)
		{
			$urls[] = [
				'loc' => Router::url(['controller' => $page['controller'], 'action' => $page['action']], true),
				'priority' => $page['priority'],
				'changefreq' => 'monthly'
			];
		}

		return $urls;
	}
}

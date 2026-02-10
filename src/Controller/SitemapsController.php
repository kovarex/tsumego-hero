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
	 * Outputs sitemap.xml with all public URLs.
	 * Caches rendered XML for 1 day. Webserver handles gzip compression.
	 *
	 * @return void
	 */
	public function index()
	{
		$cacheKey = 'sitemap_xml';
		$xml = Cache::read($cacheKey, 'long');

		if ($xml === false)
		{
			$urls = $this->_generateUrls();
			$xml = $this->_renderXml($urls);
			Cache::write($cacheKey, $xml, 'long');
		}

		$this->response->type('xml');
		$this->response->body($xml);
		$this->autoRender = false;
	}

	/**
	 * Generate all URLs for sitemap
	 *
	 * Note: Google ignores <priority> and <changefreq>, so we only use <loc>.
	 * See: https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
	 *
	 * @return string[]
	 */
	private function _generateUrls()
	{
		$urls = [];

		// Homepage
		$urls[] = Router::url('/', true);

		// All public puzzle sets
		$sets = $this->Set->find('all', [
			'conditions' => ['Set.public' => 1],
			'fields' => ['Set.id'],
			'order' => ['Set.id' => 'ASC']
		]);

		foreach ($sets as $set)
			$urls[] = Router::url(['controller' => 'sets', 'action' => 'view', $set['Set']['id']], true);

		// All tags
		$tags = $this->Tag->find('all', [
			'fields' => ['Tag.name'],
			'order' => ['Tag.name' => 'ASC']
		]);

		foreach ($tags as $tag)
			$urls[] = Router::url(['controller' => 'sets', 'action' => 'tag', $tag['Tag']['name']], true);

		// Static pages
		$staticPages = [
			['controller' => 'hero', 'action' => 'index'],
			['controller' => 'time_mode', 'action' => 'play'],
		];

		foreach ($staticPages as $page)
			$urls[] = Router::url(['controller' => $page['controller'], 'action' => $page['action']], true);

		// All individual puzzles in public sets
		$baseUrl = Router::url('/', true);
		$puzzleIds = Util::query("
SELECT set_connection.id
FROM set_connection
	JOIN `set` ON set_connection.set_id = `set`.id
WHERE `set`.public = 1
ORDER BY set_connection.id ASC");

		foreach ($puzzleIds as $row)
			$urls[] = $baseUrl . $row['id'];

		return $urls;
	}

	/**
	 * Render URL list as XML sitemap string
	 *
	 * @param string[] $urls
	 * @return string
	 */
	private function _renderXml(array $urls): string
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ($urls as $url)
			$xml .= "\t<url><loc>" . htmlspecialchars($url, ENT_XML1, 'UTF-8') . "</loc></url>\n";

		$xml .= '</urlset>' . "\n";

		return $xml;
	}
}

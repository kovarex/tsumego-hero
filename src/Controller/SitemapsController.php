<?php

/**
 * Sitemaps Controller
 *
 * Generates XML sitemap for search engines
 *
 * @link https://www.sitemaps.org/protocol.html
 */
use App\Utility\Constants;
use App\Utility\Util;

class SitemapsController extends AppController
{
	/**
	 * Models
	 */
	public $uses = ['Achievement', 'Set', 'Tag'];

	/**
	 * Pages a visitor can browse without signing in. Each one is resolved
	 * through the router before it is listed.
	 */
	private const PUBLIC_PAGES = [
		['controller' => 'achievements', 'action' => 'index'],
		['controller' => 'changelog', 'action' => 'index'],
		['controller' => 'sets', 'action' => 'index'],
		['controller' => 'sites', 'action' => 'about'],
		['controller' => 'sites', 'action' => 'gotutorial'],
		['controller' => 'sites', 'action' => 'websitefunctions'],
		['controller' => 'users', 'action' => 'achievements'],
		['controller' => 'users', 'action' => 'added_tags'],
		['controller' => 'users', 'action' => 'highscore'],
		['controller' => 'users', 'action' => 'leaderboard'],
		['controller' => 'users', 'action' => 'rating'],
		['controller' => 'users', 'action' => 'time_mode'],
	];

	/**
	 * Generate XML sitemap
	 *
	 * Outputs sitemap.xml with all public URLs.
	 * Caches rendered XML for 1 day. Webserver handles gzip compression.
	 *
	 * @return void
	 */
	public function index(): void
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
	private function _generateUrls(): array
	{
		$urls = [];

		// Homepage
		$urls[] = Router::url('/', true);

		// All public puzzle sets, one URL per page of the listing
		$setProblemCounts = [];
		foreach (Util::query("
SELECT set_id, COUNT(*) AS problems
FROM set_connection
	JOIN `set` ON set_connection.set_id = `set`.id
WHERE " . Set::officialCondition() . "
GROUP BY set_id") as $row)
			$setProblemCounts[$row['set_id']] = (int) $row['problems'];

		$sets = $this->Set->find('all', [
			'conditions' => [Set::officialCondition('Set')],
			'fields' => ['Set.id'],
			'order' => ['Set.id' => 'ASC']
		]);

		foreach ($sets as $set)
		{
			$setID = $set['Set']['id'];
			$urls[] = Set::url($setID, 1, true);

			// A visitor sees the default number of problems per page.
			$pages = (int) ceil(($setProblemCounts[$setID] ?? 0) / Constants::$DEFAULT_COLLECTION_SIZE);
			for ($page = 2; $page <= $pages; $page++)
				$urls[] = Set::url($setID, $page, true);
		}

		// All achievements, each with its own page
		$achievements = $this->Achievement->find('all', [
			'fields' => ['Achievement.id'],
			'order' => ['Achievement.order' => 'ASC']
		]);

		foreach ($achievements as $achievement)
			$urls[] = self::_urlOf(['controller' => 'achievements', 'action' => 'view', $achievement['Achievement']['id']]);

		// All approved tags, on the page that serves tags
		$tags = $this->Tag->find('all', [
			'conditions' => ['Tag.approved' => 1],
			'fields' => ['Tag.id'],
			'order' => ['Tag.name' => 'ASC']
		]);

		foreach ($tags as $tag)
			$urls[] = self::_urlOf(['controller' => 'tags', 'action' => 'view', $tag['Tag']['id']]);

		foreach (self::PUBLIC_PAGES as $page)
			$urls[] = self::_urlOf($page);

		// All individual puzzles in public sets
		$baseUrl = Router::url('/', true);
		$puzzleIds = Util::query("
SELECT set_connection.id
FROM set_connection
	JOIN `set` ON set_connection.set_id = `set`.id
WHERE " . Set::officialCondition() . "
ORDER BY set_connection.id ASC");

		foreach ($puzzleIds as $row)
			$urls[] = $baseUrl . $row['id'];

		return array_values(array_filter($urls));
	}

	/**
	 * URL for a route, or null when no controller serves it, so the sitemap
	 * only ever lists pages that answer.
	 *
	 * @param array<int|string, mixed> $route
	 */
	public static function _urlOf(array $route): ?string
	{
		$controller = (string) $route['controller'];
		$action = (string) $route['action'];
		if (!self::_controllerServesAction($controller, $action))
		{
			CakeLog::warning('Sitemap: ' . $controller . '::' . $action . '() does not exist, leaving it out');
			return null;
		}
		return Router::url($route, true);
	}

	/**
	 * Whether an action a URL would point at can actually be requested. The
	 * leading underscore keeps these helpers out of the router
	 * (Controller::_isPrivateAction).
	 */
	public static function _controllerServesAction(string $controller, string $action): bool
	{
		$class = Inflector::camelize($controller) . 'Controller';
		App::uses($class, 'Controller');
		return class_exists($class) && method_exists($class, $action);
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

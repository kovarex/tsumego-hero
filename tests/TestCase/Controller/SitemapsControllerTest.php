<?php

use App\Utility\Constants;

//Tests XML sitemap generation
class SitemapsControllerTest extends IntegrationTestCase
{
	public function testSitemapIncludesPublicSets()
	{
		$context = new ContextPreparator([
			'set' => ['public' => 1],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]SZ[19];B[aa];W[ab])']
		]);

		Cache::delete('sitemap_xml', 'long');

		$this->get('/sitemap.xml');
		$result = $this->view;

		$this->assertStringContainsString('/sets/' . $context->set['id'] . '</loc>', $result);
		$this->assertStringNotContainsString('/sets/view/', $result);
		$this->assertStringContainsString('<urlset', $result);
		$this->assertStringContainsString('<loc>', $result);
	}

	//A tag in the sitemap has to point at the page that serves tags
	public function testSitemapTagUrlsOpenTheTagPage()
	{
		$context = new ContextPreparator([
			'tags' => [['name' => 'Test Tag']],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]SZ[19];B[aa];W[ab])']
		]);

		Cache::delete('sitemap_xml', 'long');

		$this->get('/sitemap.xml');
		$result = $this->view;

		$this->assertStringContainsString('/tags/view/' . $context->tags[0]['id'], $result);
		$this->assertStringNotContainsString('/sets/tag/', $result);
	}

	//Only approved tags are published
	public function testSitemapListsOnlyApprovedTags()
	{
		$context = new ContextPreparator([
			'tags' => [
				['name' => 'Approved Tag'],
				['name' => 'Pending Tag', 'approved' => 0]
			]
		]);

		Cache::delete('sitemap_xml', 'long');

		$this->get('/sitemap.xml');
		$result = $this->view;

		$approved = self::tagIdByName('Approved Tag');
		$pending = self::tagIdByName('Pending Tag');

		$this->assertStringContainsString('/tags/view/' . $approved, $result);
		$this->assertStringNotContainsString('/tags/view/' . $pending, $result);
	}

	//The home page is listed
	public function testSitemapIncludesTheHomePage()
	{
		Cache::delete('sitemap_xml', 'long');

		$this->get('/sitemap.xml');
		$result = $this->view;

		$this->assertStringContainsString('<loc>' . self::baseUrl() . '/</loc>', $result);
	}

	//Every page the site offers to a visitor is in the sitemap
	public function testSitemapCoversThePagesTheSiteLinksTo()
	{
		Cache::delete('sitemap_xml', 'long');

		$this->get('/');
		$home = $this->view;
		$this->get('/sitemap.xml');
		$sitemap = $this->view;

		// Linked by the site but kept out of the sitemap: they need a sign-in,
		// belong to one player, or are a tool rather than content.
		$staysOut = [
			'/challenges',
			'/classes',
			'/forums',
			'/messages',
			'/mistake-training',
			'/sites/impressum',
			'/tsumegos/duplicatesearch/',
			'/tsumegos/play/',
			'/users/login',
			'/users/settings',
			'/users/view/'
		];

		$pages = [];
		preg_match_all('~href="(/[^"#?]*)"~', (string) $home, $matches);
		foreach (array_unique($matches[1]) as $link)
		{
			if ($link === '/' || str_contains(basename($link), '.'))
				continue;
			if (preg_match('#^/(\d+|sets/[^/]+|tags/view/\d+)$#', $link))
				continue;
			foreach ($staysOut as $prefix)
				if (str_starts_with($link, $prefix))
					continue 2;
			$pages[] = $link;
		}

		$this->assertNotEmpty($pages);
		foreach ($pages as $page)
			$this->assertStringContainsString('<loc>' . self::baseUrl() . $page . '</loc>', $sitemap);
	}

	//Every page in the sitemap opens without signing in
	public function testEveryPageInTheSitemapOpensForAVisitor()
	{
		Cache::delete('sitemap_xml', 'long');

		$this->get('/sitemap.xml');
		$result = $this->view;

		preg_match_all('#<loc>([^<]+)</loc>#', (string) $result, $matches);
		$this->assertNotEmpty($matches[1]);

		foreach ($matches[1] as $url)
		{
			$this->get((string) parse_url($url, PHP_URL_PATH));
			$this->assertResponseCode(200, $url);
		}
	}

	//Every listed URL is served by a controller and an action
	public function testSitemapListsOnlyUrlsThatExist()
	{
		$context = new ContextPreparator([
			'set' => ['public' => 1],
			'tags' => [['name' => 'Test Tag']],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]SZ[19];B[aa];W[ab])']
		]);

		Cache::delete('sitemap_xml', 'long');

		$this->get('/sitemap.xml');
		$result = $this->view;

		preg_match_all('#<loc>([^<]+)</loc>#', $result, $matches);
		$this->assertNotEmpty($matches[1]);

		foreach ($matches[1] as $url)
		{
			$route = Router::parse((string) parse_url($url, PHP_URL_PATH));
			$this->assertTrue(
				self::controllerServesAction($route['controller'], $route['action']),
				$url . ' has no ' . $route['controller'] . '::' . $route['action'] . '() to serve it'
			);
		}
	}

	//A route becomes a URL only when an action serves it
	public function testSitemapPublishesOnlyUrlsWithAnAction()
	{
		$this->assertNull(SitemapsController::_urlOf(['controller' => 'sets', 'action' => 'tag', 'Ladder']));
		$this->assertNull(SitemapsController::_urlOf(['controller' => 'hero', 'action' => 'index']));
		$this->assertSame(
			'/tags/view/1',
			parse_url((string) SitemapsController::_urlOf(['controller' => 'tags', 'action' => 'view', 1]), PHP_URL_PATH)
		);
	}

	private static function controllerServesAction(string $controller, string $action): bool
	{
		$class = Inflector::camelize($controller) . 'Controller';
		App::uses($class, 'Controller');
		return class_exists($class) && method_exists($class, $action);
	}

	private static function tagIdByName(string $name): int
	{
		return (int) ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $name]])['Tag']['id'];
	}

	private static function baseUrl(): string
	{
		return rtrim(Router::url('/', true), '/');
	}

	public function testSitemapIncludesIndividualPuzzles()
	{
		$context = new ContextPreparator([
			'set' => ['public' => 1],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]CA[UTF-8]SZ[19];B[aa];W[ab])']
		]);

		Cache::delete('sitemap_xml', 'long');

		$this->get('/sitemap.xml');
		$result = $this->view;

		// Verify individual puzzle URL is in sitemap (uses set_connection ID)
		$this->assertStringContainsString('/' . $context->setConnections[0]['id'] . '</loc>', $result);
	}

	//A set longer than one page of problems is listed page by page
	public function testSitemapIncludesLaterPagesOfALongSet()
	{
		$tsumegos = [];
		for ($i = 1; $i <= Constants::$DEFAULT_COLLECTION_SIZE + 1; $i++)
			$tsumegos[] = ['sets' => [['name' => 'long set', 'num' => (string) $i, 'public' => 1]]];

		$context = new ContextPreparator(['tsumegos' => $tsumegos]);
		$setID = $context->tsumegos[0]['set-connections'][0]['set_id'];

		Cache::delete('sitemap_xml', 'long');

		$this->get('/sitemap.xml');
		$result = $this->view;

		$this->assertStringContainsString('<loc>' . self::baseUrl() . '/sets/' . $setID . '</loc>', $result);
		$this->assertStringContainsString('<loc>' . self::baseUrl() . '/sets/' . $setID . '/2</loc>', $result);
		$this->assertStringNotContainsString('/sets/' . $setID . '/1</loc>', $result);
		$this->assertStringNotContainsString('/sets/' . $setID . '/3</loc>', $result);
	}

	//Every achievement has its own page, and the sitemap lists them all
	public function testSitemapIncludesEveryAchievementPage()
	{
		Cache::delete('sitemap_xml', 'long');

		$this->get('/achievements');
		$index = $this->view;
		$this->get('/sitemap.xml');
		$sitemap = $this->view;

		preg_match_all('~href="(/achievements/view/\d+)"~', $index, $matches);
		$this->assertNotEmpty($matches[1]);

		foreach (array_unique($matches[1]) as $page)
			$this->assertStringContainsString('<loc>' . self::baseUrl() . $page . '</loc>', $sitemap);
	}

	public function tearDown(): void
	{
		parent::tearDown();
		Cache::delete('sitemap_xml', 'long');
	}
}

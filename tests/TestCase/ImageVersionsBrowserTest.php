<?php

use App\Utility\Constants;
use App\Utility\HeroPowers;

//Every image of the site that the main pages show is asked for with the version of its content (Asset::url,
//imageUrl() of the scripts, the CSS of the bundles), so a browser keeps it for a year (webroot/.htaccess) and
//a changed image has a new address. An image added without one would be kept a day only, and this test
//names it.
#[RetryOnBrowserFailure]
class ImageVersionsBrowserTest extends IntegrationTestCase
{
	//The images of this site the page asked for or shows: what the browser loaded, the <img> and the SVG
	//<image> of the page, the icons and preloads of its head, and the backgrounds of its elements
	private const string IMAGES = <<<'JS'
		const urls = performance.getEntriesByType('resource').map(entry => entry.name);
		document.querySelectorAll('img[src]').forEach(image => urls.push(image.src));
		document.querySelectorAll('image').forEach(image => urls.push(image.href.baseVal));
		document.querySelectorAll('link[href]').forEach(link => urls.push(link.href));
		document.querySelectorAll('*').forEach(element => {
			for (const match of getComputedStyle(element).backgroundImage.matchAll(/url\("?([^")]+)"?\)/g))
				urls.push(match[1]);
		});
		return [...new Set(urls.map(url => new URL(url, location.href)))]
			.filter(url => url.origin === location.origin && /\.(png|jpe?g|gif|svg|webp|ico)$/i.test(url.pathname))
			.map(url => url.pathname + url.search);
		JS;

	public function testEveryImageOfTheMainPagesCarriesItsVersion(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'mode' => Constants::$LEVEL_MODE,
				'level' => HeroPowers::$REFINEMENT_MINIMUM_LEVEL,
				'premium' => 1,
				'achievement-statuses' => [['id' => 1], ['id' => 2]]],
			'tsumegos' => [['set_order' => 1, 'sets' => [['name' => 'Basics', 'num' => 1]]]],
			'time-mode-ranks' => ['5k']]);
		$setID = $context->tsumegos[0]['set-connections'][0]['set_id'];
		$this->fetchTable('Set')->updateAll(['image' => 'b1.png'], ['id' => $setID]);

		$browser = Browser::instance();
		$pages = [
			'home' => '/',
			'play' => '/' . $context->tsumegos[0]['set-connections'][0]['id'],
			'collections' => '/sets',
			'collection' => '/sets/view/' . $setID,
			'profile' => '/users/view/' . $context->user['id'],
			'time mode' => '/timeMode/overview',
			'achievements' => '/achievements',
			'achievement' => '/achievements/view/1',
			'hero powers' => '/sites/websitefunctions'];
		$imagesOfPages = [];
		foreach ($pages as $name => $url)
		{
			$browser->get($url);
			$imagesOfPages[$name] = $browser->driver->executeScript(self::IMAGES);
		}
		//The images that the scripts and the stylesheets ask for are among those checked
		$play = implode(' ', $imagesOfPages['play']);
		$this->assertMatchesRegularExpression('#/besogo/img/(black|white)\w*\.png#', $play, 'The stones of the board');
		$this->assertMatchesRegularExpression('#/besogo/img/texture\w*\.jpg#', $play, 'The texture of the board');
		$this->assertMatchesRegularExpression('#/img/hp\d\w*\.png#', $play, 'The hero powers');
		$this->assertMatchesRegularExpression('#/img/mode-select-bg-left\.png#', implode(' ', $imagesOfPages['home']), 'A background of the CSS bundle');
		$this->assertMatchesRegularExpression('#/pwa/icon-192\.png#', implode(' ', $imagesOfPages['home']), 'The icon of the head');

		$unversioned = [];
		foreach ($imagesOfPages as $name => $images)
			foreach ($images as $image)
				if (!self::isVersioned($image))
					$unversioned[] = $name . ': ' . $image;
		$this->assertSame([], $unversioned, 'Images asked for without the version of their content');
	}

	//The version after the address, or an address that changes with the content already: a cover of a
	//collection (SetImage) or an avatar (AvatarsController)
	private static function isVersioned(string $image): bool
	{
		return preg_match('#[?&]v=\w+#', $image) === 1
			|| preg_match('#^/(img/sets/|avatar/|board/)#', $image) === 1;
	}
}

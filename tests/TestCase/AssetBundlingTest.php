<?php

use Facebook\WebDriver\WebDriverBy;

/**
 * AssetBundlingTest
 *
 * Verifies that CSS and JS assets are properly bundled via Vite,
 * that styles are applied, and that JavaScript functions are loaded and working.
 *
 * @group disabled
 *
 * This test is excluded from local runs (see phpunit.xml.dist).
 * It only runs in CI where assets are pre-built via .github/workflows/ci.yml
 */
class AssetBundlingTest extends CakeTestCase
{
	/**
	 * Test that CSS bundles are loaded and individual files are NOT loaded
	 */
	public function testCssBundlesAreLoadedAndStylesApplied()
	{
		$browser = Browser::instance();
		$browser->get('sites/blank');

		// Verify Vite-built CSS bundles are loaded (not individual files)
		$html = $browser->driver->getPageSource();
		$this->assertMatchesRegularExpression('#/dist/app-theme-[a-z0-9]+\.css#', $html, 'app-theme CSS bundle should be loaded');
		$this->assertStringNotContainsString('"css/default.css', $html, 'Individual default.css should NOT be loaded');
		$this->assertStringNotContainsString('"css/home-themes.css', $html, 'Individual home-themes.css should NOT be loaded');

		// Verify theme-specific CSS is loaded
		$hasTheme = preg_match('#/dist/(dark|light)-theme-[a-z0-9]+\.css#', $html);
		$this->assertTrue((bool) $hasTheme, 'Theme CSS bundle (dark or light) should be loaded');
	}

	/**
	 * Test that JS bundle is loaded and individual files are NOT loaded
	 */
	public function testJsBundleIsLoadedAndFunctional()
	{
		$browser = Browser::instance();
		$browser->get('sites/blank');

		// Verify Vite-built legacy JS bundle is loaded (not individual files)
		$html = $browser->driver->getPageSource();
		$this->assertMatchesRegularExpression('#/dist/legacy-app-[a-z0-9]+\.js#', $html, 'legacy-app JS bundle should be loaded');
		$this->assertStringNotContainsString('"/js/util.js', $html, 'Individual util.js should NOT be loaded');
		$this->assertStringNotContainsString('"/js/dark.js', $html, 'Individual dark.js should NOT be loaded');

		// Wait for JS bundles to load and execute
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return typeof setCookie === "function" && typeof darkAndLight === "function";');
		});

		// Verify JavaScript functions from bundled files are available
		$hasUtil = $browser->driver->executeScript('return typeof setCookie === "function";');
		$this->assertTrue($hasUtil, 'setCookie function from util.js should be available in bundle');

		$hasDarkToggle = $browser->driver->executeScript('return typeof darkAndLight === "function";');
		$this->assertTrue($hasDarkToggle, 'darkAndLight function from dark.js should be available in bundle');
	}

	/**
	 * Test that play page loads bundles (not individual files)
	 */
	public function testPlayPageBundlesWork()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => ['rating' => 1000]]);

		$setConnectionId = $context->tsumegos[0]['set-connections'][0]['id'];
		$browser->get('/' . $setConnectionId);  // CakePHP routing uses /{setConnectionId} for puzzles

		// Verify JS bundle is loaded, not individual files
		$html = $browser->driver->getPageSource();
		$this->assertMatchesRegularExpression('#/dist/legacy-app-[a-z0-9]+\.js#', $html, 'legacy-app JS bundle should be loaded on play page');
		$this->assertStringNotContainsString('"/js/TagConnectionsEdit.js', $html, 'Individual TagConnectionsEdit.js should NOT be loaded');
		$this->assertStringNotContainsString('"/js/multipleChoice.js', $html, 'Individual multipleChoice.js should NOT be loaded');
		$this->assertStringNotContainsString('"/FileSaver.min.js', $html, 'Individual FileSaver.min.js should NOT be loaded');

		// Wait for JS bundles to load and execute
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return $driver->executeScript('return typeof makeAjaxCall === "function" && typeof displayMultipleChoiceResult === "function" && typeof createPreviewBoard === "function";');
		});

		// Verify functions from bundled files are available
		$hasTagFunctions = $browser->driver->executeScript('return typeof makeAjaxCall === "function";');
		$this->assertTrue($hasTagFunctions, 'makeAjaxCall function from TagConnectionsEdit.js should be available in bundle');

		$hasMcFunctions = $browser->driver->executeScript('return typeof displayMultipleChoiceResult === "function";');
		$this->assertTrue($hasMcFunctions, 'displayMultipleChoiceResult function from multipleChoice.js should be available in bundle');

		$hasPreviewFunctions = $browser->driver->executeScript('return typeof createPreviewBoard === "function";');
		$this->assertTrue($hasPreviewFunctions, 'createPreviewBoard function from previewBoard.js should be available in bundle');
	}

	/**
	 * Test that besogo library is NOT bundled into the legacy JS bundle
	 * (it should remain separate as a third-party library)
	 */
	public function testBesogoLibraryRemainsUnbundled()
	{
		// Check that besogo is NOT included in the legacy-app bundle
		$jsFiles = glob(WWW_ROOT . 'dist/legacy-app-*.js');
		$this->assertNotEmpty($jsFiles, 'legacy-app JS bundle should exist');

		$bundleContent = file_get_contents($jsFiles[0]);

		// Check that besogo's internal code patterns aren't present
		// (Our code references besogo via besogo.editor etc, but doesn't define it)
		$hasBesogoDef = stripos($bundleContent, 'var besogo=') !== false
			|| stripos($bundleContent, 'window.besogo=') !== false
			|| stripos($bundleContent, 'besogo.create') !== false;

		$this->assertFalse($hasBesogoDef, 'Besogo library definition should NOT be in bundle');
	}

	/**
	 * Test that minified bundle files exist
	 */
	public function testMinifiedBundlesExist()
	{
		// Verify Vite bundle files exist (using wildcard pattern for hashed names)
		$cssFiles = glob(WWW_ROOT . 'dist/app-theme-*.css');
		$this->assertNotEmpty($cssFiles, 'app-theme CSS bundle file should exist');

		$jsFiles = glob(WWW_ROOT . 'dist/legacy-app-*.js');
		$this->assertNotEmpty($jsFiles, 'legacy-app JS bundle file should exist');

		// Check that files have actual content (not empty)
		$cssContent = file_get_contents($cssFiles[0]);
		$jsContent = file_get_contents($jsFiles[0]);

		$this->assertGreaterThan(1000, strlen($cssContent), 'CSS bundle should have substantial content');
		$this->assertGreaterThan(1000, strlen($jsContent), 'JS bundle should have substantial content');
	}
}

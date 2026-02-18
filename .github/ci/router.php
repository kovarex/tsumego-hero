<?php
/**
 * PHP Built-in Server Router Script
 *
 * This script is used with `php -S` to route requests similar to Apache's mod_rewrite.
 * For AssetCompress to work, requests to /cache_css/* and /cache_js/* must be routed
 * through index.php so the dispatcher filter can intercept them.
 *
 * Usage: php -S localhost:8080 -t webroot router.php
 */

// Parse the URI without query string for file existence check
$requestUri = $_SERVER['REQUEST_URI'];
$uriWithoutQuery = parse_url($requestUri, PHP_URL_PATH);

// Use DOCUMENT_ROOT which php -S sets to the -t directory (webroot/)
// Ensure no double slashes when concatenating
$filePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $uriWithoutQuery;

// WebP content negotiation (mirrors .htaccess rules)
// When browser supports WebP and a .webp version exists, serve it instead of PNG/JPEG
if (file_exists($filePath) && preg_match('/\.(png|jpe?g)$/i', $uriWithoutQuery))
{
	$webpPath = preg_replace('/\.(png|jpe?g)$/i', '.webp', $filePath);
	if (file_exists($webpPath) && isset($_SERVER['HTTP_ACCEPT'])
		&& str_contains($_SERVER['HTTP_ACCEPT'], 'image/webp'))
	{
		header('Content-Type: image/webp');
		header('Vary: Accept');
		readfile($webpPath);
		return;
	}
}

// If the requested file exists, serve it directly (CSS, JS, images, etc.)
if (file_exists($filePath))
{
	return false; // Serve the file directly
}

// Otherwise, route through index.php for CakePHP routing and dispatcher filters
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';

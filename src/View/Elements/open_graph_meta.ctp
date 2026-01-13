<?php
/**
 * Open Graph meta tags element
 * 
 * Generates Open Graph and Twitter Card meta tags for social sharing.
 * Should be called from views that want rich social media previews.
 * 
 * Required variables:
 * - $t (tsumego data)
 * - $set (set data)
 * - $setConnection (set connection data)
 */

if (!isset($t) || !isset($set) || !isset($setConnection))
	return;

$puzzleId = $t['Tsumego']['id'];
$setConnectionId = $setConnection['SetConnection']['id'];
$author = $t['Tsumego']['author'] ?? 'Unknown';
$setTitle = $set['Set']['title'] ?? '';
$description = strip_tags($t['Tsumego']['description'] ?? '');

// Build Open Graph metadata
$ogTitle = $setTitle; // Use set title as main title (e.g., "Life & Death - Intermediate")
$ogDescription = $description; // Use problem description
if ($author && $author !== 'Unknown')
	$ogDescription .= " - by {$author}";

$imageUrl = Router::url("/tsumego-image/{$setConnectionId}", true);
$pageUrl = Router::url("/{$setConnectionId}", true);

// Append to meta block (rendered in layout head)
$this->append('meta');
echo '<meta property="og:title" content="' . htmlspecialchars($ogTitle) . '">' . "\n";
echo '<meta property="og:description" content="' . htmlspecialchars($ogDescription) . '">' . "\n";
echo '<meta property="og:image" content="' . htmlspecialchars($imageUrl) . '">' . "\n";
echo '<meta property="og:url" content="' . htmlspecialchars($pageUrl) . '">' . "\n";
echo '<meta property="og:type" content="website">' . "\n";
echo '<meta property="og:site_name" content="Tsumego Hero">' . "\n";
echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
echo '<meta name="twitter:title" content="' . htmlspecialchars($ogTitle) . '">' . "\n";
echo '<meta name="twitter:description" content="' . htmlspecialchars($ogDescription) . '">' . "\n";
echo '<meta name="twitter:image" content="' . htmlspecialchars($imageUrl) . '">' . "\n";
$this->end();

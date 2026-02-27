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
 * - $sgf (sgf data)
 */
App::uses('Constants', 'Utility');

if (!isset($t) || !isset($set) || !isset($setConnection) || !isset($sgf))
	return;

$setConnectionId = $setConnection['SetConnection']['id'];
$author = $t['Tsumego']['author'] ?? 'Unknown';
$setTitle = $set['Set']['title'] ?? '';
$description = strip_tags($t['Tsumego']['description'] ?? '');

// OG image always renders actual SGF colors (no board inversion).
// Descriptions are normalized: "Black" = solver. For White-first SGFs,
// swap Black<->White so the description matches the OG image stone colors.
if (isset($startingPlayer) && $startingPlayer == 1)
{
	$description = preg_replace_callback(
		'/\b(Black|black|White|white)\b/',
		fn($m) => ['Black' => 'White', 'black' => 'white', 'White' => 'Black', 'white' => 'black'][$m[1]],
		$description
	);
}

// Build Open Graph metadata — include problem position (e.g., "Korean Problem Academy 1 64/200")
$num = $setConnection['SetConnection']['num'] ?? null;
$total = $amountOfOtherCollection ?? null;
$ogTitle = $setTitle;
if ($num !== null && $total !== null)
	$ogTitle .= ' ' . $num . '/' . $total;
$ogDescription = $description; // Use problem description
if ($author && $author !== 'Unknown')
	$ogDescription .= " - by {$author}";

$imageUrl = Router::url("/tsumego-image/{$setConnectionId}?v=" . Constants::$TSUMEGO_IMAGE_VERSION
	. '&t=' . strtotime($sgf['Sgf']['created']), true);
$pageUrl = Router::url("/{$setConnectionId}", true);

// Append to meta block (rendered in layout head)
$this->append('og_meta');
echo '<meta property="og:title" content="' . htmlspecialchars($ogTitle) . '">' . "\n";
echo '<meta property="og:description" content="' . htmlspecialchars($ogDescription) . '">' . "\n";
echo '<meta property="og:image" content="' . htmlspecialchars($imageUrl) . '">' . "\n";
echo '<meta property="og:image:type" content="image/png">' . "\n";
echo '<meta property="og:image:width" content="' . Constants::$OG_IMAGE_WIDTH . '">' . "\n";
echo '<meta property="og:image:height" content="' . Constants::$OG_IMAGE_HEIGHT . '">' . "\n";
echo '<meta property="og:url" content="' . htmlspecialchars($pageUrl) . '">' . "\n";
echo '<meta property="og:type" content="website">' . "\n";
echo '<meta property="og:site_name" content="Tsumego">' . "\n";
echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
echo '<meta name="twitter:title" content="' . htmlspecialchars($ogTitle) . '">' . "\n";
echo '<meta name="twitter:description" content="' . htmlspecialchars($ogDescription) . '">' . "\n";
echo '<meta name="twitter:image" content="' . htmlspecialchars($imageUrl) . '">' . "\n";
$this->end();

<?php

/**
 * Open Graph meta tags element
 *
 * Renders Open Graph and Twitter Card meta tags from a prepared $og array.
 * The tags are appended to the 'og_meta' block, which the layout renders in <head>.
 *
 * Expected keys in $og:
 * - title (required)
 * - url (required)
 * - type (default 'website')
 * - site_name (default 'Tsumego')
 * - description (optional)
 * - image (optional)
 * - image_type, image_width, image_height, image_alt (optional, only with image)
 * - locale (optional)
 * - profile_username (optional, only with og:type=profile)
 * - twitter_card (optional, default 'summary')
 *
 * @var View $this
 * @var array $og
 */
if (empty($og))
	return;

$title = $og['title'] ?? '';
$description = $og['description'] ?? '';
$image = $og['image'] ?? '';
$url = $og['url'] ?? '';
$type = $og['type'] ?? 'website';
$siteName = $og['site_name'] ?? 'Tsumego';
$imageType = $og['image_type'] ?? '';
$imageWidth = $og['image_width'] ?? '';
$imageHeight = $og['image_height'] ?? '';
$imageAlt = $og['image_alt'] ?? '';
$locale = $og['locale'] ?? 'en_US';
$profileUsername = $og['profile_username'] ?? '';
$twitterCard = $og['twitter_card'] ?? 'summary';

$this->append('og_meta');
echo '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
if ($description !== '')
	echo '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
if ($image !== '')
	echo '<meta property="og:image" content="' . htmlspecialchars($image) . '">' . "\n";
if ($imageType !== '')
	echo '<meta property="og:image:type" content="' . htmlspecialchars($imageType) . '">' . "\n";
if ($imageWidth !== '')
	echo '<meta property="og:image:width" content="' . htmlspecialchars($imageWidth) . '">' . "\n";
if ($imageHeight !== '')
	echo '<meta property="og:image:height" content="' . htmlspecialchars($imageHeight) . '">' . "\n";
if ($imageAlt !== '')
	echo '<meta property="og:image:alt" content="' . htmlspecialchars($imageAlt) . '">' . "\n";
echo '<meta property="og:url" content="' . htmlspecialchars($url) . '">' . "\n";
echo '<meta property="og:type" content="' . htmlspecialchars($type) . '">' . "\n";
if ($profileUsername !== '')
	echo '<meta property="profile:username" content="' . htmlspecialchars($profileUsername) . '">' . "\n";
echo '<meta property="og:site_name" content="' . htmlspecialchars($siteName) . '">' . "\n";
if ($locale !== '')
	echo '<meta property="og:locale" content="' . htmlspecialchars($locale) . '">' . "\n";
echo '<meta name="twitter:card" content="' . htmlspecialchars($twitterCard) . '">' . "\n";
echo '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . "\n";
if ($description !== '')
	echo '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . "\n";
if ($image !== '')
	echo '<meta name="twitter:image" content="' . htmlspecialchars($image) . '">' . "\n";
$this->end();

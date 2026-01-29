<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
	<url>
		<loc><?php echo h($url['loc']); ?></loc>
<?php if (isset($url['lastmod'])): ?>
		<lastmod><?php echo h($url['lastmod']); ?></lastmod>
<?php endif; ?>
		<changefreq><?php echo h($url['changefreq']); ?></changefreq>
		<priority><?php echo h($url['priority']); ?></priority>
	</url>
<?php endforeach; ?>
</urlset>

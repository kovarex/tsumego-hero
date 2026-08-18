<?php

/**
 * @var View $this
 * @var array $news
 */

?>
	<div class="teuber">
		<div class="homeLeft2">
			<?php echo HtmlSanitizer::sanitize((string) ($news['Site']['body'] ?? '')); ?>
			<br><br><br><br><br><br><br><br><br><br><br>
		</div>
		<div class="homeRight2">
			<?php echo h($news['Site']['title']); ?>
		</div>
	</div>
<?php

/**
 * @var View $this
 * @var array $news
 */

?>
	<div class="split">
		<div>
			<?php echo HtmlSanitizer::sanitize((string) ($news['Site']['body'] ?? '')); ?>
			<br><br><br><br><br><br><br><br><br><br><br>
		</div>
		<div>
			<?php echo h($news['Site']['title']); ?>
		</div>
	</div>
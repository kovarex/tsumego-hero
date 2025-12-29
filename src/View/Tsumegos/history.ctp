<h2>History of <?php echo $set['title'] . ' - ' . $setConnection['num']; ?></h2>
<?php
TimeGraphRenderer::renderScriptInclude();
TimeGraphRenderer::render('Rating history', 'chart-rating', $dailyResults, 'Rating');
?>

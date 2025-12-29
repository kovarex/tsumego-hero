<div style="text-align: center;width: fit-content;margin: 0 auto;">
<h2>History of <a href="/<?php echo $setConnection['id']; ?>"><?php echo $set['title'] . ' - ' . $setConnection['num']; ?></a></h2>
<?php
App::uses('TsumegoAttemptsRenderer', 'Utility');
TimeGraphRenderer::renderScriptInclude();
TimeGraphRenderer::render('Rating history', 'chart-rating', $dailyResults, 'Rating');
new TsumegoAttemptsRenderer($urlParams, $setConnection['tsumego_id'])->render();
?>
</div>

<?php

/**
 * @var View $this
 * @var array $set
 * @var array $setConnection
 * @var array $dailyResults
 * @var array $urlParams
 */

?>
<div style="text-align: center;width: fit-content;margin: 0 auto;">
<h2>History of <a href="/<?php echo $setConnection['id']; ?>"><?php echo h($set['title']) . ' - ' . $setConnection['num']; ?></a></h2>
<?php
use App\Utility\TimeGraphRenderer;
use App\Utility\TsumegoAttemptsRenderer;
TimeGraphRenderer::renderScriptInclude();
TimeGraphRenderer::render('Rating history', 'chart-rating', $dailyResults, 'Rating');
new TsumegoAttemptsRenderer($urlParams, $setConnection['tsumego_id'])->render();
?>
</div>
